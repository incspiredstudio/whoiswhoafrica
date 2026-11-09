/* The hero's 3D W: the logo's facets as glossy, bevelled solids, far larger than the screen, lit by the brand colours.
   It flies together on arrival, turns slowly, leans toward the pointer and parts as the visitor scrolls away.
   The flat W is already in the markup, so nothing is lost when this can't run. theme/home.js drives it. */
(function () {
  var W = window.W; if (typeof document === "undefined" || !document.documentElement) return;
  var waiting = [];
  /* Three.js loads once, on demand, for both this and the particle stage. */
  W.loadThree = function (cb, fail) {
    if (window.THREE) { cb(); return; }
    waiting.push({ cb: cb, fail: fail });
    if (waiting.length > 1) return;
    var s = document.createElement("script"); s.src = "theme/vendor/three.min.js"; s.async = true;
    s.onload = function () { var q = waiting; waiting = []; q.forEach(function (w) { w.cb(); }); };
    s.onerror = function () { var q = waiting; waiting = []; q.forEach(function (w) { if (w.fail) w.fail(); }); };
    document.head.appendChild(s);
  };
  W.hasWebGL = function () { try { var c = document.createElement("canvas"); return !!(window.WebGLRenderingContext && (c.getContext("webgl") || c.getContext("experimental-webgl"))); } catch (e) { return false; } };

  var S = { status: "idle", reason: "", frames: 0, host: null, scene: null };
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function token(n) { return getComputedStyle(document.documentElement).getPropertyValue(n).trim(); }
  /* Reflections come from a small room of glowing brand-colour panels, baked once into an environment map. */
  function environment(renderer) {
    var pm = new THREE.PMREMGenerator(renderer), room = new THREE.Scene();
    room.background = new THREE.Color(0x070707);
    var panel = function (col, k, w, h, x, y, z) {
      var m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color(col).multiplyScalar(k), side: THREE.DoubleSide }));
      m.position.set(x, y, z); m.lookAt(0, 0, 0); room.add(m);
    };
    panel(token("--orange"), 4, 3, 12, -7, 1, 5);
    panel(token("--blue"), 4, 12, 2.5, 6, -4, 4);
    panel(token("--purple"), 3, 8, 8, 2, 7, -5);
    panel(token("--offwhite"), 2.2, 2, 6, 5, 4, 7);
    /* broad, dim washes so the flat faces carry a sheen as they turn */
    panel(token("--purple"), .55, 16, 10, -3, 5, 8);
    panel(token("--blue"), .45, 14, 10, 7, -2, 6);
    panel(token("--orange"), .35, 10, 10, -6, -6, 6);
    var tex = pm.fromScene(room, .035).texture; pm.dispose(); return tex;
  }
  function build() {
    var L = W.LOGO, cx = L.w / 2, cy = L.h / 2, small = window.innerWidth < 720;
    var renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: "high-performance" });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small ? 1.5 : 1.75)); renderer.setClearColor(0, 0);
    renderer.toneMapping = THREE.ACESFilmicToneMapping; renderer.toneMappingExposure = 1.3;
    var scene = new THREE.Scene(), camera = new THREE.PerspectiveCamera(30, 1, 5, 9000), group = new THREE.Group(); scene.add(group);
    var env = environment(renderer);
    scene.add(new THREE.AmbientLight(0xffffff, .15));
    var glow = new THREE.PointLight(new THREE.Color(token("--orange")), 2.2, 0, 0); glow.position.set(-160, 120, 260); scene.add(glow);
    var rim = new THREE.DirectionalLight(new THREE.Color(token("--blue")), 1.4); rim.position.set(420, -260, 120); scene.add(rim);
    var body = new THREE.MeshPhysicalMaterial({ color: 0x2a2a2a, metalness: .74, roughness: .16, clearcoat: 1, clearcoatRoughness: .05, envMap: env, envMapIntensity: 2.1 });
    var heart = new THREE.MeshPhysicalMaterial({ color: new THREE.Color(token("--orange")), metalness: .15, roughness: .24, clearcoat: 1, clearcoatRoughness: .05, envMap: env, envMapIntensity: .55, emissive: new THREE.Color(token("--orange")), emissiveIntensity: .22 });
    var extrude = function (pts, depth, bevel) {
      var sh = new THREE.Shape(); pts.forEach(function (p, i) { if (i) sh.lineTo(p[0] - cx, cy - p[1]); else sh.moveTo(p[0] - cx, cy - p[1]); }); sh.closePath();
      var g = new THREE.ExtrudeGeometry(sh, { depth: depth, curveSegments: 1, bevelEnabled: true, bevelThickness: bevel * 1.4, bevelSize: bevel, bevelOffset: -bevel, bevelSegments: 5 });
      g.translate(0, 0, -depth / 2); g.computeVertexNormals(); return g;
    };
    var pieces = L.facets.map(function (f, i) {
      var m = new THREE.Mesh(extrude(f, 48, 6), body), c = f.reduce(function (a, p) { return [a[0] + p[0] / f.length, a[1] + p[1] / f.length]; }, [0, 0]);
      var dx = c[0] - cx, dy = cy - c[1], len = Math.max(1, Math.hypot(dx, dy));
      m.userData = { dir: [dx / len, dy / len], spin: [(i % 2 ? 1 : -1) * 1.4, (i % 3 - 1) * 1.2], i: i };
      group.add(m); return m;
    });
    var africa = new THREE.Mesh(extrude(L.africa(), 70, 2.2), heart); africa.userData = { dir: [0, 0], spin: [0, 2.4], i: 6 }; group.add(africa); pieces.push(africa);
    return { renderer: renderer, scene: scene, camera: camera, group: group, pieces: pieces, glow: glow, t0: 0, wide: true };
  }
  /* Frame the W so it overflows the screen: a background, not an illustration. */
  function size() {
    var s = S.scene, h = S.host; if (!s || !h) return;
    var w = Math.max(1, h.clientWidth), ht = Math.max(1, h.clientHeight), half = Math.tan(15 * Math.PI / 180), L = W.LOGO, wide = w / ht > 1;
    s.renderer.setSize(w, ht, false); s.camera.aspect = w / ht; s.wide = wide;
    var visibleW = wide ? L.w * 1.22 : L.w * .64;
    /* desktop: Africa sits between the two lines of the title; phone: above the title */
    s.camera.position.set(wide ? -14 : 0, wide ? -6 : -66, visibleW / s.camera.aspect / 2 / half);
    s.camera.updateProjectionMatrix();
  }
  W.heroW = {
    init: function (host, done) {
      S.host = host;
      if (S.status === "ready") { if (S.scene.renderer.domElement.parentNode !== host) host.appendChild(S.scene.renderer.domElement); S.scene.t0 = 0; size(); if (done) done(); return; }
      if (S.status === "loading" || S.status === "failed") return;
      if (!W.hasWebGL()) { S.status = "failed"; S.reason = "no-webgl"; return; }
      S.status = "loading";
      var go = function () {
        W.loadThree(function () {
          try { S.scene = build(); S.status = "ready"; if (S.host) { S.host.appendChild(S.scene.renderer.domElement); size(); } if (done) done(); }
          catch (e) { S.status = "failed"; S.reason = String(e && e.message || e); }
        }, function () { S.status = "failed"; S.reason = "three-load"; });
      };
      if (window.requestIdleCallback) requestIdleCallback(go, { timeout: 500 }); else setTimeout(go, 200);
    },
    resize: size,
    /* p: { px, py: pointer from -1 to 1; open: 0 at rest, 1 once the hero has left } */
    frame: function (now, p) {
      var s = S.scene; if (!s) return;
      if (!s.t0) s.t0 = now;
      var time = (now - s.t0) / 1000, open = p.open;
      s.pieces.forEach(function (m) {
        var u = m.userData, k = clamp(time * .8 - u.i * .07, 0, 1), e = 1 - Math.pow(1 - k, 4), gone = 1 - e;
        m.position.set(u.dir[0] * (gone * 520 + open * 120), u.dir[1] * (gone * 520 + open * 120), gone * 700 + open * (u.i % 2 ? 140 : -40));
        m.rotation.set(u.spin[0] * (gone + open * .3), u.spin[1] * (gone + open * .3), 0);
      });
      s.group.rotation.y = -.3 + Math.sin(time * .21) * .16 + p.px * .22 + open * .5;
      s.group.rotation.x = .16 + Math.cos(time * .17) * .07 + p.py * .14 - open * .2;
      s.group.rotation.z = -.06 + Math.sin(time * .13) * .03;
      s.group.position.z = open * 160;
      s.glow.position.set(p.px * 320 - 60, -p.py * 220 + 60, 260);
      s.renderer.render(s.scene, s.camera); S.frames++;
    },
    status: function () { return { status: S.status, reason: S.reason, frames: S.frames }; }
  };
})();
