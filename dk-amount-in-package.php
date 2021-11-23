<?php

/**
 * @link              d.kasperavicius@gmail.com
 * @package           Dk_Amount_In_Package
 * @wordpress-plugin
 * Plugin Name:       Package amount calculator
 * Description:       Calculate quantity by the amount in the package
 * Plugin URI:        d.kasperavicius@gmail.com
 * Version:           1.2.5
 * Author:            Dainius Kasperavicius
 * Author URI:        d.kasperavicius@gmail.com
 * Text Domain:       dk-amount-in-package
 */

if (!defined('WPINC')) {
    die;
}

class DkAmountInPackage
{

    private $version = '1.2.5';
    private $requestedAmountInPackage = '_requested_amount_in_package';
    private $amountInPackage = '_amount_in_package';
    private $amountInPackageUnit = '_amount_in_package_unit';
    private $metricText = '_metrics_text';
    private $packageText = '_package_text';
    private $totalAmountText = '_total_amount_text';

    public function __construct()
    {
        add_filter('woocommerce_inventory_settings', [$this, 'dk_package_amount_inventory_settings']);
    }

    private function isEnabled(): bool
    {
        return 'yes' === get_option('dk_manage_amount_in_package');
    }

    public function init(): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        add_action(
            'woocommerce_product_options_inventory_product_data',
            [$this, 'dk_package_amount_product_options_inventory_product_data']
        );
        add_action(
            'woocommerce_admin_process_product_object',
            [$this, 'dk_package_amount_admin_process_product_object']
        );
        add_action('admin_enqueue_scripts', [$this, 'dk_package_amount_admin_enqueue_scripts']);
        wp_enqueue_script(
            'dk-amount-in-package-frontend',
            plugin_dir_url(__FILE__).'js/frontend.min.js',
            ['jquery'],
            $this->version
        );
        add_filter('woocommerce_quantity_input_args', [$this, 'dk_package_amount_quantity_input_args'], 10, 2);
        add_filter('woocommerce_locate_template', [$this, 'dk_package_amount_locate_template'], 1, 3);
        add_action(
            'woocommerce_checkout_create_order_line_item',
            [$this, 'dk_package_amount_checkout_create_order_line_item'],
            10,
            3
        );
        add_filter(
            'woocommerce_order_item_display_meta_key',
            [$this, 'dk_package_amount_order_item_display_meta_key'],
            10,
            3
        );
        add_filter(
            'woocommerce_order_item_display_meta_value',
            [$this, 'dk_package_amount_order_item_display_meta_value'],
            10,
            3
        );
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'dk_package_amount_hidden_order_itemmeta']);
        add_filter(
            'woocommerce_email_order_item_quantity',
            [$this, 'dk_package_amount_email_order_item_quantity'],
            10,
            2
        );
        add_action(
            'dk_package_amount_before_package_amount_input_field',
            [$this, 'dk_package_amount_before_package_amount_input_field']
        );
        add_filter(
            'woocommerce_add_cart_item_data',
            [$this, 'dk_package_amount_add_cart_item_data'],
            10,
            2
        );
        add_action(
            'woocommerce_cart_item_set_quantity',
            [$this, 'dk_package_amount_cart_item_set_quantity'],
            10,
            3
        );
        add_filter('woocommerce_available_variation', [$this, 'dk_package_amount_available_variation'], 10, 3);
    }

    public function dk_package_amount_available_variation(
        array $variations,
        WC_Product_Variable $parentProduct,
        WC_Product_Variation $variant
    ): array {
        $variations['max_qty'] = 0 < $variant->get_max_purchase_quantity() ?
            $variant->get_max_purchase_quantity() * $parentProduct->get_meta($this->amountInPackage, 'edit') : '';
        $variations[$this->amountInPackage] = $parentProduct->get_meta($this->amountInPackage, 'edit');
        $variations[$this->amountInPackageUnit] = $parentProduct->get_meta($this->amountInPackageUnit, 'edit');

        return $variations;
    }

    public function dk_package_amount_cart_item_set_quantity(string $cartItemKey, int $quantity, WC_Cart $cart)
    {
        if (isset($_POST['cart'])) {
            $cart->cart_contents[$cartItemKey][$this->requestedAmountInPackage] =
                $this->formatDecimal(wp_unslash($_POST['cart'][$cartItemKey][$this->requestedAmountInPackage]));
        }
    }

    private function getQuantityPackageAmountSeparator(): string
    {
        return apply_filters('dk_package_amount_quantity_package_amount_separator', '=');
    }

    public function dk_package_amount_before_package_amount_input_field(): void
    {
        $separator = $this->getQuantityPackageAmountSeparator();
        echo "<span class=\"before_package_amount\">$separator</span>";
    }

    public function dk_package_amount_product_options_inventory_product_data(): void
    {
        global $product_object;
        woocommerce_wp_checkbox(
            [
                'id' => '_manage_amount_in_package',
                'value' => $product_object->get_meta('_manage_amount_in_package', 'edit') === 'yes' ? 'yes' : 'no',
                'wrapper_class' => 'show_if_simple show_if_variable',
                'label' => __('Manage package amount?', 'dk-amount-in-package'),
                'description' => __('Enable package amount management at product level', 'dk-amount-in-package'),
            ]
        );

        echo '<div class="amount_in_package_fields">';

        woocommerce_wp_text_input(
            [
                'id' => $this->amountInPackage,
                'value' => max('1', $product_object->get_meta($this->amountInPackage, 'edit')),
                'label' => __('Amount', 'dk-amount-in-package'),
                'desc_tip' => true,
                'description' => __('Amount in one package.', 'dk-amount-in-package'),
                'type' => 'number',
                'custom_attributes' => [
                    'step' => 'any',
                ],
                'data_type' => 'decimal',
            ]
        );

        woocommerce_wp_text_input(
            [
                'id' => $this->amountInPackageUnit,
                'value' => $product_object->get_meta($this->amountInPackageUnit, 'edit'),
                'label' => __('Unit', 'dk-amount-in-package'),
                'desc_tip' => true,
                'description' => __('Package amount unit.', 'dk-amount-in-package'),
            ]
        );

        woocommerce_wp_text_input(
            [
                'id' => $this->metricText,
                'value' => $product_object->get_meta($this->metricText, 'edit') === '' ?
                    __('Cubic: ', 'dk-amount-in-package') :
                    $product_object->get_meta($this->metricText, 'edit'),
                'label' => __('Metric label', 'dk-amount-in-package')
            ]
        );

        woocommerce_wp_text_input(
            [
                'id' => $this->packageText,
                'value' => $product_object->get_meta($this->packageText, 'edit') === '' ?
                    __('Carton: ', 'dk-amount-in-package') :
                    $product_object->get_meta($this->packageText, 'edit'),
                'label' => __('Package label', 'dk-amount-in-package'),
            ]
        );

        woocommerce_wp_text_input(
            [
                'id' => $this->totalAmountText,
                'value' => $product_object->get_meta($this->totalAmountText, 'edit') === '' ?
                    __('Total amount in metrics: ', 'dk-amount-in-package') :
                    $product_object->get_meta($this->totalAmountText, 'edit'),
                'label' => __('Total amount in metrics label', 'dk-amount-in-package')
            ]
        );

        echo '</div>';
    }

    public function dk_package_amount_admin_process_product_object(WC_Product $product): void
    {
        $product->update_meta_data(
            $this->amountInPackage,
            max('1', $this->formatDecimal($this->getPostValue($this->amountInPackage)))
        );
        $product->update_meta_data(
            $this->amountInPackageUnit,
            $this->getPostValue($this->amountInPackageUnit)
        );
        $product->update_meta_data(
            '_manage_amount_in_package',
            $this->getPostValue('_manage_amount_in_package') === 'yes' ? 'yes' : 'no'
        );
        $product->update_meta_data(
            $this->metricText,
            $this->getPostValue($this->metricText)
        );
        $product->update_meta_data(
            $this->packageText,
            $this->getPostValue($this->packageText)
        );
        $product->update_meta_data(
            $this->totalAmountText,
            $this->getPostValue($this->totalAmountText)
        );
    }

    private function getPostValue(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
    }

    public function dk_package_amount_admin_enqueue_scripts(): void
    {
        $screen = get_current_screen();
        $screen_id = $screen ? $screen->id : '';
        if (in_array($screen_id, ['product', 'edit-product'])) {
            wp_register_script(
                'dk-amount-in-package-admin',
                plugin_dir_url(__FILE__).'js/admin.min.js',
                ['jquery'],
                $this->version
            );
            wp_enqueue_script('dk-amount-in-package-admin');
        }
    }

    public function dk_package_amount_locate_template(
        string $template,
        string $templateName,
        string $templatePath
    ): string {
        global $woocommerce;
        $_template = $template;
        if (!$templatePath) {
            $templatePath = $woocommerce->template_url;
        }

        $plugin_path = untrailingslashit(plugin_dir_path(__FILE__)).'/template/woocommerce/';

        $template = locate_template(
            [
                $templatePath.$templateName,
                $templateName
            ]
        );

        if (!$template && file_exists($plugin_path.$templateName)) {
            $template = $plugin_path.$templateName;
        }

        if (!$template) {
            $template = $_template;
        }

        return $template;
    }

    public function dk_package_amount_quantity_input_args(array $args, WC_Product $product): array
    {
        if ($product instanceof WC_Product_Variation) {
            $product = wc_get_product($product->get_parent_id());
        }
        $requestedAmount = null;
        if (is_cart()) {
            preg_match("/^cart\[(.*)\]\[qty\]/", $args['input_name'], $key);
            if (isset($key[1]) && !empty($key[1])) {
                $rr = WC()->cart->get_cart_contents()[$key[1]];
                $requestedAmount = $rr[$this->requestedAmountInPackage];
            }
        }
        $amountInPackage = $product->get_meta($this->amountInPackage, 'edit');
        if ($amountInPackage === '') {
            $amountInPackage = $args['input_value'];
        }
        if ($requestedAmount === null) {
            $requestedAmount = $this->getRequestedPackageAmount();
        }

        if ($requestedAmount === null) {
            $requestedAmount = $product->get_meta($this->requestedAmountInPackage) !== "" ? $product->get_meta($this->requestedAmountInPackage) : null;
        }
        if ($requestedAmount === null) {
            $requestedAmount = $args['input_value'] * $amountInPackage;
        }
        $args['classes'] = ['input-text', 'text'];
        $defaults = [
            'package_amount_enable' => $this->isManageAmountEnable($product->get_id()),
            'package_amount_input_id' => uniqid('package_amount_quantity_'),
            'package_input_id' => uniqid('package_quantity_'),
            'package_amount_name' => is_cart() ? str_replace('[qty]', '['.$this->amountInPackage.']',
                $args['input_name']) : $this->amountInPackage,
            'package_input_name' => is_cart() ? str_replace('[qty]', '['.$this->requestedAmountInPackage.']',
                $args['input_name']) : $this->requestedAmountInPackage,
            'package_amount' => $amountInPackage,
            'package_requested_amount' => $this->formatDecimal($requestedAmount),
            'package_max_value' => $args['max_value'] > 0 ? $args['max_value'] * $amountInPackage : '',
            'package_min_value' => max($args['min_value'], $amountInPackage),
            'package_classes' => ['input-text', 'text', 'qty'],
            'package_step' => 'any',
            'package_inputmode' => 'numeric',
            'package_amount_unit' => $product->get_meta($this->amountInPackageUnit, 'edit'),
            'package_real_amount' => $this->formatDecimal($args['input_value'] * $amountInPackage),
            'metric_text' => $product->get_meta($this->metricText),
            'package_text' => $product->get_meta($this->packageText),
            'total_amount_text' => $product->get_meta($this->totalAmountText)
        ];

        return wp_parse_args($args, $defaults);
    }

    public function dk_package_amount_inventory_settings(array $settings): array
    {
        $last = array_pop($settings);
        $settings[] = [
            'title' => __('Manage amount in package', 'dk-amount-in-package'),
            'desc' => __('Enable package amount management', 'dk-amount-in-package'),
            'id' => 'dk_manage_amount_in_package',
            'default' => 'no',
            'type' => 'checkbox',
        ];
        $settings[] = $last;

        return $settings;
    }

    private function isManageAmountEnable(int $id): bool
    {
        return get_post_meta($id, '_manage_amount_in_package', true) === 'yes';
    }

    public function dk_package_amount_checkout_create_order_line_item(
        WC_Order_Item_Product $item,
        string $cartItemKey,
        array $values
    ): void {
        if ($this->isManageAmountEnable($item->get_product_id())) {
            $packageAmount = max(1, get_post_meta($item->get_product_id(), $this->amountInPackage, true));
            $packageUnit = get_post_meta($item->get_product_id(), $this->amountInPackageUnit, true);
            $item->update_meta_data(
                $this->amountInPackage,
                $this->formatDecimal($item->get_quantity() * $packageAmount)
            );
            $item->update_meta_data($this->amountInPackageUnit, $packageUnit);
            $item->update_meta_data($this->requestedAmountInPackage, $values[$this->requestedAmountInPackage]);
            $item->update_meta_data($this->metricText, get_post_meta($item->get_product_id(), $this->metricText, true));
            $item->update_meta_data(
                $this->packageText,
                get_post_meta($item->get_product_id(), $this->packageText, true)
            );
            $item->update_meta_data(
                $this->totalAmountText,
                get_post_meta($item->get_product_id(), $this->totalAmountText, true)
            );
        }
    }

    public function dk_package_amount_order_item_display_meta_key(
        string $displayKey,
        WC_Meta_Data $meta,
        WC_Order_Item_Product $item
    ): string {
        if ($displayKey === $this->amountInPackage) {
            return $item->get_meta($this->totalAmountText);
        } else {
            if ($displayKey === $this->requestedAmountInPackage) {
                return __('Requested amount', 'dk-amount-in-package');
            } else {
                return $displayKey;
            }
        }
    }

    public function dk_package_amount_order_item_display_meta_value(
        string $displayValue,
        WC_Meta_Data $meta,
        WC_Order_Item_Product $item
    ): string {
        $formatValue = function (string $displayValue, WC_Order_Item_Product $item) {
            return sprintf('%s %s', $displayValue, $item->get_meta($this->amountInPackageUnit));
        };
        if ($meta->key === $this->amountInPackage || $meta->key === $this->requestedAmountInPackage) {
            return $formatValue($displayValue, $item);
        } else {
            return rtrim($displayValue, ':');
        }
    }

    public function dk_package_amount_hidden_order_itemmeta(array $items): array
    {
        return array_merge(
            $items,
            [$this->amountInPackageUnit, $this->metricText, $this->packageText, $this->totalAmountText]
        );
    }

    public function dk_package_amount_email_order_item_quantity(string $qtyDisplay, WC_Order_Item_Product $item): string
    {
        $packageAmount = $item->get_meta($this->amountInPackage);
        if ($packageAmount) {
            $packageUnit = $item->get_meta($this->amountInPackageUnit);
            $quantityLine = $item->get_meta($this->packageText).' '.$qtyDisplay.'<br/>';
            $quantityLine .= $item->get_meta($this->totalAmountText).' '.$packageAmount.' '.$packageUnit;

            return $quantityLine;
        }

        return $qtyDisplay;
    }

    /**
     * @param float|string $value
     */
    private function formatDecimal($value, int $dp = 3, bool $trimZeros = true): string
    {
        return wc_format_decimal($value, $dp, $trimZeros);
    }

    public function dk_package_amount_add_cart_item_data(array $cartItemData, int $productId): array
    {
        $product = wc_get_product($productId);
        $amountInPackage = $product->get_meta($this->amountInPackage);
        $cartItemData[$this->amountInPackage] = $amountInPackage;
        $cartItemData[$this->requestedAmountInPackage] = $this->getRequestedPackageAmount();
        $cartItemData[$this->metricText] = $product->get_meta($this->metricText);
        $cartItemData[$this->packageText] = $product->get_meta($this->packageText);
        $cartItemData[$this->totalAmountText] = $product->get_meta($this->totalAmountText);

        return $cartItemData;
    }

    private function getRequestedPackageAmount(): ?string
    {
        if (isset($_POST[$this->requestedAmountInPackage])) {
            return $this->formatDecimal(wp_unslash($_POST[$this->requestedAmountInPackage]));
        }

        return null;
    }
}

$package = new DkAmountInPackage();
$package->init();

function amount_in_package_activate()
{
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Please install and Activate WooCommerce.', 'dk-amount-in-package'),
            'Plugin dependency check',
            ['back_link' => true]
        );
    }
}

register_activation_hook(__FILE__, 'amount_in_package_activate');
