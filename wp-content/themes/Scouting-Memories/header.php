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
<div id="page" class="site h-100 container-fluid m-0 p-0">
    <header id="masthead" class="site-header site-header pb-5 container-fluid p-0 m-0" role="banner">
        <nav class="navbar navbar-expand-lg has-primary-background-color navbar-dark">
            <div class="container row mx-auto">
                <div class="col-3">
                    <div class="navbar-brand mb-0 "><a class="position-absolute" style="top:-25px"
                                                       href="<?php echo esc_url(home_url('/')); ?>" rel="home"><img
                                    src="//storage.scoutingmemories.org/2019/02/cccbadfe-scouting-memories.png"
                                    alt="Scouting Memories"></a></div>
                </div>
                <div class="col-3">
                    <div id="top-banner"><a
                                href="http://www.scoutingalumni.org/site/c.ejIPK1NNLgJ0E/b.9309477/k.BF3E/Home.htm"
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
    <div id="content" class="site-content container">


