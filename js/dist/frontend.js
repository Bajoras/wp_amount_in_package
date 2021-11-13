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

    let getSummaryQuantity = function () {
        return $('.dk_package_amount_summary span.amount_quantity')
    }

    let getSummaryTotalAmount = function () {
        return $('.dk_package_amount_summary span.amount_real_amount')
    }

    $(document.body).on('keyup change', 'input[id^="quantity_"]', function () {
        let packageAmount = init()
        let qtyInput = $(this)
        let packageQtyInput = qtyInput.closest('.quantity').find('input[id^="package_quantity_"]')
        let summaryQuantity = getSummaryQuantity()
        let summaryTotalAmount = getSummaryTotalAmount()
        if (qtyInput.val() === "") {
            qtyInput.val(0)
            packageQtyInput.val(0)
            summaryQuantity.text(0)
            summaryTotalAmount.text(0)
        } else {
            let quantity = parseFloat(qtyInput.val())
            let totalAmount = (quantity * packageAmount).toFixed(3)
            packageQtyInput.val(totalAmount)
            summaryQuantity.text(quantity)
            summaryTotalAmount.text(totalAmount)
        }
    })
    $(document.body).on('keyup change', 'input[id^="package_quantity_"]', function () {
        let packageAmount = init()
        let packageQtyInput = $(this)
        let qtyInput = packageQtyInput.closest('.quantity').find('input[id^="quantity_"]')
        let summaryQuantity = getSummaryQuantity()
        let summaryTotalAmount = getSummaryTotalAmount()
        if (packageQtyInput.val() === "") {
            qtyInput.val(0)
            packageQtyInput.val(0)
            summaryQuantity.text(0)
            summaryTotalAmount.text(0)
        } else {
            let quantity = Math.ceil(packageQtyInput.val() / packageAmount)
            qtyInput.val(quantity)
            summaryQuantity.text(quantity)
            summaryTotalAmount.text((quantity * packageAmount).toFixed(3))
        }
    })
});
