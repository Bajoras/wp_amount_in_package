jQuery(function ($) {
    $('input#_manage_amount_in_package').on('change', function () {
        if ($(this).is(':checked')) {
            $('div.amount_in_package_fields').show();
        } else {
            $('div.amount_in_package_fields').hide();
        }
        $('input.variable_manage_amount_in_package').trigger('change');
    }).trigger('change');

    $('#variable_product_options').on('change', 'input.variable_manage_amount_in_package', function () {
        $(this).closest('.woocommerce_variation').find('.show_if_variation_manage_amount_in_package').hide();
        if ($(this).is(':checked')) {
            $(this).closest('.woocommerce_variation').find('.show_if_variation_manage_amount_in_package').show();
        }
    }).trigger('change')
    $('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
        var wrapper = $('#woocommerce-product-data');
        $('input.variable_manage_amount_in_package', wrapper).trigger('change');
    });

    $(document.body).on('woocommerce-product-type-change', function () {
        $('input#_manage_amount_in_package').trigger('change')
    });
    $('input#_downloadable, input#_virtual').on('change', function () {
        $('input#_manage_amount_in_package').trigger('change')
    });
});