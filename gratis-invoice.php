<?php
/**
 * Plugin Name: Gratis Invoice
 * Plugin URI: https://github.com/juslintek/gratis-invoice
 * Description: Invoicing and estimates with printable PDF-ready output and multi-currency support. Replaces Sprout Invoices ($199/yr).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-invoice
 */

defined('ABSPATH') || exit;

define('GRATIS_INVOICE_VERSION', '1.0.0');
define('GRATIS_INVOICE_FILE', __FILE__);
define('GRATIS_INVOICE_DIR', plugin_dir_path(__FILE__));
define('GRATIS_INVOICE_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('init', [\Gratis\Invoice\InvoicePostType::class, 'register']);

// Invoice editing meta boxes.
add_action('add_meta_boxes', [\Gratis\Invoice\MetaBox::class, 'register']);
add_action('save_post_gratis_invoice', [\Gratis\Invoice\MetaBox::class, 'save']);

// Admin settings + printable invoice view.
add_action('admin_menu', [\Gratis\Invoice\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\Invoice\Settings::class, 'register_settings']);
\Gratis\Invoice\Settings::register_actions();

add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-invoice', \Gratis\Invoice\CLI\InvoiceCommand::class);
    }
});
