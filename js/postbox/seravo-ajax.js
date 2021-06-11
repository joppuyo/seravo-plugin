'use strict'

jQuery(document).ready(
  function () {

    jQuery('.seravo-ajax-autofetch').each(
      function () {
        var section = jQuery(this).attr('data-section');
        var postbox_id = jQuery(this).closest('.seravo-postbox').attr('data-postbox-id');

        jQuery.get(
          "https://wordpress.joosua.me/wp-admin/admin-ajax.php",
          {
          'action': 'seravo_ajax_' + postbox_id,
          'section': section,
          'nonce': SERAVO_AJAX_NONCE,
          },
          function (response) {
            jQuery('.seravo-spinner').hide();
            jQuery('.seravo-ajax-result').html(jQuery.parseJSON(response));
            jQuery('.seravo-ajax-result').show();
            console.log(response);
          }
        );

        }
    );

  }
);
