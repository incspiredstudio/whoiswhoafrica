/* Home page particle stage. One point cloud morphs from Africa on a globe into the brand's hand-drawn marks as the
   visitor scrolls. It only starts once the visitor nears the colour scenes, so it never blocks content; the static
   colour blocks are already in the markup. theme/home.js drives it; nothing here listens to scroll. */
(function () {
  var W = window.W; if (typeof document === "undefined" || !document.documentElement) return;
  var S = { status: "idle", reason: "", host: null, renderer: null, frames: 0 };
  function token(n) { return getComputedStyle(document.documentElement).getPropertyValue(n).trim(); }
  function maskPoints(m, n) {
    var bytes = atob(m.d), on = function (x, y) { var i = y * m.w + x; return bytes.charCodeAt(i >> 3) & (1 << (i & 7)); }, out = [], scale = 2 / Math.max(m.w, m.h), guard = 0;
    while (out.length < n && guard++ < n * 80) {
      var x = Math.random() * m.w, y = Math.random() * m.h;
      if (on(x | 0, y | 0)) out.push([(x - m.w / 2) * scale, (m.h / 2 - y) * scale, (Math.random() - .5) * .05]);
    }
    return out;
  }
  function globePoints(n) {
    var G = W.GEO, land = [], grid = [], nodes = [], toV = function (lon, lat) { var la = lat * Math.PI / 180, lo = lon * Math.PI / 180; return [Math.cos(la) * Math.sin(lo), Math.sin(la), Math.cos(la) * Math.cos(lo)]; };
    Object.keys(G.cities).forEach(function (k) { if (k === "Lagos" || G.arcs.indexOf(k) >= 0) nodes.push(toV(G.cities[k][1], G.cities[k][0])); });
    /* Africa takes most of the budget, in an even lattice so it reads as a printed halftone. */
    G.sphere(Math.round(n * 12), function (lon, lat) { if (G.isAfrica(lon, lat)) land.push(toV(lon, lat)); });
    G.sphere(Math.max(10, n - land.length - nodes.length), function (lon, lat) { if (!G.isAfrica(lon, lat)) grid.push(toV(lon, lat)); });
    var all = nodes.concat(land, grid);
    while (all.length < n) all.push(grid[all.length % grid.length] || land[0]);
    return { pts: all.slice(0, n), nodes: nodes.length, land: land.length };
  }
  var VS = [
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
    "}"
  ].join("\n");
  var FS = [
    "precision mediump float; uniform vec3 uColor, uAccent; varying float vA; varying float vAccent;",
    "void main() { vec2 c = gl_PointCoord - .5; float d = dot(c, c); if (d > .25) discard; gl_FragColor = vec4(mix(uColor, uAccent, vAccent), vA * smoothstep(.25, .17, d)); }"
  ].join("\n");
  function build() {
    var small = window.innerWidth < 720, n = small ? 7000 : 16000;
    var renderer = new THREE.WebGLRenderer({ alpha: true, antialias: false, powerPreference: "high-performance" });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small ? 1.5 : 1.75)); renderer.setClearColor(0, 0);
    var scene = new THREE.Scene(), camera = new THREE.PerspectiveCamera(35, 1, .1, 30); camera.position.z = 6;
    var globe = globePoints(n), marks = ["star", "comb", "spiral"].map(function (k) { return maskPoints(W.MARKS[k], n); });
    var toAttr = function (list) { var a = new Float32Array(n * 3); for (var i = 0; i < n; i++) { var v = list[i] || list[i % Math.max(1, list.length)]; a[i * 3] = v[0]; a[i * 3 + 1] = v[1]; a[i * 3 + 2] = v[2]; } return new THREE.BufferAttribute(a, 3); };
    var shapes = [toAttr(globe.pts)].concat(marks.map(toAttr));
    var rnd = new Float32Array(n * 4);
    for (var i = 0; i < n; i++) { rnd[i * 4] = Math.random(); rnd[i * 4 + 1] = Math.random(); rnd[i * 4 + 2] = Math.random(); rnd[i * 4 + 3] = i < globe.nodes ? 1 : (Math.random() < .04 ? .6 : (i >= globe.nodes + globe.land ? .2 : 0)); }
    var geo = new THREE.BufferGeometry();
    geo.setAttribute("position", shapes[0]); geo.setAttribute("aFrom", shapes[0]); geo.setAttribute("aTo", shapes[1]); geo.setAttribute("aRnd", new THREE.BufferAttribute(rnd, 4));
    var mat = new THREE.ShaderMaterial({ vertexShader: VS, fragmentShader: FS, transparent: true, depthTest: false, depthWrite: false, uniforms: {
      uT: { value: 0 }, uTime: { value: 0 }, uPointPx: { value: 2 }, uFromGlobe: { value: 1 }, uAlpha: { value: 1 }, uFromS: { value: 1 }, uToS: { value: 1 }, uMouseOn: { value: 0 },
      uRot: { value: new THREE.Matrix3() }, uFromO: { value: new THREE.Vector3() }, uToO: { value: new THREE.Vector3() }, uMouse: { value: new THREE.Vector2(99, 99) }, uColor: { value: new THREE.Color(token("--offwhite")) }, uAccent: { value: new THREE.Color(token("--orange")) } } });
    var pts = new THREE.Points(geo, mat); pts.frustumCulled = false; scene.add(pts);
    return { renderer: renderer, scene: scene, camera: camera, geo: geo, mat: mat, shapes: shapes, seg: 0, t0: 0, euler: new THREE.Euler(0, 0, 0, "YXZ"), m4: new THREE.Matrix4(), small: small };
  }
  function layout() {
    var w = window.innerWidth, h = window.innerHeight, aspect = w / h, H = 2 * 6 * Math.tan(17.5 * Math.PI / 180), Wd = H * aspect, wide = w >= 900;
    return { W: Wd, H: H, wide: wide, globeS: wide ? H * .4 : Math.min(H * .25, Wd * .47), globeX: wide ? Wd * .17 : 0, globeY: wide ? -H * .02 : H * .13, markS: wide ? H * .4 : Math.min(H * .25, Wd * .46), markX: wide ? Wd * .22 : 0, markY: wide ? H * .05 : H * .17 };
  }
  W.stage = {
    /* Calls back once the canvas is showing. Stays on the static fallback when it can't run. */
    init: function (host, done) {
      S.host = host;
      if (S.status === "ready") { attach(); if (done) done(); return; }
      if (S.status === "loading" || S.status === "failed") return;
      if (!W.hasWebGL()) { S.status = "failed"; S.reason = "no-webgl"; return; }
      S.status = "loading";
      var go = function () {
        W.loadThree(function () { try { S.scene = build(); S.status = "ready"; attach(); if (done) done(); } catch (e) { S.status = "failed"; S.reason = String(e && e.message || e); } }, function () { S.status = "failed"; S.reason = "three-load"; });
      };
      if (window.requestIdleCallback) requestIdleCallback(go, { timeout: 900 }); else setTimeout(go, 300);
    },
    layout: layout,
    resize: function () { if (S.scene) size(); },
    /* p: { seg, t, globe: {s,x,y}, mark: {s,x,y}, color, accent, alpha, mouse:[x,y]|null, spin:[yaw,pitch] } */
    frame: function (now, p) {
      var s = S.scene; if (!s) return;
      if (!s.t0) s.t0 = now;
      var u = s.mat.uniforms, time = (now - s.t0) / 1000;
      if (s.seg !== p.seg) { s.geo.setAttribute("aFrom", s.shapes[p.seg]); s.geo.setAttribute("aTo", s.shapes[p.seg + 1]); s.seg = p.seg; }
      var isGlobe = p.seg === 0;
      s.euler.set(p.spin[1], p.spin[0], 0); s.m4.makeRotationFromEuler(s.euler); u.uRot.value.setFromMatrix4(s.m4);
      u.uT.value = p.t; u.uTime.value = time; u.uFromGlobe.value = isGlobe ? 1 : 0; u.uAlpha.value = p.alpha;
      u.uFromS.value = isGlobe ? p.globe.s : p.mark.s; u.uToS.value = p.mark.s;
      u.uFromO.value.set(isGlobe ? p.globe.x : p.mark.x, isGlobe ? p.globe.y : p.mark.y, 0); u.uToO.value.set(p.mark.x, p.mark.y, 0);
      u.uPointPx.value = (s.small ? 1.75 : 2.3) * s.renderer.getPixelRatio();
      u.uColor.value.setRGB(p.color[0] / 255, p.color[1] / 255, p.color[2] / 255); u.uAccent.value.setRGB(p.accent[0] / 255, p.accent[1] / 255, p.accent[2] / 255);
      if (p.mouse) { u.uMouse.value.set(p.mouse[0], p.mouse[1]); u.uMouseOn.value += (1 - u.uMouseOn.value) * .08; } else u.uMouseOn.value *= .92;
      s.renderer.render(s.scene, s.camera); S.frames++;
    },
    status: function () { return { status: S.status, reason: S.reason, frames: S.frames }; }
  };
  function size() { var w = window.innerWidth, h = window.innerHeight; S.scene.renderer.setSize(w, h, false); S.scene.camera.aspect = w / h; S.scene.camera.updateProjectionMatrix(); }
  function attach() { var cv = S.scene.renderer.domElement; if (cv.parentNode !== S.host) S.host.appendChild(cv); size(); }
})();
