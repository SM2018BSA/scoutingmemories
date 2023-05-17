jQuery(document).ready(function () {


    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);

    let state = urlParams.get('state');
    state = state !== null  ? state = state.split(',') : state = '';

    let council = urlParams.get('council');
    council = council !== null ? council = council.split(',') : council = '';

    let lodge = urlParams.get('lodge');
    lodge = lodge !== null ? lodge = lodge.split(',') : lodge = '';

    let camp = urlParams.get('camp');
    camp = camp !== null ? camp = camp.split(',') : camp = '';

    let start_date = urlParams.get('start_date');
    let end_date = urlParams.get('end_date');


    let cat = urlParams.get('cat');
    jQuery('#cat').select2({
        maximumSelectionLength: 1,
        width: '100%'
    });


    jQuery('#cat').val(cat);
    jQuery('#cat').trigger('change');



    if (jQuery('#cat').hasClass("select2-hidden-accessible")) {
        // Select2 has been initialized
        jQuery('#select_category_loading').addClass('hidden');
        jQuery('#cat').parent().removeClass('hidden');
    }



    jQuery('#select_state').select2({
        maximumSelectionLength: 3,
        width: '100%'
    });

    if (jQuery('#select_state').hasClass("select2-hidden-accessible")) {
        // Select2 has been initialized
        jQuery('#select_states_loading').addClass('hidden');
        jQuery('#select_state').parent().removeClass('hidden');
    }





    jQuery('#show_merged').on('click', function(){

        jQuery('#select_state').change();

        if (!jQuery('#show_merged').is(':checked')) {
            jQuery('#select_merged_council').parent().addClass('hidden');
        }

    });






    jQuery('#select_state').on('change', function () {

        let selected_states = jQuery(this).val();
        let nonce = jQuery('#search_nonce').attr("value");

        //console.log(selected_states);

        jQuery('#select_council').parent().addClass('hidden');
        jQuery('#select_lodge').parent().addClass('hidden');
        jQuery('#select_camp').parent().addClass('hidden');
        jQuery('#select_council_loading').addClass('hidden');
        jQuery('#select_lodge_loading').addClass('hidden');
        jQuery('#select_camp_loading').addClass('hidden');


        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: myCouncilAjax.ajaxurl,
            data: {action: "search_councils", selected_states: selected_states, nonce: nonce},
            success: function (response) {

                jQuery('#select_council').html(response.state).change();

                let select = jQuery('#select_council');
                select.html(select.find('option').sort(function (x, y) {
                    // to change to descending order switch "<" for ">"
                    return $(x).text() > $(y).text() ? 1 : -1;
                }));


                if (jQuery('#select_council option').length > 0) {
                    if (council.length > 0 ) {
                        jQuery('#select_council').val(council).change();
                    }
                    jQuery('#select_council_loading').addClass('hidden');
                    jQuery('#select_council').select2({ maximumSelectionLength: 3 }).parent().removeClass('hidden');
                } else {
                    jQuery('#select_council').parent().addClass('hidden');
                    jQuery('#select_lodge').parent().addClass('hidden');
                    jQuery('#select_camp').parent().addClass('hidden');
                    jQuery('#select_council_loading').addClass('hidden');
                    jQuery('#select_lodge_loading').addClass('hidden');
                    jQuery('#select_camp_loading').addClass('hidden');
                }


            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        });


    });












    jQuery('#select_council').on('change', function () {

        let selected_councils = jQuery(this).val();
        let nonce = jQuery('#search_nonce').attr("value");

        jQuery('#select_merged_council').val(null).change();

        let show_merged = jQuery('#show_merged').is(':checked');

        //console.log('in selecte council change');

        if (show_merged) {

            //console.log ('show merge is true');

            if (selected_councils.length > 0) {
                jQuery('#select_merged_council_loading').removeClass('hidden');
            }



            jQuery.ajax({
                type: "post",
                dataType: "json",
                url: myCouncilAjax.ajaxurl,
                data: {action: "search_merged_councils", selected_councils: selected_councils, nonce: nonce},
                success: function (response) {


                    jQuery('#select_merged_council').html(response.state).change();


                    let select = jQuery('#select_merged_council');
                    select.html(select.find('option').sort(function (x, y) {
                        // to change to descending order switch "<" for ">"
                        return $(x).text() > $(y).text() ? 1 : -1;
                    }));




                    if (jQuery('#select_merged_council option').length > 0) {

                        jQuery('#select_merged_council_loading').addClass('hidden');
                        jQuery('#select_merged_council').select2({width: '100%'}).parent().removeClass('hidden');

                    } else {
                        jQuery('#select_merged_council').parent().addClass('hidden');
                        jQuery('#select_merged_council_loading').addClass('hidden');
                    }


                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
            });

        }



    });












    jQuery('#select_merged_council').on('change', function () {

        //console.log('selected_merge_council changed');


        let show_merged = jQuery('#show_merged').is(':checked');

        jQuery('#select_lodge').val(null).parent().addClass('hidden').change();
        jQuery('#select_camp').val(null).parent().addClass('hidden').change();
        jQuery('#select_lodge_loading').addClass('hidden');
        jQuery('#select_camp_loading').addClass('hidden');

        let selected_councils = jQuery('#select_council').val();



        if (show_merged) {
            if (Boolean(jQuery(this).val())) {
                selected_councils += ',' + jQuery(this).val();

            }
        }

        let nonce = jQuery('#search_nonce').attr("value");



        if (selected_councils.length  > 1) {




            jQuery('#select_lodge_loading').removeClass('hidden');

            //console.log('selected_councils: '+selected_councils);

            jQuery.ajax({
                type: "post",
                dataType: "json",
                url: myLodgeAjax.ajaxurl,
                data: {action: "search_lodges", selected_councils: selected_councils, nonce: nonce},
                success: function (response) {

                    jQuery('#select_lodge').html(response.state);


                    let select = jQuery('#select_lodge');
                    select.html(select.find('option').sort(function (x, y) {
                        // to change to descending order switch "<" for ">"
                        return $(x).text() > $(y).text() ? 1 : -1;
                    }));

                    if (jQuery('#select_lodge option').length > 0) {
                        if (lodge.length > 0 ) {
                            jQuery('#select_lodge').val(lodge);
                        }
                        jQuery('#select_lodge').select2({ maximumSelectionLength: 5, width: '100%' }).parent().removeClass('hidden');
                        jQuery('#select_lodge_loading').addClass('hidden');
                    } else {
                        jQuery('#select_lodge').parent().addClass('hidden');
                        jQuery('#select_lodge_loading').addClass('hidden');
                    }


                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
            });


            jQuery('#select_camp_loading').removeClass('hidden');
            jQuery.ajax({
                type: "post",
                dataType: "json",
                url: myCampAjax.ajaxurl,
                data: {action: "search_camps", selected_councils: selected_councils, nonce: nonce},
                success: function (response) {
                    // console.log("camp: ");
                    // console.log(response);

                    jQuery('#select_camp').html(response.state);


                    let select = jQuery('#select_camp');
                    select.html(select.find('option').sort(function (x, y) {
                        // to change to descending order switch "<" for ">"
                        return $(x).text() > $(y).text() ? 1 : -1;
                    })).promise().done(function () {
                        //your callback logic / code here
                        const options = []
                        document.querySelectorAll('#select_camp > option').forEach((option) => {
                            if (options.includes(option.value)) option.remove()
                            else options.push(option.value)
                        })


                        if (jQuery('#select_camp option').length > 0) {
                            if (camp.length > 0 ) {
                                jQuery('#select_camp').val(camp);
                            }
                            jQuery('#select_camp').select2({ maximumSelectionLength: 3 }).parent().removeClass('hidden');
                            jQuery('#select_camp_loading').addClass('hidden');
                        } else {
                            jQuery('#select_camp').parent().addClass('hidden');
                            jQuery('#select_camp_loading').addClass('hidden');
                        }


                    });


                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
            });




        }


    });




    if (state !== '') {
        jQuery('#select_state').val(state).trigger('change');
    }




});