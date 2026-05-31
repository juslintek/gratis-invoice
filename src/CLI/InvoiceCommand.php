<?php declare(strict_types=1);
namespace Gratis\Invoice\CLI;

use Gratis\Invoice\InvoicePostType;

final class InvoiceCommand
{
    /**
     * List invoices.
     * [--status=<status>] : Filter (draft, sent, paid, overdue).
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void
    {
        $query = ['post_type' => 'gratis_invoice', 'numberposts' => 50];
        if (!empty($assoc_args['status'])) $query['meta_query'] = [['key' => '_status', 'value' => $assoc_args['status']]];
        $invoices = get_posts($query);
        if (empty($invoices)) { \WP_CLI::log("No invoices."); return; }
        $rows = array_map(fn($i) => [
            'ID' => $i->ID, 'Number' => $i->post_title,
            'Client' => get_post_meta($i->ID, '_client_name', true),
            'Total' => '$' . get_post_meta($i->ID, '_total', true),
            'Status' => get_post_meta($i->ID, '_status', true),
            'Due' => get_post_meta($i->ID, '_due_date', true),
        ], $invoices);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Number', 'Client', 'Total', 'Status', 'Due']);
    }

    /**
     * Create an invoice.
     * --client=<name> : Client name.
     * --email=<email> : Client email.
     * --items=<json> : JSON array of items.
     * @when after_wp_load
     */
    public function create($args, $assoc_args): void
    {
        $items = json_decode($assoc_args['items'] ?? '[]', true);
        $id = InvoicePostType::create([
            'client' => $assoc_args['client'] ?? '',
            'email'  => $assoc_args['email'] ?? '',
            'items'  => $items,
        ]);
        \WP_CLI::success("Invoice #{$id} created: " . get_the_title($id));
    }

    /**
     * Send invoice to client.
     * <id> : Invoice ID.
     * @when after_wp_load
     */
    public function send($args, $assoc_args): void
    {
        $id = (int) $args[0];
        $email = get_post_meta($id, '_client_email', true);
        if (!$email) { \WP_CLI::error("No client email set."); }
        $html = InvoicePostType::generateHTML($id);
        $sent = wp_mail($email, 'Invoice: ' . get_the_title($id), $html, ['Content-Type: text/html; charset=UTF-8']);
        if ($sent) {
            update_post_meta($id, '_status', 'sent');
            \WP_CLI::success("Invoice sent to {$email}");
        } else {
            \WP_CLI::error("Failed to send.");
        }
    }
}
