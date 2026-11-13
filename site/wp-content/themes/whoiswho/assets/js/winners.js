/* Winners page: one burst of confetti when the page loads (REQ-246, REQ-043). It runs only when winners are on the page and the
   visitor has not asked for reduced motion (data saver and slow connections included, through the shared motion check), plays once,
   and takes its colours from the theme tokens, so no brand value lives here. The canvas is decorative and hidden from assistive
   technology; it never takes pointer events or focus and removes itself when the burst ends. */
import { motionAllowed } from "./motion.js";

const TOKENS = ["--orange", "--blue-700", "--purple", "--green", "--red"];
const COUNT = 90;
const FRAMES = 150;

function colours() {
  const style = getComputedStyle(document.documentElement);
  return TOKENS.map((t) => style.getPropertyValue(t).trim()).filter(Boolean);
}

function burst(section) {
  const palette = colours();
  if (!palette.length) return;
  const canvas = document.createElement("canvas");
  canvas.setAttribute("aria-hidden", "true");
  canvas.className = "win-confetti";
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
  document.body.appendChild(canvas);
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    canvas.remove();
    return;
  }
  const bits = Array.from({ length: COUNT }, (_, i) => ({
    x: canvas.width * (0.15 + Math.random() * 0.7),
    y: -20 - Math.random() * canvas.height * 0.3,
    vx: (Math.random() - 0.5) * 4,
    vy: 2 + Math.random() * 3.5,
    size: 6 + Math.random() * 6,
    turn: Math.random() * Math.PI,
    spin: (Math.random() - 0.5) * 0.3,
    colour: palette[i % palette.length],
  }));
  let frame = 0;
  const step = () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (const b of bits) {
      b.x += b.vx;
      b.y += b.vy;
      b.turn += b.spin;
      ctx.save();
      ctx.translate(b.x, b.y);
      ctx.rotate(b.turn);
      ctx.globalAlpha = Math.max(0, 1 - frame / FRAMES);
      ctx.fillStyle = b.colour;
      ctx.fillRect(-b.size / 2, -b.size / 4, b.size, b.size / 2);
      ctx.restore();
    }
    frame += 1;
    if (frame < FRAMES) {
      requestAnimationFrame(step);
    } else {
      canvas.remove();
      section.setAttribute("data-confetti", "done");
    }
  };
  section.setAttribute("data-confetti", "playing");
  requestAnimationFrame(step);
}

const section = document.querySelector(".win-body[data-confetti='ready']");
if (section) {
  if (motionAllowed()) {
    burst(section);
  } else {
    section.setAttribute("data-confetti", "skipped");
  }
}
