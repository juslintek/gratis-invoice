<?php declare(strict_types=1);

namespace Gratis\Invoice\CLI;

use Gratis\Invoice\InvoicePostType;

defined('ABSPATH') || exit;

/**
 * WP-CLI commands for Gratis Invoice.
 */
final class InvoiceCommand {

    /**
     * List invoices.
     *
     * [--status=<status>]
     * : Filter (draft, sent, paid, overdue).
     *
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void {
        unset($args);
        $query = ['post_type' => 'gratis_invoice', 'numberposts' => 50];
        if (! empty($assoc_args['status'])) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- intentional admin CLI filter.
            $query['meta_query'] = [['key' => '_status', 'value' => sanitize_text_field((string) $assoc_args['status'])]];
        }
        $invoices = get_posts($query);
        if (empty($invoices)) {
            \WP_CLI::log('No invoices.');
            return;
        }
        $rows = array_map(static fn($i) => [
            'ID'     => $i->ID,
            'Number' => $i->post_title,
            'Client' => get_post_meta($i->ID, '_client_name', true),
            'Total'  => get_post_meta($i->ID, '_currency', true) . ' ' . number_format((float) get_post_meta($i->ID, '_total', true), 2),
            'Status' => get_post_meta($i->ID, '_status', true),
            'Due'    => get_post_meta($i->ID, '_due_date', true),
        ], $invoices);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Number', 'Client', 'Total', 'Status', 'Due']);
    }

    /**
     * Create an invoice.
     *
     * --client=<name>
     * : Client name.
     *
     * --email=<email>
     * : Client email.
     *
     * [--items=<json>]
     * : JSON array of items.
     *
     * @when after_wp_load
     */
    public function create($args, $assoc_args): void {
        unset($args);
        $items = json_decode((string) ($assoc_args['items'] ?? '[]'), true);
        $id    = InvoicePostType::create([
            'client' => $assoc_args['client'] ?? '',
            'email'  => $assoc_args['email'] ?? '',
            'items'  => is_array($items) ? $items : [],
        ]);
        if ($id === 0) {
            \WP_CLI::error('Failed to create invoice.');
        }
        \WP_CLI::success("Invoice #{$id} created: " . get_the_title($id));
    }

    /**
     * Send an invoice to its client by email.
     *
     * <id>
     * : Invoice ID.
     *
     * @when after_wp_load
     */
    public function send($args, $assoc_args): void {
        unset($assoc_args);
        $id    = (int) $args[0];
        $email = (string) get_post_meta($id, '_client_email', true);
        if ($email === '') {
            \WP_CLI::error('No client email set.');
        }
        $html = InvoicePostType::generateHTML($id);
        if ($html === '') {
            \WP_CLI::error('Invoice not found.');
        }
        $sent = wp_mail($email, 'Invoice: ' . get_the_title($id), $html, ['Content-Type: text/html; charset=UTF-8']);
        if ($sent) {
            update_post_meta($id, '_status', 'sent');
            \WP_CLI::success("Invoice sent to {$email}");
        } else {
            \WP_CLI::error('Failed to send.');
        }
    }
}
