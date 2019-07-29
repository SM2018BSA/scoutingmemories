<?php /* Template Name: Short Centered Content  */

get_header();


?>
    <div class="container d-flex h-100">
        <div id="primary" class="row w-100 justify-content-center align-self-center mx-auto">
            <main id="main" class="col" role="main">
                <div class="card-body w-lg-50 w-md-100 mx-auto">
                    <?php
                    while (have_posts()) : the_post();

                        get_template_part('template-parts/content', 'page');

                        // If comments are open or we have at least one comment, load up the comment template.
                        if (comments_open() || get_comments_number()) :
                            comments_template();
                        endif;

                    endwhile; // End of the loop.
                    ?>
                </div>
            </main><!-- #main -->
        </div><!-- #primary -->
    </div>


<?php
get_footer();
