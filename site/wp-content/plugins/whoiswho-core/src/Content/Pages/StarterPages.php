<?php
/**
 * The starter pages (REQ-260, REQ-120): About, FAQ, Awards and its rules, Complaints, Privacy, Terms, Cookies and the Nominate
 * guidance, created with draft copy from the prototype so staff only edit. Idempotent: a page whose path already exists in any
 * status (published, draft, private or in the trash) is left exactly as it is, so edited copy is never overwritten. The About page
 * uses the theme pattern when the theme provides it. No commercial terms page is created (REQ-120). Every page carries a draft note
 * (an ordinary block that staff delete) until the wording is signed off. The Nominate page is seeded as a draft because its form
 * is gated by the Nominations flag.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Pages;

use WhoIsWho\Core\Compliance\Legal\LegalDrafts;
use WhoIsWho\Core\Compliance\Legal\LegalPages;

/**
 * Starter pages.
 */
final class StarterPages {

	/**
	 * Paths that must never be seeded (commercial terms are agreed outside the site, REQ-120).
	 */
	public const FORBIDDEN = array( 'commercial-terms', 'sponsor-terms', 'advertising-terms', 'rate-card' );

	/**
	 * Create the pages that do not exist yet.
	 *
	 * @return array{created: string[], skipped: string[]} Paths created and paths left alone.
	 */
	public function seed(): array {
		$result = array(
			'created' => array(),
			'skipped' => array(),
		);
		foreach ( $this->definitions() as $path => $def ) {
			if ( in_array( $path, self::FORBIDDEN, true ) || $this->exists( $path ) ) {
				$result['skipped'][] = $path;
				continue;
			}
			$slug      = basename( $path );
			$parent_id = 0;
			if ( $slug !== $path ) {
				$parent = $this->find( dirname( $path ) );
				if ( ! $parent instanceof \WP_Post ) {
					$result['skipped'][] = $path;
					continue;
				}
				$parent_id = $parent->ID;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => $def['status'],
					'post_title'   => $def['title'],
					'post_name'    => $slug,
					'post_parent'  => $parent_id,
					'post_content' => $def['content'],
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$result['skipped'][] = $path;
				continue;
			}
			if ( in_array( $path, LegalPages::SLUGS, true ) ) {
				update_post_meta( (int) $id, LegalDrafts::DRAFT_META, '1' );
			}
			$result['created'][] = $path;
		}//end foreach
		return $result;
	}

	/**
	 * The page definitions, parents before children.
	 *
	 * @return array<string, array{title: string, status: string, content: string}>
	 */
	public function definitions(): array {
		return array(
			'about'        => $this->def( 'About', 'publish', $this->about() ),
			'faq'          => $this->def( 'Frequently asked questions', 'publish', $this->faq() ),
			'awards'       => $this->def( 'Awards', 'publish', $this->awards_overview() ),
			'awards/rules' => $this->def( 'Awards rules', 'publish', $this->awards_rules() ),
			'nominate'     => $this->def( 'Nominate', 'draft', $this->nominate() ),
			'complaints'   => $this->legal_def( 'complaints' ),
			'privacy'      => $this->legal_def( 'privacy' ),
			'terms'        => $this->legal_def( 'terms' ),
			'cookies'      => $this->legal_def( 'cookies' ),
			'data-rights'  => $this->legal_def( 'data-rights' ),
		);
	}

	/**
	 * A legal page definition from the shared drafts, the same text the legal-pages migration seeds.
	 *
	 * @param string $slug Legal page slug.
	 * @return array{title: string, status: string, content: string}
	 */
	private function legal_def( string $slug ): array {
		$draft = LegalDrafts::for_slug( $slug );
		return $this->def( $draft['title'], 'publish', $draft['body'] );
	}

	/**
	 * One definition.
	 *
	 * @param string $title   Title.
	 * @param string $status Post status.
	 * @param string $content Block markup.
	 * @return array{title: string, status: string, content: string}
	 */
	private function def( string $title, string $status, string $content ): array {
		return array(
			'title'   => $title,
			'status'  => $status,
			'content' => $content,
		);
	}

	/**
	 * Whether a page exists at the path in any status, trash included.
	 *
	 * @param string $path Page path.
	 */
	private function exists( string $path ): bool {
		return $this->find( $path ) instanceof \WP_Post;
	}

	/**
	 * The page at a path, in any status.
	 *
	 * @param string $path Page path.
	 */
	private function find( string $path ): ?\WP_Post {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( $page instanceof \WP_Post ) {
			return $page;
		}
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'draft', 'pending', 'private', 'future', 'trash' ),
				'name'           => basename( $path ),
				'posts_per_page' => 20,
				'no_found_rows'  => true,
			)
		);
		foreach ( $found as $candidate ) {
			if ( get_page_uri( $candidate ) === $path ) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * The draft note block.
	 *
	 * @param string $text Note text.
	 */
	private function note( string $text ): string {
		return '<!-- wp:paragraph {"className":"notice notice--draft"} -->' . "\n" . '<p class="notice notice--draft">' . esc_html( $text ) . '</p>' . "\n<!-- /wp:paragraph -->\n\n";
	}

	/**
	 * A heading block.
	 *
	 * @param string $text  Text.
	 * @param int    $level Level.
	 */
	private function heading( string $text, int $level = 2 ): string {
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return '<!-- wp:heading' . $attrs . " -->\n" . '<h' . $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . $level . ">\n<!-- /wp:heading -->\n\n";
	}

	/**
	 * A paragraph block.
	 *
	 * @param string $text Text (plain; links are added by staff in the editor).
	 */
	private function para( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->\n\n";
	}

	/**
	 * Headed paragraphs.
	 *
	 * @param array<string, string> $sections Heading to text.
	 */
	private function sections( array $sections ): string {
		$out = '';
		foreach ( $sections as $heading => $text ) {
			$out .= $this->heading( $heading ) . $this->para( $text );
		}
		return $out;
	}

	/**
	 * About: the theme pattern when it is registered, otherwise a short body.
	 */
	private function about(): string {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		if ( $registry->is_registered( 'whoiswho/about-page' ) ) {
			$pattern = $registry->get_registered( 'whoiswho/about-page' );
			if ( is_array( $pattern ) && '' !== (string) ( $pattern['content'] ?? '' ) ) {
				return (string) $pattern['content'];
			}
		}
		return $this->note( 'Draft copy for review. Delete this note when the wording is signed off.' )
			. $this->heading( 'Africa’s influence, on the record.', 1 )
			. $this->para( 'An African discovery, recognition and media platform. We document the people, businesses and institutions shaping the continent, tell the stories behind them, and recognise achievement through independent awards.' )
			. $this->sections(
				array(
					'Why we exist' => 'To make African influence visible, celebrate meaningful achievement and preserve the stories and legacies that shape the continent.',
				)
			);
	}

	/**
	 * FAQ with groups (the faq-group and faq-question blocks).
	 */
	private function faq(): string {
		$groups = array(
			'Being listed'         => array(
				'How do I get listed?'       => 'We compile profiles from public sources, and you can ask us to create or improve yours with a short enquiry. Our team reviews each profile. A person’s profile goes public only after that review.',
				'Does it cost anything?'     => 'Being listed is free. Paid options are agreed with our team and never on this site. They do not change how a profile is ranked, and they do not affect who can be nominated or who wins.',
				'How do I claim my profile?' => 'Open your profile and choose Claim this profile. Tell us who you are and how we can check it. We reply within five days, and our team checks every change.',
			),
			'Accuracy and removal' => array(
				'Something on a profile is wrong. How do I fix it?' => 'Open the profile and choose Suggest a correction. We acknowledge it within one business day and resolve it within seven days. No account is needed.',
				'Where do your facts come from?' => 'From public sources: registries, official websites, annual reports and news coverage. Each fact on a profile shows its source and the date we retrieved it.',
				'Can I have my profile removed?' => 'Yes. Open the profile and choose Request removal, or read our complaints and takedown policy. When the request comes from the person or their representative, we hide the profile within two business days.',
				'Can I pay for a better position in search or lists?' => 'No. Results are ordered by relevance or by name, never by payment.',
			),
			'Awards'               => array(
				'Who can nominate someone?'            => 'Anyone. Nominating is free and needs no account. Your name is never shown to the nominee or the public.',
				'How are winners chosen?'              => 'Nominations are screened against the published rules. Independent judges then score each nominee against the published criteria.',
				'Can I pay to be nominated or to win?' => 'No. Judges never see payment, subscription or sponsorship information, and sponsors have no influence over who is nominated or who wins.',
			),
			'Your data'            => array(
				'Do I need an account?' => 'No. There are no public accounts. Everything on this site, including corrections, claims and nominations, works without one.',
			),
		);
		$out    = $this->note( 'Draft copy for review. Delete this note when the wording is signed off.' );
		foreach ( $groups as $heading => $questions ) {
			$out .= '<!-- wp:whoiswho/faq-group ' . wp_json_encode( array( 'heading' => $heading ) ) . " -->\n";
			foreach ( $questions as $question => $answer ) {
				$out .= '<!-- wp:whoiswho/faq-question ' . wp_json_encode( array( 'title' => $question ) ) . " -->\n" . $this->para( $answer ) . "<!-- /wp:whoiswho/faq-question -->\n\n";
			}
			$out .= "<!-- /wp:whoiswho/faq-group -->\n\n";
		}
		return $out;
	}

	/**
	 * Awards overview (the parent of the rules). The theme draws the hero and the stage button; this is the editable text.
	 */
	private function awards_overview(): string {
		return $this->note( 'Draft copy for review. Delete this note when the wording is signed off.' )
			. $this->para( 'Recognising the people and organisations shaping the continent. Independent judges decide, against criteria published before nominations open.' );
	}

	/**
	 * Awards rules.
	 */
	private function awards_rules(): string {
		return $this->note( 'Draft. Governance and the conflict-of-interest policy come from the Venture CEO.' )
			. $this->sections(
				array(
					'Eligibility'      => 'Open to individuals and organisations in Nigeria first, then other African countries, with a professional record in the last five years. Final wording is set when the rules are approved.',
					'Nominations'      => 'Anyone may nominate. Our team checks each nomination against the published rules, and only nominees our team makes live are listed.',
					'Judging'          => 'Independent judges score against published criteria: impact, achievement, credibility and integrity, and leadership or innovation. Judges declare conflicts and never see payment or sponsorship data.',
					'No public voting' => 'Outcomes are decided by judges, not by votes or payments.',
				)
			);
	}

	/**
	 * Nominate guidance (shown below the form).
	 */
	private function nominate(): string {
		return $this->note( 'Draft copy for review. Delete this note when the wording is signed off.' )
			. $this->sections(
				array(
					'How it works'       => 'Anyone can put someone forward: a person or an organisation, with a short statement of why. Our team checks the nomination against the published rules before the nominee is listed.',
					'Eligibility'        => 'Open to individuals and organisations in Nigeria first, then other African countries, with a professional record in the last five years.',
					'What to have ready' => 'The nominee’s name, the category, a short statement of why, and one or two public sources. Please leave out phone numbers, home addresses and sensitive personal details.',
					'Short answers'      => 'Nominating is free and needs no account. Your name and email are never shown to the nominee or the public.',
				)
			);
	}
}
