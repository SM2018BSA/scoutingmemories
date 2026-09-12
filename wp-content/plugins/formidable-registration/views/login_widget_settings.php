<?php
/**
 * @since 2.0
 */
?>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
		<?php esc_html_e( 'Title:', 'frmreg' ); ?>
	</label>
	<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( stripslashes( $instance['title'] ) ); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>">
		<?php esc_html_e( 'Display fields in', 'frmreg' ); ?>
	</label>
	<select id="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'layout' ) ); ?>">
		<option value="v" <?php selected( $instance['layout'], 'v' ); ?>><?php esc_html_e( 'multiple rows', 'frmreg' ); ?></option>
		<option value="h" <?php selected( $instance['layout'], 'h' ); ?>><?php esc_html_e( 'a single row', 'frmreg' ); ?></option>
	</select>
</p>
<p>
	<input class="checkbox" type="checkbox" <?php checked( $instance['slide'], true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'slide' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'slide' ) ); ?>" value="1" />
	<label for="<?php echo esc_attr( $this->get_field_id( 'slide' ) ); ?>">
		<?php esc_html_e( 'Slide the login area', 'frmreg' ); ?>
	</label>
</p>
<p>
	<input class="checkbox" type="checkbox" <?php checked( $instance['remember'], true ) ?> id="<?php echo esc_attr( $this->get_field_id( 'remember' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'remember' ) ); ?>" value="1" />
	<label for="<?php echo esc_attr( $this->get_field_id( 'remember' ) ); ?>">
		<?php esc_html_e( 'Include Remember Me checkbox', 'frmreg' ); ?>
	</label>
</p>
<p>
	<input class="checkbox" type="checkbox" <?php checked( $instance['show_lost_password'], true ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_lost_password' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_lost_password' ) ); ?>" value="1" />
	<label for="<?php echo esc_attr( $this->get_field_id( 'show_lost_password' ) ); ?>">
		<?php esc_html_e( 'Show lost password link', 'frmreg' ); ?>
	</label>
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>">
		<?php esc_html_e( 'Formidable Style', 'frmreg' ); ?>:
	</label>
	<select name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>">
		<option value="1" <?php selected( $instance['style'], '1' ); ?>><?php esc_html_e( 'Use default Style', 'frmreg' ); ?></option>
		<?php foreach ( FrmStylesController::get_style_opts() as $style ) { ?>
			<option value="<?php echo esc_attr( $style->post_name ) ?>" <?php selected( $style->post_name, $instance['style'] ); ?>>
				<?php echo esc_html( $style->post_title . ( empty( $style->menu_order ) ? '' : ' (' . __( 'default', 'frmreg' ) . ')' ) ); ?>
			</option>
		<?php }
		unset( $style );
		?>
		<option value="0" <?php selected( $instance['style'], '0' ) ?>><?php esc_html_e( 'Do not use Formidable styling', 'frmreg' ); ?></option>
	</select>
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'label_username' ) ); ?>">
		<?php esc_html_e( 'Username Label', 'frmreg' ); ?>:
	</label>
	<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'label_username' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'label_username' ) ); ?>" value="<?php echo esc_attr( stripslashes( $instance['label_username'] ) ); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'label_password' ) ); ?>">
		<?php esc_html_e( 'Password Label', 'frmreg' ); ?>:
	</label>
	<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'label_password' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'label_password' ) ); ?>" value="<?php echo esc_attr( stripslashes( $instance['label_password'] ) ); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'label_remember' ) ); ?>">
		<?php esc_html_e( 'Remember Me Label', 'frmreg' ) ?>:
	</label>
	<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'label_remember' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'label_remember' ) ); ?>" value="<?php echo esc_attr( stripslashes( $instance['label_remember'] ) ); ?>" />
</p>

