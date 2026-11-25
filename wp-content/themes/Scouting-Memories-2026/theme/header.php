<?php
/**
 * The header for our theme
 *
 * This is the template that displays the `head` element and everything up
 * until the `#content` element.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Scouting_Memories_2026
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'flex min-h-screen flex-col bg-white font-sans text-ink' ); ?>>

<?php wp_body_open(); ?>

<div id="page" class="flex min-h-screen w-full flex-col">
	<a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:text-black"><?php esc_html_e( 'Skip to content', 'scouting-memories-2026' ); ?></a>

	<?php get_template_part( 'template-parts/layout/header', 'content' ); ?>

	<div id="content" class="w-full grow px-3 has-[>[data-centered]]:flex has-[>[data-centered]]:flex-col" tabindex="-1">
