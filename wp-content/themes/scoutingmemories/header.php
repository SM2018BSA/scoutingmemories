<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package ScoutingMemories
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="profile" href="https://gmpg.org/xfn/11">

	<?php wp_head(); ?>
</head>

<body <?php body_class( 'd-flex flex-column' ); ?>><?php wp_body_open(); ?>
<header id="masthead" class="sm_green_bkg_color d-flex justify-content-between container-fluid p-2 ">

    <nav class="navbar navbar-expand-lg navbar-dark w-100" role="navigation">

        <div class="container-fluid">

            <div class="col-4 navbar-head  ">
                <a class="position-relative d-block"
                   href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                    <div id="sm_logo" class=" position-absolute" style="top:-2em;">
                        <img src="//storage.scoutingmemories.org/2021/02/b5564491-scouting_memories_project-e1613015799300.png"
                             class="img-fluid  d-block"
                             alt="Scouting Memories">

                    </div>
                </a>
            </div>


            <div class="col-4 text-center">
                <a class="col-4" href="http://www.scoutingalumni.org/" target="_blank">
                    <img class="img-fluid" style="max-height:60px;"
                         src="//storage.scoutingmemories.org/2023/03/6e4408b7-scoutingalumni_2023.jpg"/>
                    <?php /*<img class="img-fluid" style="max-height:60px;"
                         src="//storage.scoutingmemories.org/2019/03/e52d5880-scouting-alumni-banner.png"/>*/ ?>
                </a>
            </div>


            <div class="col-4   ">
                <div class="container d-flex justify-content-end position-relative">


                    <div id="mobile-menu" class="container-fluid w-100 d-flex justify-content-end "><?php
                        wp_nav_menu( array(
                            'theme_location'  => 'primary',
                            'depth'           => 2,
                            'container'       => 'div',
                            'container_class' => 'navbar-collapse collapse ',
                            'container_id'    => 'sm-navbar-collapse-1',
                            'menu_class'      => 'navbar navbar-nav navbar-dark text-white col d-flex justify-content-end ',
                            'fallback_cb'     => 'WP_Bootstrap_Navwalker::fallback',
                            'walker'          => new WP_Bootstrap_Navwalker(),
                        ) );
                        ?>
                    </div>

                    <!-- Brand and toggle get grouped for better mobile display -->
                    <button class="navbar-toggler"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#sm-navbar-collapse-1"
                            aria-controls="navbarSupportedContent" aria-expanded="false"
                            aria-label="<?php esc_attr_e( 'Toggle navigation', 'scoutingmemories' ); ?>">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                </div>

                <!-- #site-navigation -->
            </div>

        </div>

    </nav>
</header><!-- #masthead -->
<main id="page" class=" container-fluid flex-fill">



