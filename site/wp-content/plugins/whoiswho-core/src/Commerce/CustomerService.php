<?php
/**
 * The customer service (REQ-205, REQ-112, REQ-187, REQ-188). Creating and editing a customer needs the support inbox capability
 * (Editors, REQ-178) and writes an audit row that names the fields that changed and never holds a value, so no personal data
 * reaches the audit log. It has no payment, price or arrangement logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Customer service.
 */
final class CustomerService {

	public const CAP = 'wiw_support_inbox';

	/**
	 * Repository.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $customers;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Support items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Clock.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param CustomerRepository $customers Repository.
	 * @param AuditLogger        $audit     Audit logger.
	 * @param SupportItems|null  $items     Support items, for create-from-enquiry.
	 * @param callable|null      $clock     fn(): \DateTimeImmutable.
	 */
	public function __construct( CustomerRepository $customers, AuditLogger $audit, ?SupportItems $items = null, ?callable $clock = null ) {
		$this->customers = $customers;
		$this->audit     = $audit;
		$this->items     = $items ?? new SupportItems();
		$this->clock     = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Create a customer.
	 *
	 * @param array<string, string> $fields name, segment, contact_email, contact_phone, notes.
	 * @return int Customer id.
	 * @throws \DomainException When the user lacks the capability.
	 */
	public function create( array $fields ): int {
		$this->require_cap();
		$id = $this->customers->insert( $fields, null, ( $this->clock )() );
		$this->log( 'customer.create', $id, null, array_keys( array_intersect_key( $fields, array_flip( CustomerRepository::WRITABLE ) ) ) );
		return $id;
	}

	/**
	 * Create the customer for an enquiry (one customer per enquiry: a second call returns the first).
	 *
	 * @param int $enquiry_id Support item id of an enquiry.
	 * @return int Customer id.
	 * @throws \DomainException When the user lacks the capability or the item is not an enquiry.
	 */
	public function create_from_enquiry( int $enquiry_id ): int {
		$this->require_cap();
		$existing = $this->customers->find_by_enquiry( $enquiry_id );
		if ( null !== $existing ) {
			return (int) $existing['id'];
		}
		$item = $this->items->get( $enquiry_id );
		if ( null === $item || ItemType::ENQUIRY !== $item['item_type'] ) {
			throw new \DomainException( 'Not an enquiry.' );
		}
		$name = '' !== (string) ( $item['organisation'] ?? '' ) ? (string) $item['organisation'] : (string) $item['requester_name'];
		$id   = $this->customers->insert(
			array(
				'name'          => $name,
				'contact_email' => (string) $item['requester_email'],
				'contact_phone' => (string) ( $item['requester_phone'] ?? '' ),
			),
			$enquiry_id,
			( $this->clock )()
		);
		$this->log( 'customer.create', $id, array( 'enquiry' => $enquiry_id ), array( 'name', 'contact_email', 'contact_phone' ) );
		return $id;
	}

	/**
	 * Edit a customer.
	 *
	 * @param int                   $id     Customer id.
	 * @param array<string, string> $fields Values to change.
	 * @throws \DomainException When the user lacks the capability.
	 */
	public function update( int $id, array $fields ): void {
		$this->require_cap();
		$changed = $this->customers->update( $id, $fields, ( $this->clock )() );
		if ( array() !== $changed ) {
			$this->log( 'customer.update', $id, null, $changed );
		}
	}

	/**
	 * Audit row: ids and field names only.
	 *
	 * @param string                  $action Action.
	 * @param int                     $id     Customer.
	 * @param array<string, int>|null $before Ids only.
	 * @param array<int, string>      $fields Names of the fields written.
	 */
	private function log( string $action, int $id, ?array $before, array $fields ): void {
		$this->audit->log(
			$action,
			array(
				'type' => 'customer',
				'id'   => $id,
			),
			$before,
			array( 'fields' => array_values( $fields ) )
		);
	}

	/**
	 * Refuse without the capability.
	 *
	 * @throws \DomainException When the user lacks it.
	 */
	private function require_cap(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to manage customers.' );
		}
	}
}
