<?php
/**
 * Show user meta on profile page
 *
 * @package FrmReg
 *
 * @var array   $meta_keys    Meta keys.
 * @var WP_User $profile_user The current WP_User object.
 */
?>

<h2><?php esc_html_e( 'Registration Form Details', 'frmreg' ) ?></h2>

<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Name', 'frmreg' ); ?></th>
			<th><?php esc_html_e( 'Value', 'frmreg' ); ?></th>
			<?php if ( $user_can_edit_entries ) { ?>
				<th><?php esc_html_e( 'Edit', 'frmreg' ); ?></th>
			<?php } ?>
		</tr>
	</thead>

	<tbody>
		<?php
		foreach ( $meta_keys as $meta_key => $field_id ) {
			if ( empty( $profile_user->{$meta_key} ) ) {
				continue;
			}
			?>
			<tr>
				<th><strong><?php echo esc_html( ucwords( $meta_key ) ); ?></strong></th>
				<td>
					<?php
					$field = FrmField::getOne( $field_id );
					if ( $field ) {
						$meta_val = $profile_user->{$meta_key};
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo FrmEntriesHelper::display_value( $meta_val, $field, array( 'type' => $field->type, 'truncate' => false ) );
					}
					?>
				</td>
				<?php if ( $user_can_edit_entries ) {
					$entry_id = FrmRegEntry::get_entry_for_user( $profile_user );
					?>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=formidable-entries&frm_action=edit&id=' . absint( $entry_id ) ) ); ?>">
							<?php esc_html_e( 'Edit Entry', 'frmreg' ); ?>
						</a>
					</td>
				<?php } ?>
			</tr>
			<?php
			// Clean up the variables for the next iteration.
			unset( $field, $meta_key, $field_id );	
		} ?>
	</tbody>
</table>
