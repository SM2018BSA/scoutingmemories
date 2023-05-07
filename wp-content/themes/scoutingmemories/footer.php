<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package ScoutingMemories
 */

?></main><!-- #page -->
<footer id="colophon" class="navbar navbar-dark bg-dark container-fluid site-footer p-1  text-white "
        role="contentinfo">

    <div class="row w-100 d-flex">


        <div class=" d-flex justify-content-center ">

            <a href="https://www.facebook.com/nationalscoutingmuseumbsa/" target="_blank">
                <img class="img-fluid"
                     src="//storage.scoutingmemories.org/2020/10/8fa5300b-nsm_psr-banner-20201013.png"
                     height="80" width="640" alt="">
            </a>

        </div>


        <div class=" w-100 d-flex justify-content-center ">
            <div id="footer-bottom" class="row w-100 p-3">

                    <div class="nav  justify-content-center ">
                        <div class="flex-row">
                            <div class="col nav-link text-light">&copy; <?php echo date("Y"); ?> Scouting Memories Project&nbsp;</strong> |
                                Boy Scouts of America
                            </div>

                        </div>
                        <div class="flex-row ">
                            <div class="d-flex justify-content-center">
                                <a class="nav-link  text-light border-right" href="<?= site_url() ?>/contact-us/"
                                   target="_self">Contact Us </a>
                                <a class="nav-link  text-light border-right"
                                   href="https://scoutingmemories.org/publishing-guidelines/" target="_self">Publishing
                                    Guidelines </a>
                                <a class="nav-link  text-white" href="<?= site_url() ?>/privacy-policy/" target="_self">Privacy
                                    Policy </a>
                            </div>
                        </div>
                    </div>


            </div>    <!-- .container -->
        </div>


    </div>

</footer>
<?php wp_footer(); ?>

</body>
</html>