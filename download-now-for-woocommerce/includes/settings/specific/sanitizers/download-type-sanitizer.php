<?php
/**
 * Sanitizer for Download Delivery Settings (somdn_download_type_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-download-type', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // DELIVERY TYPE
  // ==========================================================================
  
  // Download Type Option (0 = instant, 1 = redirect, 2 = email link)
  $download_type = isset($data['somdn_download_type_option']) ? $data['somdn_download_type_option'] : '0';
  $out['somdn_download_type_option'] = in_array($download_type, ['0', '1', '2'], true) ? $download_type : '0';

  // ==========================================================================
  // REDIRECT PAGE SETTINGS
  // ==========================================================================
  
  // Redirect Page (page ID)
  $redirect_page = isset($data['somdn_download_type_redirect_page']) ? $data['somdn_download_type_redirect_page'] : '';
  $out['somdn_download_type_redirect_page'] = $redirect_page !== '' ? absint($redirect_page) : '';

  // Redirect Time (1-60 seconds)
  $redirect_time = isset($data['somdn_download_type_redirect_time']) ? $data['somdn_download_type_redirect_time'] : '5';
  if ($redirect_time !== '' && $redirect_time !== null) {
    $redirect_time = absint($redirect_time);
    $redirect_time = max(1, min($redirect_time, 60));
    $out['somdn_download_type_redirect_time'] = $redirect_time;
  } else {
    $out['somdn_download_type_redirect_time'] = 5;
  }

  // Redirect Message (allow HTML)
  $out['somdn_download_type_redirect_message'] = wp_kses_post($data['somdn_download_type_redirect_message'] ?? '');

  // Redirect "Click Here" Text
  $out['somdn_download_type_redirect_text'] = sanitize_text_field($data['somdn_download_type_redirect_text'] ?? '');

  // ==========================================================================
  // EMAIL LINK SETTINGS
  // ==========================================================================
  
  // Email Confirmation Page (page ID)
  $email_page = isset($data['somdn_download_type_email_page']) ? $data['somdn_download_type_email_page'] : '';
  $out['somdn_download_type_email_page'] = $email_page !== '' ? absint($email_page) : '';

  // Email Page Message (allow HTML)
  $out['somdn_download_type_email_page_message'] = wp_kses_post($data['somdn_download_type_email_page_message'] ?? '');

  // Email Link Expiry Time (1-168 hours)
  $expire_time = isset($data['somdn_download_type_email_expire_time']) ? $data['somdn_download_type_email_expire_time'] : '24';
  if ($expire_time !== '' && $expire_time !== null) {
    $expire_time = absint($expire_time);
    $expire_time = max(1, min($expire_time, 168));
    $out['somdn_download_type_email_expire_time'] = $expire_time;
  } else {
    $out['somdn_download_type_email_expire_time'] = 24;
  }

  // Email Expire When Used (checkbox/toggle)
  $out['somdn_download_type_email_expire_used'] = !empty($data['somdn_download_type_email_expire_used']) ? 'on' : '';

  // Expired Link Message
  $out['somdn_download_type_email_expire_message'] = sanitize_text_field($data['somdn_download_type_email_expire_message'] ?? '');

  // Used Link Message
  $out['somdn_download_type_email_used_message'] = sanitize_text_field($data['somdn_download_type_email_used_message'] ?? '');

  return $out;
}, 10, 2);

