<?php
/**
 * Logs post table
 *
 * @since 1.0.1
 *
 * @package formidable-logs
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<table class="form-table">
	<tr>
		<td><?php esc_html_e( 'Response', 'formidable-logs' ); ?></td>
		<td><?php echo FrmAppHelper::kses( FrmLog::prepare_for_output( $post->post_content ), 'all' );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
	</tr>
	<?php foreach ( $custom_fields as $custom_field ) : ?>
		<tr>
			<td><?php echo FrmAppHelper::kses( $custom_field->meta_key, array( 'p' ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			<td><?php echo FrmAppHelper::kses( FrmLog::prepare_meta_for_output( $custom_field ), array( 'p' ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
		</tr>
	<?php endforeach; ?>
</table>
