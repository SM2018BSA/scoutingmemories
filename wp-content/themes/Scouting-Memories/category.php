<?php
/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package StrapPress
 */

get_header(); ?>



    <div id="primary" class="content-area">
        <main id="main" class="site-main smbsa container " role="main">



            <?php
            if (have_posts()) : ?>

            <header class="page-header">
                <?php
                the_archive_title('<h1 class="page-title">', '</h1>');
                the_archive_description('<div class="archive-description">', '</div>');
                ?>
            </header><!-- .page-header -->

            <div class="container">
               <?php echo FrmFormsController::show_form('31', $key = '', $title=false, $description=true); ?>
            </div>


            <div class="row pt-5">
                <?php
                /* Start the Loop */
                while (have_posts()) : the_post();

                    /*
                        * Include the Post-Format-specific template for the content.
                        * If you want to override this in a child theme, then include a file
                        * called content-___.php (where ___ is the Post Format name) and that will be used instead.
                        */
                    get_template_part('template-parts/content-search', get_post_format());

                endwhile;

                echo '<div class="p-5 w-100">';
                the_posts_pagination(array(
                    'prev_text' => '<i class="fa fa-arrow-left" aria-hidden="true"></i><span class="screen-reader-text">' . __('Previous Page', 'pool') . '</span>',
                    'next_text' => '<span class="screen-reader-text">' . __('Next Page', 'pool') . '</span><i class="fa fa-arrow-right" aria-hidden="true"></i>',
                ));
                echo '</div>';

                else :

                    get_template_part('template-parts/content', 'none');

                endif; ?>

            </div>
        </main><!-- #main -->
    </div><!-- #primary -->

<?php
get_footer();
