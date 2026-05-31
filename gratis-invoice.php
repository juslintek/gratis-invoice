<?php
/**
 * Plugin Name: Gratis Invoice
 * Description: Invoicing + estimates + PDF generation. Replaces Sprout Invoices ($199/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_action('init', [\Gratis\Invoice\InvoicePostType::class, 'register']);
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('gratis-invoice', \Gratis\Invoice\CLI\InvoiceCommand::class);
});
