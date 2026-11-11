<?php
/**
 * Form field: label, control, hint tied with aria-describedby, error state. Variants: text types, textarea, select, checkbox.
 * Args: name, label (may contain safe links), type, required, hint, error, options (value => label), value, id.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_f = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'name'     => '',
		'label'    => '',
		'type'     => 'text',
		'required' => false,
		'hint'     => '',
		'error'    => '',
		'options'  => array(),
		'value'    => '',
		'id'       => '',
	)
);

$wiw_name   = (string) $wiw_f['name'];
$wiw_id     = '' !== $wiw_f['id'] ? (string) $wiw_f['id'] : 'f-' . sanitize_html_class( $wiw_name );
$wiw_type   = (string) $wiw_f['type'];
$wiw_hint   = (string) $wiw_f['hint'];
$wiw_error  = (string) $wiw_f['error'];
$wiw_desc   = trim( ( '' !== $wiw_hint ? $wiw_id . '-hint ' : '' ) . ( '' !== $wiw_error ? $wiw_id . '-err' : '' ) );
$wiw_common = sprintf(
	'id="%1$s" name="%2$s"%3$s%4$s%5$s',
	esc_attr( $wiw_id ),
	esc_attr( $wiw_name ),
	$wiw_f['required'] ? ' required aria-required="true"' : '',
	'' !== $wiw_desc ? ' aria-describedby="' . esc_attr( $wiw_desc ) . '"' : '',
	'' !== $wiw_error ? ' aria-invalid="true"' : ''
);
$wiw_label  = wp_kses( (string) $wiw_f['label'], array( 'a' => array( 'href' => true ) ) );
$wiw_req    = $wiw_f['required'] ? ' <span class="visually-hidden">' . esc_html__( '(required)', 'whoiswho' ) . '</span>' : '';
?>
<div class="field">
	<?php if ( 'checkbox' === $wiw_type ) : ?>
		<label class="check" for="<?php echo esc_attr( $wiw_id ); ?>">
			<input type="checkbox" value="1" <?php echo $wiw_common; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above. ?>>
			<span><?php echo $wiw_label . $wiw_req; // phpcs:ignore WordPress.Security.EscapeOutput -- label passed through wp_kses, suffix escaped. ?></span>
		</label>
	<?php else : ?>
		<label for="<?php echo esc_attr( $wiw_id ); ?>"><?php echo $wiw_label . $wiw_req; // phpcs:ignore WordPress.Security.EscapeOutput -- label passed through wp_kses, suffix escaped. ?></label>
		<?php if ( 'textarea' === $wiw_type ) : ?>
			<textarea <?php echo $wiw_common; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above. ?> rows="5"><?php echo esc_textarea( (string) $wiw_f['value'] ); ?></textarea>
		<?php elseif ( 'select' === $wiw_type ) : ?>
			<select <?php echo $wiw_common; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above. ?>>
				<option value=""><?php esc_html_e( 'Choose one', 'whoiswho' ); ?></option>
				<?php foreach ( (array) $wiw_f['options'] as $wiw_val => $wiw_opt ) : ?>
					<option value="<?php echo esc_attr( (string) $wiw_val ); ?>" <?php selected( (string) $wiw_f['value'], (string) $wiw_val ); ?>><?php echo esc_html( (string) $wiw_opt ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<input type="<?php echo esc_attr( in_array( $wiw_type, array( 'text', 'email', 'tel', 'url', 'search' ), true ) ? $wiw_type : 'text' ); ?>" value="<?php echo esc_attr( (string) $wiw_f['value'] ); ?>" <?php echo $wiw_common; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above. ?>>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( '' !== $wiw_hint ) : ?>
		<span class="hint" id="<?php echo esc_attr( $wiw_id . '-hint' ); ?>"><?php echo esc_html( $wiw_hint ); ?></span>
	<?php endif; ?>
	<?php if ( '' !== $wiw_error ) : ?>
		<p class="err" id="<?php echo esc_attr( $wiw_id . '-err' ); ?>" role="alert"><?php echo esc_html( $wiw_error ); ?></p>
	<?php endif; ?>
</div>
