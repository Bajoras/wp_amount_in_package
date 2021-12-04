<?php

/**
 * @link              d.kasperavicius@gmail.com
 * @package           Dk_Amount_In_Package
 * @wordpress-plugin
 * Plugin Name:       Package amount calculator
 * Description:       Calculate quantity by the amount in the package
 * Plugin URI:        d.kasperavicius@gmail.com
 * Version:           1.3.2
 * Author:            Dainius Kasperavicius
 * Author URI:        d.kasperavicius@gmail.com
 * Text Domain:       dk-amount-in-package
 */

if (!defined('WPINC')) {
    die;
}

class DkAmountInPackage
{

    private $version = '1.3.2';
    private $requestedAmountInPackage = '_requested_amount_in_package';
    private $amountInPackage = '_amount_in_package';
    private $totalAmountInPackage = '_total_amount_in_package';
    private $amountInPackageUnit = '_amount_in_package_unit';
    private $metricText = '_metrics_text';
    private $packageText = '_package_text';
    private $totalAmountText = '_total_amount_text';
    private $manageAmountInPackage = '_manage_amount_in_package';
    private $variablePrefix = 'variable';

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
            ['jquery',],
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
            3
        );
        add_action(
            'woocommerce_cart_item_set_quantity',
            [$this, 'dk_package_amount_cart_item_set_quantity'],
            10,
            3
        );
        add_filter('woocommerce_available_variation', [$this, 'dk_package_amount_available_variation'], 10, 3);
        add_action('woocommerce_variation_options', function (int $loop, array $variationData, WP_Post $variation) {
            $parentManageAmountInPackage = $this->isManageAmountEnable(wc_get_product($variation->ID)->get_parent_id());
            if ($this->isEnabled() && $parentManageAmountInPackage) {
                $tip = esc_attr__('Enable package amount management at product variant level', 'dk-amount-in-package');
                echo "<label class='tips' data-tip='$tip'>";
                esc_html_e('Manage package amount?', 'dk-amount-in-package');

                $loop = esc_attr($loop);
                $disabled = disabled(
                    true,
                    true,
                    false
                );
                $checked = checked(
                    true,
                    true,
                    false
                );
                echo "<input value='yes' type='checkbox' class='checkbox variable$this->manageAmountInPackage' name='".$this->variablePrefix.$this->manageAmountInPackage.'['.$loop.']\''.$checked." ".$disabled."/>";
                echo "</label>";
            }
        }, 10, 3);

        add_action('woocommerce_product_after_variable_attributes',
            function (int $loop, array $variationData, WP_Post $variation) {
                if ($this->isEnabled()) {
                    $variationObject = wc_get_product_object('variation', $variation->ID);
                    echo "<div class='show_if_variation$this->manageAmountInPackage' style='display: none;'>";
                    woocommerce_wp_text_input([
                        'id' => $this->variablePrefix.$this->amountInPackage.'_'.$loop,
                        'name' => $this->variablePrefix.$this->amountInPackage.'['.$loop.']',
                        'value' => $this->formatDecimal(
                            max('1', $variationObject->get_meta($this->amountInPackage, true, 'edit'))
                        ),
                        'label' => __('Amount', 'dk-amount-in-package'),
                        'description' => __('Amount in one package.', 'dk-amount-in-package'),
                        'type' => 'number',
                        'desc_tip' => true,
                        'custom_attributes' => [
                            'step' => 'any',
                        ],
                        'data_type' => 'decimal'
                    ]);
                    woocommerce_wp_text_input([
                        'id' => $this->variablePrefix.$this->amountInPackageUnit.'_'.$loop,
                        'name' => $this->variablePrefix.$this->amountInPackageUnit.'['.$loop.']',
                        'value' => $variationObject->get_meta($this->amountInPackageUnit, true, 'edit'),
                        'label' => __('Unit', 'dk-amount-in-package'),
                        'description' => __('Package amount unit.', 'dk-amount-in-package'),
                        'desc_tip' => true
                    ]);
                    echo '</div>';
                }
            }, 10, 3);

        //save variants
        add_action('woocommerce_admin_process_variation_object', function (WC_Product_Variation $variation, int $loop) {
            $parentManageAmountInPackage = $this->isManageAmountEnable($variation->get_parent_id());
            if ($parentManageAmountInPackage) {
                if (($value = $this->getPostValue($this->variablePrefix.$this->amountInPackage, $loop)) !== null) {
                    $variation->update_meta_data($this->amountInPackage, $this->formatDecimal(max('1', $value)));
                }
                if (($value = $this->getPostValue($this->variablePrefix.$this->amountInPackageUnit, $loop)) !== null) {
                    $variation->update_meta_data($this->amountInPackageUnit, $value);
                }
                $parentMetas = [
                    $this->metricText,
                    $this->packageText,
                    $this->totalAmountText,
                ];
                $parentProduct = wc_get_product($variation->get_parent_id());
                foreach ($parentMetas as $meta) {
                    $variation->update_meta_data($meta, $parentProduct->get_meta($meta));
                }
            }
        }, 10, 2);
    }

    public function dk_package_amount_available_variation(
        array $variations,
        WC_Product_Variable $parentProduct,
        WC_Product_Variation $variant
    ): array {
        $variations['max_qty'] = 0 < $variant->get_max_purchase_quantity() ?
            $this->formatDecimal(
                $variant->get_max_purchase_quantity() * $variant->get_meta($this->amountInPackage, true, 'edit')
            ) :
            '';
        $variations['variation'.$this->amountInPackage] =
            $this->formatDecimal($variant->get_meta($this->amountInPackage, true, 'edit'));
        $variations['variation'.$this->amountInPackageUnit] =
            $variant->get_meta($this->amountInPackageUnit, true, 'edit');

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
        woocommerce_wp_checkbox([
            'id' => $this->manageAmountInPackage,
            'value' => $this->isManageAmountEnable($product_object) ? 'yes' : 'no',
            'wrapper_class' => 'show_if_simple show_if_variable',
            'label' => __('Manage package amount?', 'dk-amount-in-package'),
            'description' => __('Enable package amount management at product level', 'dk-amount-in-package')
        ]);
        echo '<div class="amount_in_package_fields show_if_simple show_if_variable">';

        woocommerce_wp_text_input([
            'id' => $this->amountInPackage,
            'wrapper_class' => 'hide_if_variable',
            'value' => $this->formatDecimal(max('1', $product_object->get_meta($this->amountInPackage, true, 'edit'))),
            'label' => __('Amount', 'dk-amount-in-package'),
            'desc_tip' => true,
            'description' => __('Amount in one package.', 'dk-amount-in-package'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => 'any',
            ],
            'data_type' => 'decimal'
        ]);

        woocommerce_wp_text_input([
            'id' => $this->amountInPackageUnit,
            'wrapper_class' => 'hide_if_variable',
            'value' => $product_object->get_meta($this->amountInPackageUnit, true, 'edit'),
            'label' => __('Unit', 'dk-amount-in-package'),
            'desc_tip' => true,
            'description' => __('Package amount unit.', 'dk-amount-in-package'),
        ]);

        woocommerce_wp_text_input([
            'id' => $this->metricText,
            'value' => $product_object->get_meta($this->metricText, true, 'edit') === '' ?
                __('Cubic: ', 'dk-amount-in-package') :
                $product_object->get_meta($this->metricText, true, 'edit'),
            'label' => __('Metric label', 'dk-amount-in-package')
        ]);

        woocommerce_wp_text_input([
            'id' => $this->packageText,
            'value' => $product_object->get_meta($this->packageText, true, 'edit') === '' ?
                __('Carton: ', 'dk-amount-in-package') :
                $product_object->get_meta($this->packageText, true, 'edit'),
            'label' => __('Package label', 'dk-amount-in-package'),
        ]);

        woocommerce_wp_text_input([
            'id' => $this->totalAmountText,
            'value' => $product_object->get_meta($this->totalAmountText, true, 'edit') === '' ?
                __('Total amount in metrics: ', 'dk-amount-in-package') :
                $product_object->get_meta($this->totalAmountText, true, 'edit'),
            'label' => __('Total amount in metrics label', 'dk-amount-in-package')
        ]);

        echo '</div>';
    }

    public function dk_package_amount_admin_process_product_object(WC_Product $product): void
    {
        $value = $this->getPostValue($this->manageAmountInPackage) === 'yes' ? 'yes' : 'no';
        $product->update_meta_data($this->manageAmountInPackage, $value);
        if ($value === 'yes') {
            if ($product->is_type('simple')) {
                if (($value = $this->getPostValue($this->amountInPackage)) !== null) {
                    $product->update_meta_data(
                        $this->amountInPackage,
                        $this->formatDecimal(max('1', $value))
                    );
                }
                if (($value = $this->getPostValue($this->amountInPackageUnit)) !== null) {
                    $product->update_meta_data(
                        $this->amountInPackageUnit,
                        $value
                    );
                }
            }

            $metas = [
                $this->metricText,
                $this->packageText,
                $this->totalAmountText,
            ];
            foreach ($metas as $meta) {
                if (($value = $this->getPostValue($meta)) !== null) {
                    $product->update_meta_data($meta, $value);
                    if ($product->is_type('variable')) {
                        $childrens = $product->get_children();
                        foreach ($childrens as $children) {
                            update_post_meta($children, $meta, $value);
                        }
                    }
                }
            }
        }
    }

    private function getPostValue(string $key, int $index = 0): ?string
    {
        if (isset($_POST[$key])) {
            if (is_array($_POST[$key]) && isset($_POST[$key][$index])) {
                return sanitize_text_field($_POST[$key][$index]);
            } else {
                return sanitize_text_field($_POST[$key]);
            }
        } else {
            return null;
        }
    }

    public function dk_package_amount_admin_enqueue_scripts(): void
    {
        $screen = get_current_screen();
        $screen_id = $screen ? $screen->id : '';
        if (in_array($screen_id, ['product', 'edit-product'])) {
            wp_register_script(
                'dk-amount-in-package-admin',
                plugin_dir_url(__FILE__).'js/admin.min.js',
                ['jquery', 'wc-admin-product-meta-boxes', 'wc-admin-variation-meta-boxes'],
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
        $parentProduct = $product;
        if ($product->is_type('variation')) {
            $parentProduct = wc_get_product($product->get_parent_id());
        }
        $requestedAmount = null;
        if (is_cart()) {
            preg_match("/^cart\[(.*)\]\[qty\]/", $args['input_name'], $key);
            if (isset($key[1]) && !empty($key[1])) {
                $rr = WC()->cart->get_cart_contents()[$key[1]];
                $requestedAmount = $rr[$this->requestedAmountInPackage];
            }
        }
        $amountInPackage = $product->get_meta($this->amountInPackage, true, 'edit');
        if ($amountInPackage === '') {
            $amountInPackage = $args['input_value'];
        }
        if ($requestedAmount === null) {
            $requestedAmount = $this->getRequestedPackageAmount();
        }

        if ($requestedAmount === null) {
            $requestedAmount = $args['input_value'];
        }
        $defaults = [
            'package_amount_enable' => $this->isManageAmountEnable($parentProduct),
            'package_amount_input_id' => uniqid('package_amount_quantity_'),
            'package_input_id' => uniqid('package_quantity_'),
            'package_amount_name' => is_cart() ? str_replace('[qty]', '['.$this->amountInPackage.']',
                $args['input_name']) : $this->amountInPackage,
            'package_input_name' => is_cart() ? str_replace('[qty]', '['.$this->requestedAmountInPackage.']',
                $args['input_name']) : $this->requestedAmountInPackage,
            'package_amount' => $this->formatDecimal($amountInPackage),
            'package_requested_amount' => $this->formatDecimal($requestedAmount, true),
            'package_max_value' => $args['max_value'] > 0 ? $args['max_value'] * $amountInPackage : '',
            'package_min_value' => $args['min_value'],
            'package_classes' => ['input-text', 'text', 'qty'],
            'package_step' => 'any',
            'package_inputmode' => 'numeric',
            'package_amount_unit' => $product->get_meta($this->amountInPackageUnit, true, 'edit'),
            'package_real_amount' => $this->formatDecimal($args['input_value'] * $amountInPackage),
            'metric_text' => $parentProduct->get_meta($this->metricText),
            'package_text' => $parentProduct->get_meta($this->packageText),
            'total_amount_text' => $parentProduct->get_meta($this->totalAmountText)
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

    /**
     * @param int|\WC_Product $product
     * @return bool
     */
    private function isManageAmountEnable($product): bool
    {
        if (is_int($product)) {
            return get_post_meta($product, $this->manageAmountInPackage, true) === 'yes';
        }

        return $product->get_meta($this->manageAmountInPackage) === 'yes';
    }

    public function dk_package_amount_checkout_create_order_line_item(
        WC_Order_Item_Product $item,
        string $cartItemKey,
        array $values
    ): void {
        if ($this->isManageAmountEnable($item->get_product_id())) {
            if ($item->get_variation_id() > 0) {
                $packageAmount = max('1', get_post_meta($item->get_variation_id(), $this->amountInPackage, true));
                $packageUnit = get_post_meta($item->get_variation_id(), $this->amountInPackageUnit, true);
            } else {
                $packageAmount = max('1', get_post_meta($item->get_product_id(), $this->amountInPackage, true));
                $packageUnit = get_post_meta($item->get_product_id(), $this->amountInPackageUnit, true);
            }
            $item->update_meta_data(
                $this->amountInPackage,
                $this->formatDecimal($packageAmount)
            );
            $item->update_meta_data(
                $this->totalAmountInPackage,
                $this->formatDecimal($item->get_quantity() * $packageAmount)
            );
            $item->update_meta_data($this->amountInPackageUnit, $packageUnit);
            $item->update_meta_data(
                $this->requestedAmountInPackage,
                $this->formatDecimal($values[$this->requestedAmountInPackage])
            );
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
        switch ($displayKey) {
            case $this->amountInPackage:
                return rtrim($item->get_meta($this->metricText), ':');
            case $this->totalAmountInPackage:
                return rtrim($item->get_meta($this->totalAmountText), ':');
            case $this->requestedAmountInPackage:
                return __('Requested amount', 'dk-amount-in-package');
            default:
                return $displayKey;
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
        $displayMetas = [$this->amountInPackage, $this->totalAmountInPackage, $this->requestedAmountInPackage];
        if (in_array($meta->key, $displayMetas, true)) {
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
        $packageAmount = $this->formatDecimal($item->get_meta($this->amountInPackage));
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
    private function formatDecimal($value, bool $trimZeros = false): string
    {
        return wc_format_decimal($value, 3, $trimZeros);
    }

    public function dk_package_amount_add_cart_item_data(array $cartItemData, int $productId, int $variationId): array
    {
        $product = wc_get_product($productId);
        if ($variationId > 0) {
            $cartItemData[$this->amountInPackage] = $this->formatDecimal(
                get_post_meta($variationId, $this->amountInPackage, true)
            );
            $cartItemData[$this->amountInPackageUnit] = $this->formatDecimal(
                get_post_meta($variationId, $this->amountInPackageUnit, true)
            );
        } else {
            $cartItemData[$this->amountInPackage] = $this->formatDecimal($product->get_meta($this->amountInPackage));
            $cartItemData[$this->amountInPackageUnit] = $this->formatDecimal($product->get_meta($this->amountInPackageUnit));
        }
        $cartItemData[$this->requestedAmountInPackage] = $this->getRequestedPackageAmount();
        $cartItemData[$this->metricText] = $product->get_meta($this->metricText);
        $cartItemData[$this->packageText] = $product->get_meta($this->packageText);
        $cartItemData[$this->totalAmountText] = $product->get_meta($this->totalAmountText,);

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
