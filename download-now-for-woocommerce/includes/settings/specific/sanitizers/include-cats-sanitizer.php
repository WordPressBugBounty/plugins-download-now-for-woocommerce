<?php
/**
 * Sanitizer for Category Restrictions Settings (somdn_pro_include_cat_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-include-cats', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // CATEGORY RESTRICTIONS
  // ==========================================================================
  
  // Category Restriction Type (empty, included, excluded)
  $restriction_type = isset($data['somdn_pro_include_cat_setting']) ? sanitize_text_field($data['somdn_pro_include_cat_setting']) : '';
  $out['somdn_pro_include_cat_setting'] = in_array($restriction_type, ['', 'included', 'excluded'], true) ? $restriction_type : '';

  // Category List (array of term IDs)
  $cat_list = isset($data['somdn_pro_include_cat_list']) ? $data['somdn_pro_include_cat_list'] : [];
  if (!is_array($cat_list)) {
    $cat_list = [];
  }
  // Sanitize each ID as integer
  $out['somdn_pro_include_cat_list'] = array_map('absint', array_filter($cat_list));

  return $out;
}, 10, 2);

