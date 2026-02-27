<?php
/**
 * Sanitizer for Multiple Files Settings (somdn_multi_settings)
 * 
 * Handles sanitization of all multiple file settings fields.
 */

add_filter('wpe_settings_sanitize_free-downloads-multi', function($sanitized, $data) {
  $out = [];

  // Display Type - must be 1-5
  $display_type = isset($data['somdn_display_type']) ? absint($data['somdn_display_type']) : 1;
  $out['somdn_display_type'] = in_array($display_type, [1, 2, 3, 4, 5], true) ? $display_type : 1;

  // Download Button Text
  $out['somdn_multi_button_text'] = isset($data['somdn_multi_button_text']) 
    ? sanitize_text_field($data['somdn_multi_button_text']) 
    : '';

  // Available Downloads Heading Text
  $out['somdn_available_downloads_text'] = isset($data['somdn_available_downloads_text']) 
    ? sanitize_text_field($data['somdn_available_downloads_text']) 
    : '';

  // Checkbox Error Text
  $out['somdn_checkbox_error_text'] = isset($data['somdn_checkbox_error_text']) 
    ? sanitize_text_field($data['somdn_checkbox_error_text']) 
    : '';

  // Select All Checkbox (Toggle)
  $out['somdn_select_all'] = !empty($data['somdn_select_all']) ? 1 : '';

  // Show Numbers Next to Filename (Toggle)
  $out['somdn_show_numbers'] = !empty($data['somdn_show_numbers']) ? 1 : '';

  return $out;
}, 10, 2);
