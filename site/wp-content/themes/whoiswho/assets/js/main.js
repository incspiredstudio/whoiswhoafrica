/* Small shared script, loaded deferred on every page. Exposes the accessibility helpers to the page scripts that need them. */
import { trapFocus } from "./focus-trap.js";
import { announce } from "./live-region.js";
import { initFilterBars } from "./filter-bar.js";

window.wiw = Object.assign(window.wiw || {}, { trapFocus, announce });
initFilterBars();
