/* Hand-simplified outlines of Africa (longitude, latitude) and the city nodes the particle globe is built from. Not survey data: it only has to read as Africa at hero size. Ported from prototype/theme/hero-geo.js. */
const W = {};
W.GEO = {
  africa: [[-5.9, 35.8], [-1.9, 35.1], [3, 36.9], [10.2, 37.3], [11, 33.6], [15.2, 32.3], [19.9, 30.4], [20.1, 32.7], [25, 31.7], [29.9, 31.2], [32.3, 31.2], [32.6, 29.9], [34, 27], [35.6, 23.9], [37.2, 21], [38.6, 18], [39.7, 15.1], [43.2, 12.6], [43.3, 11.5], [45, 10.4], [51.2, 11.8], [50.8, 9], [48.9, 5], [45.3, 2], [41.6, -1.7], [39.7, -4], [39.3, -6.8], [40.5, -10.5], [40.7, -14.5], [37, -17.8], [34.9, -19.8], [35.5, -24], [32.9, -26], [32.4, -28.6], [30.9, -30], [28, -33], [25.7, -34], [20, -34.8], [18.4, -34.2], [18.1, -31.9], [16.5, -28.6], [15, -26.5], [14.5, -22.9], [11.8, -17.3], [12.2, -13], [13.2, -8.8], [12.3, -6], [11.8, -4.7], [9.3, -1.6], [9.5, .4], [9.7, 3], [9.5, 4], [8.4, 4.6], [6.1, 4.3], [4.4, 6.3], [1.2, 6.1], [-2, 4.8], [-4, 5.2], [-7.6, 4.4], [-11.4, 6.9], [-13.3, 8.5], [-15, 11], [-16.8, 12.4], [-17.5, 14.7], [-16.5, 16], [-16.3, 19], [-17, 21], [-14.9, 24.5], [-12.9, 27.9], [-9.8, 29.8], [-9.6, 32], [-6.8, 34]],
  madagascar: [[49.3, -12], [50.5, -15.5], [49.4, -18], [47.1, -24.9], [45.2, -25.6], [43.6, -23.4], [44.4, -19.9], [44.2, -16.5], [47.2, -15]],
  /* name: [latitude, longitude]. The first entry is the lead node. */
  cities: { Lagos: [6.52, 3.38], Abuja: [9.06, 7.49], "Port Harcourt": [4.82, 7.03], Ibadan: [7.38, 3.95], Kano: [12, 8.52], Enugu: [6.46, 7.55], Accra: [5.6, -.19], Nairobi: [-1.29, 36.82], Kigali: [-1.94, 30.06], Cairo: [30.04, 31.24], Johannesburg: [-26.2, 28.05], Dakar: [14.72, -17.47], Casablanca: [33.57, -7.59], "Addis Ababa": [9.03, 38.74] },
  arcs: ["Abuja", "Accra", "Nairobi", "Cairo", "Johannesburg", "Dakar", "Casablanca", "Addis Ababa", "Kigali"]
};
W.GEO.inPoly = function (lon, lat, poly) {
  var inside = false;
  for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
    var xi = poly[i][0], yi = poly[i][1], xj = poly[j][0], yj = poly[j][1];
    if ((yi > lat) !== (yj > lat) && lon < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) inside = !inside;
  }
  return inside;
};
W.GEO.isAfrica = function (lon, lat) { return W.GEO.inPoly(lon, lat, W.GEO.africa) || W.GEO.inPoly(lon, lat, W.GEO.madagascar); };
/* Evenly spaced points on a sphere (Fibonacci). Calls fn(lon, lat) for each. */
W.GEO.sphere = function (n, fn) {
  var g = Math.PI * (3 - Math.sqrt(5));
  for (var i = 0; i < n; i++) {
    var y = 1 - (i / (n - 1)) * 2, t = g * i;
    var lat = Math.asin(y) * 180 / Math.PI, lon = ((t * 180 / Math.PI) % 360 + 540) % 360 - 180;
    fn(lon, lat);
  }
};
export const GEO = W.GEO;
