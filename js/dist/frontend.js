jQuery(function ($) {
    let init = function () {
        let packageAmount = $('input[id^="package_amount_quantity_"]').val()
        if (packageAmount === "") {
            packageAmount = 1
        } else {
            packageAmount = parseFloat(packageAmount)
        }

        $('.dk_package_amount_summary span.amount').text(packageAmount)

        return packageAmount
    }

    $(document.body).on('keyup change', 'input[id^="package_quantity_"]', function () {
        let packageAmount = init()
        let packageQtyInput = $(this)
        let quantityWrapper = packageQtyInput.closest('.quantity')
        let qtyInput = quantityWrapper.find('input[id^="quantity_"]')
        let summaryQuantity = quantityWrapper.find('.dk_package_amount_summary span.amount_quantity')
        let summaryTotalAmount = quantityWrapper.find('.dk_package_amount_summary span.amount_real_amount')
        if (packageQtyInput.val() === "") {
            qtyInput.val(1)
            summaryQuantity.text(1)
            summaryTotalAmount.text(packageAmount)
        } else {
            let quantity = Math.ceil(packageQtyInput.val() / packageAmount)
            qtyInput.val(quantity)
            summaryQuantity.text(quantity)
            summaryTotalAmount.text((quantity * packageAmount).toFixed(3))
        }
    })
});
