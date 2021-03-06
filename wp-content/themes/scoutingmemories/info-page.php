<?php /*  Template Name: Info Page */

get_header();



?>

<div class="content-area container">

<div class="container-fluid d-flex flex-column h-100">
	<div class="row d-flex h-100 align-items-center">
		<div class="col col-sm-12 col-md-4 d-flex flex-column h-100 justify-content-center frm_submit frm_style_divi with_frm_style ">

			<div class="d-flex row ">
				<div class="col mt-4 mt-md-0">
					<a class=" btn btn-primary  d-block d-md-inline-block   mt-2 " 
                       href="/category/history/?state=<?=get_request_parameter('state')?>&amp;council=<?=get_request_parameter('council')?>&amp;lodge=<?=get_request_parameter('lodge')?>&amp;camp=<?=get_request_parameter('camp')?>&amp;start_date=<?=get_request_parameter('start_date')?>&amp;end_date=<?=get_request_parameter('end_date')?>" 
                       target="_self" rel="noopener noreferrer">History</a>
				</div>
			</div>
			<div class="f-flex row">
				<div class="col">
					<a class=" btn btn-primary d-block d-md-inline-block   mt-5 mb-5" 
                       href="/category/photographs/?state=<?=get_request_parameter('state')?>&amp;council=<?=get_request_parameter('council')?>&amp;lodge=<?=get_request_parameter('lodge')?>&amp;camp=<?=get_request_parameter('camp')?>&amp;start_date=<?=get_request_parameter('start_date')?>&amp;end_date=<?=get_request_parameter('end_date')?>" 
                       target="_self" rel="noopener noreferrer">Photographs</a>
				</div>
			</div>
			<div class="d-flex row">
				<div class="col mb-4 mb-md-0">
					<a class=" btn btn-primary d-block d-md-inline-block  mb-2 " 
                       href="/category/movies/?state=<?=get_request_parameter('state')?>&amp;council=<?=get_request_parameter('council')?>&amp;lodge=<?=get_request_parameter('lodge')?>&amp;camp=<?=get_request_parameter('camp')?>&amp;start_date=<?=get_request_parameter('start_date')?>&amp;end_date=<?=get_request_parameter('end_date')?>" 
                       target="_self" rel="noopener noreferrer">Movies</a>
				</div>
			</div>

		</div>

		<div class="col col-sm-12 col-md-4 d-none d-md-block">
			<img class=" img-responsive" src="http://storage.scoutingmemories.org/2019/02/904438c9-1911-boy-scount-original-cove-2r.jpg" alt="">

		</div>
		<div class="col col-sm-12 col-md-4 d-flex flex-column h-100 justify-content-center frm_submit frm_style_divi with_frm_style">

			<div class="d-flex row ">
				<div class="col ">
					<div class="text-right">
						<a class="btn btn-primary d-block d-md-inline-block mt-2 "
                           href="/category/oral-history/?state=<?=get_request_parameter('state')?>&amp;council=<?=get_request_parameter('council')?>&amp;lodge=<?=get_request_parameter('lodge')?>&amp;camp=<?=get_request_parameter('camp')?>&amp;start_date=<?=get_request_parameter('start_date')?>&amp;end_date=<?=get_request_parameter('end_date')?>"
                           target="_self" rel="noopener noreferrer">Oral History</a>
					</div>
				</div>
			</div>
			<div class="d-flex row  ">
				<div class="col ">
					<div class="text-right">
						<a class="btn btn-primary d-block d-md-inline-block  mt-5 mb-5"
                           href="/category/memorabilia/?state=<?=get_request_parameter('state')?>&amp;council=<?=get_request_parameter('council')?>&amp;lodge=<?=get_request_parameter('lodge')?>&amp;camp=<?=get_request_parameter('camp')?>&amp;start_date=<?=get_request_parameter('start_date')?>&amp;end_date=<?=get_request_parameter('end_date')?>"
                           target="_self" rel="noopener noreferrer">Memorabilia</a>
					</div>
				</div>
			</div>
			<div class="d-flex row ">
				<div class="col mb-4 mb-md-0 ">
					<div class="text-right">
						<a class="btn btn-primary d-block d-md-inline-block mb-2 "
                           href="/category/museums/?state=<?=get_request_parameter('state')?>&amp;council=<?=get_request_parameter('council')?>&amp;lodge=<?=get_request_parameter('lodge')?>&amp;camp=<?=get_request_parameter('camp')?>&amp;start_date=<?=get_request_parameter('start_date')?>&amp;end_date=<?=get_request_parameter('end_date')?>"
                           target="_self" rel="noopener noreferrer">Museums</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="w-100"></div>

	<div class="row">
		<div class="col">
            <?php

            echo SearchForm::show_form2();


            ?>
            <?php  /*[formidable id="16" title="1" description="1" minimize="1" ] */ ?>
        </div>
	</div>
</div>

</div>


<?php echo SearchForm::search_form_js() ?>

<?php get_footer(); ?>