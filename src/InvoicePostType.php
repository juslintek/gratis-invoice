<?php declare(strict_types=1);
namespace Gratis\Invoice;

final class InvoicePostType
{
    public static function register(): void
    {
        register_post_type('gratis_invoice', [
            'labels' => ['name' => 'Invoices', 'singular_name' => 'Invoice'],
            'public' => false, 'show_ui' => true, 'show_in_rest' => true,
            'menu_icon' => 'dashicons-media-text', 'supports' => ['title', 'custom-fields'],
        ]);
        register_post_meta('gratis_invoice', '_client_name', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_invoice', '_client_email', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_invoice', '_items', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_invoice', '_total', ['type' => 'number', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_invoice', '_status', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_invoice', '_due_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_invoice', '_currency', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    }

    public static function create(array $data): int
    {
        $items = $data['items'] ?? [];
        $total = array_sum(array_map(fn($i) => ($i['qty'] ?? 1) * ($i['price'] ?? 0), $items));
        $number = 'INV-' . date('Ymd') . '-' . wp_rand(100, 999);

        return wp_insert_post([
            'post_type' => 'gratis_invoice', 'post_title' => $number, 'post_status' => 'publish',
            'meta_input' => [
                '_client_name'  => sanitize_text_field($data['client'] ?? ''),
                '_client_email' => sanitize_email($data['email'] ?? ''),
                '_items'        => json_encode($items),
                '_total'        => $total,
                '_status'       => 'draft',
                '_due_date'     => $data['due'] ?? date('Y-m-d', strtotime('+30 days')),
                '_currency'     => $data['currency'] ?? 'USD',
            ],
        ]);
    }

    public static function generateHTML(int $invoiceId): string
    {
        $inv = get_post($invoiceId);
        if (!$inv) return '';
        $items = json_decode(get_post_meta($invoiceId, '_items', true) ?: '[]', true);
        $total = get_post_meta($invoiceId, '_total', true);
        $client = get_post_meta($invoiceId, '_client_name', true);
        $due = get_post_meta($invoiceId, '_due_date', true);
        $currency = get_post_meta($invoiceId, '_currency', true) ?: 'USD';

        $html = '<div style="font-family:system-ui;max-width:700px;margin:0 auto;padding:40px">';
        $html .= '<h1 style="margin:0">' . esc_html($inv->post_title) . '</h1>';
        $html .= '<p>Client: <strong>' . esc_html($client) . '</strong> | Due: ' . esc_html($due) . '</p>';
        $html .= '<table style="width:100%;border-collapse:collapse;margin:20px 0"><thead><tr><th style="text-align:left;border-bottom:2px solid #000;padding:8px">Item</th><th style="text-align:right;border-bottom:2px solid #000;padding:8px">Qty</th><th style="text-align:right;border-bottom:2px solid #000;padding:8px">Price</th><th style="text-align:right;border-bottom:2px solid #000;padding:8px">Total</th></tr></thead><tbody>';
        foreach ($items as $item) {
            $lineTotal = ($item['qty'] ?? 1) * ($item['price'] ?? 0);
            $html .= '<tr><td style="padding:8px;border-bottom:1px solid #eee">' . esc_html($item['desc'] ?? '') . '</td><td style="text-align:right;padding:8px;border-bottom:1px solid #eee">' . ($item['qty'] ?? 1) . '</td><td style="text-align:right;padding:8px;border-bottom:1px solid #eee">' . number_format($item['price'] ?? 0, 2) . '</td><td style="text-align:right;padding:8px;border-bottom:1px solid #eee">' . number_format($lineTotal, 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<p style="text-align:right;font-size:1.5em;font-weight:700">Total: ' . $currency . ' ' . number_format((float) $total, 2) . '</p>';
        $html .= '</div>';
        return $html;
    }
}
