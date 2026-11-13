/* Front page particle stage (issue 744, REQ-043, REQ-041). One point cloud morphs from Africa on a globe into the brand's marks as the
   visitor scrolls through the three colour scenes. This file and Three.js are fetched only on demand by home-fx.js, after the visitor
   starts scrolling, with motion allowed and WebGL available; the scenes already read in full with their static marks before this runs.
   Ported from prototype/theme/stage3d.js and the stage part of prototype/theme/home.js. Colours are read from the theme tokens. */
import { GEO } from "./stage/geo.js";
import { MARKS } from "./stage/marks.js";

const THREE = window.THREE;
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

function build(light, accent) {
  const small = window.innerWidth < 720;
  const n = small ? 7000 : 16000;
  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: false, powerPreference: "high-performance" });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small ? 1.5 : 1.75));
  renderer.setClearColor(0, 0);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 30);
  camera.position.z = 6;
  const globe = globePoints(n);
  const marks = ["star", "comb", "spiral"].map((k) => maskPoints(MARKS[k], n));
  const toAttr = (list) => {
    const a = new Float32Array(n * 3);
    for (let i = 0; i < n; i++) {
      const v = list[i] || list[i % Math.max(1, list.length)];
      a[i * 3] = v[0];
      a[i * 3 + 1] = v[1];
      a[i * 3 + 2] = v[2];
    }
    return new THREE.BufferAttribute(a, 3);
  };
  const shapes = [toAttr(globe.pts)].concat(marks.map(toAttr));
  const rnd = new Float32Array(n * 4);
  for (let i = 0; i < n; i++) {
    rnd[i * 4] = Math.random();
    rnd[i * 4 + 1] = Math.random();
    rnd[i * 4 + 2] = Math.random();
    rnd[i * 4 + 3] = i < globe.nodes ? 1 : Math.random() < 0.04 ? 0.6 : i >= globe.nodes + globe.land ? 0.2 : 0;
  }
  const geo = new THREE.BufferGeometry();
  geo.setAttribute("position", shapes[0]);
  geo.setAttribute("aFrom", shapes[0]);
  geo.setAttribute("aTo", shapes[1]);
  geo.setAttribute("aRnd", new THREE.BufferAttribute(rnd, 4));
  const mat = new THREE.ShaderMaterial({
    vertexShader: VS,
    fragmentShader: FS,
    transparent: true,
    depthTest: false,
    depthWrite: false,
    uniforms: {
      uT: { value: 0 },
      uTime: { value: 0 },
      uPointPx: { value: 2 },
      uFromGlobe: { value: 1 },
      uAlpha: { value: 1 },
      uFromS: { value: 1 },
      uToS: { value: 1 },
      uMouseOn: { value: 0 },
      uRot: { value: new THREE.Matrix3() },
      uFromO: { value: new THREE.Vector3() },
      uToO: { value: new THREE.Vector3() },
      uMouse: { value: new THREE.Vector2(99, 99) },
      uColor: { value: new THREE.Color().setRGB(light[0] / 255, light[1] / 255, light[2] / 255) },
      uAccent: { value: new THREE.Color().setRGB(accent[0] / 255, accent[1] / 255, accent[2] / 255) },
    },
  });
  const points = new THREE.Points(geo, mat);
  points.frustumCulled = false;
  scene.add(points);
  return { renderer, scene, camera, geo, mat, shapes, seg: 0, t0: 0, euler: new THREE.Euler(0, 0, 0, "YXZ"), m4: new THREE.Matrix4(), small };
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
  host.appendChild(scene.renderer.domElement);
  let L = layout();
  let acts = { top: 0, h: 1 };
  let vh = window.innerHeight;
  const pointer = { x: 0, y: 0, sx: 0, sy: 0, on: false };
  let running = false;
  let raf = 0;

  const measure = () => {
    vh = window.innerHeight;
    const b = section.getBoundingClientRect();
    acts = { top: b.top + window.scrollY, h: b.height };
    L = layout();
    scene.renderer.setSize(window.innerWidth, vh, false);
    scene.camera.aspect = window.innerWidth / vh;
    scene.camera.updateProjectionMatrix();
  };
  const frame = (now) => {
    raf = 0;
    if (!running) return;
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
    if (top >= vh || bot >= vh || document.hidden) return;
    pointer.sx += (pointer.x - pointer.sx) * 0.06;
    pointer.sy += (pointer.y - pointer.sy) * 0.06;
    const u = scene.mat.uniforms;
    if (!scene.t0) scene.t0 = now;
    if (scene.seg !== seg) {
      scene.geo.setAttribute("aFrom", scene.shapes[seg]);
      scene.geo.setAttribute("aTo", scene.shapes[seg + 1]);
      scene.seg = seg;
    }
    const col = mixC(light, dark, toComb * (1 - toSpiral));
    const acc = mixC(accent, light, toComb * (1 - toSpiral));
    const isGlobe = seg === 0;
    scene.euler.set(0.08 + pointer.sy * 0.1, -0.32 + pointer.sx * 0.2, 0);
    scene.m4.makeRotationFromEuler(scene.euler);
    u.uRot.value.setFromMatrix4(scene.m4);
    u.uT.value = t;
    u.uTime.value = (now - scene.t0) / 1000;
    u.uFromGlobe.value = isGlobe ? 1 : 0;
    u.uFromS.value = isGlobe ? L.globeS : L.markS;
    u.uToS.value = L.markS;
    u.uFromO.value.set(isGlobe ? L.globeX : L.markX, isGlobe ? L.globeY : L.markY, 0);
    u.uToO.value.set(L.markX, L.markY, 0);
    u.uPointPx.value = (scene.small ? 1.75 : 2.3) * scene.renderer.getPixelRatio();
    u.uColor.value.setRGB(col[0] / 255, col[1] / 255, col[2] / 255);
    u.uAccent.value.setRGB(acc[0] / 255, acc[1] / 255, acc[2] / 255);
    if (pointer.on) {
      u.uMouse.value.set(pointer.sx * 0.5 * L.W, -pointer.sy * 0.5 * L.H);
      u.uMouseOn.value += (1 - u.uMouseOn.value) * 0.08;
    } else u.uMouseOn.value *= 0.92;
    scene.renderer.render(scene.scene, scene.camera);
  };
  // Render only while the colour scenes are near the screen.
  new IntersectionObserver(
    (entries) =>
      entries.forEach((e) => {
        running = e.isIntersecting;
        if (running && !raf) raf = window.requestAnimationFrame(frame);
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
