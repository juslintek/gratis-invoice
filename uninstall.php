<?php
/**
 * Uninstall gratis-invoice.
 *
 * Removes all plugin data: options, custom tables, and scheduled events.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Remove plugin options.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'gratis_invoice_%'));

// Remove custom post type data.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_type = %s", 'gratis_invoice'));
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");
