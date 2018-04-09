<?php
/**
 * BuddyPress - Members Register
 *
 * @package BuddyPress
 * @subpackage bp-legacy
 */

?>

<?php

/**
 * Fires at the top of the BuddyPress member registration page template.
 *
 * @since 1.1.0
 */
do_action( 'bp_before_register_page' ); ?>

<form action=""  name="signup_form" id="signup_form" class="frm_forms  with_frm_style frm_style_divi" method="post" enctype="multipart/form-data">


    <div class="et_pb_section et_pb_section_0 et_section_regular">


        <div class=" et_pb_row et_pb_row_0 et_pb_row_fullwidth">
            <div class="et_pb_column et_pb_column_4_4  et_pb_column_0 et_pb_css_mix_blend_mode_passthrough et-last-child">


                <div class="et_pb_module et_pb_post_title   et_pb_post_title_0 et_pb_bg_layout_light et_pb_text_align_center">


                    <div class="et_pb_title_container">
                        <h1 class="entry-title">Create an Account</h1>
                        <p class="et_pb_title_meta_container">

							<?php if ( 'registration-disabled' == bp_get_current_signup_step() ) : ?>
                        <div id="template-notices" role="alert" aria-atomic="true">
							<?php

							/** This action is documented in bp-templates/bp-legacy/buddypress/activity/index.php */
							do_action( 'template_notices' ); ?>

                        </div>

						<?php

						/**
						 * Fires before the display of the registration disabled message.
						 *
						 * @since 1.5.0
						 */
						do_action( 'bp_before_registration_disabled' ); ?>

                        <p><?php _e( 'User registration is currently not allowed.', 'buddypress' ); ?></p>

						<?php

						/**
						 * Fires after the display of the registration disabled message.
						 *
						 * @since 1.5.0
						 */
						do_action( 'bp_after_registration_disabled' ); ?>
						<?php endif; // registration-disabled signup step ?>




						<?php if ( 'request-details' == bp_get_current_signup_step() ) : ?>

                            <div id="template-notices" role="alert" aria-atomic="true">
								<?php

								/** This action is documented in bp-templates/bp-legacy/buddypress/activity/index.php */
								do_action( 'template_notices' ); ?>

                            </div>

                            <p><?php _e( 'Registering for this site is easy. Just fill in the fields below, and we\'ll get a new account set up for you in no time.', 'buddypress' ); ?></p>

						<?php endif; // request-details signup step ?>


                        </p>
                    </div>
                    <div class="et_pb_title_featured_container"></div>
                </div>
            </div> <!-- .et_pb_column -->


        </div> <!-- .et_pb_row -->
        <div class=" et_pb_row et_pb_row_1 et_pb_row_fullwidth">
            <div class="et_pb_column et_pb_column_1_2  et_pb_column_1 et_pb_css_mix_blend_mode_passthrough">


                <div class="et_pb_blurb et_pb_module et_pb_bg_layout_light et_pb_text_align_left  et_pb_blurb_0 et_pb_blurb_position_top">


                    <div class="et_pb_blurb_content">

                        <div class="et_pb_blurb_container">
                            <h4 class="et_pb_module_header"><?php /*left side */?></h4>
                            <div class="et_pb_blurb_description">


								<?php if ( 'request-details' == bp_get_current_signup_step() ) : ?>

									<?php

									/**
									 * Fires before the display of member registration account details fields.
									 *
									 * @since 1.1.0
									 */
									do_action( 'bp_before_account_details_fields' ); ?>

                                    <div class="register-section" id="basic-details-section">

										<?php /***** Basic Account Details ******/ ?>

                                        <h2><?php _e( 'Account Details', 'buddypress' ); ?></h2>


                                        <div class="frm_form_field form-field  frm_top_container">
                                            <label for="signup_username" class="frm_primary_label"><?php _e( 'Username', 'buddypress' ); ?><?php _e( '(required)', 'buddypress' ); ?></label>
											<?php

											/**
											 * Fires and displays any member registration username errors.
											 *
											 * @since 1.1.0
											 */
											do_action( 'bp_signup_username_errors' ); ?>
                                            <input type="text" name="signup_username" id="signup_username"
                                                   value="<?php bp_signup_username_value(); ?>" <?php bp_form_field_attributes( 'username' ); ?>/>
                                        </div>

                                        <div class="frm_form_field form-field  frm_top_container">
                                            <label for="signup_email"><?php _e( 'Email Address', 'buddypress' ); ?><?php _e( '(required)', 'buddypress' ); ?></label>
											<?php

											/**
											 * Fires and displays any member registration email errors.
											 *
											 * @since 1.1.0
											 */
											do_action( 'bp_signup_email_errors' ); ?>
                                            <input type="email" name="signup_email" id="signup_email"
                                                   value="<?php bp_signup_email_value(); ?>" <?php bp_form_field_attributes( 'email' ); ?>/>
                                        </div>

                                        <div class="frm_form_field form-field  frm_top_container">
                                            <label for="signup_password"><?php _e( 'Choose a Password', 'buddypress' ); ?><?php _e( '(required)', 'buddypress' ); ?></label>
											<?php

											/**
											 * Fires and displays any member registration password errors.
											 *
											 * @since 1.1.0
											 */
											do_action( 'bp_signup_password_errors' ); ?>
                                            <input type="password" name="signup_password" id="signup_password" value=""
                                                   class="password-entry" <?php bp_form_field_attributes( 'password' ); ?>/>
                                            <div id="pass-strength-result"></div>
                                        </div>

                                        <div class="frm_form_field form-field  frm_top_container">
                                            <label for="signup_password_confirm"><?php _e( 'Confirm Password', 'buddypress' ); ?><?php _e( '(required)', 'buddypress' ); ?></label>
											<?php

											/**
											 * Fires and displays any member registration password confirmation errors.
											 *
											 * @since 1.1.0
											 */
											do_action( 'bp_signup_password_confirm_errors' ); ?>
                                            <input type="password" name="signup_password_confirm"
                                                   id="signup_password_confirm"
                                                   value=""
                                                   class="password-entry-confirm" <?php bp_form_field_attributes( 'password' ); ?>/>
                                        </div>


										<?php

										/**
										 * Fires and displays any extra member registration details fields.
										 *
										 * @since 1.9.0
										 */
										do_action( 'bp_account_details_fields' ); ?>

                                    </div><!-- #basic-details-section -->

									<?php

									/**
									 * Fires after the display of member registration account details fields.
									 *
									 * @since 1.1.0
									 */
									do_action( 'bp_after_account_details_fields' ); ?>

								<?php endif; ?>


                            </div><!-- .et_pb_blurb_description -->
                        </div>
                    </div> <!-- .et_pb_blurb_content -->
                </div> <!-- .et_pb_blurb -->
            </div> <!-- .et_pb_column -->
            <div class="et_pb_column et_pb_column_1_2  et_pb_column_2 et_pb_css_mix_blend_mode_passthrough et-last-child">


                <div class="et_pb_blurb et_pb_module et_pb_bg_layout_light et_pb_text_align_left  et_pb_blurb_1 et_pb_blurb_position_top">


                    <div class="et_pb_blurb_content">

                        <div class="et_pb_blurb_container">
                            <h4 class="et_pb_module_header"><?php /*right side */?></h4>
                            <div class="et_pb_blurb_description">


								<?php if ( 'request-details' == bp_get_current_signup_step() ) : ?>

									<?php /***** Extra Profile Details ******/ ?>

									<?php if ( bp_is_active( 'xprofile' ) ) : ?>

										<?php

										/**
										 * Fires before the display of member registration xprofile fields.
										 *
										 * @since 1.2.4
										 */
										do_action( 'bp_before_signup_profile_fields' ); ?>

                                        <div class="register-section" id="profile-details-section">

                                            <h2><?php _e( 'Profile Details', 'buddypress' ); ?></h2>

											<?php /* Use the profile field loop to render input fields for the 'base' profile field group */ ?>
											<?php if ( bp_is_active( 'xprofile' ) ) : if ( bp_has_profile( array(
												'profile_group_id' => 1,
												'fetch_field_data' => false
											) ) ) : while ( bp_profile_groups() ) : bp_the_profile_group(); ?>

												<?php while ( bp_profile_fields() ) : bp_the_profile_field(); ?>

                                                    <div<?php bp_field_css_class( 'editfield' ); ?>>
                                                        <fieldset>

															<?php
															$field_type = bp_xprofile_create_field_type( bp_get_the_profile_field_type() );
															$field_type->edit_field_html();

															/**
															 * Fires before the display of the visibility options for xprofile fields.
															 *
															 * @since 1.7.0
															 */
															do_action( 'bp_custom_profile_edit_fields_pre_visibility' );

															if ( bp_current_user_can( 'bp_xprofile_change_field_visibility' ) ) : ?>
                                                                <p class="hidden field-visibility-settings-toggle"
                                                                   id="field-visibility-settings-toggle-<?php bp_the_profile_field_id() ?>"><span
                                                                            id="<?php bp_the_profile_field_input_name(); ?>-2">
									<?php
									printf(
										__( 'This field can be seen by: %s', 'buddypress' ),
										'<span class="current-visibility-level">' . bp_get_the_profile_field_visibility_level_label() . '</span>'
									);
									?>
									</span>
                                                                    <button type="button" class="visibility-toggle-link"
                                                                            aria-describedby="<?php bp_the_profile_field_input_name(); ?>-2"
                                                                            aria-expanded="false"><?php _ex( 'Change', 'Change profile field visibility level', 'buddypress' ); ?></button>
                                                                </p>

                                                                <div class=" hidden field-visibility-settings"
                                                                     id="field-visibility-settings-<?php bp_the_profile_field_id() ?>">
                                                                    <fieldset>
                                                                        <legend><?php _e( 'Who can see this field?', 'buddypress' ) ?></legend>

																		<?php bp_profile_visibility_radio_buttons() ?>

                                                                    </fieldset>
                                                                    <button type="button"
                                                                            class="field-visibility-settings-close"><?php _e( 'Close', 'buddypress' ) ?></button>

                                                                </div>
															<?php else : ?>
                                                                <p class="field-visibility-settings-notoggle"
                                                                   id="field-visibility-settings-toggle-<?php bp_the_profile_field_id() ?>">
																	<?php
																	printf(
																		__( 'This field can be seen by: %s', 'buddypress' ),
																		'<span class="current-visibility-level">' . bp_get_the_profile_field_visibility_level_label() . '</span>'
																	);
																	?>
                                                                </p>
															<?php endif ?>

															<?php

															/**
															 * Fires after the display of the visibility options for xprofile fields.
															 *
															 * @since 1.1.0
															 */
															do_action( 'bp_custom_profile_edit_fields' ); ?>

                                                        </fieldset>
                                                    </div>

												<?php endwhile; ?>

                                                <input type="hidden" name="signup_profile_field_ids"
                                                       id="signup_profile_field_ids"
                                                       value="<?php bp_the_profile_field_ids(); ?>"/>

											<?php endwhile; endif; endif; ?>

											<?php

											/**
											 * Fires and displays any extra member registration xprofile fields.
											 *
											 * @since 1.9.0
											 */
											do_action( 'bp_signup_profile_fields' ); ?>

                                        </div><!-- #profile-details-section -->

										<?php

										/**
										 * Fires after the display of member registration xprofile fields.
										 *
										 * @since 1.1.0
										 */
										do_action( 'bp_after_signup_profile_fields' ); ?>

									<?php endif; ?>

									<?php if ( bp_get_blog_signup_allowed() ) : ?>

										<?php

										/**
										 * Fires before the display of member registration blog details fields.
										 *
										 * @since 1.1.0
										 */
										do_action( 'bp_before_blog_details_fields' ); ?>

										<?php /***** Blog Creation Details ******/ ?>

                                        <div class="register-section" id="blog-details-section">

                                            <h2><?php _e( 'Blog Details', 'buddypress' ); ?></h2>

                                            <p><label for="signup_with_blog"><input type="checkbox"
                                                                                    name="signup_with_blog"
                                                                                    id="signup_with_blog"
                                                                                    value="1"<?php if ( (int) bp_get_signup_with_blog_value() ) : ?> checked="checked"<?php endif; ?> /> <?php _e( 'Yes, I\'d like to create a new site', 'buddypress' ); ?>
                                                </label></p>

                                            <div id="blog-details"
											     <?php if ( (int) bp_get_signup_with_blog_value() ) : ?>class="show"<?php endif; ?>>

                                                <label for="signup_blog_url"><?php _e( 'Blog URL', 'buddypress' ); ?><?php _e( '(required)', 'buddypress' ); ?></label>
												<?php

												/**
												 * Fires and displays any member registration blog URL errors.
												 *
												 * @since 1.1.0
												 */
												do_action( 'bp_signup_blog_url_errors' ); ?>

												<?php if ( is_subdomain_install() ) : ?>
                                                    http:// <input type="text" name="signup_blog_url"
                                                                   id="signup_blog_url"
                                                                   value="<?php bp_signup_blog_url_value(); ?>"/> .<?php bp_signup_subdomain_base(); ?>
												<?php else : ?>
													<?php echo home_url( '/' ); ?> <input type="text"
                                                                                          name="signup_blog_url"
                                                                                          id="signup_blog_url"
                                                                                          value="<?php bp_signup_blog_url_value(); ?>"/>
												<?php endif; ?>

                                                <label for="signup_blog_title"><?php _e( 'Site Title', 'buddypress' ); ?><?php _e( '(required)', 'buddypress' ); ?></label>
												<?php

												/**
												 * Fires and displays any member registration blog title errors.
												 *
												 * @since 1.1.0
												 */
												do_action( 'bp_signup_blog_title_errors' ); ?>
                                                <input type="text" name="signup_blog_title" id="signup_blog_title"
                                                       value="<?php bp_signup_blog_title_value(); ?>"/>

                                                <fieldset class="register-site">
                                                    <legend class="label"><?php _e( 'Privacy: I would like my site to appear in search engines, and in public listings around this network.', 'buddypress' ); ?></legend>
													<?php

													/**
													 * Fires and displays any member registration blog privacy errors.
													 *
													 * @since 1.1.0
													 */
													do_action( 'bp_signup_blog_privacy_errors' ); ?>

                                                    <label for="signup_blog_privacy_public"><input type="radio"
                                                                                                   name="signup_blog_privacy"
                                                                                                   id="signup_blog_privacy_public"
                                                                                                   value="public"<?php if ( 'public' == bp_get_signup_blog_privacy_value() || ! bp_get_signup_blog_privacy_value() ) : ?> checked="checked"<?php endif; ?> /> <?php _e( 'Yes', 'buddypress' ); ?>
                                                    </label>
                                                    <label for="signup_blog_privacy_private"><input type="radio"
                                                                                                    name="signup_blog_privacy"
                                                                                                    id="signup_blog_privacy_private"
                                                                                                    value="private"<?php if ( 'private' == bp_get_signup_blog_privacy_value() ) : ?> checked="checked"<?php endif; ?> /> <?php _e( 'No', 'buddypress' ); ?>
                                                    </label>
                                                </fieldset>

												<?php

												/**
												 * Fires and displays any extra member registration blog details fields.
												 *
												 * @since 1.9.0
												 */
												do_action( 'bp_blog_details_fields' ); ?>

                                            </div>

                                        </div><!-- #blog-details-section -->

										<?php

										/**
										 * Fires after the display of member registration blog details fields.
										 *
										 * @since 1.1.0
										 */
										do_action( 'bp_after_blog_details_fields' ); ?>

									<?php endif; ?>


								<?php endif; ?>








								<?php if ( 'request-details' == bp_get_current_signup_step() ) : ?>


									<?php

									/**
									 * Fires before the display of the registration submit buttons.
									 *
									 * @since 1.1.0
									 */
									do_action( 'bp_before_registration_submit_buttons' ); ?>

                                    <div class="submit" style="margin-top:2em; ">
                                        <input style="cursor: pointer" class="et_pb_newsletter_button et_pb_button" type="submit" name="signup_submit" id="signup_submit"
                                               value="<?php esc_attr_e( 'Complete Sign Up', 'buddypress' ); ?>"/>
                                    </div>

									<?php

									/**
									 * Fires after the display of the registration submit buttons.
									 *
									 * @since 1.1.0
									 */
									do_action( 'bp_after_registration_submit_buttons' ); ?>

									<?php wp_nonce_field( 'bp_new_signup' ); ?>

								<?php endif; // request-details signup step ?>


                            </div><!-- .et_pb_blurb_description -->
                        </div>
                    </div> <!-- .et_pb_blurb_content -->
                </div> <!-- .et_pb_blurb -->
            </div> <!-- .et_pb_column -->


        </div> <!-- .et_pb_row -->


    </div>


	<?php

	/**
	 * Fires and displays any custom signup steps.
	 *
	 * @since 1.1.0
	 */
	do_action( 'bp_custom_signup_steps' ); ?>

</form>
<?php

/**
 * Fires at the bottom of the BuddyPress member registration page template.
 *
 * @since 1.1.0
 */
do_action( 'bp_after_register_page' ); ?>


<div id="buddypress">


    <div class="page" id="register-page">


		<?php if ( 'completed-confirmation' == bp_get_current_signup_step() ) : ?>

            <div id="template-notices" role="alert" aria-atomic="true">
				<?php

				/** This action is documented in bp-templates/bp-legacy/buddypress/activity/index.php */
				do_action( 'template_notices' ); ?>

            </div>

			<?php

			/**
			 * Fires before the display of the registration confirmed messages.
			 *
			 * @since 1.5.0
			 */
			do_action( 'bp_before_registration_confirmed' ); ?>

            <div id="template-notices" role="alert" aria-atomic="true">
				<?php if ( bp_registration_needs_activation() ) : ?>
                    <p><?php _e( 'You have successfully created your account! To begin using this site you will need to activate your account via the email we have just sent to your address.', 'buddypress' ); ?></p>
				<?php else : ?>
                    <p><?php _e( 'You have successfully created your account! Please log in using the username and password you have just created.', 'buddypress' ); ?></p>
				<?php endif; ?>
            </div>

			<?php

			/**
			 * Fires after the display of the registration confirmed messages.
			 *
			 * @since 1.5.0
			 */
			do_action( 'bp_after_registration_confirmed' ); ?>

		<?php endif; // completed-confirmation signup step ?>


    </div>


</div><!-- #buddypress -->

<script>
    $j = jQuery.noConflict();
    $j(window).load(function () {

        $j('.frm_button_submit').on('click', function(){

            $j('#signup_form').submit();
        });
    });
</script>








