<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package StrapPress
 * @param $state_key
 */








get_header(); ?>

    <div id="primary" class="content-area container pt-0 pl-5 pr-5 mt-5">
        <main id="main" class="site-main container-fluid pt-0 pl-5 pr-5 m-0" role="main">
            <?php
            while (have_posts()) : the_post(); ?>


                <div class="row">
                    <div class="col-8">
                        <div class="row post-content"><?php get_template_part('template-parts/content', get_post_format()); ?></div>
                        <div class="row post-comments">
                            <div class="container">
                                <?php
                                // If comments are open or we have at least one comment, load up the comment template.
                                if (comments_open() || get_comments_number()) :
                                    comments_template();
                                endif ?>
                            </div>
                        </div>
                        <div class="row post-meta">
                            <div class="row container timeline mt-5 mb-5">
                                <ul class="list-group col">
                                    <li class="list-group-item-dark text-white strong p-3 list-unstyled">META</li>
                                    <li class="list-group-item"><strong>Publisher of
                                            Digital:</strong> <?php the_field('publisher_of_digital') ?></li>
                                    <li class="list-group-item"><strong>Date of
                                            Original:</strong> <?php the_field('date_of_original') ?></li>
                                    <li class="list-group-item"><strong>Date of
                                            Digital:</strong> <?php the_field('date_of_digital') ?></li>
                                    <li class="list-group-item">
                                        <strong>Identifier:</strong> <?php the_field('identifier') ?></li>
                                    <li class="list-group-item">
                                        <strong>Subject:</strong> <?php the_field('meta_subject') ?></li>
                                    <li class="list-group-item">
                                        <strong>Location:</strong> <?php the_field('meta_location') ?></li>
                                    <li class="list-group-item"><strong>Physical
                                            Description:</strong> <?php the_field('meta_physical_description') ?></li>
                                </ul>
                            </div>
                        </div>

                    </div>
                    <div class="col-4">

                        <?php

                       // echo '<pre>';
                        //var_dump(get_field('lodge', get_the_ID()) );

                        ?>

                        <div class="container credits">
                            <ul class="list-group ">
                                <li class="list-group-item-dark text-white strong p-3 list-unstyled font-weight-bold" >Timeline</li>
                                <li class="list-group-item"><strong>State:</strong>   <?=get_state_name(         get_field('state'))  ?></li>
                                <li class="list-group-item"><strong>Council:</strong> <?=get_council_name_number(get_field('council'))?></li>
                                <li class="list-group-item"><strong>Camp:</strong>    <?=get_camp_name(          get_field('camp')) ?></li>
                                <li class="list-group-item"><strong>Lodge:</strong>   <?=get_lodge_name(         get_field('lodge'));  ?></li>
                                <li class="list-group-item"><strong>Start Date:</strong>  <span class="small"><?php the_field('start_date') ?></span> </li>
                                <li class="list-group-item"><strong>End Date:</strong>  <span class="small"><?php the_field('end_date')   ?></span> </li>
                            </ul>
                        </div>
                    </div>
                </div>

            <?php endwhile; // End of the loop. ?>


        </main><!-- #main -->
        <?php


        $post_id = get_the_ID();
        $post_categories = wp_get_post_categories($post_id);


        $cats = array();
        foreach ($post_categories as $c) {
            $cat = get_category($c);
            $cats[] = array('name' => $cat->name, 'slug' => $cat->slug);
        }


        // Only show this widget on posts in the museums category
        $show_related = false;
        foreach ($cats as $cat) {
            if (in_array("museums", $cat))
                $show_related = true;
        }


        ?>

        <?php if ($show_related) : ?>
            <?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar("Related Info")) : ?>
            <?php endif; ?>
        <?php endif; ?>

    </div><!-- #primary -->

<?php
get_footer();
