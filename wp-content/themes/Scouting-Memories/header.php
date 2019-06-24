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


        <?php /*


                    <?php if (!is_user_logged_in()) : ?>
                        <div class="collapse navbar-collapse has-primary-background-color " id="navbarNav">
                            <?php
                            $args = array(
                                'theme_location' => 'primary',
                                'depth' => 2,
                                'container' => false,
                                'menu_class' => 'navbar-nav',
                                'walker' => new Bootstrap_Walker_Nav_Menu()
                            );
                            if (has_nav_menu('primary')) {
                                wp_nav_menu($args);
                            }
                            ?>
                        </div>
                    <?php else: ?>

                        <div class="collapse navbar-collapse has-primary-background-color " id="navbarNav">
                            <ul id="menu-primary" class="navbar-nav">
                                <li id="menu-item-848"
                                    class="menu-item menu-item-type-post_type menu-item-object-page menu-item-848 nav-item">
                                    <a href="http://scoutingmemories.org/my-account/" class="nav-link">My Account</a>
                                </li>
                                <li id="menu-item-826"
                                    class="megamenu-li menu-item menu-item-type-post_type menu-item-object-page current-menu-ancestor current-menu-parent current_page_parent current_page_ancestor menu-item-has-children dropdown active menu-item-826 nav-item">
                                    <a href="http://scoutingmemories.org/add-a-post/" class="nav-link dropdown-toggle"
                                       id="navbarDropdownMenuLink" data-toggle="dropdown" aria-haspopup="true"
                                       aria-expanded="false">Create</a>

                                    <div class="dropdown-menu megamenu has-primary-background-color text-white">
                                        <div class="container pt-5">
                                            <div class="row">
                                                <div id="menu-item-829"
                                                     class="col-3 text-center menu-item menu-item-type-post_type menu-item-object-page menu-item-829 nav-item">
                                                    <a class="dropdown-item text-white"
                                                       href="http://scoutingmemories.org/add-a-council/">
                                                        <i class="fas fa-mountain " style="font-size: 42px;"></i>
                                                        <span class="btn-block">Add a Council</span></a>
                                                </div>
                                                <div id="menu-item-827"
                                                     class="col-3 text-center menu-item menu-item-type-post_type menu-item-object-page menu-item-827 nav-item">
                                                    <a class="dropdown-item text-white"
                                                       href="http://scoutingmemories.org/add-a-lodge/">
                                                        <i class="fas fa-tree" style="font-size: 42px;"></i>
                                                        <span class="btn-block">Add a Lodge</span></a>
                                                </div>
                                                <div id="menu-item-828"
                                                     class="col-3 text-center menu-item menu-item-type-post_type menu-item-object-page current-menu-item page_item page-item-393 current_page_item active menu-item-828 nav-item">
                                                    <a class="dropdown-item text-white"
                                                       href="http://scoutingmemories.org/add-a-camp/">
                                                        <i class="fas fa-campground" style="font-size: 42px;"></i>
                                                        <span class="btn-block">Add a Camp</span></a>
                                                </div>
                                                <div id="menu-item-831"
                                                     class="col-3 text-center menu-item menu-item-type-post_type menu-item-object-page menu-item-831 nav-item">
                                                    <a class="dropdown-item text-white"
                                                       href="http://scoutingmemories.org/add-a-post/">
                                                        <i class="fas fa-plus-square" style="font-size: 42px;"></i>
                                                        <span class="btn-block">Add a Post</span></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
*/?>
