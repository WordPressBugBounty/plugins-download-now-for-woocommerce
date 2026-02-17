<?php
/**
 * Sanitizer for MailChimp Settings (somdn_pro_newsletter_mailchimp_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-mailchimp', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // MAILCHIMP SETTINGS
  // ==========================================================================
  
  // API Key
  $out['somdn_newsletter_mailchimp_api_key'] = sanitize_text_field($data['somdn_newsletter_mailchimp_api_key'] ?? '');

  // List ID
  $out['somdn_newsletter_mailchimp_list_id'] = sanitize_text_field($data['somdn_newsletter_mailchimp_list_id'] ?? '');

  // Double Opt-In
  $out['somdn_newsletter_mailchimp_doubleoptin'] = !empty($data['somdn_newsletter_mailchimp_doubleoptin']) ? 'on' : '';

  // Tags (allow commas and placeholders like {product_id}, {product_name}, etc.)
  $out['somdn_newsletter_tags'] = sanitize_text_field($data['somdn_newsletter_tags'] ?? '');

  return $out;
}, 10, 2);

