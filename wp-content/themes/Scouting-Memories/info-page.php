<?php
/*
  Template Name: Info Page


 */
get_header();

$show_default_title = get_post_meta( get_the_ID(), '_et_pb_show_title', true );

$is_page_builder_used = et_pb_is_pagebuilder_used( get_the_ID() );



if ( et_builder_is_product_tour_enabled() ):
	// load fullwidth page in Product Tour mode
	while ( have_posts() ): the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <div class="entry-content">
				<?php
				the_content();
				?>
            </div> <!-- .entry-content -->

        </article> <!-- .et_pb_post -->

	<?php endwhile;
else:
	?>
    <div class="container">
        <div id="content-area" class="clearfix">

			<?php while ( have_posts() ) : the_post(); ?>
				<?php if ( et_get_option( 'divi_integration_single_top' ) <> '' && et_get_option( 'divi_integrate_singletop_enable' ) == 'on' ) {
					echo( et_get_option( 'divi_integration_single_top' ) );
				} ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class( 'et_pb_post' ); ?>>


                    <div class="entry-content">
						<?php
						do_action( 'et_before_content' );

						the_content();

						wp_link_pages( array(
							'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'Divi' ),
							'after'  => '</div>'
						) );
						?>
                    </div> <!-- .entry-content -->
                    <div class="et_post_meta_wrapper">
						<?php
						if ( et_get_option( 'divi_468_enable' ) == 'on' ) {
							echo '<div class="et-single-post-ad">';
							if ( et_get_option( 'divi_468_adsense' ) <> '' ) {
								echo( et_get_option( 'divi_468_adsense' ) );
							} else { ?>
                                <a href="<?php echo esc_url( et_get_option( 'divi_468_url' ) ); ?>"><img
                                            src="<?php echo esc_attr( et_get_option( 'divi_468_image' ) ); ?>" alt="468"
                                            class="foursixeight"/></a>
							<?php }
							echo '</div> <!-- .et-single-post-ad -->';
						}
						?>

						<?php if ( et_get_option( 'divi_integration_single_bottom' ) <> '' && et_get_option( 'divi_integrate_singlebottom_enable' ) == 'on' ) {
							echo( et_get_option( 'divi_integration_single_bottom' ) );
						} ?>

						<?php
						if ( ( comments_open() || get_comments_number() ) && 'on' == et_get_option( 'divi_show_postcomments', 'on' ) ) {
							comments_template( '', true );
						}
						?>
                    </div> <!-- .et_post_meta_wrapper -->
                </article> <!-- .et_pb_post -->

			<?php endwhile; ?>


        </div> <!-- #content-area -->
    </div> <!-- .container -->
<?php endif; ?>


<?php

get_footer();
?>
<script>
    $j = jQuery.noConflict();
    $j(window).load(function () {

        var url_args = '?';

        // check if we have a state, and if we do show it,
		<?php if (isset( $_GET['state'] ) && strlen( $_GET['state'] ) > 1 ) : ?>
        //echo "alert('we have a state')";

        var state = "<?=$_GET['state']?>";
        //console.log(state);
        $j('#which_state').html(state);

        //url_args += 'state='  to be added later

		<?php else: ?>
        //echo "alert('we dont have a state')";
		<?php endif; ?>





		<?php if (isset( $_GET['council'] ) && strlen( $_GET['council'] ) > 1 ) :

		$value_key = $_GET['council'];
		$field = get_field_object( 'field_5a5bd52cc24b9' );
		$value_label = $field['choices'][ $value_key ];

		?>
        var council = "<?=$value_label?>";
        var council_slug = "<?=$value_key?>";
        //console.log(state);
        $j('#which_council').html(council);

        url_args += 'council=' + council_slug + '&';
        console.log(url_args);
		<?php else: ?>
        //echo "alert('we dont have a council')";
		<?php endif; ?>






		<?php if (isset( $_GET['lodge'] ) && strlen( $_GET['lodge'] ) > 1 ) :

		$value_key = $_GET['lodge'];
		$field = get_field_object( 'field_5a5bd409c24b8' );
		$value_label = $field['choices'][ $value_key ];

		?>
        //echo "alert('we have a lodge')";

        var state = "<?=$value_label?>";
        var lodge_slug = "<?=$value_key?>";
        //console.log(lodge);
        $j('#which_lodge').html(state);

        url_args += 'lodge=' + lodge_slug + '&';
        //console.log(url_args);
		<?php else: ?>
        //echo "alert('we dont have a lodge')";
		<?php endif; ?>



		<?php if (isset( $_GET['camp'] ) && strlen( $_GET['camp'] ) > 1 ) :

		$value_key = $_GET['camp'];
		$field = get_field_object( 'field_5a5bd3b2c24b7' );
		$value_label = $field['choices'][ $value_key ];

		?>
        //echo "alert('we have a camp')";

        var state = "<?=$value_label?>";
        var camp_slug = "<?=$value_key?>";
        //console.log(camp);
        $j('#which_camp').html(state);

        url_args += 'camp=' + camp_slug + '&';
        //console.log(url_args);
		<?php else: ?>
        //echo "alert('we dont have a camp')";
		<?php endif; ?>


        $j('.entry-content .et_pb_button').each( function () {
            url = $j(this).attr('href');
            url += url_args;
            $j(this).attr('href', url);
        })



    });
</script>
