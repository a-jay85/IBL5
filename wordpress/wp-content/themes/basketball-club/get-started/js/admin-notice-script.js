( function( $ ){
    // Attach a click event handler to the dismiss button within elements with the specified class
    $( document ).on( 'click', '.notice-get-started-class .notice-dismiss', function () {
        // Retrieve the type of the dismissed notice from the "data-notice" attribute
        var type = $( this ).closest( '.notice-get-started-class' ).data( 'notice' );
        
        // Prepare and send an AJAX request to notify the server about the dismissed notice
        $.ajax( ajaxurl,
          {
            type: 'POST',
            // Specify the server-side action for handling the dismissed notice
            data: {
              action: 'basketball_club_dismissed_notice',
              // Include the type of the dismissed notice in the data payload
              type: type,
            }
          } );
      } );
}( jQuery ) )
