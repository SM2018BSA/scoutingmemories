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
        
            if ($the_user->cap_allowed('index_contributor')) {

                echo '<h1>' . get_the_title() . '</h1>';
                echo $AddLodgeForm->show_form();

            }else{

                echo "You do not have permissions to add a council."

                ;}

		?>

	    </main><!-- #main -->
    </div>

<?php
//get_sidebar();
get_footer();
