<?php
/**
 * The photo or logo of an existing profile, changed by staff on the Edit profile screen (REQ-162, REQ-203, REQ-125, REQ-188,
 * REQ-244, D-024; issue 806). A staff member with edit_wiw_profiles picks a JPEG, PNG or WebP from the media library and gives
 * its open licence (only ImagePolicy's allowlist), the creator or credit and the source page; or removes the image. The rules are
 * the ones a text edit follows (ProfileEditor): a mandatory reason, a version row with the old and new values, an audit row through
 * AuditLogger, and a purge of a public page. A reviewed profile stays reviewed. An edit by the assigned manager of a claimed
 * profile is not applied: it is stored as a pending revision and RevisionService applies it on approval (SideFields). The image
 * link stays in _wiw_image_id and the credit is written by ImageMeta::save(). The media library item itself is never deleted.
 * Domain service; the screen and handler stay thin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Edit;

use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Purge\PurgeQueue;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Profile image editor.
 */
final class ProfileImageEditor {

	public const CAPABILITY = ProfileEditor::EDIT_CAPABILITY;

	/**
	 * Longest creator or credit text, in characters.
	 */
	public const CREDIT_MAX = 300;

	/**
	 * Person entity types (they get a photo; the others a logo).
	 */
	private const PERSON_TYPES = array( 'individual', 'entrepreneur' );

	/**
	 * The current image of a profile and its credit, for the form.
	 *
	 * @param int $profile_id Profile.
	 * @return array{id: int, licence: string, creator: string, source_url: string}
	 */
	public static function current( int $profile_id ): array {
		$id = (int) SideFields::current( $profile_id )[ SideFields::IMAGE ];
		return array( 'id' => $id ) + self::credit_of( $id );
	}

	/**
	 * Set or change the image and its credit.
	 *
	 * @param int                  $profile_id    Profile.
	 * @param int                  $attachment_id Media library item.
	 * @param array<string, mixed> $credit        licence, creator, source_url.
	 * @param string               $reason        Why. Required.
	 * @param int                  $actor_id      The user making the change.
	 * @return array{version_id: int, applied: bool, changed: string[]}
	 * @throws EditRefused With a message fit to show.
	 */
	public function set( int $profile_id, int $attachment_id, array $credit, string $reason, int $actor_id ): array {
		$state = $this->checked( $profile_id, $reason, $actor_id );
		$mime  = $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ? (string) get_post_mime_type( $attachment_id ) : '';
		if ( ! isset( ImagePolicy::TYPES[ $mime ] ) ) {
			throw EditRefused::because( 'Choose a JPEG, PNG or WebP image from the media library.' );
		}
		$licence = trim( (string) ( $credit['licence'] ?? '' ) );
		if ( ! ImagePolicy::licence_allowed( $licence ) ) {
			throw EditRefused::because( 'Choose one of the open licences in the list.' );
		}
		$creator = trim( sanitize_text_field( (string) ( $credit['creator'] ?? '' ) ) );
		if ( '' === $creator || mb_strlen( $creator ) > self::CREDIT_MAX ) {
			throw EditRefused::because( 'Give the creator or credit the licence asks for (up to 300 characters).' );
		}
		$source = trim( (string) ( $credit['source_url'] ?? '' ) );
		if ( strlen( $source ) > 2000 || false === filter_var( $source, FILTER_VALIDATE_URL ) || 1 !== preg_match( '#^https?://#i', $source ) ) {
			throw EditRefused::because( 'Give the full web address of the page the image comes from (starting with http:// or https://).' );
		}
		$kind  = in_array( (string) $state['entity_type'], self::PERSON_TYPES, true ) ? 'photo' : 'logo';
		$now   = self::current( $profile_id );
		$after = array(
			'licence'    => $licence,
			'creator'    => $creator,
			'source_url' => esc_url_raw( $source, array( 'http', 'https' ) ),
		);
		$same  = self::credit_of( $attachment_id );
		if ( $now['id'] === $attachment_id && $same === $after ) {
			throw EditRefused::because( 'Nothing changed.' );
		}
		$old = array(
			SideFields::IMAGE        => $now['id'] > 0 ? $now['id'] : null,
			SideFields::IMAGE_CREDIT => $now['id'] > 0 ? array( 'attachment_id' => $now['id'] ) + self::credit_of( $now['id'] ) : null,
		);
		$new = array(
			SideFields::IMAGE        => $attachment_id,
			SideFields::IMAGE_CREDIT => array( 'attachment_id' => $attachment_id ) + $after + array( 'kind' => $kind ),
		);
		return $this->record( $profile_id, $state, $old, $new, trim( $reason ), $actor_id );
	}

	/**
	 * Remove the image from the profile (the media library item stays).
	 *
	 * @param int    $profile_id Profile.
	 * @param string $reason     Why. Required.
	 * @param int    $actor_id   The user making the change.
	 * @return array{version_id: int, applied: bool, changed: string[]}
	 * @throws EditRefused With a message fit to show.
	 */
	public function remove( int $profile_id, string $reason, int $actor_id ): array {
		$state = $this->checked( $profile_id, $reason, $actor_id );
		$now   = self::current( $profile_id );
		if ( $now['id'] < 1 ) {
			throw EditRefused::because( 'Nothing changed.' );
		}
		return $this->record(
			$profile_id,
			$state,
			array( SideFields::IMAGE => $now['id'] ),
			array( SideFields::IMAGE => null ),
			trim( $reason ),
			$actor_id
		);
	}

	/**
	 * The checks every change makes: capability, reason and the profile.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $reason     Reason.
	 * @param int    $actor_id   Actor.
	 * @return array<string, mixed> The state row.
	 * @throws EditRefused When refused.
	 */
	private function checked( int $profile_id, string $reason, int $actor_id ): array {
		if ( $actor_id < 1 || ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			throw EditRefused::because( 'You are not allowed to edit profiles.' );
		}
		if ( '' === trim( $reason ) ) {
			throw EditRefused::because( 'A reason or request reference is required to change the image.' );
		}
		$state = ( new StateRepository() )->get( $profile_id );
		if ( null === $state || ProfileType::POST_TYPE !== get_post_type( $profile_id ) ) {
			throw EditRefused::because( 'That profile does not exist.' );
		}
		return $state;
	}

	/**
	 * Write the version row, apply (unless it waits for review), audit and purge, as a text edit does.
	 *
	 * @param int                  $profile_id Profile.
	 * @param array<string, mixed> $state      State row.
	 * @param array<string, mixed> $old        Old values.
	 * @param array<string, mixed> $new        New values.
	 * @param string               $reason     Reason.
	 * @param int                  $actor_id   Actor.
	 * @return array{version_id: int, applied: bool, changed: string[]}
	 */
	private function record( int $profile_id, array $state, array $old, array $new, string $reason, int $actor_id ): array {
		$pending = (int) ( $state['claimed'] ?? 0 ) > 0 && (int) ( $state['assigned_user_id'] ?? 0 ) === $actor_id;
		$version = ( new VersionRepository() )->append( $profile_id, $actor_id, $pending ? 'revision' : 'edit', $reason, $old, $new, $pending ? 'pending' : null );
		if ( ! $pending ) {
			if ( isset( $new[ SideFields::IMAGE_CREDIT ] ) ) {
				SideFields::apply( $profile_id, SideFields::IMAGE_CREDIT, $new[ SideFields::IMAGE_CREDIT ] );
			}
			SideFields::apply( $profile_id, SideFields::IMAGE, $new[ SideFields::IMAGE ] );
		}
		AuditLogger::for_site()->log(
			$pending ? 'profile.revision_submitted' : 'profile.image_change',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			$old,
			$new,
			$reason
		);
		if ( ! $pending && PubState::is_public( (string) $state['pub_state'] ) ) {
			PurgeQueue::for_site()->queue( array( $profile_id ), 'edit' );
		}
		return array(
			'version_id' => $version,
			'applied'    => ! $pending,
			'changed'    => array_keys( $new ),
		);
	}

	/**
	 * The credit stored on an attachment: licence, creator (the attribution text, else the creator) and source page.
	 *
	 * @param int $attachment_id Attachment, or 0.
	 * @return array{licence: string, creator: string, source_url: string}
	 */
	private static function credit_of( int $attachment_id ): array {
		if ( $attachment_id < 1 ) {
			return array(
				'licence'    => '',
				'creator'    => '',
				'source_url' => '',
			);
		}
		$meta        = static fn ( string $key ): string => (string) get_post_meta( $attachment_id, $key, true );
		$attribution = $meta( ImageMeta::ATTRIBUTION );
		return array(
			'licence'    => $meta( ImageMeta::LICENCE ),
			'creator'    => '' !== $attribution ? $attribution : $meta( ImageMeta::CREATOR ),
			'source_url' => $meta( ImageMeta::SOURCE_URL ),
		);
	}
}
