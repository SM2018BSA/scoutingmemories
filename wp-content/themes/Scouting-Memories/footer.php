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
</div><!-- #content col -->
</div><!-- #content row -->


<footer id="colophon" class="navbar navbar-dark bg-dark site-footer p-1  text-white row " role="contentinfo">

    <div class="col">
        <div class="text-center">
            <a href="https://www.facebook.com/nationalscoutingmuseumbsa/" target="_blank">
                <img src="//storage.scoutingmemories.org/2019/03/506c9609-national-scout-museum-banner.jpg"
                     height="80" width="640" alt="">
            </a>
        </div>
    </div>
    <div class="w-100"></div>
    <div class="col">
        <div id="footer-bottom" class="row p-3">

            <div class="col">
                <div id="footer-info"><strong>&copy; <?php echo date("Y"); ?> Scouting Memories Project&nbsp;</strong> |
                    Boy Scouts of America
                </div>
            </div>
            <div class="col">
                <div class=" nav justify-content-center  ">
                    <a class="nav-link  text-white" href="<?= site_url() ?>/contact-us/" target="_self">Contact Us </a> |
                    <a class="nav-link  text-white" href="<?= site_url() ?>/privacy-policy/" target="_self">Privacy Policy </a></div>
            </div>
            <div class="col">
                <div class="col text-right">
                    <a class="fb-ic ml-5 " role="button"><i class="fab fa-lg fa-facebook-f"></i></a>

                    <a class="tw-ic ml-5 " role="button"><i class="fab fa-lg fa-twitter"></i></a>

                    <a class="fb-ic ml-5 " role="button"><i class="fas fa-lg fa-rss"></i></a>
                </div>
            </div>

        </div>    <!-- .container -->
    </div>


</footer>


</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
