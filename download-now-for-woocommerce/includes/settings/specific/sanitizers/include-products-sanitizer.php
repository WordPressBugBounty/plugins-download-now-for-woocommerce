<?php
/**
 * Sanitizer for Product Restrictions Settings (somdn_pro_include_product_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-include-products', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // PRODUCT RESTRICTIONS
  // ==========================================================================
  
  // Product Restriction Type (empty, included, excluded)
  $restriction_type = isset($data['somdn_pro_include_product_setting']) ? sanitize_text_field($data['somdn_pro_include_product_setting']) : '';
  $out['somdn_pro_include_product_setting'] = in_array($restriction_type, ['', 'included', 'excluded'], true) ? $restriction_type : '';

  // Product List (array of product IDs)
  $product_list = isset($data['somdn_pro_include_product_list']) ? $data['somdn_pro_include_product_list'] : [];
  if (!is_array($product_list)) {
    $product_list = [];
  }
  // Sanitize each ID as integer
  $out['somdn_pro_include_product_list'] = array_map('absint', array_filter($product_list));

  return $out;
}, 10, 2);

