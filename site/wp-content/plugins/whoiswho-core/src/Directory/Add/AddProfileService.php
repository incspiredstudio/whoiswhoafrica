<?php
/**
 * Creates a profile entered by hand by staff (REQ-209, REQ-208, REQ-143, REQ-147, REQ-244). The profile is created through the
 * same path as an import: a hidden post, its state row through Review\StateMachine (Imported not public, so it sits in the review
 * queue and is not public until a reviewer approves it), the allowlisted fields, the terms, and a provenance row per field with the
 * method "manual" and the given source URL. The creator is recorded as the importer, so the same person cannot approve it. A name
 * or identifier on the suppression list is refused. Everything is written in one transaction and audited through AuditLogger with
 * ids and field names only, never the values.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Add;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Db\Transaction;
use WhoIsWho\Core\Review\Duplicates\Matcher;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Add profile service.
 */
final class AddProfileService {

	public const CAPABILITY = 'wiw_add_profile';
	public const ORIGIN     = 'manual';

	/**
	 * Confidence recorded for a field a staff member entered with a source.
	 */
	public const CONFIDENCE = 'medium';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Suppression list.
	 *
	 * @var Suppression
	 */
	private Suppression $suppression;

	/**
	 * Form fields.
	 *
	 * @var AddProfileFields
	 */
	private AddProfileFields $form;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger|null      $audit       Audit logger.
	 * @param Suppression|null      $suppression Suppression list.
	 * @param AddProfileFields|null $form        Form fields.
	 */
	public function __construct( ?AuditLogger $audit = null, ?Suppression $suppression = null, ?AddProfileFields $form = null ) {
		$this->audit       = $audit ?? AuditLogger::for_site();
		$this->suppression = $suppression ?? new Suppression( $this->audit );
		$this->form        = $form ?? new AddProfileFields();
	}

	/**
	 * Whether a name or identifier is on the suppression list.
	 *
	 * @param string                $name         Name.
	 * @param string                $type         Entity type.
	 * @param array<string, string> $external_ids Identifiers.
	 */
	public function is_suppressed( string $name, string $type, array $external_ids = array() ): bool {
		return null !== $this->suppression->match( $name, $type, array_filter( $external_ids ) );
	}

	/**
	 * Create the profile.
	 *
	 * @param string                $type         Entity type.
	 * @param array<string, mixed>  $fields       Field key => value: name, the type's form fields (a list is an array of strings, a
	 *                                            controlled-list field is a term id).
	 * @param string[]              $sources      Source URLs, at least one.
	 * @param int                   $country      Country term id (0 for none).
	 * @param array<string, string> $external_ids Identifiers (wikidata_qid, rc_number).
	 * @param array<string, mixed>  $image        Optional image: id, licence, credit, source.
	 * @param bool                  $override     The staff member confirmed "create anyway" over possible duplicates.
	 * @return int Profile id.
	 * @throws \DomainException Without the capability, for a suppressed name, an unknown type or no source.
	 */
	public function create( string $type, array $fields, array $sources, int $country, array $external_ids = array(), array $image = array(), bool $override = false ): int {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'You are not allowed to add profiles.' );
		}
		$name = trim( (string) ( $fields['name'] ?? '' ) );
		if ( ! $this->form->is_type( $type ) || '' === $name ) {
			throw new \DomainException( 'Choose a type and give a name.' );
		}
		$sources = array_values( array_filter( array_map( 'trim', $sources ), static fn ( string $url ): bool => '' !== $url ) );
		if ( array() === $sources ) {
			throw new \DomainException( 'Give at least one source URL.' );
		}
		$external_ids = array_filter( array_map( 'trim', $external_ids ), static fn ( string $value ): bool => '' !== $value );
		if ( $this->is_suppressed( $name, $type, $external_ids ) ) {
			throw new \DomainException( 'This name is on the suppression list, so it cannot be added. Ask an Administrator.' );
		}
		$actor = get_current_user_id();
		$id    = (int) Transaction::run( fn (): int => $this->write( $type, $name, $fields, $sources, $country, $external_ids, $image, $actor, $override ) );
		// Flag probable duplicates for the Duplicates screen as the import does; this only writes pairs for a person to resolve.
		( new Matcher() )->scan( $id );
		return $id;
	}

	/**
	 * Write the profile inside the transaction.
	 *
	 * @param string                $type         Entity type.
	 * @param string                $name         Name.
	 * @param array<string, mixed>  $fields       Fields.
	 * @param string[]              $sources      Sources.
	 * @param int                   $country      Country term.
	 * @param array<string, string> $external_ids Identifiers.
	 * @param array<string, mixed>  $image        Image.
	 * @param int                   $actor        Staff member.
	 * @param bool                  $override     Create anyway.
	 * @throws \RuntimeException When the post cannot be created.
	 */
	private function write( string $type, string $name, array $fields, array $sources, int $country, array $external_ids, array $image, int $actor, bool $override ): int {
		$post = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => ProfileType::STATUS_HIDDEN,
			),
			true
		);
		if ( is_wp_error( $post ) || (int) $post < 1 ) {
			throw new \RuntimeException( 'The profile could not be created.' );
		}
		$id = (int) $post;
		( new StateMachine( null, $this->audit ) )->enter(
			$id,
			PubState::IMPORTED_NOT_PUBLIC,
			array(
				'entity_type' => $type,
				'origin'      => self::ORIGIN,
				'source'      => self::ORIGIN,
				'importer_id' => $actor,
			),
			'Added by hand'
		);
		$stored = $this->store_fields( $id, $type, $name, $fields, $sources );
		if ( $country > 0 ) {
			wp_set_object_terms( $id, array( $country ), Taxonomies::PLACE, true );
		}
		if ( array() !== $external_ids ) {
			update_post_meta( $id, Matcher::EXTERNAL_IDS_META, (string) wp_json_encode( $external_ids ) );
		}
		$image_id = $this->attach_image( $id, $type, $image );
		$this->audit->log(
			'profile.create',
			array(
				'type' => 'profile',
				'id'   => $id,
			),
			null,
			array(
				'entity_type'        => $type,
				'origin'             => self::ORIGIN,
				'fields'             => $stored,
				'sources'            => count( $sources ),
				'image'              => $image_id,
				'duplicate_override' => $override,
			),
			$override ? 'Created by hand; staff confirmed it is not a duplicate' : 'Created by hand'
		);
		return $id;
	}

	/**
	 * Store the name and the filled fields, their terms and their provenance.
	 *
	 * @param int                  $id      Profile.
	 * @param string               $type    Entity type.
	 * @param string               $name    Name.
	 * @param array<string, mixed> $fields  Fields.
	 * @param string[]             $sources Sources.
	 * @return string[] The keys stored.
	 */
	private function store_fields( int $id, string $type, string $name, array $fields, array $sources ): array {
		$store = new ProfileFields();
		$prov  = new ProvenanceRepository();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$keys  = array( 'name' );
		foreach ( $sources as $url ) {
			$prov->record( $id, $this->provenance( 'name', $url, $now ) );
		}
		foreach ( $this->form->for_type( $type ) as $key => $rule ) {
			$value = $fields[ $key ] ?? null;
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}
			$taxonomy = TermMapper::FIELDS[ $key ] ?? '';
			if ( '' !== $taxonomy ) {
				$term = get_term( (int) $value, $taxonomy );
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				wp_set_object_terms( $id, array( $term->term_id ), $taxonomy, Taxonomies::PLACE === $taxonomy );
				$value = $term->name;
			}
			if ( 'list' === $rule['type'] && ! is_array( $value ) ) {
				$value = array( (string) $value );
			}
			if ( ! $this->form->is_listed( $type, $key ) ) {
				continue;
			}
			$store->set( $id, $key, $value );
			$prov->record( $id, $this->provenance( $key, $sources[0], $now ) );
			$keys[] = $key;
		}//end foreach
		return $keys;
	}

	/**
	 * One provenance entry.
	 *
	 * @param string $field Field.
	 * @param string $url   Source URL.
	 * @param string $now   Time.
	 * @return array<string, string>
	 */
	private function provenance( string $field, string $url, string $now ): array {
		return array(
			'field'        => $field,
			'source_url'   => $url,
			'retrieved_at' => $now,
			'confidence'   => self::CONFIDENCE,
			'method'       => self::ORIGIN,
		);
	}

	/**
	 * Make a media library image the profile's photo or logo and keep its licence and credit on the attachment.
	 *
	 * @param int                  $id    Profile.
	 * @param string               $type  Entity type.
	 * @param array<string, mixed> $image id, licence, credit, source.
	 * @return int The attachment id, or 0 for none.
	 */
	private function attach_image( int $id, string $type, array $image ): int {
		$attachment = (int) ( $image['id'] ?? 0 );
		$licence    = (string) ( $image['licence'] ?? '' );
		if ( $attachment < 1 || 'attachment' !== get_post_type( $attachment ) || ! ImagePolicy::licence_allowed( $licence ) ) {
			return 0;
		}
		ImageMeta::save(
			$attachment,
			array(
				'licence'          => $licence,
				'licence_url'      => ImagePolicy::licence_url( $licence ),
				'attribution_text' => (string) ( $image['credit'] ?? '' ),
				'source_url'       => (string) ( $image['source'] ?? '' ),
				'kind'             => in_array( $type, AddProfileFields::PERSON_TYPES, true ) ? 'photo' : 'logo',
			)
		);
		update_post_meta( $id, PublicProfile::IMAGE_META, (string) $attachment );
		return $attachment;
	}
}
