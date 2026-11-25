<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the `#content` element and all content thereafter.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Scouting_Memories_2026
 */

?>

	</div><!-- #content -->

	<?php get_template_part( 'template-parts/layout/footer', 'content' ); ?>

</div><!-- #page -->

<?php
// The "choose a file" window of the rich-text editor; it prints nothing for someone who cannot upload.
get_template_part( 'template-parts/media/picker' );

wp_footer();
?>

</body>
</html>
