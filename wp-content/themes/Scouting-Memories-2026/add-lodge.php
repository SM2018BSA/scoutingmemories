<?php
/*
  Template Name: Add Lodge


 */

get_header();

global $the_user;

$AddLodgeForm = new Form(ADD_A_LODGE_FORMID);


?>

    <div id="primary" class="content-area container mt-5 h-100 aasdf" >
        <main id="main" class="site-main container-fluid p-0 m-0" role="main">

		<?php
            if (($the_user && $the_user->cap_allowed('index_contributor')) || current_user_can('edit_others_posts')) {
                echo '<h1>' . get_the_title() . '</h1>';
                if (shortcode_exists('sm_add_lodge_form')) {
                    echo do_shortcode('[sm_add_lodge_form]');
                } elseif (isset($AddLodgeForm)) {
                    echo $AddLodgeForm->show_form();
                }
            } else {
                echo '<div class="alert alert-warning">' . esc_html__('You do not have permissions to add a lodge.', 'scouting-memories-2026') . '</div>';
            }
		?>

	    </main><!-- #main -->
    </div>

<?php
//get_sidebar();
get_footer();
