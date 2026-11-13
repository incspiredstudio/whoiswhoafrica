<?php
/**
 * Draft wording for the five legal pages (REQ-100, REQ-120, REQ-058, REQ-059, REQ-062, REQ-063, REQ-064, REQ-158). Plain language
 * sample text that describes what the platform really does, taken from the product docs. It is a draft for Legal: every period,
 * contact and date that is not settled is marked "to be confirmed", the visible draft note stays at the top of each page, and the
 * pages carry the draft marker so the launch safeguards still flag them. Nothing here states prices, packages or fees (REQ-120).
 * Page content is editor content, so seeding never overwrites an edited page; the wording lives in one place so the migration and
 * the starter-pages seed make identical pages.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Legal;

/**
 * Legal page drafts.
 */
final class LegalDrafts {

	/**
	 * Post meta that marks a page whose wording is still a draft for Legal.
	 */
	public const DRAFT_META = '_wiw_draft_legal';

	/**
	 * The visible draft note, as the first block of every legal page.
	 */
	public const NOTE = 'Draft, pending legal review. This is sample wording that describes how the site works today. It is not legal advice and will be replaced by the reviewed text.';

	/**
	 * Placeholders that Legal and the owner fill in. Kept in one place so they read the same on every page.
	 */
	private const CONTACT   = '[contact email to be confirmed]';
	private const EFFECTIVE = '[effective date to be confirmed]';
	private const OPERATOR  = '[operating company name and address to be confirmed]';

	/**
	 * Title and block markup for a legal page slug.
	 *
	 * @param string $slug One of LegalPages::SLUGS.
	 * @return array{title: string, body: string}
	 */
	public static function for_slug( string $slug ): array {
		$all = self::all();
		return $all[ $slug ] ?? array(
			'title' => '',
			'body'  => '',
		);
	}

	/**
	 * Every draft, keyed by slug.
	 *
	 * @return array<string, array{title: string, body: string}>
	 */
	public static function all(): array {
		return array(
			'privacy'     => array(
				'title' => 'Privacy policy',
				'body'  => self::privacy(),
			),
			'terms'       => array(
				'title' => 'Terms of use',
				'body'  => self::terms(),
			),
			'cookies'     => array(
				'title' => 'Cookie policy',
				'body'  => self::cookies(),
			),
			'data-rights' => array(
				'title' => 'Your data rights',
				'body'  => self::data_rights(),
			),
			'complaints'  => array(
				'title' => 'Complaints and takedown',
				'body'  => self::complaints(),
			),
		);
	}

	/**
	 * Privacy policy.
	 */
	private static function privacy(): string {
		return self::open( 'This policy explains what personal information the ' . self::brand() . ' site holds, why, for how long, who handles it and what you can do about it. It covers the public directory and also the forms and emails around it.' )
			. self::h( 'Who we are' )
			. self::p( self::brand() . ' is an African discovery, recognition and media platform, run by ' . self::OPERATOR . '. For privacy questions write to ' . self::CONTACT . '.' )
			. self::h( 'Information in the directory' )
			. self::p( 'The directory shows profiles of people and organisations. We build them from public sources such as registries, official websites, annual reports and news coverage. Each fact on a profile shows its source and the date we retrieved it. A member of our team reviews a profile before it goes public.' )
			. self::p( 'A profile of a person holds only a name, a current title, a current organisation, an industry, public-record roles, published awards and an official or organisational website. We do not store home addresses, phone numbers, personal email addresses, family details, dates of birth, personal social media handles, or information about health, religion, politics, ethnicity, finances, biometrics, criminal records or sexual life. Nobody under 18 has a profile.' )
			. self::p( 'For people published from public sources without asking them first, our working basis is legitimate interests: the public interest in an accurate record of who shapes the continent. You have a right to object, and you can ask for a correction or removal at any time. The lawful basis for each group of people is still to be confirmed by Legal.' )
			. self::h( 'Information you give us' )
			. self::p( 'You do not need an account to use the site, and visitors cannot create one. These are the forms that collect personal information and what each one keeps.' )
			. self::ul(
				array(
					'Corrections, removal requests and reports: your name, your email address, the profile concerned and your message. We confirm your email address with a single-use link before we act.',
					'Get listed and other enquiries: your name, email address, organisation and message.',
					'Claims and profile managers: your name, role, work email address, a phone number if you give one, the evidence you describe, and your consent. Evidence is typed text and links, not uploaded files.',
					'Nominations: the nominee and your reason, plus your name and email address as the nominator. Your details are never shown to the nominee or the public. A staff-only contact email for the nominee may be kept so the team can tell them about a nomination or an award.',
					'Newsletter: your email address and the consent you gave, stored with the date and the version of this policy. You can unsubscribe with one click from every message.',
					'Comments on stories: your name, email address and comment. Only your name and comment appear, and only after approval. Your email address is never shown.',
					'Data requests: your name, email address and the request, so we can find and answer it.',
				)
			)
			. self::h( 'Sponsored content and customers' )
			. self::p( 'Some stories are paid for by a sponsor. They are always labelled Sponsored and never change who is listed, how profiles are ordered, who is nominated or who wins. We keep a private record of these arrangements, including the contact details of the people we deal with. This site takes no payments and holds no card details.' )
			. self::h( 'How we use it' )
			. self::ul(
				array(
					'To publish and keep accurate the directory, stories and events.',
					'To run corrections, removals, reports, claims, enquiries and the awards and nominations programme.',
					'To send the messages you asked for, such as a confirmation link, an acknowledgement, a decision or the newsletter.',
					'To count visits in a privacy-friendly way, only if you accept analytics cookies.',
					'To keep the site secure and to keep a record of staff actions, so that changes to profiles can be explained.',
				)
			)
			. self::p( 'We use your information for marketing email only if you opted in to the newsletter, and that opt-in is separate from every other form. We do not sell personal information.' )
			. self::h( 'Who handles it' )
			. self::p( 'Our own team, in roles that only see what they need, and the services that run the site: our web host, our email service and, if you accept analytics cookies, our analytics provider. Each one is listed in our register of processors with its location and its data-processing agreement. Locations and agreements are to be confirmed. Staff may use software tools to help draft records from public sources before a person reviews them; Legal is still to confirm how this is described here.' )
			. self::h( 'How long we keep it' )
			. self::p( 'These are the proposed periods and all are to be confirmed by Legal.' )
			. self::ul(
				array(
					'Nominations, including the staff-only contact email: 2 years.',
					'Signed links in emails: they expire after 14 days, or 48 hours for a link that confirms a nominator or requester.',
					'Logs: 30 days, holding reference numbers rather than names or email addresses. The record of staff actions is kept.',
					'Names on our suppression list, kept so that someone who asked to be removed is not added again: kept for as long as needed, name only.',
					'Newsletter subscribers: until you unsubscribe, then the period to be confirmed.',
					'Enquiries, corrections, claims, comments and data requests: period to be confirmed.',
				)
			)
			. self::h( 'Your rights' )
			. self::p( 'You can ask to see, correct, erase, object to or take a copy of the information we hold about you. Use the ' . self::link( '/data-rights/', 'Your data rights' ) . ' page. To stop marketing email, use the unsubscribe link in any message. To correct or remove a profile, use the buttons on the profile or see ' . self::link( '/complaints/', 'Complaints and takedown' ) . '.' )
			. self::h( 'Where it is held' )
			. self::p( 'The site is hosted at a location to be confirmed. If your information is moved to another country, we will say so here and describe the safeguards. This is to be confirmed by Legal.' )
			. self::h( 'Security and incidents' )
			. self::p( 'Access is limited by role. Personal data exports are limited to administrators and are logged. If personal data is lost or exposed, we follow a written procedure to contain it, assess it and tell the regulator and the people affected within the legal time limit.' )
			. self::h( 'Changes to this policy' )
			. self::p( 'When the wording changes we update the policy version. Consent records show which version you agreed to. Effective date: ' . self::EFFECTIVE . '.' )
			. self::h( 'Contact' )
			. self::p( 'Questions or complaints about privacy: ' . self::CONTACT . '. You may also complain to the data protection regulator in your country.' );
	}

	/**
	 * Terms of use.
	 */
	private static function terms(): string {
		return self::open( 'These terms are the rules for using the ' . self::brand() . ' site. By using it you accept them.' )
			. self::h( 'What the site is' )
			. self::p( self::brand() . ' is a directory, a stories and events magazine, and an awards programme. The directory is published for information. We take care to check the record, we correct it when it is wrong, and we cannot promise it is free of error or always complete.' )
			. self::h( 'Using the site' )
			. self::ul(
				array(
					'You may read, search, share and link to the directory, stories and events.',
					'You may not copy the directory in bulk, collect it with automated tools, or use it to build a competing database.',
					'You may not use the site to harass anyone, to pass yourself off as someone else, or to try to break or overload it.',
					'You do not need an account. Staff accounts are for the team only.',
				)
			)
			. self::h( 'Profiles and your control over them' )
			. self::p( 'Profiles are compiled from public sources and reviewed by our team before they go public. Being listed is not an endorsement. You can ask for a correction, ask for removal, or report a profile. See ' . self::link( '/complaints/', 'Complaints and takedown' ) . '. A person or organisation can also ask to manage their profile. We check who you are and our team reviews every change before it goes live.' )
			. self::h( 'What you send us' )
			. self::p( 'If you send us a correction, a claim, a nomination, a comment or an enquiry, you confirm that it is accurate as far as you know, that you have the right to send it, and that it does not include personal details that do not belong in a public record. You allow us to use it to run the service and, for comments you submit and we approve, to publish them. Do not send sensitive personal information.' )
			. self::h( 'Comments' )
			. self::p( 'Comments are held for approval. We may remove a comment that is abusive, unlawful, off-topic or promotional.' )
			. self::h( 'Awards and nominations' )
			. self::p( 'Anyone may nominate, with no account. Nominations are screened against the published rules and independent judges decide. Payment, sponsorship and advertising never affect who is nominated or who wins. There is no public voting.' )
			. self::h( 'Sponsored content' )
			. self::p( 'Some stories are placed by sponsors and are labelled Sponsored. Sponsored content does not change how profiles are ordered or whether a profile is Verified or Featured.' )
			. self::h( 'Our content and links' )
			. self::p( 'The design, text and images that we make belong to us or our licensors. Facts in profiles come from public sources, which we cite. Links to other sites are for convenience and we do not control those sites.' )
			. self::h( 'Liability' )
			. self::p( 'To the extent the law allows, we are not responsible for loss that results from relying on the site. Nothing here limits any right you have that the law does not allow us to limit. The exact wording is to be confirmed by Legal.' )
			. self::h( 'Changes, law and contact' )
			. self::p( 'We may change these terms and will update the effective date: ' . self::EFFECTIVE . '. The governing law and the courts that apply are to be confirmed. Questions: ' . self::CONTACT . '.' );
	}

	/**
	 * Cookie policy.
	 */
	private static function cookies(): string {
		return self::open( 'This policy lists the cookies the ' . self::brand() . ' site uses and what each one is for. Cookies that are not essential stay off until you accept them.' )
			. self::h( 'What a cookie is' )
			. self::p( 'A cookie is a small file that a site saves in your browser. Some are needed for the site to work. Others measure how the site is used.' )
			. self::h( 'Essential cookies' )
			. self::p( 'These are always on. Visitors have no accounts, so these are few.' )
			. self::ul(
				array(
					'wiw_consent: remembers your cookie choice (accepted or essential only). First-party, kept for about 180 days.',
					'Cookies WordPress sets for staff who sign in to edit the site. Visitors do not receive them.',
				)
			)
			. self::h( 'Analytics cookies' )
			. self::p( 'If we use analytics, it is privacy-friendly and first-party, and it only counts visits. The script loads only after you press Accept. If you press Decline, or do nothing, it does not load. The analytics provider and any cookie names are to be confirmed.' )
			. self::h( 'Other cookies' )
			. self::p( 'We do not use advertising cookies. If the comment form or another feature adds a cookie, it will be listed here before it goes live.' )
			. self::h( 'Your choice' )
			. self::p( 'You can change your mind at any time with the Cookie preferences button below or in the footer. You can also delete cookies in your browser settings. The choice you make is stored in the wiw_consent cookie and applies to this browser only.' )
			. self::p( 'More about how we handle personal information: ' . self::link( '/privacy/', 'Privacy policy' ) . '. Questions: ' . self::CONTACT . '. Effective date: ' . self::EFFECTIVE . '.' );
	}

	/**
	 * Your data rights.
	 */
	private static function data_rights(): string {
		return self::open( 'You can ask what personal information ' . self::brand() . ' holds about you and what we do with it. You do not need an account.' )
			. self::h( 'What you can ask for' )
			. self::ul(
				array(
					'Access: a copy of the information we hold about you.',
					'Correction: fix something that is wrong.',
					'Erasure: delete or anonymise your information.',
					'Objection: ask us to stop using your information, for example for a profile built from public sources.',
					'Portability: a copy in a form you can reuse.',
				)
			)
			. self::p( 'Some information we may have to keep, for example a name on the suppression list so that a person who asked for removal is not added again, or records the law requires. We will tell you if that applies.' )
			. self::h( 'How to ask' )
			. self::ol(
				array(
					'Send us your name, your email address and what you want, by email to ' . self::CONTACT . '. An online request form for this page is being added.',
					'We send a single-use link to that email address to confirm that it is yours. An unconfirmed request cannot be acted on and lapses.',
					'We find the information, check that nothing in it belongs to someone else, and reply to the same email address.',
				)
			)
			. self::h( 'How long it takes' )
			. self::p( 'We reply within 30 calendar days of you confirming your email address, to be confirmed by Legal for the law that applies. Each request has a due date that our team can see.' )
			. self::h( 'Related requests' )
			. self::ul(
				array(
					'To correct or remove a profile, use the buttons on the profile. We acknowledge within one business day, and hide a profile within two business days of a valid request from the person it is about.',
					'To stop newsletter email, use the unsubscribe link in any message. It works in one click.',
					'To complain about how we handled a request, see ' . self::link( '/complaints/', 'Complaints and takedown' ) . ' or contact the data protection regulator in your country.',
				)
			)
			. self::p( 'Contact: ' . self::CONTACT . '. See also the ' . self::link( '/privacy/', 'Privacy policy' ) . '. Effective date: ' . self::EFFECTIVE . '.' );
	}

	/**
	 * Complaints and takedown.
	 */
	private static function complaints(): string {
		return self::open( 'If something on ' . self::brand() . ' is wrong, harmful or should not be public, tell us. You do not need an account, and raising a concern costs nothing.' )
			. self::h( 'What you can raise' )
			. self::ul(
				array(
					'A fact that is wrong or out of date.',
					'A profile that should not be public, including a request from the person or organisation it is about.',
					'Defamation, privacy, copyright or other legal concerns about a profile, story, event or comment.',
					'A comment that breaks our rules.',
					'The way we handled an earlier request.',
				)
			)
			. self::h( 'How to raise it' )
			. self::p( 'Open the profile and choose Suggest a correction, Request removal or Report this profile. We send a single-use link to your email address to confirm it, and you get a reference number. For anything else, write to ' . self::CONTACT . ' and give the page address and what you want us to do.' )
			. self::h( 'What we commit to' )
			. self::ul(
				array(
					'We acknowledge every request within one business day.',
					'When a valid removal request comes from the person it is about, or their representative, we hide the profile within two business days.',
					'We resolve corrections within seven days.',
					'A person on our team decides, not software. Disputes go to an Editor.',
					'We tell you the outcome and the reason.',
				)
			)
			. self::h( 'What a removal means' )
			. self::p( 'A profile that is removed is no longer shown, it is taken out of search results and the sitemap, and its address answers that the page is gone. We keep the name on a private suppression list so that it is not added again by a later import. If you want to be listed again, you can ask.' )
			. self::h( 'Legal notices' )
			. self::p( 'For copyright or court-order notices, write to ' . self::CONTACT . ' with the page address, your details, and the basis for your notice. The notice procedure is to be confirmed by Legal.' )
			. self::p( 'See also ' . self::link( '/data-rights/', 'Your data rights' ) . ' and the ' . self::link( '/privacy/', 'Privacy policy' ) . '. Effective date: ' . self::EFFECTIVE . '.' );
	}

	/**
	 * The brand name, escaped, from the same filter the rest of the plugin uses (the child theme supplies it).
	 */
	private static function brand(): string {
		return esc_html( (string) apply_filters( 'wiw_brand_name', (string) get_bloginfo( 'name' ) ) );
	}

	/**
	 * The draft note and the introduction.
	 *
	 * @param string $intro Introduction.
	 */
	private static function open( string $intro ): string {
		return '<!-- wp:paragraph {"className":"notice notice--draft"} -->' . "\n" . '<p class="notice notice--draft">' . esc_html( self::NOTE ) . '</p>' . "\n<!-- /wp:paragraph -->\n\n"
			. self::p( 'Effective date: ' . self::EFFECTIVE . '. This is draft wording for review. ' . $intro );
	}

	/**
	 * A heading block.
	 *
	 * @param string $text Text.
	 */
	private static function h( string $text ): string {
		return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->\n\n";
	}

	/**
	 * A paragraph block. The text is written in this file and may hold links from link().
	 *
	 * @param string $html Trusted markup.
	 */
	private static function p( string $html ): string {
		return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->\n\n";
	}

	/**
	 * A link to a path on this site. Relative, so it survives a move to another address.
	 *
	 * @param string $path  Path.
	 * @param string $label Text.
	 */
	private static function link( string $path, string $label ): string {
		return '<a href="' . esc_attr( $path ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * A bulleted list block.
	 *
	 * @param string[] $items Item text (may hold links).
	 */
	private static function ul( array $items ): string {
		return self::list_block( $items, false );
	}

	/**
	 * A numbered list block.
	 *
	 * @param string[] $items Item text.
	 */
	private static function ol( array $items ): string {
		return self::list_block( $items, true );
	}

	/**
	 * A list block.
	 *
	 * @param string[] $items   Items.
	 * @param bool     $ordered Whether numbered.
	 */
	private static function list_block( array $items, bool $ordered ): string {
		$tag   = $ordered ? 'ol' : 'ul';
		$attrs = $ordered ? ' {"ordered":true}' : '';
		$out   = '<!-- wp:list' . $attrs . " -->\n<" . $tag . ' class="wp-block-list">';
		foreach ( $items as $item ) {
			$out .= "<!-- wp:list-item -->\n<li>" . $item . "</li>\n<!-- /wp:list-item -->";
		}
		return $out . '</' . $tag . ">\n<!-- /wp:list -->\n\n";
	}
}
