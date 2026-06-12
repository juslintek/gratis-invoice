<?php declare(strict_types=1);

namespace Gratis\Invoice;

defined('ABSPATH') || exit;

/**
 * Invoice custom post type, creation helper and printable HTML renderer.
 */
final class InvoicePostType {

    public const STATUSES = ['draft', 'sent', 'paid', 'overdue'];

    /** Register the CPT and its meta. */
    public static function register(): void {
        register_post_type('gratis_invoice', [
            'labels'       => [
                'name'          => __('Invoices', 'gratis-invoice'),
                'singular_name' => __('Invoice', 'gratis-invoice'),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-media-text',
            'supports'     => ['title', 'custom-fields'],
        ]);

        $strings = ['_client_name', '_client_email', '_items', '_status', '_due_date', '_currency'];
        foreach ($strings as $key) {
            register_post_meta('gratis_invoice', $key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => fn() => current_user_can('edit_posts'),
            ]);
        }
        register_post_meta('gratis_invoice', '_total', [
            'type'          => 'number',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /** Create an invoice from a data array; returns the new post ID. */
    public static function create(array $data): int {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $total = array_sum(array_map(
            static fn($i) => (float) ($i['qty'] ?? 1) * (float) ($i['price'] ?? 0),
            $items
        ));
        $number = 'INV-' . gmdate('Ymd') . '-' . wp_rand(100, 999);

        $id = wp_insert_post([
            'post_type'   => 'gratis_invoice',
            'post_title'  => $number,
            'post_status' => 'publish',
            'meta_input'  => [
                '_client_name'  => sanitize_text_field((string) ($data['client'] ?? '')),
                '_client_email' => sanitize_email((string) ($data['email'] ?? '')),
                '_items'        => wp_json_encode(array_values($items)),
                '_total'        => $total,
                '_status'       => 'draft',
                '_due_date'     => sanitize_text_field((string) ($data['due'] ?? gmdate('Y-m-d', strtotime('+30 days')))),
                '_currency'     => sanitize_text_field((string) ($data['currency'] ?? Settings::defaultCurrency())),
            ],
        ]);

        return is_wp_error($id) ? 0 : (int) $id;
    }

    /** Decode the stored line items for an invoice. */
    public static function items(int $invoiceId): array {
        $raw  = (string) get_post_meta($invoiceId, '_items', true);
        $data = json_decode($raw !== '' ? $raw : '[]', true);
        return is_array($data) ? $data : [];
    }

    /** Build printable invoice HTML (escaped, safe to echo via wp_kses_post). */
    public static function generateHTML(int $invoiceId): string {
        $inv = get_post($invoiceId);
        if (! $inv || $inv->post_type !== 'gratis_invoice') {
            return '';
        }

        $items    = self::items($invoiceId);
        $total    = (float) get_post_meta($invoiceId, '_total', true);
        $client   = (string) get_post_meta($invoiceId, '_client_name', true);
        $due      = (string) get_post_meta($invoiceId, '_due_date', true);
        $currency = (string) get_post_meta($invoiceId, '_currency', true);
        $currency = $currency !== '' ? $currency : 'USD';
        $business = Settings::businessName();

        $html  = '<div style="font-family:system-ui;max-width:700px;margin:0 auto;padding:40px">';
        if ($business !== '') {
            $html .= '<p style="font-weight:700;font-size:1.1em;margin:0 0 8px">' . esc_html($business) . '</p>';
        }
        $html .= '<h1 style="margin:0">' . esc_html($inv->post_title) . '</h1>';
        $html .= '<p>' . esc_html__('Client:', 'gratis-invoice') . ' <strong>' . esc_html($client) . '</strong> | ' . esc_html__('Due:', 'gratis-invoice') . ' ' . esc_html($due) . '</p>';
        $html .= '<table style="width:100%;border-collapse:collapse;margin:20px 0"><thead><tr>';
        $html .= '<th style="text-align:left;border-bottom:2px solid #000;padding:8px">' . esc_html__('Item', 'gratis-invoice') . '</th>';
        $html .= '<th style="text-align:right;border-bottom:2px solid #000;padding:8px">' . esc_html__('Qty', 'gratis-invoice') . '</th>';
        $html .= '<th style="text-align:right;border-bottom:2px solid #000;padding:8px">' . esc_html__('Price', 'gratis-invoice') . '</th>';
        $html .= '<th style="text-align:right;border-bottom:2px solid #000;padding:8px">' . esc_html__('Total', 'gratis-invoice') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $qty       = (float) ($item['qty'] ?? 1);
            $price     = (float) ($item['price'] ?? 0);
            $lineTotal = $qty * $price;
            $html     .= '<tr>';
            $html     .= '<td style="padding:8px;border-bottom:1px solid #eee">' . esc_html((string) ($item['desc'] ?? '')) . '</td>';
            $html     .= '<td style="text-align:right;padding:8px;border-bottom:1px solid #eee">' . esc_html(number_format_i18n($qty)) . '</td>';
            $html     .= '<td style="text-align:right;padding:8px;border-bottom:1px solid #eee">' . esc_html(number_format($price, 2)) . '</td>';
            $html     .= '<td style="text-align:right;padding:8px;border-bottom:1px solid #eee">' . esc_html(number_format($lineTotal, 2)) . '</td>';
            $html     .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p style="text-align:right;font-size:1.5em;font-weight:700">' . esc_html__('Total:', 'gratis-invoice') . ' ' . esc_html($currency . ' ' . number_format($total, 2)) . '</p>';
        $html .= '</div>';

        return $html;
    }
}
