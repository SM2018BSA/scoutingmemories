<?php /* Template Name: Centered Content  */

get_header(); ?>


<div class="container h-100">
    <div id="primary" class="content-area row h-100">
        <main id="main" class="site-main container my-auto" role="main">

            <?php
            while ( have_posts() ) : the_post();

                get_template_part( 'template-parts/content', 'page' );

                // If comments are open or we have at least one comment, load up the comment template.
                if ( comments_open() || get_comments_number() ) :
                    comments_template();
                endif;

            endwhile; // End of the loop.
            ?>

        </main><!-- #main -->
    </div><!-- #primary -->
</div>

 

<?php
get_footer();
