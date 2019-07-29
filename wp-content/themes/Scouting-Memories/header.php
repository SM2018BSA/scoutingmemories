<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package StrapPress
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">

    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div id="page" class="container-fluid d-flex flex-column h-100 ">
    <header id="masthead" class="row " role="banner">
        <nav class="navbar navbar-expand-lg has-primary-background-color navbar-dark col" style="z-index: 100; ">
            <div class="container row mx-auto">
                <div class="col-3">
                    <div class="navbar-brand mb-0 "><a class="position-absolute" style="top:-50px"
                                                       href="<?php echo esc_url(home_url('/')); ?>" rel="home"><img
                                    src="//storage.scoutingmemories.org/2019/02/cccbadfe-scouting-memories.png"
                                    alt="Scouting Memories"></a></div>
                </div>
                <div class="col-3">
                    <div id="top-banner"><a
                                href="http://www.scoutingalumni.org/"
                                target="_blank">
                            <img src="//storage.scoutingmemories.org/2019/03/e52d5880-scouting-alumni-banner.png"
                                 height="80"
                                 width="315"/>
                        </a>
                    </div>
                </div>
                <div>


                    <button class="navbar-toggler navbar-toggler-right" type="button"
                            data-toggle="collapse"
                            data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false"
                            aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse has-primary-background-color " id="navbarNav">
                        <?php
                        $args = array(
                            'theme_location' => 'primary',
                            'depth' => 2,
                            'container' => false,
                            'menu_class' => 'navbar-nav',
                            'walker' => new WP_Bootstrap_Navwalker()
                        );
                        if (has_nav_menu('primary')) {
                            wp_nav_menu($args);
                        }
                        ?>
                    </div>

                </div>
            </div>
        </nav>
    </header><!-- #masthead -->
    <div id="content" class="site-content row flex-fill ">
        <div class="col d-flex flex-column">

