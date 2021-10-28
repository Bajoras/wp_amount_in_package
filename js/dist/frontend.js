jQuery(function ($) {
    let packageAmount = $('input[id^="package_amount_quantity_"]').val()
    if (packageAmount === "") {
        packageAmount = 1
    } else {
        packageAmount = parseFloat(packageAmount)
    }
    $('input[id^="quantity_"]').change(function () {
        let qtyInput = $(this)
        let packageQtyInput = qtyInput.closest('.quantity').find('input[id^="package_quantity_"]')
        if (qtyInput.val() === "") {
            qtyInput.val(0)
            packageQtyInput.val(0)
        } else {
            packageQtyInput.val(parseFloat(qtyInput.val()) * packageAmount)
        }
    })
    $('input[id^="package_quantity_"]').change(function () {
        let packageQtyInput = $(this)
        let qtyInput = packageQtyInput.closest('.quantity').find('input[id^="quantity_"]')
        if (packageQtyInput.val() === "") {
            qtyInput.val(0)
            packageQtyInput.val(0)
        } else {
            qtyInput.val(Math.ceil(packageQtyInput.val() / packageAmount))
        }
    })
});
