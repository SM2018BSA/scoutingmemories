<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package StrapPress
 */

?>

</div><!-- #content -->


<footer id="colophon" class="navbar navbar-dark bg-dark site-footer p-1  text-white  w-100" role="contentinfo">

    <div class="container-fluid">
        <div class=" container">
            <div class="text-center">
                <a href="https://www.facebook.com/nationalscoutingmuseumbsa/" target="_blank">
                    <img src="//storage.scoutingmemories.org/2019/03/506c9609-national-scout-museum-banner.jpg"
                         height="80" width="640" alt="">
                </a>
            </div>
        </div>    <!-- .container -->
    </div>

    <div class="container">
        <div id="footer-bottom" class="row container-fluid ">
            <div class="row w-100 m-3">


                <div class="col">
                    <div id="footer-info" class="row"><strong>&copy; <?php echo date("Y"); ?> Scouting Memories
                            Project&nbsp;</strong> | Boy Scouts of America
                    </div>
                </div>

                <div class="col">
                    <div class="row">
                        <div class="col text-right">
                            <a class="fb-ic ml-5 " role="button"><i class="fab fa-lg fa-facebook-f"></i></a>

                            <a class="tw-ic ml-5 " role="button"><i class="fab fa-lg fa-twitter"></i></a>

                            <a class="fb-ic ml-5 " role="button"><i class="fas fa-lg fa-rss"></i></a>
                        </div>
                    </div>
                </div>


            </div>
        </div>    <!-- .container -->
    </div>


</footer>


</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
