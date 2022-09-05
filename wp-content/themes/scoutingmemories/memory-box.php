<?php
/*
  Template Name: Memory Box
 */

require_once get_template_directory() . '/Classes/Helpers.php';

$interviewForm = new Form('39');

get_header();
?>

    <div id="primary" class="content-area container-fluid mt-5 h-100 ">
        <main id="main" class="site-main p-sm-0 m-sm-5 p-lg-5 m-lg-5" role="main">
            <div class="d-flex justify-content-center">

                <div class="col-md-6 col-sm-12 ">
                    <?= $interviewForm->show_form(); ?>
                    <?= Helpers::doShortCode('[cliptakes_interview templateId="brkOaLmaBpyaAaLoi3mw"]'); ?>
                </div>

            </div>
        </main><!-- #main -->
    </div>
<style>
    .btn-primary {
        color: #fff !important;
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
    }
    .frm_message { display: none !important;}
    .ctiv-nav-button svg { width:25px !important; }
</style>
    <script type="text/javascript">
        (function ($) {

            let button_style = "<div class='frm_style_bootstrap5 with_frm_style js'><div class='frm_submit' ></div></div>";

            $("#ctiv-signup-form .ctiv-signup-row").addClass('hidden');
            $("#form_videointerviews .form-group.frm_submit").addClass('hidden');
            $("#ctiv-main-container button").wrap(button_style).addClass("btn-primary").addClass("btn");
            $("#ctiv-signup-form input[value='Continue']").wrap(button_style).addClass("btn-primary").addClass("btn");



            $(document).on("keyup", "#field_mb_name_first", function (){
                $("#ctiv-signup-first-name").val( $("#field_mb_name_first").val() );
            });
            $(document).on("keyup", "#field_mb_name_last", function (){
                $("#ctiv-signup-last-name").val( $("#field_mb_name_last").val() );
            });

            $(document).on("click", ".frm_slider", function (){
                $("#mb_lodge_indexing").toggleClass("hidden");
                $("#mb_lodge_text").toggleClass("hidden");
            });



            $("#ctiv-intro-next").on("click", function (){
               $(".frm_page_num_1 #mbox_submit").click();
            });
            $("#ctiv-signup-form input[value='Continue']").on("click", function (){
               $(".frm_page_num_2 #mbox_submit").click();
               $("#frm_form_39_container").addClass("hidden");
            });

        })(jQuery);
    </script>
<?php
//get_sidebar();
get_footer();
