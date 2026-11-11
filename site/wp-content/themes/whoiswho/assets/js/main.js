/* Small shared script, loaded deferred on every page. Exposes the accessibility helpers to the page scripts that need them and starts the motion base. */
import { trapFocus } from "./focus-trap.js";
import { announce } from "./live-region.js";
import { initMotion, motionAllowed } from "./motion.js";

window.wiw = Object.assign(window.wiw || {}, { trapFocus, announce, motionAllowed });
initMotion();
