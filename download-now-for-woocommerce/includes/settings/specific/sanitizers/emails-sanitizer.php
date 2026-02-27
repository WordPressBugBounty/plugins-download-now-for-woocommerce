<?php
/**
 * Sanitizer for Email Settings (somdn_email_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-emails', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // GLOBAL EMAIL SETTINGS
  // ==========================================================================
  
  // From Name
  $out['somdn_email_settings_sender_name'] = sanitize_text_field($data['somdn_email_settings_sender_name'] ?? '');

  // From Address (email validation)
  $sender_address = isset($data['somdn_email_settings_sender_address']) ? sanitize_email($data['somdn_email_settings_sender_address']) : '';
  $out['somdn_email_settings_sender_address'] = $sender_address;

  // Content Type (0 = HTML, 1 = Plain Text)
  $content_type = isset($data['somdn_email_settings_content_type']) ? sanitize_text_field($data['somdn_email_settings_content_type']) : '0';
  $out['somdn_email_settings_content_type'] = in_array($content_type, ['0', '1'], true) ? $content_type : '0';

  // ==========================================================================
  // DOWNLOAD LINKS EMAIL
  // ==========================================================================
  
  // Email Subject
  $out['somdn_email_download_url_subject'] = sanitize_text_field($data['somdn_email_download_url_subject'] ?? '');

  // Email Heading
  $out['somdn_email_download_url_heading'] = sanitize_text_field($data['somdn_email_download_url_heading'] ?? '');

  // Email Body (allow some HTML via wp_kses_post)
  $out['somdn_email_download_url_message'] = wp_kses_post($data['somdn_email_download_url_message'] ?? '');

  // ==========================================================================
  // NEW FREE DOWNLOADS EMAIL (ADMIN NOTIFICATION)
  // ==========================================================================
  
  // Enable Email Notifications
  $out['somdn_email_new_download_enable'] = !empty($data['somdn_email_new_download_enable']) ? 'on' : '';

  // Email Recipients (comma-separated emails)
  $sendto = isset($data['somdn_email_new_download_sendto']) ? sanitize_text_field($data['somdn_email_new_download_sendto']) : '';
  // Clean up multiple emails - sanitize each individually
  if (!empty($sendto)) {
    $emails = array_map('trim', explode(',', $sendto));
    $emails = array_filter(array_map('sanitize_email', $emails));
    $sendto = implode(', ', $emails);
  }
  $out['somdn_email_new_download_sendto'] = $sendto;

  // Email Subject
  $out['somdn_email_new_download_subject'] = sanitize_text_field($data['somdn_email_new_download_subject'] ?? '');

  // Email Heading
  $out['somdn_email_new_download_heading'] = sanitize_text_field($data['somdn_email_new_download_heading'] ?? '');

  // Email Body (allow some HTML via wp_kses_post)
  $out['somdn_email_new_download_message'] = wp_kses_post($data['somdn_email_new_download_message'] ?? '');

  return $out;
}, 10, 2);

