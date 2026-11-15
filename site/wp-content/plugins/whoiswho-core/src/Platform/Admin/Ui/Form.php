<?php
/**
 * The staff form kit (REQ-275): helpers that print one field with its label above, muted help text below, an inline error and the
 * kept value, in the shared staff style (assets/admin/ui/staff.css). Field types: text, email, url, textarea (with an optional
 * character counter that reuses char-count.js), select, searchable select (a filter box added by staff.js; a plain select without
 * JavaScript), checkbox, toggle switch, radio cards, a card section with heading and description, and an actions bar (sticky on
 * long forms).
 *
 * Markup only: names, values and validators stay with the screen. Every helper escapes what it prints; the control markup passed
 * to row() and section() must already be escaped. A field with an error gets aria-invalid="true" and its error (and help) text
 * in aria-describedby.
 *
 * Common $args: id (default wiw-f-{name}), help, error, required (bool), class (extra input classes), attrs (extra attributes,
 * name => value; true prints the bare attribute).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Admin\Ui;

/**
 * Staff form kit.
 */
final class Form {

	/**
	 * The id a field gets by default: wiw-f- and its name without brackets.
	 *
	 * @param string $name Field name.
	 */
	public static function field_id( string $name ): string {
		return 'wiw-f-' . trim( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', $name ), '-' );
	}

	/**
	 * A one-line text field.
	 *
	 * @param string               $name  Field name.
	 * @param string               $label Label.
	 * @param string               $value Kept value.
	 * @param array<string, mixed> $args  Common args, plus maxlength and placeholder.
	 */
	public static function text( string $name, string $label, string $value = '', array $args = array() ): string {
		return self::input( 'text', $name, $label, $value, $args );
	}

	/**
	 * An email field.
	 *
	 * @param string               $name  Field name.
	 * @param string               $label Label.
	 * @param string               $value Kept value.
	 * @param array<string, mixed> $args  Common args.
	 */
	public static function email( string $name, string $label, string $value = '', array $args = array() ): string {
		return self::input( 'email', $name, $label, $value, $args );
	}

	/**
	 * A web address field.
	 *
	 * @param string               $name  Field name.
	 * @param string               $label Label.
	 * @param string               $value Kept value.
	 * @param array<string, mixed> $args  Common args.
	 */
	public static function url( string $name, string $label, string $value = '', array $args = array() ): string {
		return self::input( 'url', $name, $label, $value, $args );
	}

	/**
	 * A multi-line box. With counter => true (and a maxlength) it shows "N / MAX characters", kept live by char-count.js.
	 *
	 * @param string               $name  Field name.
	 * @param string               $label Label.
	 * @param string               $value Kept value.
	 * @param array<string, mixed> $args  Common args, plus rows, maxlength, counter (bool) and counter_note (text after the count).
	 */
	public static function textarea( string $name, string $label, string $value = '', array $args = array() ): string {
		$id        = self::id( $name, $args );
		$max       = (int) ( $args['maxlength'] ?? 0 );
		$counter   = ! empty( $args['counter'] ) && $max > 0;
		$count_id  = 'wiw-count-' . $name;
		$described = self::described_by( $id, $args, $counter ? array( $count_id ) : array() );
		$html      = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) (int) ( $args['rows'] ?? 4 ) ) . '" class="' . esc_attr( self::classes( 'wiw-input wiw-textarea large-text', $args ) ) . '"';
		$html     .= $max > 0 ? ' maxlength="' . esc_attr( (string) $max ) . '"' : '';
		$html     .= $counter ? ' data-wiw-count="' . esc_attr( $count_id ) . '"' : '';
		$html     .= self::state_attrs( $described, $args ) . '>' . esc_textarea( $value ) . '</textarea>';
		$extra     = '';
		if ( $counter ) {
			$used   = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			$note   = isset( $args['counter_note'] ) ? ' ' . esc_html( (string) $args['counter_note'] ) : '';
			$extra .= '<p class="wiw-help wiw-help--count"><span id="' . esc_attr( $count_id ) . '" class="wiw-char-count" aria-live="polite">' . esc_html( sprintf( /* translators: 1: characters used, 2: the limit. */ __( '%1$d / %2$d characters', 'whoiswho' ), $used, $max ) ) . '</span>' . $note . '</p>';
		}
		return self::wrap( $id, $label, $html . $extra, $args );
	}

	/**
	 * A select. Options are value => label, or group label => (value => label) for optgroups.
	 *
	 * @param string                                                                $name     Field name.
	 * @param string                                                                $label    Label.
	 * @param array<string, string|array<string, string>>|array<int|string, string> $options  Options.
	 * @param string                                                                $selected Selected value.
	 * @param array<string, mixed>                                                  $args     Common args, plus placeholder (an empty first option) and searchable (bool).
	 */
	public static function select( string $name, string $label, array $options, string $selected = '', array $args = array() ): string {
		$id    = self::id( $name, $args );
		$html  = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="' . esc_attr( self::classes( 'wiw-input wiw-select', $args ) ) . '"';
		$html .= ! empty( $args['searchable'] ) ? ' data-wiw-search="1" data-wiw-search-label="' . esc_attr( sprintf( /* translators: %s: field label. */ __( 'Filter %s', 'whoiswho' ), $label ) ) . '"' : '';
		$html .= self::state_attrs( self::described_by( $id, $args ), $args ) . '>';
		if ( isset( $args['placeholder'] ) ) {
			$html .= '<option value="">' . esc_html( (string) $args['placeholder'] ) . '</option>';
		}
		foreach ( $options as $value => $text ) {
			if ( is_array( $text ) ) {
				$html .= '<optgroup label="' . esc_attr( (string) $value ) . '">';
				foreach ( $text as $sub_value => $sub_text ) {
					$html .= self::option( (string) $sub_value, (string) $sub_text, $selected );
				}
				$html .= '</optgroup>';
				continue;
			}
			$html .= self::option( (string) $value, (string) $text, $selected );
		}
		return self::wrap( $id, $label, $html . '</select>', $args );
	}

	/**
	 * A select with a filter box (progressive enhancement: a plain select without JavaScript).
	 *
	 * @param string                                                                $name     Field name.
	 * @param string                                                                $label    Label.
	 * @param array<string, string|array<string, string>>|array<int|string, string> $options  Options.
	 * @param string                                                                $selected Selected value.
	 * @param array<string, mixed>                                                  $args     As select().
	 */
	public static function searchable_select( string $name, string $label, array $options, string $selected = '', array $args = array() ): string {
		return self::select( $name, $label, $options, $selected, array_merge( $args, array( 'searchable' => true ) ) );
	}

	/**
	 * A checkbox with its label beside it.
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Label.
	 * @param bool                 $checked Checked.
	 * @param array<string, mixed> $args    Common args, plus value (default 1).
	 */
	public static function checkbox( string $name, string $label, bool $checked = false, array $args = array() ): string {
		return self::check( 'wiw-check', $name, $label, $checked, $args, false );
	}

	/**
	 * An on/off toggle switch: a checkbox with role="switch", for settings that are on or off.
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Label.
	 * @param bool                 $checked On.
	 * @param array<string, mixed> $args    Common args, plus value (default 1).
	 */
	public static function toggle( string $name, string $label, bool $checked = false, array $args = array() ): string {
		return self::check( 'wiw-toggle', $name, $label, $checked, $args, true );
	}

	/**
	 * Radio cards: one choice from a few, each a card with a title and an optional description.
	 *
	 * @param string                                                           $name     Field name.
	 * @param string                                                           $legend   The group's legend.
	 * @param array<string, string|array{label: string, description?: string}> $options  Value => label, or value => label and description.
	 * @param string                                                           $selected Selected value.
	 * @param array<string, mixed>                                             $args     help, error, required.
	 */
	public static function radio_cards( string $name, string $legend, array $options, string $selected = '', array $args = array() ): string {
		$id        = self::id( $name, $args );
		$described = self::described_by( $id, $args );
		$out       = '<fieldset class="wiw-field wiw-radio-cards' . ( isset( $args['error'] ) ? ' wiw-field--invalid' : '' ) . '"' . ( '' !== $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : '' ) . '><legend class="wiw-label">' . esc_html( $legend ) . '</legend><div class="wiw-radio-cards__grid">';
		$i         = 0;
		foreach ( $options as $value => $option ) {
			$option = is_array( $option ) ? $option : array( 'label' => (string) $option );
			$opt_id = $id . '-' . ( ++$i );
			$out   .= '<label class="wiw-radio-card" for="' . esc_attr( $opt_id ) . '"><input type="radio" id="' . esc_attr( $opt_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . checked( $selected, (string) $value, false ) . ( ! empty( $args['required'] ) ? ' required="required"' : '' ) . ' />';
			$out   .= '<span class="wiw-radio-card__body"><span class="wiw-radio-card__title">' . esc_html( $option['label'] ) . '</span>';
			$out   .= isset( $option['description'] ) && '' !== $option['description'] ? '<span class="wiw-radio-card__desc">' . esc_html( $option['description'] ) . '</span>' : '';
			$out   .= '</span></label>';
		}
		return $out . '</div>' . self::notes( $id, $args ) . '</fieldset>';
	}

	/**
	 * A field around a control the screen built itself (a picker, a media chooser): label above, help and error below. When there
	 * is an error, the control with the field's id gets aria-invalid and the error id in aria-describedby.
	 *
	 * @param string               $id      The control's id (the label points at it).
	 * @param string               $label   Label.
	 * @param string               $control Control markup, already escaped.
	 * @param array<string, mixed> $args    help, error, required.
	 */
	public static function row( string $id, string $label, string $control, array $args = array() ): string {
		if ( isset( $args['error'] ) && '' !== (string) $args['error'] ) {
			$control = self::mark_invalid( $control, $id, $id . '-error' );
		}
		return self::wrap( $id, $label, $control, $args );
	}

	/**
	 * A card section: heading, optional description, body.
	 *
	 * @param string               $title Heading.
	 * @param string               $body  Body markup, already escaped.
	 * @param array<string, mixed> $args  description, id (the section's id, also the heading's anchor), class, level (2 or 3).
	 */
	public static function section( string $title, string $body, array $args = array() ): string {
		$level = 3 === (int) ( $args['level'] ?? 2 ) ? 'h3' : 'h2';
		$id    = isset( $args['id'] ) ? (string) $args['id'] : '';
		$class = trim( 'wiw-card ' . (string) ( $args['class'] ?? '' ) );
		$out   = '<section class="' . esc_attr( $class ) . '"' . ( '' !== $id ? ' aria-labelledby="' . esc_attr( $id ) . '"' : '' ) . '>';
		$out  .= '<' . $level . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' class="wiw-card__title">' . esc_html( $title ) . '</' . $level . '>';
		if ( isset( $args['description'] ) && '' !== (string) $args['description'] ) {
			$out .= '<p class="wiw-card__desc">' . esc_html( (string) $args['description'] ) . '</p>';
		}
		return $out . '<div class="wiw-card__body">' . $body . '</div></section>';
	}

	/**
	 * The actions bar: the form's buttons. sticky => true keeps it at the bottom of the window on a long form.
	 *
	 * @param string               $buttons Buttons markup, already escaped.
	 * @param array<string, mixed> $args    sticky (bool).
	 */
	public static function actions( string $buttons, array $args = array() ): string {
		return '<div class="wiw-actions' . ( ! empty( $args['sticky'] ) ? ' wiw-actions--sticky' : '' ) . '">' . $buttons . '</div>';
	}

	/**
	 * The opening tag of a kit form: method post to admin-post.php with the action and its nonce fields given as markup.
	 * data-wiw-unsaved turns on the unsaved-changes warning.
	 *
	 * @param string               $hidden Hidden fields markup, already escaped.
	 * @param array<string, mixed> $args   class, unsaved (bool), action (the form's address; admin-post.php by default), method.
	 */
	public static function open( string $hidden, array $args = array() ): string {
		$action = isset( $args['action'] ) ? (string) $args['action'] : admin_url( 'admin-post.php' );
		$method = 'get' === ( $args['method'] ?? 'post' ) ? 'get' : 'post';
		$class  = trim( 'wiw-form ' . (string) ( $args['class'] ?? '' ) );
		return '<form method="' . $method . '" action="' . esc_url( $action ) . '" class="' . esc_attr( $class ) . '"' . ( ! empty( $args['unsaved'] ) ? ' data-wiw-unsaved="1"' : '' ) . '>' . $hidden;
	}

	/* ---------------------------------------------------------------- Internals. */

	/**
	 * An input of a type.
	 *
	 * @param string               $type  text, email or url.
	 * @param string               $name  Field name.
	 * @param string               $label Label.
	 * @param string               $value Value.
	 * @param array<string, mixed> $args  Args.
	 */
	private static function input( string $type, string $name, string $label, string $value, array $args ): string {
		$id    = self::id( $name, $args );
		$html  = '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="' . esc_attr( self::classes( 'wiw-input regular-text', $args ) ) . '" value="' . esc_attr( $value ) . '"';
		$html .= isset( $args['maxlength'] ) ? ' maxlength="' . esc_attr( (string) (int) $args['maxlength'] ) . '"' : '';
		$html .= isset( $args['placeholder'] ) ? ' placeholder="' . esc_attr( (string) $args['placeholder'] ) . '"' : '';
		return self::wrap( $id, $label, $html . self::state_attrs( self::described_by( $id, $args ), $args ) . ' />', $args );
	}

	/**
	 * A checkbox or a switch.
	 *
	 * @param string               $kind    wiw-check or wiw-toggle.
	 * @param string               $name    Name.
	 * @param string               $label   Label.
	 * @param bool                 $checked Checked.
	 * @param array<string, mixed> $args    Args.
	 * @param bool                 $is_switch A switch.
	 */
	private static function check( string $kind, string $name, string $label, bool $checked, array $args, bool $is_switch ): string {
		$id    = self::id( $name, $args );
		$value = isset( $args['value'] ) ? (string) $args['value'] : '1';
		$html  = '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( self::classes( $kind . '__input', $args ) ) . '"' . ( $is_switch ? ' role="switch"' : '' ) . ( $checked ? ' checked="checked"' : '' );
		$html .= self::state_attrs( self::described_by( $id, $args ), $args ) . ' />';
		$track = $is_switch ? '<span class="wiw-toggle__track" aria-hidden="true"><span class="wiw-toggle__thumb"></span></span>' : '';
		return '<div class="wiw-field wiw-field--check ' . esc_attr( $kind ) . ( isset( $args['error'] ) ? ' wiw-field--invalid' : '' ) . '"><label class="' . esc_attr( $kind ) . '__label" for="' . esc_attr( $id ) . '">' . $html . $track . '<span class="' . esc_attr( $kind ) . '__text">' . esc_html( $label ) . '</span></label>' . self::notes( $id, $args ) . '</div>';
	}

	/**
	 * One option.
	 *
	 * @param string $value    Value.
	 * @param string $text     Text.
	 * @param string $selected Selected value.
	 */
	private static function option( string $value, string $text, string $selected ): string {
		return '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>' . esc_html( $text ) . '</option>';
	}

	/**
	 * Label above, the control, help and error below.
	 *
	 * @param string               $id      Control id.
	 * @param string               $label   Label.
	 * @param string               $control Control markup.
	 * @param array<string, mixed> $args    Args.
	 */
	private static function wrap( string $id, string $label, string $control, array $args ): string {
		$invalid  = isset( $args['error'] ) && '' !== (string) $args['error'];
		$required = ! empty( $args['required'] ) ? ' <span class="wiw-required" aria-hidden="true">*</span>' : '';
		return '<div class="wiw-field' . ( $invalid ? ' wiw-field--invalid' : '' ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $required . '</label><div class="wiw-control">' . $control . '</div>' . self::notes( $id, $args ) . '</div>';
	}

	/**
	 * Help and error text.
	 *
	 * @param string               $id   Control id.
	 * @param array<string, mixed> $args Args.
	 */
	private static function notes( string $id, array $args ): string {
		$out = '';
		if ( isset( $args['help'] ) && '' !== (string) $args['help'] ) {
			$out .= '<p class="wiw-help" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( (string) $args['help'] ) . '</p>';
		}
		if ( isset( $args['error'] ) && '' !== (string) $args['error'] ) {
			$out .= '<p class="wiw-error" id="' . esc_attr( $id . '-error' ) . '" role="alert">' . esc_html( (string) $args['error'] ) . '</p>';
		}
		return $out;
	}

	/**
	 * The ids for aria-describedby: help, error, then extras.
	 *
	 * @param string               $id     Control id.
	 * @param array<string, mixed> $args   Args.
	 * @param array<int, string>   $extras More ids.
	 */
	private static function described_by( string $id, array $args, array $extras = array() ): string {
		$ids = array();
		if ( isset( $args['help'] ) && '' !== (string) $args['help'] ) {
			$ids[] = $id . '-help';
		}
		if ( isset( $args['error'] ) && '' !== (string) $args['error'] ) {
			$ids[] = $id . '-error';
		}
		return implode( ' ', array_merge( $extras, $ids ) );
	}

	/**
	 * The described-by, invalid, required and extra attributes.
	 *
	 * @param string               $described The described-by ids.
	 * @param array<string, mixed> $args      Args.
	 */
	private static function state_attrs( string $described, array $args ): string {
		$out  = '' !== $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : '';
		$out .= isset( $args['error'] ) && '' !== (string) $args['error'] ? ' aria-invalid="true"' : '';
		$out .= ! empty( $args['required'] ) ? ' required="required"' : '';
		foreach ( (array) ( $args['attrs'] ?? array() ) as $attr => $value ) {
			$attr = (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $attr ) );
			if ( '' === $attr || false === $value || null === $value ) {
				continue;
			}
			$out .= true === $value ? ' ' . $attr . '="' . $attr . '"' : ' ' . $attr . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}

	/**
	 * The control's id.
	 *
	 * @param string               $name Name.
	 * @param array<string, mixed> $args Args.
	 */
	private static function id( string $name, array $args ): string {
		return isset( $args['id'] ) && '' !== (string) $args['id'] ? (string) $args['id'] : self::field_id( $name );
	}

	/**
	 * Input classes plus extras.
	 *
	 * @param string               $base Base classes.
	 * @param array<string, mixed> $args Args.
	 */
	private static function classes( string $base, array $args ): string {
		return trim( $base . ' ' . (string) ( $args['class'] ?? '' ) );
	}

	/**
	 * Mark a ready-made control invalid: aria-invalid on the element with the id, and the error id added to its described-by.
	 *
	 * @param string $html     Control markup.
	 * @param string $id       Control id.
	 * @param string $error_id Error text id.
	 */
	private static function mark_invalid( string $html, string $id, string $error_id ): string {
		$needle = 'id="' . esc_attr( $id ) . '"';
		$pos    = strpos( $html, $needle );
		if ( false === $pos ) {
			return $html;
		}
		$end = strpos( $html, '>', $pos );
		if ( false === $end ) {
			return $html;
		}
		$tag   = rtrim( substr( $html, $pos, $end - $pos ) );
		$close = '/' === substr( $tag, -1 );
		if ( $close ) {
			$tag = rtrim( substr( $tag, 0, -1 ) );
		}
		if ( 1 === preg_match( '/aria-describedby="([^"]*)"/', $tag, $m ) ) {
			$tag = str_replace( $m[0], 'aria-describedby="' . esc_attr( trim( $m[1] . ' ' . $error_id ) ) . '"', $tag );
		} else {
			$tag .= ' aria-describedby="' . esc_attr( $error_id ) . '"';
		}
		$tag .= ' aria-invalid="true"' . ( $close ? ' /' : '' );
		return substr( $html, 0, $pos ) . $tag . substr( $html, $end );
	}
}
