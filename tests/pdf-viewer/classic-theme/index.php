<?php
/**
 * Test environment only: the one template of the SM Test Classic theme. Search results are
 * printed like the Scouting Memories theme's template-parts/content-search.php.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title(''); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<main id="main" class="container mt-5">
    <?php if (is_search()) : ?>
        <h1 class="page-title">Search Results for: <span><?php echo get_search_query(); ?></span></h1>
    <?php endif; ?>
    <?php while (have_posts()) : the_post(); ?>
        <?php if (is_singular()) : ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                <div class="entry-content"><?php the_content(); ?></div>
            </article>
        <?php else : ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('container card'); ?>>
                <div class="card-body">
                    <?php the_title(sprintf('<h2 class="entry-title card-title"><a href="%s" rel="bookmark">', esc_url(get_permalink())), '</a></h2>'); ?>
                    <div class="entry-summary"></div>
                </div>
            </article>
        <?php endif; ?>
    <?php endwhile; ?>
    <?php the_posts_navigation(); ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
