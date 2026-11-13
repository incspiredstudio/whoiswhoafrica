<?php
/**
 * The invented page copy of the sample data for the FAQ and the Awards rules pages, as block markup: the same questions and
 * sections the prototype shows, so a local preview reads like the design. Plain sentences only, no links, nothing about a real
 * person or organisation (REQ-208, REQ-052).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli\Sample;

/**
 * Sample page copy.
 */
final class SamplePageCopy {

	/**
	 * The FAQ groups: heading => list of array( question, answer ).
	 *
	 * @return array<string, array<int, array{0: string, 1: string}>>
	 */
	public static function faq_groups(): array {
		return array(
			'Being listed'         => array(
				array( 'How do I get listed?', 'We compile profiles from public sources, and you can ask us to create or improve yours with a short enquiry. Our team reviews each profile. A person’s profile goes public only after that review.' ),
				array( 'Does it cost anything?', 'Being listed is free. Paid options, such as a subscription or a sponsored story, are agreed with our team and never on this site. They do not change how a profile is ranked, and they do not affect who can be nominated or who wins.' ),
				array( 'There is a profile of me that I did not create. Why?', 'Many profiles are compiled from public sources such as company registries, official websites and annual reports. You can correct it, claim it, or ask for it to be removed, with no account and no fee.' ),
				array( 'How do I claim my profile?', 'Open your profile and choose “Claim this profile”. Tell us who you are and how we can check it. We reply within five days. Once a claim is approved, a named person on your side can ask us to keep the profile up to date, and our team checks every change.' ),
			),
			'Accuracy and removal' => array(
				array( 'Something on a profile is wrong. How do I fix it?', 'Open the profile and choose “Suggest a correction”. We acknowledge it within one business day and resolve it within seven days. No account is needed.' ),
				array( 'Where do your facts come from?', 'From public sources: registries, official websites, annual reports and news coverage. Each fact on a profile shows its source and the date we retrieved it, so you can check it yourself.' ),
				array( 'Can I have my profile removed?', 'Yes. Open the profile and choose “Request removal”. When the request comes from the person or their representative, we hide the profile within two business days and keep the name on a list so it is not added again.' ),
				array( 'Can I pay for a better position in search or lists?', 'No. Results are ordered by relevance or by name, never by payment.' ),
			),
			'Awards'               => array(
				array( 'Who can nominate someone?', 'Anyone. Nominating is free and needs no account. We check your email address, and your name is never shown to the nominee or the public.' ),
				array( 'How are winners chosen?', 'Nominations are screened against the published rules. Independent judges then score each nominee on impact, achievement, credibility and integrity, and leadership or innovation.' ),
				array( 'Can I pay to be nominated or to win?', 'No. Judges never see payment, subscription or sponsorship information, and sponsors have no influence over who is nominated or who wins.' ),
			),
			'Stories'              => array(
				array( 'What is a sponsored story?', 'A story whose placement a sponsor paid for: a labelled slot on the home page or the stories page for set dates. It is always marked “Sponsored by” with the sponsor’s name. The sponsor does not write it, and it never changes the order of other stories, search results, profiles, nominations or winners.' ),
			),
			'Your data'            => array(
				array( 'Do I need an account?', 'No. There are no public accounts. Everything on this site, including corrections, claims and nominations, works without one.' ),
				array( 'What do you hold about me, and how do I see or change it?', 'We hold what is on your public profile and the sources behind it, plus any messages you send us. You can ask to see, correct, erase or object to it on the data rights page. We track every request with a due date.' ),
			),
		);
	}

	/**
	 * The FAQ page as FAQ group and question blocks.
	 */
	public static function faq_blocks(): string {
		$out = '';
		foreach ( self::faq_groups() as $heading => $questions ) {
			$out .= '<!-- wp:whoiswho/faq-group ' . wp_json_encode( array( 'heading' => $heading ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " -->\n";
			foreach ( $questions as $row ) {
				$out .= '<!-- wp:whoiswho/faq-question ' . wp_json_encode( array( 'title' => $row[0] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " -->\n";
				$out .= '<!-- wp:paragraph --><p>' . esc_html( $row[1] ) . "</p><!-- /wp:paragraph -->\n";
				$out .= "<!-- /wp:whoiswho/faq-question -->\n";
			}
			$out .= "<!-- /wp:whoiswho/faq-group -->\n";
		}
		return $out;
	}

	/**
	 * The Awards rules page: a heading and a paragraph for each section.
	 */
	public static function rules_blocks(): string {
		$sections = array(
			'Eligibility'      => 'Open to individuals and organisations in Nigeria first, then other African countries, with a professional record in the last five years. These rules are a sample for the preview.',
			'Nominations'      => 'Anyone may nominate. Our team checks each nomination against the published rules, and only nominees our team makes live are listed.',
			'Judging'          => 'Independent judges score against published criteria. Judges declare conflicts and never see payment or sponsorship data.',
			'No public voting' => 'Outcomes are decided by judges, not by votes or payments.',
			'Criteria'         => 'Impact. Achievement. Credibility and integrity. Leadership or innovation.',
		);
		$out      = '';
		foreach ( $sections as $heading => $text ) {
			$out .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . "</h2><!-- /wp:heading -->\n";
			$out .= '<!-- wp:paragraph --><p>' . esc_html( $text ) . "</p><!-- /wp:paragraph -->\n";
		}
		return $out;
	}
}
