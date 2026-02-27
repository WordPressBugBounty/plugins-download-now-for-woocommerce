<?php
/**
 * Sanitizer for Single File Settings (somdn_single_settings)
 */

add_filter('wpe_settings_sanitize_free-downloads-single', function($sanitized, $data) {
  $out = [];
  $out['somdn_single_type'] = absint($data['somdn_single_type'] ?? 1);
  $out['somdn_single_button_text'] = sanitize_text_field($data['somdn_single_button_text'] ?? '');
  $out['somdn_single_again_button_text'] = sanitize_text_field($data['somdn_single_again_button_text'] ?? '');
  $out['somdn_single_button_filename'] = !empty($data['somdn_single_button_filename']) ? 'on' : '';
  $out['somdn_single_force_zip'] = !empty($data['somdn_single_force_zip']) ? 'on' : '';
  $out['somdn_single_zip_rename'] = !empty($data['somdn_single_zip_rename']) ? 'on' : '';
  $out['somdn_single_zip_rename_attributes'] = !empty($data['somdn_single_zip_rename_attributes']) ? 'on' : '';
  return $out;
}, 10, 2);

