/* A minimal WebGL point renderer for the home particle stage (issue 808, REQ-041, REQ-043). It replaces the 651 KB Three.js bundle:
   the stage only ever drew one point cloud with one shader, so it needs a program, a few buffers, uniforms and two matrices. */

/** Column-major 4x4 perspective matrix, as a camera with a vertical field of view in degrees. */
export function perspective(fovDeg, aspect, near, far) {
  const top = near * Math.tan((fovDeg * Math.PI) / 360);
  const height = 2 * top;
  const width = aspect * height;
  const m = new Float32Array(16);
  m[0] = (2 * near) / width;
  m[5] = (2 * near) / height;
  m[10] = -(far + near) / (far - near);
  m[11] = -1;
  m[14] = (-2 * far * near) / (far - near);
  return m;
}

/** Column-major 3x3 rotation for Euler angles applied in Y, X, Z order (z is always 0 here). */
export function rotationYXZ(x, y) {
  const a = Math.cos(x);
  const b = Math.sin(x);
  const c = Math.cos(y);
  const d = Math.sin(y);
  return new Float32Array([c, 0, -d, d * b, a, c * b, a * d, -b, a * c]);
}

function shader(gl, type, source) {
  const s = gl.createShader(type);
  gl.shaderSource(s, source);
  gl.compileShader(s);
  if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error("shader");
  return s;
}

/**
 * A point cloud: `attributes` maps an attribute name to its size (3 or 4). Returns null when WebGL is not available.
 * Uniforms are set by name with uniform(name, value): a number, an array of 2 or 3, or a Float32Array of 9 or 16 (a matrix).
 */
export function createPoints(canvas, vertexSource, fragmentSource, attributes) {
  const gl = canvas.getContext("webgl", { alpha: true, antialias: false, premultipliedAlpha: true, depth: false, stencil: false, powerPreference: "high-performance" });
  if (!gl) return null;
  const program = gl.createProgram();
  gl.attachShader(program, shader(gl, gl.VERTEX_SHADER, vertexSource));
  gl.attachShader(program, shader(gl, gl.FRAGMENT_SHADER, fragmentSource));
  gl.linkProgram(program);
  if (!gl.getProgramParameter(program, gl.LINK_STATUS)) throw new Error("program");
  gl.useProgram(program);
  gl.disable(gl.DEPTH_TEST);
  gl.enable(gl.BLEND);
  gl.blendFuncSeparate(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA, gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
  gl.clearColor(0, 0, 0, 0);
  const locations = {};
  const sizes = {};
  for (const name of Object.keys(attributes)) {
    locations[name] = gl.getAttribLocation(program, name);
    sizes[name] = attributes[name];
  }
  const uniforms = {};
  let count = 0;
  return {
    gl,
    /** Upload a Float32Array once; returns a handle for bind(). */
    buffer(data) {
      const b = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, b);
      gl.bufferData(gl.ARRAY_BUFFER, data, gl.STATIC_DRAW);
      count = Math.max(count, data.length / 3);
      return b;
    },
    /** Point an attribute at a buffer made by buffer(). */
    bind(name, handle) {
      const loc = locations[name];
      if (loc < 0) return;
      gl.bindBuffer(gl.ARRAY_BUFFER, handle);
      gl.enableVertexAttribArray(loc);
      gl.vertexAttribPointer(loc, sizes[name], gl.FLOAT, false, 0, 0);
    },
    uniform(name, value) {
      if (!(name in uniforms)) uniforms[name] = gl.getUniformLocation(program, name);
      const loc = uniforms[name];
      if (loc === null) return;
      if (typeof value === "number") gl.uniform1f(loc, value);
      else if (value.length === 2) gl.uniform2f(loc, value[0], value[1]);
      else if (value.length === 3) gl.uniform3f(loc, value[0], value[1], value[2]);
      else if (value.length === 9) gl.uniformMatrix3fv(loc, false, value);
      else if (value.length === 16) gl.uniformMatrix4fv(loc, false, value);
    },
    /** Size the drawing buffer in device pixels; the canvas keeps its CSS size. */
    size(cssWidth, cssHeight, ratio) {
      canvas.width = Math.max(1, Math.round(cssWidth * ratio));
      canvas.height = Math.max(1, Math.round(cssHeight * ratio));
      gl.viewport(0, 0, canvas.width, canvas.height);
    },
    draw(points) {
      gl.clear(gl.COLOR_BUFFER_BIT);
      gl.drawArrays(gl.POINTS, 0, points || count);
    },
    /** Free the GPU objects (the page is leaving or the stage stops for good). */
    dispose() {
      const lose = gl.getExtension("WEBGL_lose_context");
      if (lose) lose.loseContext();
    },
  };
}
