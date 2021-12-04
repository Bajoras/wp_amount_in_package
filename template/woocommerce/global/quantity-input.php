<?php
/**
 * Product quantity inputs
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/global/quantity-input.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.0.0
 */

defined('ABSPATH') || exit;
if ($max_value && $min_value === $max_value) {
    ?>
    <div class="quantity hidden">
        <input type="hidden" id="<?php echo esc_attr($input_id); ?>" class="qty"
               name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($min_value); ?>"/>
    </div>
    <?php
} else {
    /* translators: %s: Quantity. */
    $label = !empty($args['product_name']) ? sprintf(esc_html__('%s quantity', 'woocommerce'),
        wp_strip_all_tags($args['product_name'])) : esc_html__('Quantity', 'woocommerce');
    ?>
    <div class="quantity">
        <?php do_action('woocommerce_before_quantity_input_field'); ?>
        <label class="screen-reader-text<?php if ($package_amount_enable) {
            echo ' hidden';
        } ?>" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_attr($label); ?></label>
        <?php
        if ($package_amount_enable) {
            $package_label = !empty($args['product_name'])
                ? sprintf(
                    esc_html__('%s amount in package', 'dk-amount-in-package'),
                    wp_strip_all_tags($args['product_name'])
                ) : esc_html__('Amount in package', 'dk-amount-in-package');
            ?>
            <label class="screen-reader-text"
                   for="<?php echo esc_attr($package_input_id); ?>"><?php echo esc_attr($package_label); ?></label>
            <input type="hidden" id="<?php echo esc_attr($package_amount_input_id); ?>"
                   name="<?php echo esc_attr($package_amount_name); ?>"
                   value="<?php echo esc_attr($package_amount); ?>"/>
            <input
                    type="number"
                    id="<?php echo esc_attr($package_input_id); ?>"
                    class="<?php echo esc_attr(join(' ', (array)$package_classes)); ?>"
                    step="<?php echo esc_attr($package_step); ?>"
                    min="<?php echo esc_attr($package_min_value); ?>"
                    max="<?php echo esc_attr($package_max_value); ?>"
                    name="<?php echo esc_attr($package_input_name); ?>"
                    value="<?php echo esc_attr($package_requested_amount); ?>"
                    title="<?php echo esc_attr_x(
                        'Package amount',
                        'Product package amount input tooltip',
                        'dk-amount-in-package'
                    ); ?>"
                    size="4"
                    placeholder="<?php echo esc_attr($package_placeholder); ?>"
                    inputmode="<?php echo esc_attr($package_inputmode); ?>"/>
            <span class="after_amount_in_package package_amount_unit"><?php echo esc_attr($package_amount_unit) ?></span>
            <div class="dk_package_amount_summary">
                <?php if (!is_cart()) { ?>
                    <span class="amount_wrapper">
                        <?php echo $metric_text; ?>
                        <span class="amount"><?php echo esc_attr($package_amount) ?></span>
                        <span class="package_amount_unit"><?php echo esc_attr($package_amount_unit) ?></span>
                    </span><br/>
                <?php } ?>
                <span class="quantity_wrapper">
                    <?php echo $package_text; ?>
                    <span class="amount_quantity"><?php echo esc_attr($input_value); ?></span>
                </span><br/>
                <span class="total_real_wrapper">
                    <?php echo $total_amount_text; ?>
                    <span class="amount_real_amount"><?php echo esc_attr($package_real_amount) ?></span>
                    <span class="package_amount_unit"><?php echo esc_attr($package_amount_unit) ?></span>
                </span><br/>
                <span class="weight_wrapper" style="display: none">
                    <?php _e('Weight', 'woocommerce'); ?>:
                    <span class="weight"></span>
                    <span class="single_weight" style="display: none"></span>
                    <span><?php echo esc_html(get_option('woocommerce_weight_unit')); ?></span>
                </span>
            </div>
            <?php
        }
        ?>
        <input
                type="<?php if ($package_amount_enable) {
                    echo 'hidden';
                } else {
                    echo 'number';
                } ?>"
                id="<?php echo esc_attr($input_id); ?>"
                class="<?php echo esc_attr(join(' ', (array)$classes)); ?>"
                step="<?php echo esc_attr($step); ?>"
                min="<?php echo esc_attr($min_value); ?>"
                max="<?php echo esc_attr(0 < $max_value ? $max_value : ''); ?>"
                name="<?php echo esc_attr($input_name); ?>"
                value="<?php echo esc_attr($input_value); ?>"
                title="<?php echo esc_attr_x('Qty', 'Product quantity input tooltip', 'woocommerce'); ?>"
                size="4"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                inputmode="<?php echo esc_attr($inputmode); ?>"/>
    </div>
    <?php do_action('woocommerce_after_quantity_input_field');
}
