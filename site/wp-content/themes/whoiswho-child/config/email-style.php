<?php
/**
 * Which brand tokens the email layout uses (REQ-052). Names only, no values: inc/email.php reads the values from the child
 * theme's tokens file (assets/css/tokens.css) so the emails follow the brand when the tokens change. Email clients ignore
 * stylesheets and custom properties, so the layout receives the resolved values as inline styles.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	'page'     => '--c-bg',
	'card'     => '--c-surface',
	'text'     => '--c-text',
	'muted'    => '--c-muted',
	'line'     => '--c-line',
	'link'     => '--c-link',
	'cta'      => '--c-cta',
	'on_cta'   => '--c-on-cta',
	'dark'     => '--c-dark',
	'on_dark'  => '--c-on-dark',
	'headline' => '--font-display',
	'body'     => '--font-body',
);
