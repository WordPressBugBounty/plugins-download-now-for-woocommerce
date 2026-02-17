<?php
/**
 * Sanitizer for Debug Settings (somdn_debug_settings)
 * 
 * All fields in this section are FREE features.
 */

add_filter('wpe_settings_sanitize_free-downloads-debug', function($sanitized, $data) {
  $out = [];

  // Toggle: Enable debug logging
  $out['somdn_debug_logging_enable'] = !empty($data['somdn_debug_logging_enable']) ? 'on' : '';

  return $out;
}, 10, 2);
