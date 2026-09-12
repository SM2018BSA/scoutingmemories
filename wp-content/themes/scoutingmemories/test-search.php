<?php /* Template Name: Test Search  */


//$lodge = new LodgeEntry('6245');




// TODO
// make this a  admin function to update meta for lodges council slugs
//
//$camp_entries  = FrmEntry::getAll([	'form_id' => ADD_A_CAMP_FORMID  ]);
//$lodge_entries = FrmEntry::getAll([ 'form_id' => ADD_A_LODGE_FORMID ]);
//
//
//
//foreach ($lodge_entries as $entries) {
//	$lodges[] = new LodgeEntry($entries->id);
//}


//foreach ($camp_entries as $entries) {
//	$camps[] = new CampEntry($entries->id);
//}

 


get_header();




//$states_view_id = 2300; // local and live
////$states_view_id = 1553; // online dev
//$states_view = new View($states_view_id);
//
//
//







//$camp_entry = new CampEntry(385);

//var_dump($camp_entry);die();



//var_dump(Entry::get_repeater_ids_from_key('Trenton_Area_Council_362', '269'));

//$val = Entry::get_repeater_ids_from_key('Utica_Council_406', AACAMP_ADD_LINKED_COUNCIL_SLUGS_FID);
//
//echo $val;

//var_dump($council_entry);die();

//$council_view = new CouncilView();
//$lodge_view = new LodgeView();
//$camp_view = new CampView();


//$lodge = new LodgeEntry(6245);
//
//var_dump($lodge);
//
//die();

//var_dump(get_post_meta(3094));

//
//die();






?>





    <div class="container d-flex h-100">
        <div id="primary" class="row w-100 justify-content-center align-self-center mx-auto">
            <main id="main" class="container w-50" role="main">
                <div class="row">
                    <div class="col">


<?php /*
                        <div class="card-body w-lg-50 w-md-100 mx-auto">

<!--                    <form method="post" action="/info-page?state=NY&council=general_herkimer_400&lodge=kamargo_lodge_294&camp=camp_russell_385&start_date=1900&end_date=2019" class="row" >-->
                    <form method="post" enctype="multipart/form-data" class="row" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"   >
	                    <?php wp_nonce_field( 'search_form', 'search_nonce' ); ?>
                        <input type="hidden" name="action" value="search_form">
                        <input type="hidden" name="page" value="info-page">

                        <div class="form-group col-12" >
                            <label for="select_state">Select State</label>
                            <select name="select_state[]" class="form-control" id="select_state" multiple="multiple" style="display: none;">
                                <?=$states_view->show_view(); ?>
                            </select>
                        </div>
                        <div class="form-group col-12 hidden">
                            <label for="select_council">Select Council</label>
                            <select name="select_council[]" class="form-control" id="select_council" multiple="multiple" ></select>
                        </div>

                        <div class="form-group col-12 hidden">
                            <label for="select_lodge">Select Lodge</label>
                            <select name="select_lodge[]" class="form-control" id="select_lodge" multiple="multiple" ></select>
                        </div>

                        <div class="form-group col-12 hidden">
                            <label for="select_camp">Select Camp</label>
                            <select name="select_camp[]" class="form-control" id="select_camp" multiple="multiple" ></select>
                        </div>

                        <div class="form-group col-6 ">
                            <label for="start_date">Start Date</label>
                            <input name="start_date" class="form-control" id="start_date" value="1900"  />
                        </div>
                        <div class="form-group col-6 ">
                            <label for="end_date">End Date</label>
                            <input name="end_date" class="form-control" id="end_date" value="2020"  />
                        </div>

                        <div class="form-group col-3">
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>



                    </form>




                </div>


                    </div>
                </div>
                <div class="row">
                    <div class="col">
*/?>

                        <?php





                     //  echo SearchForm::show_form();

                        ?>


<?php



// fix up slugs for loges and camps
//$camp_entries = FrmEntry::getAll([	'form_id' => ADD_A_CAMP_FORMID ]);
//$lodge_entries = FrmEntry::getAll([	'form_id' => ADD_A_LODGE_FORMID ]);
//
//foreach ($camp_entries as $entries)
//	$camps[] = new CampEntry($entries->id);
//
//foreach ($lodge_entries as $entries)
//    $lodges[] = new LodgeEntry($entries->id);



//$councilTest = new CouncilEntry(378);
//var_dump($councilTest);





//$camp_entry = new CampEntry(385);
//var_dump($camp_entry);
//
//$lodge_view = new LodgeView(ALL_LODGES_SEARCH_VIEWID);
//echo $lodge_view->show_view( array( 'search_param' => 'Herkimer_County_Council_' ));

//$camp_view = new CampView(ALL_CAMPS_SEARCH_VIEWID);
//echo $camp_view->show_view( array( 'search_param' => 'Revolutionary_Trail_Council_400' ));





?>




                    </div>
                </div>

            </main><!-- #main -->
        </div><!-- #primary -->
    </div>




<?php
get_footer();
