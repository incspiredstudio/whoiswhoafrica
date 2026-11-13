import { createServer, type Server, type Socket } from "node:net";

/**
 * A tiny local SMTP sink for browser tests. TEST ENVIRONMENT ONLY. The site sends mail to 127.0.0.1:1025 when it has no SMTP
 * account and is not in production (the local mail catcher's address, Transport::configure), so a test can listen there, read what the
 * site would have sent and never send anything to a real person. It accepts any message and keeps it in memory.
 */
export type CapturedMail = {
  from: string;
  to: string[];
  subject: string;
  headers: string;
  text: string;
};

const SMTP_PORT = 1025;

/** Decode a quoted-printable body. */
function decodeQuotedPrintable(input: string): string {
  return input
    .replace(/=\r?\n/g, "")
    .replace(/=([0-9A-F]{2})/gi, (_m, hex: string) =>
      String.fromCharCode(parseInt(hex, 16)),
    );
}

/** The readable text of a raw message: headers kept apart, the body decoded when it is base64 or quoted-printable. */
function parseMessage(raw: string): {
  headers: string;
  text: string;
  subject: string;
} {
  const split = raw.search(/\r?\n\r?\n/);
  const headers = split === -1 ? raw : raw.slice(0, split);
  const body = split === -1 ? "" : raw.slice(split).replace(/^\r?\n\r?\n/, "");
  const subjectRaw =
    /^Subject:\s*(.*(?:\r?\n[ \t].*)*)/im.exec(headers)?.[1] ?? "";
  const subject = subjectRaw
    .replace(/\r?\n[ \t]+/g, " ")
    .replace(/=\?UTF-8\?[BQ]\?([^?]*)\?=/gi, (_m, enc: string) => {
      return /\?B\?/i.test(_m)
        ? Buffer.from(enc, "base64").toString("utf8")
        : decodeQuotedPrintable(enc.replace(/_/g, " "));
    });
  let text = body;
  // Walk every part: decode what is base64 or quoted-printable, keep the rest.
  const parts = body.split(/\r?\n--[^\r\n]+(?:--)?\r?\n/);
  text = parts
    .map((part) => {
      const idx = part.search(/\r?\n\r?\n/);
      const partHead = idx === -1 ? "" : part.slice(0, idx);
      const partBody =
        idx === -1 ? part : part.slice(idx).replace(/^\r?\n\r?\n/, "");
      if (/Content-Transfer-Encoding:\s*base64/i.test(partHead || headers))
        return Buffer.from(partBody.replace(/\s+/g, ""), "base64").toString(
          "utf8",
        );
      if (
        /Content-Transfer-Encoding:\s*quoted-printable/i.test(
          partHead || headers,
        )
      )
        return decodeQuotedPrintable(partBody);
      return partBody;
    })
    .join("\n");
  return { headers, text, subject };
}

export class MailSink {
  readonly mails: CapturedMail[] = [];
  private server: Server | null = null;

  /** Start listening. Returns false when the port is taken (another catcher is running); the caller skips with a note. */
  async start(): Promise<boolean> {
    this.server = createServer((socket) => this.handle(socket));
    return new Promise((resolve) => {
      this.server?.once("error", () => resolve(false));
      this.server?.listen(SMTP_PORT, "127.0.0.1", () => resolve(true));
    });
  }

  async stop(): Promise<void> {
    await new Promise<void>((resolve) =>
      this.server ? this.server.close(() => resolve()) : resolve(),
    );
    this.server = null;
  }

  /** Mails whose recipient list or text mentions the given address or text. */
  find(match: (mail: CapturedMail) => boolean): CapturedMail[] {
    return this.mails.filter(match);
  }

  /** The first http(s) link in a mail's text that contains the given path part, or null. */
  static link(mail: CapturedMail, pathPart: string): string | null {
    const found = mail.text.match(/https?:\/\/[^\s"'<>]+/g) ?? [];
    return found.find((u) => u.includes(pathPart)) ?? null;
  }

  private handle(socket: Socket): void {
    let from = "";
    let to: string[] = [];
    let inData = false;
    let data = "";
    let buffer = "";
    socket.write("220 wiw-test-sink ESMTP\r\n");
    socket.on("error", () => socket.destroy());
    socket.on("data", (chunk) => {
      buffer += chunk.toString("utf8");
      let nl: number;
      while ((nl = buffer.indexOf("\r\n")) !== -1) {
        const line = buffer.slice(0, nl);
        buffer = buffer.slice(nl + 2);
        if (inData) {
          if (line === ".") {
            inData = false;
            const parsed = parseMessage(data);
            this.mails.push({
              from,
              to,
              subject: parsed.subject,
              headers: parsed.headers,
              text: parsed.text,
            });
            data = "";
            socket.write("250 queued\r\n");
          } else {
            data += (line.startsWith("..") ? line.slice(1) : line) + "\r\n";
          }
          continue;
        }
        const cmd = line.slice(0, 4).toUpperCase();
        if (cmd === "EHLO" || cmd === "HELO")
          socket.write("250 wiw-test-sink\r\n");
        else if (cmd === "MAIL") {
          from = /<([^>]*)>/.exec(line)?.[1] ?? "";
          to = [];
          socket.write("250 ok\r\n");
        } else if (cmd === "RCPT") {
          to.push(/<([^>]*)>/.exec(line)?.[1] ?? "");
          socket.write("250 ok\r\n");
        } else if (cmd === "DATA") {
          inData = true;
          socket.write("354 end with a dot\r\n");
        } else if (cmd === "QUIT") {
          socket.write("221 bye\r\n");
          socket.end();
        } else socket.write("250 ok\r\n");
      }
    });
  }
}
