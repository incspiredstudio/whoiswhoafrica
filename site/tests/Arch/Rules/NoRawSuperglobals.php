<?php
/**
 * No raw $_GET, $_POST or $_REQUEST outside the validators and the request readers (REQ-046). Everything else reads input through
 * Platform\Admin\Request, Platform\Forms\FormInput or a validator. Two guard files read the query string or the sign-in form before
 * WordPress has routed the request; they are listed with the reason.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use PhpParser\Node;
use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * Input is read in one place.
 */
final class NoRawSuperglobals implements Rule {

	/**
	 * Allowed places: path prefix or file. Guard files act before routing and sanitise what they read.
	 */
	public const ALLOWED = array(
		'Platform/Forms/',
		'Platform/Admin/Request.php',
		'Exposure/Exposure.php',
		'Login/Login.php',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			foreach ( self::ALLOWED as $allowed ) {
				if ( $path === $allowed || ( '/' === substr( $allowed, -1 ) && 0 === strpos( $path, $allowed ) ) ) {
					continue 2;
				}
			}
			foreach ( $sources->nodes( $path, Node\Expr\Variable::class ) as $variable ) {
				if ( is_string( $variable->name ) && in_array( $variable->name, array( '_GET', '_POST', '_REQUEST' ), true ) ) {
					$bad[] = "$path reads \${$variable->name}";
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}
}
