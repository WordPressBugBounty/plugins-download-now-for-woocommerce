<?php
/**
 * Sanitizer for Download Limits Settings (somdn_pro_basic_limit_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-limits', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // Enable/Disable
  $out['somdn_pro_basic_limit_enable'] = !empty($data['somdn_pro_basic_limit_enable']) ? 'on' : '';

  // Limit Type (0 = none, 1 = User ID, 2 = IP Address)
  $limit_type = isset($data['somdn_pro_basic_limit_type']) ? absint($data['somdn_pro_basic_limit_type']) : 0;
  $out['somdn_pro_basic_limit_type'] = in_array($limit_type, [0, 1, 2], true) ? $limit_type : 0;

  // Login Required
  $login_req = isset($data['somdn_pro_basic_login_req']) ? sanitize_text_field($data['somdn_pro_basic_login_req']) : 'login-required';
  $out['somdn_pro_basic_login_req'] = in_array($login_req, ['login-required', 'not-required'], true) ? $login_req : 'login-required';

  // Download Limit Period (empty = none, 1 = day, 2 = week, 3 = month, 4 = year)
  $limit_freq = isset($data['somdn_pro_basic_limit_freq']) ? $data['somdn_pro_basic_limit_freq'] : '';
  if ($limit_freq !== '') {
    $limit_freq = absint($limit_freq);
    $out['somdn_pro_basic_limit_freq'] = in_array($limit_freq, [1, 2, 3, 4], true) ? $limit_freq : '';
  } else {
    $out['somdn_pro_basic_limit_freq'] = '';
  }

  // Number of Downloads (0-1000, empty = unlimited)
  $limit_amount = isset($data['somdn_pro_basic_limit_amount']) ? $data['somdn_pro_basic_limit_amount'] : '';
  if ($limit_amount !== '' && $limit_amount !== null) {
    $limit_amount = absint($limit_amount);
    $out['somdn_pro_basic_limit_amount'] = min($limit_amount, 1000);
  } else {
    $out['somdn_pro_basic_limit_amount'] = '';
  }

  // Number of Products (0-1000, empty = unlimited)
  $limit_products = isset($data['somdn_pro_basic_limit_products']) ? $data['somdn_pro_basic_limit_products'] : '';
  if ($limit_products !== '' && $limit_products !== null) {
    $limit_products = absint($limit_products);
    $out['somdn_pro_basic_limit_products'] = min($limit_products, 1000);
  } else {
    $out['somdn_pro_basic_limit_products'] = '';
  }

  // Products Text
  $out['somdn_pro_basic_limit_product_name'] = sanitize_text_field($data['somdn_pro_basic_limit_product_name'] ?? '');

  // Exclude Months (0-36)
  $exclude_months = isset($data['somdn_pro_exclude_months']) ? $data['somdn_pro_exclude_months'] : '';
  if ($exclude_months !== '' && $exclude_months !== null) {
    $exclude_months = absint($exclude_months);
    $out['somdn_pro_exclude_months'] = min($exclude_months, 36);
  } else {
    $out['somdn_pro_exclude_months'] = '';
  }

  // Exclude Categories (array of term IDs)
  $exclude_cats = isset($data['somdn_pro_exclude_cat_list']) ? $data['somdn_pro_exclude_cat_list'] : [];
  if (is_array($exclude_cats)) {
    $out['somdn_pro_exclude_cat_list'] = array_map('absint', $exclude_cats);
  } else {
    $out['somdn_pro_exclude_cat_list'] = [];
  }

  // Error Message (allow HTML)
  $out['somdn_pro_basic_limit_error'] = wp_kses_post($data['somdn_pro_basic_limit_error'] ?? '');

  // Account Page Toggle
  $out['somdn_pro_limit_acc_page'] = !empty($data['somdn_pro_limit_acc_page']) ? 'on' : '';

  // Account Page Title Enable
  $out['somdn_pro_limit_acc_page_title_enable'] = !empty($data['somdn_pro_limit_acc_page_title_enable']) ? 'on' : '';

  // Account Page Title
  $out['somdn_pro_limit_acc_page_title'] = sanitize_text_field($data['somdn_pro_limit_acc_page_title'] ?? '');

  // Account Page Message (allow HTML)
  $out['somdn_pro_limit_acc_page_message'] = wp_kses_post($data['somdn_pro_limit_acc_page_message'] ?? '');

  return $out;
}, 10, 2);

