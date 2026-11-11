<?php
/**
 * Only the code under Platform/Audit (AuditLogger and its connection) touches wiw_audit_log. Any other source that
 * names the table in SQL or a call is a violation (REQ-051). The PII catalogue may list its columns as "table.column".
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * One writer for the audit log.
 */
final class OnlyAuditLoggerWritesAuditTable implements Rule {

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( 0 === strpos( $path, 'Platform/Audit/' ) ) {
				continue;
			}
			foreach ( $sources->strings( $path ) as $text ) {
				if ( false === strpos( $text, 'wiw_audit_log' ) ) {
					continue;
				}
				if ( 1 === preg_match( '/^wiw_audit_log\.[a-z_]+$/', $text ) ) {
					continue;
				}
				$bad[] = "$path names the audit table";
			}
		}
		return array_values( array_unique( $bad ) );
	}
}
