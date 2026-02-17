<?php
/**
 * Sanitizer for Tracking & Email Capture Settings (somdn_pro_track_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-tracking', function($sanitized, $data) {
  $out = [];
  
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

  // Newsletter Subscription (0 = none, manual, mailchimp)
  $subscribe = isset($data['somdn_capture_email_subscribe']) ? sanitize_text_field($data['somdn_capture_email_subscribe']) : '0';
  $out['somdn_capture_email_subscribe'] = in_array($subscribe, ['0', 'manual', 'mailchimp'], true) ? $subscribe : '0';

  // Box Title
  $out['somdn_capture_email_title'] = sanitize_text_field($data['somdn_capture_email_title'] ?? '');

  // Email Placeholder
  $out['somdn_capture_email_placeholder'] = sanitize_text_field($data['somdn_capture_email_placeholder'] ?? '');

  // Error Message - No Email
  $out['somdn_capture_email_error_none'] = sanitize_text_field($data['somdn_capture_email_error_none'] ?? '');

  // Error Message - Invalid Email
  $out['somdn_capture_email_error_invalid'] = sanitize_text_field($data['somdn_capture_email_error_invalid'] ?? '');

  // Title Background Color (hex color)
  $title_bg = isset($data['somdn_capture_email_title_bg']) ? sanitize_hex_color($data['somdn_capture_email_title_bg']) : '#2679ce';
  $out['somdn_capture_email_title_bg'] = $title_bg ?: '#2679ce';

  // Title Font Color (hex color)
  $title_colour = isset($data['somdn_capture_email_title_colour']) ? sanitize_hex_color($data['somdn_capture_email_title_colour']) : '#ffffff';
  $out['somdn_capture_email_title_colour'] = $title_colour ?: '#ffffff';

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

