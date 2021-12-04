jQuery(function ($) {
    let calculate = function (event) {
        let packageAmount = parseFloat($(event.target.parentElement).find('input[id^="package_amount_quantity_"]').val())
        if (isNaN(packageAmount) || packageAmount === 0) {
            packageAmount = 1
        }
        $(event.target.parentElement).find('.dk_package_amount_summary span.amount').text(packageAmount.toFixed(3))
        let packageQtyInput = $(event.target.parentElement).find('input[id^="package_quantity_"]')
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
    }

    $(document.body).on('calculate', function (event) {
        calculate(event)
    })

    $(document.body).on('keyup change', 'input[id^="package_quantity_"]', function (event) {
        $(this).trigger('calculate')
    })

    $(document.body).on('found_variation', function (event, variation) {
        let form = $(event.target)
        form.find('input[name^="_amount_in_package"]').val(variation.variation_amount_in_package)
        let qty_val = parseFloat(form.find('input[name^="_requested_amount_in_package"]').val())
        if (isNaN(qty_val)) {
            qty_val = variation.min_qty;
        } else {
            qty_val = qty_val > parseFloat(variation.max_qty) ? variation.max_qty : qty_val
            qty_val = qty_val < parseFloat(variation.min_qty) ? variation.min_qty : qty_val
        }
        form.find('input[name^="_requested_amount_in_package"]').val(qty_val)
        form.find('span.after_amount_in_package').val(variation.variation_amount_in_package_unit)
        form.find('span.package_amount_unit').each(function () {
            $(this).text(variation.variation_amount_in_package_unit)
        })
        form.trigger('calculate')
    })
});
