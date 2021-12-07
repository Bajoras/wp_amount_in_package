jQuery(function ($) {
    let calculate = function (event) {
        let packageAmount = parseFloat($(event.target.parentElement).find('input[id^="package_amount_quantity_"]').val()),
            packageQtyInput = $(event.target.parentElement).find('input[id^="package_quantity_"]'),
            quantityWrapper = packageQtyInput.closest('.quantity'),
            qtyInput = quantityWrapper.find('input[id^="quantity_"]'),
            summaryQuantity = quantityWrapper.find('.dk_package_amount_summary span.quantity_wrapper > span.amount_quantity'),
            summaryTotalAmount = quantityWrapper.find('.dk_package_amount_summary span.total_real_wrapper > span.amount_real_amount'),
            summaryWeightWrapper = quantityWrapper.find('.dk_package_amount_summary span.weight_wrapper'),
            summaryWeight = summaryWeightWrapper.find('span.weight'),
            summarySingleWeight = summaryWeightWrapper.find('span.single_weight'),
            summarySingleWeightVal = parseFloat(summarySingleWeight.text() || 0),
            summaryPriceWrapper = quantityWrapper.find('.dk_package_amount_summary span.price_wrapper'),
            summaryPrice = summaryPriceWrapper.find('span.price'),
            summarySinglePrice = summaryPriceWrapper.find('span.single_price'),
            summarySinglePriceVal = parseFloat(summarySinglePrice.text() || 0)

        if (isNaN(packageAmount) || packageAmount === 0) {
            packageAmount = 1
        }
        if (isNaN(summarySingleWeightVal) || summarySingleWeightVal === 0) {
            summarySingleWeightVal = 0;
            summaryWeightWrapper.hide()
        } else {
            summaryWeightWrapper.show()
        }
        if (isNaN(summarySinglePriceVal) || summarySinglePriceVal === 0) {
            summarySinglePriceVal = 0;
            summaryPriceWrapper.hide()
        } else {
            summaryPriceWrapper.show()
        }

        $(event.target.parentElement).find('.dk_package_amount_summary span.amount').text(packageAmount.toFixed(3))

        if (packageQtyInput.val() === "") {
            qtyInput.val(1)
            summaryQuantity.text(1)
            summaryTotalAmount.text(packageAmount)
        } else {
            let quantity = Math.ceil(packageQtyInput.val() / packageAmount)
            qtyInput.val(quantity)
            summaryQuantity.text(quantity)
            summaryTotalAmount.text((quantity * packageAmount).toFixed(3))
            summarySingleWeight.text(summarySingleWeightVal)
            summaryWeight.text(quantity * summarySingleWeightVal)
            summaryPrice.text(formatPrice(quantity * summarySinglePriceVal))
        }
    }

    function formatPrice(value) {
        return value
            .toFixed(_price_settings.woocommerce_price_num_decimals)
            .replace('.', _price_settings.woocommerce_price_decimal_sep)
            .replace(new RegExp("\\B(?=(\\d{3})+(?!\\d))", 'g'), '$&' + _price_settings.woocommerce_price_thousand_sep)
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
        form.find('.dk_package_amount_summary span.package_amount_unit').each(function () {
            $(this).text(variation.variation_amount_in_package_unit)
        })
        form.find('span.weight_wrapper > span.single_weight').text(variation.weight)
        form.find('span.price_wrapper > span.single_price').text(variation.display_price)

        form.trigger('calculate')
    })
});
