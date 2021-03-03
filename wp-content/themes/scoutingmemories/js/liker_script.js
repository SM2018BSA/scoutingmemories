jQuery(document).ready( function() {


    jQuery(".user_like").click( function(e) {
        e.preventDefault();
        post_id = jQuery(this).attr("data-post_id");
        nonce = jQuery(this).attr("data-nonce");



        jQuery.ajax({
            type : "post",
            dataType : "json",
            url : myAjax.ajaxurl,
            data : {action: "my_user_like", post_id : post_id, nonce: nonce},
            success: function(response) {
                alert(response.test);


            }
        });
    });



});