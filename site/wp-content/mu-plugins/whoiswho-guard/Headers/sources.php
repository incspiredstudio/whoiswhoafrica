<?php
/**
 * The one list of extra sources the Content-Security-Policy allows. Each issue that adds an outside source (embedded video,
 * analytics, a font host) adds one line here, with the reason. Directive => list of allowed sources.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	// Cloudflare Turnstile (REQ-081): its script and its challenge frame, used only once the Administrator saves both keys.
	'script-src'  => array( 'https://challenges.cloudflare.com' ),
	'style-src'   => array(),
	'img-src'     => array(),
	// The admin bar a signed-in user sees on public pages loads WordPress's Dashicons, whose font is embedded as a data: URL (issue 806).
	'font-src'    => array( 'data:' ),
	'connect-src' => array(),
	'frame-src'   => array( 'https://challenges.cloudflare.com' ),
	'media-src'   => array(),
);
