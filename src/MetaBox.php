<?php declare(strict_types=1);

namespace Gratis\Invoice;

defined('ABSPATH') || exit;

/**
 * Invoice editing meta box (client details, line items, status).
 */
final class MetaBox {

    private const NONCE = 'gratis_invoice_meta';

    /** Register the meta box on the invoice CPT. */
    public static function register(): void {
        add_meta_box(
            'gratis_invoice_details',
            __('Invoice Details', 'gratis-invoice'),
            [__CLASS__, 'render'],
            'gratis_invoice',
            'normal',
            'high'
        );
    }

    /** Render the meta box fields. */
    public static function render(\WP_Post $post): void {
        wp_nonce_field(self::NONCE, self::NONCE);

        $client   = (string) get_post_meta($post->ID, '_client_name', true);
        $email    = (string) get_post_meta($post->ID, '_client_email', true);
        $items    = (string) get_post_meta($post->ID, '_items', true);
        $status   = (string) get_post_meta($post->ID, '_status', true);
        $due      = (string) get_post_meta($post->ID, '_due_date', true);
        $currency = (string) get_post_meta($post->ID, '_currency', true);
        $currency = $currency !== '' ? $currency : Settings::defaultCurrency();

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="gi_client">' . esc_html__('Client name', 'gratis-invoice') . '</label></th><td>';
        echo '<input type="text" id="gi_client" class="regular-text" name="gi_client" value="' . esc_attr($client) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gi_email">' . esc_html__('Client email', 'gratis-invoice') . '</label></th><td>';
        echo '<input type="email" id="gi_email" class="regular-text" name="gi_email" value="' . esc_attr($email) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gi_status">' . esc_html__('Status', 'gratis-invoice') . '</label></th><td>';
        echo '<select id="gi_status" name="gi_status">';
        foreach (InvoicePostType::STATUSES as $slug) {
            echo '<option value="' . esc_attr($slug) . '"' . selected($status, $slug, false) . '>' . esc_html(ucfirst($slug)) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="gi_due">' . esc_html__('Due date', 'gratis-invoice') . '</label></th><td>';
        echo '<input type="date" id="gi_due" name="gi_due" value="' . esc_attr($due) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gi_currency">' . esc_html__('Currency', 'gratis-invoice') . '</label></th><td>';
        echo '<input type="text" id="gi_currency" class="small-text" name="gi_currency" value="' . esc_attr($currency) . '" maxlength="3">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gi_items">' . esc_html__('Line items (JSON)', 'gratis-invoice') . '</label></th><td>';
        echo '<textarea id="gi_items" class="large-text code" rows="6" name="gi_items">' . esc_textarea($items) . '</textarea>';
        echo '<p class="description">' . esc_html__('Array of {"desc":"...","qty":1,"price":0} objects. Total recalculates on save.', 'gratis-invoice') . '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        if ($post->post_status === 'publish') {
            $url = wp_nonce_url(
                add_query_arg(['action' => 'gratis_invoice_print', 'invoice' => $post->ID], admin_url('admin-post.php')),
                'gratis_invoice_print'
            );
            echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url($url) . '">' . esc_html__('Open printable invoice', 'gratis-invoice') . '</a></p>';
        }
    }

    /** Persist the meta box fields. */
    public static function save(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! isset($_POST[self::NONCE]) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), self::NONCE)) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Nonce + capability verified above; each field sanitised below.
        update_post_meta($post_id, '_client_name', sanitize_text_field(wp_unslash($_POST['gi_client'] ?? '')));
        update_post_meta($post_id, '_client_email', sanitize_email(wp_unslash($_POST['gi_email'] ?? '')));
        update_post_meta($post_id, '_due_date', sanitize_text_field(wp_unslash($_POST['gi_due'] ?? '')));

        $status = sanitize_key(wp_unslash($_POST['gi_status'] ?? 'draft'));
        update_post_meta($post_id, '_status', in_array($status, InvoicePostType::STATUSES, true) ? $status : 'draft');

        $currency = strtoupper(sanitize_text_field(wp_unslash($_POST['gi_currency'] ?? 'USD')));
        update_post_meta($post_id, '_currency', substr(preg_replace('/[^A-Z]/', '', $currency) ?: 'USD', 0, 3));

        $raw_items = isset($_POST['gi_items']) ? sanitize_textarea_field(wp_unslash($_POST['gi_items'])) : '[]';
        $items     = json_decode($raw_items, true);
        $items     = is_array($items) ? array_values($items) : [];
        $clean     = [];
        $total     = 0.0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty   = (float) ($item['qty'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $clean[] = [
                'desc'  => sanitize_text_field((string) ($item['desc'] ?? '')),
                'qty'   => $qty,
                'price' => $price,
            ];
            $total += $qty * $price;
        }
        update_post_meta($post_id, '_items', wp_json_encode($clean));
        update_post_meta($post_id, '_total', $total);
    }
}
