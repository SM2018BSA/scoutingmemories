<?php
/*
  Template Name: Add Post Page
 */

get_header();

global $the_user;
if (!$the_user && class_exists('CurrentUser')) {
    $the_user = new CurrentUser();
}
?>
    <div id="primary" class="content-area container mt-5 h-100">
        <main id="main" class="site-main container-fluid p-0 m-0" role="main">

            <?php
            if (shortcode_exists('sm_add_memory_form')) {
                if (($the_user && $the_user->cap_allowed('create_posts')) || current_user_can('edit_posts')) {
                    echo '<h1 class="mb-4">' . get_the_title() . '</h1>';
                    echo do_shortcode('[sm_add_memory_form]');
                } else {
                    echo '<div class="alert alert-warning mt-4">' . esc_html__('You do not have permission to submit content.', 'scouting-memories-2026') . '</div>';
                }
            } else {
                while ( have_posts() ) :
                    the_post();
                    get_template_part( 'template-parts/content', 'page' );
                    if ( comments_open() || get_comments_number() ) :
                        comments_template();
                    endif;
                endwhile;
            }
            ?>

        </main><!-- #main -->
    </div><!-- #primary -->

<?php
get_footer();

