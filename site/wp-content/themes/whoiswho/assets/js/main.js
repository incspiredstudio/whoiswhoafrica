/* Small shared script, loaded deferred on every page. Exposes the accessibility helpers to the page scripts that need them and starts the header, filter bars, the motion base and the compact image credit. */
import { trapFocus } from "./focus-trap.js";
import { announce } from "./live-region.js";
import { initFilterBars } from "./filter-bar.js";
import { initHeader } from "./header.js";
import { initMotion, motionAllowed, scrollToY } from "./motion.js";
import { initCookieNotice } from "./cookie.js";
import { initReportDialog } from "./report-dialog.js";
import { initNomineeTabs } from "./nominee-tabs.js";
import { initCreditToggles } from "./credit-toggle.js";
import { initPagerJump } from "./pager.js";

window.wiw = Object.assign(window.wiw || {}, { trapFocus, announce, motionAllowed, scrollToY });
initFilterBars();
initHeader();
initMotion();
initCookieNotice();
initReportDialog();
initNomineeTabs();
initCreditToggles();
initPagerJump();
