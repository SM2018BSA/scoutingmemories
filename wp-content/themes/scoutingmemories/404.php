<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package ScoutingMemories
 */

get_header();
?>

    <div id="primary" class="content-area container mt-5 h-100">
        <main id="main" class="site-main container-fluid p-0 m-0" role="main">

            <section class="error-404 not-found">
                <header class="page-header">
                    <h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'scoutingmemories' ); ?></h1>
                </header><!-- .page-header -->

                <div class="page-content">


					<?php




					?>





                </div><!-- .page-content -->
            </section><!-- .error-404 -->

        </main><!-- #main -->
    </div>

<?php
get_footer();
