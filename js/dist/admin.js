jQuery( function( $ ) {
    $( 'input#_manage_amount_in_package' ).on( 'change', function() {
        if ( $( this ).is( ':checked' ) ) {
            $( 'div.amount_in_package_fields' ).show();
        } else {
            $( 'div.amount_in_package_fields' ).hide();
        }
    }).trigger( 'change' );
});
