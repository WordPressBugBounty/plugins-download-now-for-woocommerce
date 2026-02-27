<?php
/**
 * Sanitizer for Owned Products Settings (somdn_owned_settings)
 * 
 * All fields in this section are FREE features.
 */

add_filter('wpe_settings_sanitize_free-downloads-owned', function($sanitized, $data) {
  $out = [];

  // Toggle: Enable free downloads for owned products
  $out['somdn_owned_enable'] = !empty($data['somdn_owned_enable']) ? 'on' : '';

  // Text: Download button text for owned items
  $out['somdn_owned_button_text'] = isset($data['somdn_owned_button_text']) 
    ? sanitize_text_field($data['somdn_owned_button_text']) 
    : '';

  // Text: Badge text for owned products
  $out['somdn_owned_badge_text'] = isset($data['somdn_owned_badge_text']) 
    ? sanitize_text_field($data['somdn_owned_badge_text']) 
    : '';

  // Toggle: Hide the owned badge
  $out['somdn_owned_badge_hide'] = !empty($data['somdn_owned_badge_hide']) ? 'on' : '';

  return $out;
}, 10, 2);
