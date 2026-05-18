<?php
/**
 * Sanitizer for Tracking & Email Capture Settings (somdn_pro_track_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-tracking', function($sanitized, $data) {
  $out = [];
  $sanitize_color_value = static function($value, $default) {
    // React ColorFieldset can send either hex strings or RGBA objects.
    if (is_array($value)) {
      $r = isset($value['r']) ? max(0, min(255, absint($value['r']))) : null;
      $g = isset($value['g']) ? max(0, min(255, absint($value['g']))) : null;
      $b = isset($value['b']) ? max(0, min(255, absint($value['b']))) : null;
      $a_raw = isset($value['a']) ? (float) $value['a'] : 1.0;
      $a = max(0, min(1, $a_raw));

      if ($r !== null && $g !== null && $b !== null) {
        return sprintf('rgba(%d, %d, %d, %.2F)', $r, $g, $b, $a);
      }
    }

    if (is_string($value)) {
      $value = trim($value);
      if ($value === '') {
        return $default;
      }

      $hex = sanitize_hex_color($value);
      if (!empty($hex)) {
        return $hex;
      }

      if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*((?:0|1)(?:\.\d+)?))?\s*\)$/i', $value, $matches)) {
        $r = max(0, min(255, absint($matches[1])));
        $g = max(0, min(255, absint($matches[2])));
        $b = max(0, min(255, absint($matches[3])));
        $a = isset($matches[4]) ? max(0, min(1, (float) $matches[4])) : 1.0;

        return sprintf('rgba(%d, %d, %d, %.2F)', $r, $g, $b, $a);
      }
    }

    return $default;
  };
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // TRACK DOWNLOADS
  // ==========================================================================
  
  // Enable Tracking
  $out['somdn_pro_track_enable'] = !empty($data['somdn_pro_track_enable']) ? 'on' : '';

  // ==========================================================================
  // EMAIL CAPTURE
  // ==========================================================================
  
  // Enable Email Capture
  $out['somdn_capture_email_enable'] = !empty($data['somdn_capture_email_enable']) ? 'on' : '';

  // Remember Guest Email
  $out['somdn_capture_email_remember'] = !empty($data['somdn_capture_email_remember']) ? 'on' : '';

  // Remember Duration (1-365 days)
  $remember_time = isset($data['somdn_capture_email_remember_time']) ? $data['somdn_capture_email_remember_time'] : '30';
  if ($remember_time !== '' && $remember_time !== null) {
    $remember_time = absint($remember_time);
    $remember_time = max(1, min($remember_time, 365));
    $out['somdn_capture_email_remember_time'] = $remember_time;
  } else {
    $out['somdn_capture_email_remember_time'] = 30;
  }

  // Show for Registered Users
  $out['somdn_capture_email_users_enable'] = !empty($data['somdn_capture_email_users_enable']) ? 'on' : '';

  // Newsletter Subscription: allow 0, manual, mailchimp, and any value from somdn_sub_options (e.g. addon providers).
  $subscribe = isset($data['somdn_capture_email_subscribe']) ? sanitize_text_field($data['somdn_capture_email_subscribe']) : '0';
  $allowed_subscribe = array_keys(apply_filters('somdn_sub_options', array('manual' => 'Manual', 'mailchimp' => 'MailChimp')));
  $allowed_subscribe[] = '0';
  $out['somdn_capture_email_subscribe'] = in_array($subscribe, $allowed_subscribe, true) ? $subscribe : '0';

  // Box Title
  $out['somdn_capture_email_title'] = sanitize_text_field($data['somdn_capture_email_title'] ?? '');

  // Email Placeholder
  $out['somdn_capture_email_placeholder'] = sanitize_text_field($data['somdn_capture_email_placeholder'] ?? '');

  // Error Message - No Email
  $out['somdn_capture_email_error_none'] = sanitize_text_field($data['somdn_capture_email_error_none'] ?? '');

  // Error Message - Invalid Email
  $out['somdn_capture_email_error_invalid'] = sanitize_text_field($data['somdn_capture_email_error_invalid'] ?? '');

  // Title Background Color (hex color)
  $out['somdn_capture_email_title_bg'] = $sanitize_color_value(
    $data['somdn_capture_email_title_bg'] ?? null,
    '#2679ce'
  );

  // Title Font Color (hex color)
  $out['somdn_capture_email_title_colour'] = $sanitize_color_value(
    $data['somdn_capture_email_title_colour'] ?? null,
    '#ffffff'
  );

  // Box Text (allow HTML)
  $out['somdn_capture_email_body'] = wp_kses_post($data['somdn_capture_email_body'] ?? '');

  // Button Text
  $out['somdn_capture_email_button'] = sanitize_text_field($data['somdn_capture_email_button'] ?? '');

  // ==========================================================================
  // DOWNLOAD HISTORY
  // ==========================================================================
  
  // Enable Download History
  $out['somdn_download_history_enable'] = !empty($data['somdn_download_history_enable']) ? 'on' : '';

  // Hide Purchased Downloads Section
  $out['somdn_download_history_hide_purchase'] = !empty($data['somdn_download_history_hide_purchase']) ? 'on' : '';

  // Show Section Title Heading
  $out['somdn_download_history_title_enable'] = !empty($data['somdn_download_history_title_enable']) ? 'on' : '';

  // Section Title
  $out['somdn_download_history_title'] = sanitize_text_field($data['somdn_download_history_title'] ?? '');

  // Section Message (allow HTML)
  $out['somdn_download_history_message'] = wp_kses_post($data['somdn_download_history_message'] ?? '');

  // No Downloads Text (allow HTML)
  $out['somdn_download_no_results_text'] = wp_kses_post($data['somdn_download_no_results_text'] ?? '');

  // Button Text
  $out['somdn_download_history_button'] = sanitize_text_field($data['somdn_download_history_button'] ?? '');

  return $out;
}, 10, 2);

