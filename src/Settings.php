<?php declare(strict_types=1);

namespace Gratis\Invoice;

defined('ABSPATH') || exit;

/**
 * Admin settings + printable invoice handler for Gratis Invoice.
 */
final class Settings {

    private const GROUP = 'gratis_invoice_settings_group';
    private const PAGE  = 'gratis-invoice-settings';

    private const OPT_BUSINESS = 'gratis_invoice_business_name';
    private const OPT_CURRENCY = 'gratis_invoice_currency';
    private const OPT_DUE_DAYS = 'gratis_invoice_due_days';

    /** Register the settings submenu under the Invoices menu. */
    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=gratis_invoice',
            __('Invoice Settings', 'gratis-invoice'),
            __('Settings', 'gratis-invoice'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register options with typed sanitisers. */
    public static function register_settings(): void {
        register_setting(self::GROUP, self::OPT_BUSINESS, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting(self::GROUP, self::OPT_CURRENCY, [
            'type'              => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_currency'],
            'default'           => 'USD',
        ]);
        register_setting(self::GROUP, self::OPT_DUE_DAYS, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);
    }

    /** Register the printable-invoice admin-post handler. */
    public static function register_actions(): void {
        add_action('admin_post_gratis_invoice_print', [__CLASS__, 'handle_print']);
    }

    /** Normalise a 3-letter currency code. */
    public static function sanitize_currency($value): string {
        $code = strtoupper(sanitize_text_field((string) $value));
        $code = preg_replace('/[^A-Z]/', '', $code);
        return substr($code !== '' ? $code : 'USD', 0, 3);
    }

    /** Configured business name. */
    public static function businessName(): string {
        return (string) get_option(self::OPT_BUSINESS, '');
    }

    /** Default invoice currency. */
    public static function defaultCurrency(): string {
        $code = (string) get_option(self::OPT_CURRENCY, 'USD');
        return $code !== '' ? $code : 'USD';
    }

    /** Default number of days until an invoice is due. */
    public static function dueDays(): int {
        return max(1, (int) get_option(self::OPT_DUE_DAYS, 30));
    }

    /** Stream a printable invoice (nonce + capability gated). */
    public static function handle_print(): void {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to do that.', 'gratis-invoice'));
        }
        check_admin_referer('gratis_invoice_print');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above.
        $invoice_id = isset($_GET['invoice']) ? absint(wp_unslash($_GET['invoice'])) : 0;
        $html       = InvoicePostType::generateHTML($invoice_id);
        if ($html === '') {
            wp_die(esc_html__('Invoice not found.', 'gratis-invoice'));
        }

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html(get_the_title($invoice_id)) . '</title></head><body>';
        echo wp_kses_post($html);
        echo '<script>window.print();</script></body></html>';
        exit;
    }

    /** Render the settings screen. */
    public static function render_page(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-invoice'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Invoice Settings', 'gratis-invoice') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="gi_business">' . esc_html__('Business name', 'gratis-invoice') . '</label></th><td>';
        echo '<input type="text" id="gi_business" class="regular-text" name="' . esc_attr(self::OPT_BUSINESS) . '" value="' . esc_attr(self::businessName()) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gi_currency">' . esc_html__('Default currency', 'gratis-invoice') . '</label></th><td>';
        echo '<input type="text" id="gi_currency" class="small-text" name="' . esc_attr(self::OPT_CURRENCY) . '" value="' . esc_attr(self::defaultCurrency()) . '" maxlength="3">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gi_due">' . esc_html__('Default due (days)', 'gratis-invoice') . '</label></th><td>';
        echo '<input type="number" min="1" id="gi_due" class="small-text" name="' . esc_attr(self::OPT_DUE_DAYS) . '" value="' . esc_attr((string) self::dueDays()) . '">';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save settings', 'gratis-invoice'));
        echo '</form>';
        echo '</div>';
    }
}
