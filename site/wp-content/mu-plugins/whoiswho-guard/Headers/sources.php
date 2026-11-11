<?php
/**
 * The one list of extra sources the Content-Security-Policy allows. Each issue that adds an outside source (embedded video,
 * analytics, a font host) adds one line here, with the reason. Directive => list of allowed sources.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	'script-src'  => array(),
	'style-src'   => array(),
	'img-src'     => array(),
	'font-src'    => array(),
	'connect-src' => array(),
	'frame-src'   => array(),
	'media-src'   => array(),
);
