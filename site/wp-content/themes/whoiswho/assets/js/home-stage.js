/* Front page particle stage (issue 744, REQ-043, REQ-041). One point cloud morphs from Africa on a globe into the brand's marks as the
   visitor scrolls through the three colour scenes. This file is fetched only on demand by home-fx.js, after the visitor starts
   scrolling, with motion allowed and WebGL available; the scenes already read in full with their static marks before this runs.
   It draws with a small WebGL helper (stage/gl.js) instead of Three.js (issue 808). The loop runs only while the scenes are near the
   screen and the tab is visible, the pixel ratio is capped at 2, and turning on reduced motion stops it.
   Ported from prototype/theme/stage3d.js and the stage part of prototype/theme/home.js. Colours are read from the theme tokens. */
import { GEO } from "./stage/geo.js";
import { MARKS } from "./stage/marks.js";
import { createPoints, perspective, rotationYXZ } from "./stage/gl.js";

const root = document.documentElement;
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
const smooth = (a, b, x) => {
  const t = clamp((x - a) / (b - a), 0, 1);
  return t * t * (3 - 2 * t);
};
const mix = (a, b, t) => a + (b - a) * t;
const mixC = (a, b, t) => [mix(a[0], b[0], t), mix(a[1], b[1], t), mix(a[2], b[2], t)];

/** Reads a colour token such as --orange (hex or rgb) as [r, g, b], or null when it is missing. */
function token(name) {
  const v = getComputedStyle(root).getPropertyValue(name).trim();
  const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(v);
  if (hex) {
    const h = hex[1].length === 3 ? hex[1].replace(/./g, (c) => c + c) : hex[1];
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
  }
  const rgb = /rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/.exec(v);
  return rgb ? [+rgb[1], +rgb[2], +rgb[3]] : null;
}

function maskPoints(m, n) {
  const bytes = atob(m.d);
  const on = (x, y) => bytes.charCodeAt((y * m.w + x) >> 3) & (1 << ((y * m.w + x) & 7));
  const scale = 2 / Math.max(m.w, m.h);
  const out = [];
  let guard = 0;
  while (out.length < n && guard++ < n * 80) {
    const x = Math.random() * m.w;
    const y = Math.random() * m.h;
    if (on(x | 0, y | 0)) out.push([(x - m.w / 2) * scale, (m.h / 2 - y) * scale, (Math.random() - 0.5) * 0.05]);
  }
  return out;
}

function globePoints(n) {
  const toV = (lon, lat) => {
    const la = (lat * Math.PI) / 180;
    const lo = (lon * Math.PI) / 180;
    return [Math.cos(la) * Math.sin(lo), Math.sin(la), Math.cos(la) * Math.cos(lo)];
  };
  const land = [];
  const grid = [];
  const nodes = [];
  Object.keys(GEO.cities).forEach((k) => {
    if (k === "Lagos" || GEO.arcs.indexOf(k) >= 0) nodes.push(toV(GEO.cities[k][1], GEO.cities[k][0]));
  });
  // Africa takes most of the budget, in an even lattice so it reads as a printed halftone.
  GEO.sphere(Math.round(n * 12), (lon, lat) => {
    if (GEO.isAfrica(lon, lat)) land.push(toV(lon, lat));
  });
  GEO.sphere(Math.max(10, n - land.length - nodes.length), (lon, lat) => {
    if (!GEO.isAfrica(lon, lat)) grid.push(toV(lon, lat));
  });
  const all = nodes.concat(land, grid);
  while (all.length < n) all.push(grid[all.length % grid.length] || land[0]);
  return { pts: all.slice(0, n), nodes: nodes.length, land: land.length };
}

const VS = [
  "precision highp float; uniform mat4 modelViewMatrix, projectionMatrix;",
  "attribute vec3 aFrom; attribute vec3 aTo; attribute vec4 aRnd;",
  "uniform float uT, uTime, uPointPx, uFromGlobe, uAlpha, uFromS, uToS, uMouseOn; uniform mat3 uRot; uniform vec3 uFromO, uToO; uniform vec2 uMouse;",
  "varying float vA; varying float vAccent;",
  "void main() {",
  "  float t = clamp(uT * 1.5 - aRnd.x * .5, 0., 1.); t = t * t * (3. - 2. * t);",
  "  vec3 a = aFrom; float face = 1.;",
  "  if (uFromGlobe > .5) { a = uRot * a; face = mix(.07, 1., smoothstep(-.3, .25, a.z)); }",
  "  vec3 p = mix(a * uFromS + uFromO, aTo * uToS + uToO, t);",
  "  float sw = sin(t * 3.14159), ang = aRnd.z * 6.283;",
  "  p += vec3(cos(ang + uTime * .6), sin(ang * 1.7 + uTime * .5), sin(ang * .7)) * sw * (.45 + aRnd.y * .7);",
  "  p.xy += vec2(sin(uTime * .5 + ang), cos(uTime * .4 + ang)) * mix(.0012, .006, t) * (1. - sw);",
  "  vec2 dm = p.xy - uMouse; float f = exp(-dot(dm, dm) / .14) * uMouseOn; p.xy += normalize(dm + .0001) * f * .2;",
  "  vec4 mv = modelViewMatrix * vec4(p, 1.);",
  "  gl_Position = projectionMatrix * mv;",
  "  float size = mix(mix(1., .8, 1. - t), mix(.8, 1.35, aRnd.y), t); if (aRnd.w > .9) size *= mix(3.4, 1.2, t);",
  "  gl_PointSize = uPointPx * size * (6. / -mv.z);",
  "  float dim = (aRnd.w > .1 && aRnd.w < .5) ? mix(.42, 1., t) : 1.;",
  "  vA = uAlpha * mix(face, 1., t) * dim;",
  "  vAccent = aRnd.w > .9 ? 1. : (aRnd.w > .5 ? t : 0.);",
  "}",
].join("\n");
const FS = [
  "precision mediump float; uniform vec3 uColor, uAccent; varying float vA; varying float vAccent;",
  "void main() { vec2 c = gl_PointCoord - .5; float d = dot(c, c); if (d > .25) discard; gl_FragColor = vec4(mix(uColor, uAccent, vAccent), vA * smoothstep(.25, .17, d)); }",
].join("\n");

// The camera sits at z = 6 looking at the origin, so the model-view matrix is a fixed move back by 6.
const VIEW = new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, -6, 1]);

function build(light, accent) {
  const small = window.innerWidth < 720;
  const n = small ? 7000 : 16000;
  const canvas = document.createElement("canvas");
  const gl = createPoints(canvas, VS, FS, { aFrom: 3, aTo: 3, aRnd: 4 });
  if (!gl) throw new Error("webgl");
  const globe = globePoints(n);
  const marks = ["star", "comb", "spiral"].map((k) => maskPoints(MARKS[k], n));
  const toBuffer = (list) => {
    const a = new Float32Array(n * 3);
    for (let i = 0; i < n; i++) {
      const v = list[i] || list[i % Math.max(1, list.length)];
      a[i * 3] = v[0];
      a[i * 3 + 1] = v[1];
      a[i * 3 + 2] = v[2];
    }
    return gl.buffer(a);
  };
  const shapes = [toBuffer(globe.pts)].concat(marks.map(toBuffer));
  const rnd = new Float32Array(n * 4);
  for (let i = 0; i < n; i++) {
    rnd[i * 4] = Math.random();
    rnd[i * 4 + 1] = Math.random();
    rnd[i * 4 + 2] = Math.random();
    rnd[i * 4 + 3] = i < globe.nodes ? 1 : Math.random() < 0.04 ? 0.6 : i >= globe.nodes + globe.land ? 0.2 : 0;
  }
  gl.bind("aRnd", gl.buffer(rnd));
  gl.bind("aFrom", shapes[0]);
  gl.bind("aTo", shapes[1]);
  gl.uniform("modelViewMatrix", VIEW);
  gl.uniform("uColor", [light[0] / 255, light[1] / 255, light[2] / 255]);
  gl.uniform("uAccent", [accent[0] / 255, accent[1] / 255, accent[2] / 255]);
  // Device pixel ratio capped at 2 everywhere, and lower on small screens where the points are many and the GPU is weaker.
  const ratio = Math.min(window.devicePixelRatio || 1, small ? 1.5 : 1.75, 2);
  return { canvas, gl, n, shapes, seg: 0, t0: 0, small, ratio, mouseOn: 0 };
}

function layout() {
  const w = window.innerWidth;
  const h = window.innerHeight;
  const H = 2 * 6 * Math.tan((17.5 * Math.PI) / 180);
  const Wd = H * (w / h);
  const wide = w >= 900;
  return {
    W: Wd,
    H,
    globeS: wide ? H * 0.4 : Math.min(H * 0.25, Wd * 0.47),
    globeX: wide ? Wd * 0.17 : 0,
    globeY: wide ? -H * 0.02 : H * 0.13,
    markS: wide ? H * 0.4 : Math.min(H * 0.25, Wd * 0.46),
    markX: wide ? Wd * 0.22 : 0,
    markY: wide ? H * 0.05 : H * 0.17,
  };
}

/** Starts the stage in `host` (a fixed, full-screen, decorative element) over the .h-acts section. Returns false when it cannot run. */
function start(host, section) {
  const light = token("--offwhite");
  const accent = token("--orange");
  const dark = token("--charcoal-900");
  if (!light || !accent || !dark || !section) return false;
  let scene;
  try {
    scene = build(light, accent);
  } catch {
    return false;
  }
  host.appendChild(scene.canvas);
  let L = layout();
  let acts = { top: 0, h: 1 };
  let vh = window.innerHeight;
  const pointer = { x: 0, y: 0, sx: 0, sy: 0, on: false };
  let near = false;
  let raf = 0;
  const reduce = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
  // The loop runs only while the scenes are near the screen, the tab is visible and motion is allowed.
  const active = () => near && !document.hidden && !(reduce && reduce.matches);
  const wake = () => {
    if (active() && !raf) raf = window.requestAnimationFrame(frame);
    else if (!active() && raf) {
      window.cancelAnimationFrame(raf);
      raf = 0;
    }
  };

  const measure = () => {
    vh = window.innerHeight;
    const b = section.getBoundingClientRect();
    acts = { top: b.top + window.scrollY, h: b.height };
    L = layout();
    scene.gl.size(window.innerWidth, vh, scene.ratio);
    scene.gl.uniform("projectionMatrix", perspective(35, window.innerWidth / vh, 0.1, 30));
  };
  const frame = (now) => {
    raf = 0;
    if (!active()) return;
    raf = window.requestAnimationFrame(frame);
    const s = window.scrollY;
    const a = clamp((s - acts.top) / Math.max(1, acts.h - vh), 0, 1);
    const toStar = smooth(acts.top - vh * 0.9, acts.top - vh * 0.1, s);
    const toComb = smooth(0.14, 0.4, a);
    const toSpiral = smooth(0.6, 0.86, a);
    let seg = 0;
    let t = toStar;
    if (toSpiral > 0) {
      seg = 2;
      t = toSpiral;
    } else if (toComb > 0) {
      seg = 1;
      t = toComb;
    }
    // The particles only show inside the scenes.
    const top = Math.max(0, acts.top - s);
    const bot = Math.max(0, vh - (acts.top + acts.h - s));
    host.style.clipPath = top >= vh || bot >= vh ? "inset(50% 0 50% 0)" : "inset(" + top.toFixed(0) + "px 0 " + bot.toFixed(0) + "px 0)";
    if (top >= vh || bot >= vh) return;
    pointer.sx += (pointer.x - pointer.sx) * 0.06;
    pointer.sy += (pointer.y - pointer.sy) * 0.06;
    const gl = scene.gl;
    if (!scene.t0) scene.t0 = now;
    if (scene.seg !== seg) {
      gl.bind("aFrom", scene.shapes[seg]);
      gl.bind("aTo", scene.shapes[seg + 1]);
      scene.seg = seg;
    }
    const col = mixC(light, dark, toComb * (1 - toSpiral));
    const acc = mixC(accent, light, toComb * (1 - toSpiral));
    const isGlobe = seg === 0;
    gl.uniform("uRot", rotationYXZ(0.08 + pointer.sy * 0.1, -0.32 + pointer.sx * 0.2));
    gl.uniform("uT", t);
    gl.uniform("uTime", (now - scene.t0) / 1000);
    gl.uniform("uAlpha", 1);
    gl.uniform("uFromGlobe", isGlobe ? 1 : 0);
    gl.uniform("uFromS", isGlobe ? L.globeS : L.markS);
    gl.uniform("uToS", L.markS);
    gl.uniform("uFromO", [isGlobe ? L.globeX : L.markX, isGlobe ? L.globeY : L.markY, 0]);
    gl.uniform("uToO", [L.markX, L.markY, 0]);
    gl.uniform("uPointPx", (scene.small ? 1.75 : 2.3) * scene.ratio);
    gl.uniform("uColor", [col[0] / 255, col[1] / 255, col[2] / 255]);
    gl.uniform("uAccent", [acc[0] / 255, acc[1] / 255, acc[2] / 255]);
    if (pointer.on) {
      gl.uniform("uMouse", [pointer.sx * 0.5 * L.W, -pointer.sy * 0.5 * L.H]);
      scene.mouseOn += (1 - scene.mouseOn) * 0.08;
    } else scene.mouseOn *= 0.92;
    gl.uniform("uMouseOn", scene.mouseOn);
    gl.draw(scene.n);
  };
  // Render only while the colour scenes are near the screen.
  new IntersectionObserver(
    (entries) =>
      entries.forEach((e) => {
        near = e.isIntersecting;
        wake();
      }),
    { rootMargin: "100% 0px" }
  ).observe(section);
  document.addEventListener(
    "pointermove",
    (ev) => {
      pointer.on = ev.pointerType === "mouse" && ev.clientY < vh;
      pointer.x = (ev.clientX / window.innerWidth) * 2 - 1;
      pointer.y = (ev.clientY / vh) * 2 - 1;
    },
    { passive: true }
  );
  document.addEventListener("visibilitychange", wake);
  if (reduce) {
    const follow = () => {
      host.style.display = reduce.matches ? "none" : "";
      wake();
    };
    if (reduce.addEventListener) reduce.addEventListener("change", follow);
    else if (reduce.addListener) reduce.addListener(follow);
  }
  let timer = 0;
  window.addEventListener("resize", () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(measure, 120);
  });
  window.addEventListener("load", measure);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(measure);
  measure();
  root.classList.add("stage-on");
  return true;
}

window.wiwStage = { start };
