/* Profile photo viewer (REQ-003, REQ-043): the header photo links to its large version; with scripts the link opens a native
   modal dialog instead, and the large image grows out of the photo (a FLIP transform from the thumbnail's box). The large file
   loads only on the first open. The shared focus trap keeps Tab inside; Escape, the close button, a click on the backdrop or on
   the image close it, shrinking back, and focus returns to the photo. Without motion it simply appears and disappears. */
import { trapFocus } from "./focus-trap.js";
import { motionAllowed } from "./motion.js";

const EASE = "cubic-bezier(.2, .8, .2, 1)";

function flip(from, to) {
  const a = from.getBoundingClientRect();
  const b = to.getBoundingClientRect();
  if (!a.width || !b.width) return null;
  const dx = a.left + a.width / 2 - (b.left + b.width / 2);
  const dy = a.top + a.height / 2 - (b.top + b.height / 2);
  return `translate(${dx}px, ${dy}px) scale(${a.width / b.width}, ${a.height / b.height})`;
}

export function initPhotoZoom(root = document) {
  root.querySelectorAll("[data-photo-zoom]").forEach((link) => {
    const dialog = document.getElementById(link.getAttribute("data-photo-zoom") || "");
    if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== "function") return;
    const img = dialog.querySelector("img[data-src]");
    const thumb = link.querySelector("img");
    if (!img || !thumb) return;
    let release = null;
    let closing = false;

    const open = () => {
      if (!img.getAttribute("src")) img.setAttribute("src", img.getAttribute("data-src") || "");
      dialog.showModal();
      release = trapFocus(dialog, { onEscape: close });
      if (!motionAllowed()) return;
      const from = flip(thumb, img);
      if (from) img.animate([{ transform: from }, { transform: "none" }], { duration: 420, easing: EASE });
      dialog.animate([{ backgroundColor: "transparent" }, {}], { duration: 320, easing: "ease-out" });
    };

    function close() {
      if (closing || !dialog.open) return;
      const done = () => {
        closing = false;
        dialog.close();
      };
      const to = motionAllowed() ? flip(thumb, img) : null;
      if (!to) return done();
      closing = true;
      dialog.querySelectorAll("figcaption, .photo-dialog-close").forEach((el) => el.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 160, fill: "forwards" }));
      img.animate([{ transform: "none" }, { transform: to }], { duration: 300, easing: EASE, fill: "forwards" }).finished.then(() => {
        img.getAnimations().forEach((a) => a.cancel());
        dialog.querySelectorAll("figcaption, .photo-dialog-close").forEach((el) => el.getAnimations().forEach((a) => a.cancel()));
        done();
      });
    }

    link.addEventListener("click", (event) => {
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
      event.preventDefault();
      open();
    });
    dialog.querySelectorAll("[data-photo-close]").forEach((button) => button.addEventListener("click", close));
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog || event.target === img) close();
    });
    // Escape: the browser fires cancel; animate the close instead of the instant one.
    dialog.addEventListener("cancel", (event) => {
      event.preventDefault();
      close();
    });
    dialog.addEventListener("close", () => {
      if (release) release();
      release = null;
      link.focus();
    });
  });
}
