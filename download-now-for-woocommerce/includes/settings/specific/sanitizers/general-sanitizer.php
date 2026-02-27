<?php
/**
 * Sanitizer for General Settings (somdn_gen_settings)
 */

add_filter('wpe_settings_sanitize_free-downloads-gen', function($sanitized, $data) {
  $out = [];
  $is_pro = defined('SOMDN_PRO_VERSION');

  // PRO: Global Site Settings - only save if Pro is active
  if ($is_pro) {
    $out['somdn_pro_disable_ecommerce'] = !empty($data['somdn_pro_disable_ecommerce']) ? 'on' : '';
  }

  // Shop Button Text (archive download enabled)
  $out['somdn_shop_download_logged_in_text'] = sanitize_text_field($data['somdn_shop_download_logged_in_text'] ?? '');
  $out['somdn_shop_download_logged_out_text'] = sanitize_text_field($data['somdn_shop_download_logged_out_text'] ?? '');

  // Shop Button Text (archive download disabled)
  $out['somdn_shop_disabled_logged_in_text'] = sanitize_text_field($data['somdn_shop_disabled_logged_in_text'] ?? '');
  $out['somdn_shop_disabled_logged_out_text'] = sanitize_text_field($data['somdn_shop_disabled_logged_out_text'] ?? '');

  // Files section - toggles
  $out['somdn_require_login'] = !empty($data['somdn_require_login']) ? 'on' : '';
  $out['somdn_require_login_message'] = wp_kses_post($data['somdn_require_login_message'] ?? '');
  
  // PRO: Grouped message - only save if Pro is active
  if ($is_pro) {
    $out['somdn_require_login_grouped_message'] = wp_kses_post($data['somdn_require_login_grouped_message'] ?? '');
  }
  
  $out['somdn_download_in_new_window'] = !empty($data['somdn_download_in_new_window']) ? 'on' : '';
  $out['somdn_include_archive_items'] = !empty($data['somdn_include_archive_items']) ? 'on' : '';
  $out['somdn_hide_readmore_button_archive'] = !empty($data['somdn_hide_readmore_button_archive']) ? 'on' : '';
  $out['somdn_include_sale_items'] = !empty($data['somdn_include_sale_items']) ? 'on' : '';
  $out['somdn_indy_items'] = !empty($data['somdn_indy_items']) ? 'on' : '';
  $out['somdn_indy_exclude_items'] = !empty($data['somdn_indy_exclude_items']) ? 'on' : '';
  $out['somdn_disable_security_key_check'] = !empty($data['somdn_disable_security_key_check']) ? 'on' : '';

  // Product Sorting
  $out['somdn_enable_download_sorting'] = !empty($data['somdn_enable_download_sorting']) ? 'on' : '';

  // Download Counts
  $out['somdn_download_counts_output'] = absint($data['somdn_download_counts_output'] ?? 0);
  $out['somdn_download_counts_output_text'] = sanitize_text_field($data['somdn_download_counts_output_text'] ?? '');

  // Button/Link Styling
  $out['somdn_button_class'] = sanitize_text_field($data['somdn_button_class'] ?? '');
  $out['somdn_button_css'] = sanitize_text_field($data['somdn_button_css'] ?? '');
  $out['somdn_link_class'] = sanitize_text_field($data['somdn_link_class'] ?? '');
  $out['somdn_link_css'] = sanitize_text_field($data['somdn_link_css'] ?? '');

  return $out;
}, 10, 2);

