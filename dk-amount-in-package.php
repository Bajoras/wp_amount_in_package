<?php

/**
 * @link              d.kasperavicius@gmail.com
 * @since             1.0.1
 * @package           Dk_Amount_In_Package
 * @wordpress-plugin
 * Plugin Name:       Package amount calculator
 * Description:       Calculate quantity by the amount in the package
 * Plugin URI:        d.kasperavicius@gmail.com
 * Version:           1.0.0
 * Author:            Dainius Kasperavicius
 * Author URI:        d.kasperavicius@gmail.com
 * Text Domain:       dk-amount-in-package
 */

if (!defined('WPINC')) {
    die;
}

class DkAmountInPackage
{

    private $version = '1.0.1';

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
            [$this, 'dk_package_amount_checkout_create_order_line_item']
        );
        add_filter(
            'woocommerce_order_item_display_meta_key',
            [$this, 'dk_package_amount_order_item_display_meta_key']
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
                'id' => '_amount_in_package',
                'value' => max('1', $product_object->get_meta('_amount_in_package', 'edit')),
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
                'id' => '_amount_in_package_unit',
                'value' => $product_object->get_meta('_amount_in_package_unit', 'edit'),
                'label' => __('Unit', 'dk-amount-in-package'),
                'desc_tip' => true,
                'description' => __('Package amount unit.', 'dk-amount-in-package'),
            ]
        );

        echo '</div>';
    }

    public function dk_package_amount_admin_process_product_object($product): void
    {
        $product->update_meta_data(
            '_amount_in_package',
            isset($_POST['_amount_in_package'])
                ? max('1', wc_format_decimal($_POST['_amount_in_package'], 2, true))
                : '1'
        );
        $product->update_meta_data(
            '_amount_in_package_unit',
            isset($_POST['_amount_in_package_unit']) ? sanitize_text_field($_POST['_amount_in_package_unit']) : ''
        );
        $product->update_meta_data(
            '_manage_amount_in_package',
            isset($_POST['_manage_amount_in_package']) && $_POST['_manage_amount_in_package'] === 'yes' ? 'yes' : 'no'
        );
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

    public function dk_package_amount_locate_template($template, $template_name, $template_path): string
    {
        global $woocommerce;
        $_template = $template;
        if (!$template_path) {
            $template_path = $woocommerce->template_url;
        }

        $plugin_path = untrailingslashit(plugin_dir_path(__FILE__)).'/template/woocommerce/';

        $template = locate_template(
            [
                $template_path.$template_name,
                $template_name
            ]
        );

        if (!$template && file_exists($plugin_path.$template_name)) {
            $template = $plugin_path.$template_name;
        }

        if (!$template) {
            $template = $_template;
        }

        return $template;
    }

    public function dk_package_amount_quantity_input_args($args, $product): array
    {
        $packageAmount = $product->get_meta('_amount_in_package', 'edit');
        if ($packageAmount === '') {
            $packageAmount = $args['input_value'];
        }
        $defaults = [
            'package_amount_enable' => $this->isManageAmountEnable($product->get_id()),
            'package_amount_input_id' => uniqid('package_amount_quantity_'),
            'package_input_id' => uniqid('package_quantity_'),
            'package_input_name' => 'package_quantity',
            'package_amount' => $packageAmount,
            'package_amount_value' => $args['input_value'] * $packageAmount,
            'package_max_value' => $args['max_value'] > 0 ? $args['max_value'] * $packageAmount : '',
            'package_min_value' => $args['min_value'],
            'package_classes' => ['input-text', 'qty', 'text'],
            'package_step' => 'any',
            'package_inputmode' => 'numeric',
            'package_amount_unit' => $product->get_meta('_amount_in_package_unit', 'edit')
        ];

        return wp_parse_args($args, $defaults);
    }

    public function dk_package_amount_inventory_settings($settings): array
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

    public function dk_package_amount_checkout_create_order_line_item($item): void
    {
        if ($this->isManageAmountEnable($item->get_product_id())) {
            $packageAmount = max(1, get_post_meta($item->get_product_id(), '_amount_in_package', true));
            $packageUnit = get_post_meta($item->get_product_id(), '_amount_in_package_unit', true);
            $item->update_meta_data('_amount_in_package', wc_format_decimal($item->get_quantity() * $packageAmount), 2,
                true);
            $item->update_meta_data('_amount_in_package_unit', $packageUnit);
        }
    }

    public function dk_package_amount_order_item_display_meta_key($display_key): string
    {
        if ($display_key === '_amount_in_package') {
            return __('Amount in package', 'dk-amount-in-package');
        } else {
            return $display_key;
        }
    }

    public function dk_package_amount_order_item_display_meta_value($display_value, $meta, $item): string
    {
        if ($meta->key === '_amount_in_package') {
            return sprintf('%s %s',$display_value, $item->get_meta('_amount_in_package_unit'));
        } else {
            return $display_value;
        }
    }

    public function dk_package_amount_hidden_order_itemmeta($items): array
    {
        $items[] = '_amount_in_package_unit';

        return $items;
    }

    public function dk_package_amount_email_order_item_quantity($qty_display, $item): string
    {
        $packageAmount = $item->get_meta('_amount_in_package', true);
        if ($packageAmount) {
            $packageUnit = $item->get_meta('_amount_in_package_unit', true);
            $separator = $this->getQuantityPackageAmountSeparator();
            return $qty_display." $separator $packageAmount $packageUnit";
        }

        return $qty_display;
    }
}

$package = new DkAmountInPackage();
$package->init();
