<?php
/**
 * Free Downloads - Database index creation for performance
 *
 * Creates indexes on activation and upgrade so all installs benefit.
 *
 * @package FreeDownloadsWooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Create postmeta composite index (meta_key, meta_value) if not already created.
 * Improves performance for meta_key + meta_value lookups (e.g. My Account downloads).
 * Uses table prefix; InnoDB uses BTREE (default), not HASH.
 */
function somdn_maybe_create_postmeta_index() {
	$option_key = 'somdn_postmeta_index_created';
	if ( get_option( $option_key ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->postmeta;
	$index_name = 'somdn_postmeta_kv';

	// Check if index already exists (e.g. created manually).
	$suppress = $wpdb->suppress_errors();
	$result = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index_name ) );
	$wpdb->suppress_errors( $suppress );

	if ( ! empty( $result ) ) {
		update_option( $option_key, '1' );
		return;
	}

	// meta_value(191) for utf8mb4 compatibility; prefix allows index use on value.
	$index_esc = '`' . str_replace( '`', '``', $index_name ) . '`';
	$table_esc = '`' . str_replace( '`', '``', $table ) . '`';
	$wpdb->query( "CREATE INDEX {$index_esc} ON {$table_esc} (meta_key, meta_value(191))" );

	if ( empty( $wpdb->last_error ) ) {
		update_option( $option_key, '1' );
	}
}
