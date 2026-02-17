<?php
/**
 * Sanitizer for Tag Restrictions Settings (somdn_pro_include_tag_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-include-tags', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // TAG RESTRICTIONS
  // ==========================================================================
  
  // Tag Restriction Type (empty, included, excluded)
  $restriction_type = isset($data['somdn_pro_include_tag_setting']) ? sanitize_text_field($data['somdn_pro_include_tag_setting']) : '';
  $out['somdn_pro_include_tag_setting'] = in_array($restriction_type, ['', 'included', 'excluded'], true) ? $restriction_type : '';

  // Tag List (array of term IDs)
  $tag_list = isset($data['somdn_pro_include_tag_list']) ? $data['somdn_pro_include_tag_list'] : [];
  if (!is_array($tag_list)) {
    $tag_list = [];
  }
  // Sanitize each ID as integer
  $out['somdn_pro_include_tag_list'] = array_map('absint', array_filter($tag_list));

  return $out;
}, 10, 2);

