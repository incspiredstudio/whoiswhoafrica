/* Runs inline in the head, before first paint: sets the header state classes so a refresh does not flash.
   data-home="1" is set by the server on the home page. */
(function () {
  var r = document.documentElement;
  var s = document.currentScript;
  if (s && s.getAttribute("data-home") === "1") {
    r.classList.add("is-home", "hdr-init");
    if ((window.scrollY || 0) < 60) r.classList.add("at-top");
  }
})();
