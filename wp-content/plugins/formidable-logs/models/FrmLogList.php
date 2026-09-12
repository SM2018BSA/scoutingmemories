<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Class FrmLogList.
 *
 * @since 1.0
 */
class FrmLogList {

	/**
	 * Manage columns.
	 *
	 * @param array<string> $columns table columns.
	 *
	 * @return array<string>
	 */
	public static function manage_columns( $columns ) {
		$columns['entry']  = __( 'Entry', 'formidable' );
		$columns['action'] = __( 'Action', 'formidable' );
		$columns['code']   = __( 'Status', 'formidable' );

		return $columns;
	}

	/**
	 * Display extra table which is our buttons in top of the frmlogs table.
	 *
	 * @since 1.0.1
	 *
	 * @param string $which determines which part of the page is.
	 * @return void
	 */
	public static function extra_tablenav( $which ) {
		$screen = get_current_screen();

		$validate = (
			isset( $screen->base ) &&
			'edit-frm_logs' === $screen->id
		);
		// Simple checks to demonstrate button in proper location.
		if ( ! $validate ) {
			return;
		}

		if ( 'top' !== $which ) {
			return;
		}

		// Check for right access to clear data.
		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		// No cache, just check is there any logs to display buttons or not.
		$frmlog_posts = get_posts(
			array(
				'post_type'   => 'frm_logs',
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		if ( ! $frmlog_posts ) {
			return;
		}

		// Invoke buttons from helper.
		FrmLogAppHelper::show_list_entry_buttons();

	}

	/**
	 * Add custom columns to the table.
	 *
	 * @param string $column_name column name.
	 * @param int    $id post id.
	 * @return void
	 */
	public static function manage_custom_columns( $column_name, $id ) {
		switch ( $column_name ) {
			case 'name':
			case 'content':
				$post = get_post( $id );
				$val  = FrmAppHelper::truncate( strip_tags( $post->{"post_$column_name"} ), 100 );
				break;
			case 'entry':
				$entry_id = absint( get_post_meta( $id, 'frm_' . $column_name, true ) );
				if ( current_user_can( 'frm_edit_entries' ) ) {
					$edit_link = '?page=formidable-entries&frm_action=edit&id=' . $entry_id;
					$val       = '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Entry', 'formidable-logs' ) . ' ' . $entry_id . '</a>';
				} else {
					$val = $entry_id;
				}
				break;
			case 'action':
			case 'code':
				$val = absint( get_post_meta( $id, 'frm_' . $column_name, true ) );
				break;
			default:
				$val = esc_html( $column_name );
				break;
		}

		echo $val; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}
