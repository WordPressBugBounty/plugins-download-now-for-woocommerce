<?php
/**
 * Sanitizer for Quick View Settings (somdn_woo_quickview_settings)
 * 
 * All fields in this section are FREE features.
 */

add_filter('wpe_settings_sanitize_free-downloads-quickview', function($sanitized, $data) {
  $out = [];

  // Toggle: Enable Quick View
  $out['somdn_woo_quickview_enable'] = !empty($data['somdn_woo_quickview_enable']) ? 'on' : '';

  // Toggle: Show Variation Options in Quick View
  $out['somdn_woo_quickview_variations'] = !empty($data['somdn_woo_quickview_variations']) ? 'on' : '';

  // Text: Button text
  $out['somdn_woo_quickview_button_text'] = isset($data['somdn_woo_quickview_button_text']) 
    ? sanitize_text_field($data['somdn_woo_quickview_button_text']) 
    : '';

  // Color: Button background colour (default #2679ce)
  $out['somdn_woo_quickview_button_colour'] = isset($data['somdn_woo_quickview_button_colour']) 
    ? sanitize_hex_color($data['somdn_woo_quickview_button_colour']) 
    : '#2679ce';

  // Color: Button text colour (default #ffffff)
  $out['somdn_woo_quickview_button_text_colour'] = isset($data['somdn_woo_quickview_button_text_colour']) 
    ? sanitize_hex_color($data['somdn_woo_quickview_button_text_colour']) 
    : '#ffffff';

  return $out;
}, 10, 2);
