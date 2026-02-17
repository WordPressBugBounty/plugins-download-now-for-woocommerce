<?php
/**
 * Sanitizer for Newsletter General Settings (somdn_pro_newsletter_general_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-newsletter', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // ==========================================================================
  // DISPLAY SETTINGS
  // ==========================================================================
  
  // Display Type (0 = checkbox with text, 1 = text only, 2 = none, 3 = required checkbox)
  $display_type = isset($data['somdn_newsletter_display_type']) ? sanitize_text_field($data['somdn_newsletter_display_type']) : '0';
  $out['somdn_newsletter_display_type'] = in_array($display_type, ['0', '1', '2', '3'], true) ? $display_type : '0';

  // Checkbox Error Message
  $out['somdn_newsletter_checkbox_error'] = sanitize_text_field($data['somdn_newsletter_checkbox_error'] ?? '');

  // Newsletter Text
  $out['somdn_newsletter_text'] = sanitize_text_field($data['somdn_newsletter_text'] ?? '');

  // ==========================================================================
  // FIRST NAME FIELDS
  // ==========================================================================
  
  // First Name Placeholder
  $out['somdn_newsletter_fname_placeholder'] = sanitize_text_field($data['somdn_newsletter_fname_placeholder'] ?? '');

  // First Name Error Message
  $out['somdn_newsletter_fname_error'] = sanitize_text_field($data['somdn_newsletter_fname_error'] ?? '');

  // Add First Name to Email Notification
  $out['somdn_newsletter_fname_email'] = !empty($data['somdn_newsletter_fname_email']) ? 'on' : '';

  // ==========================================================================
  // LAST NAME FIELDS
  // ==========================================================================
  
  // Enable Last Name Field
  $out['somdn_newsletter_lname'] = !empty($data['somdn_newsletter_lname']) ? 'on' : '';

  // Last Name Placeholder
  $out['somdn_newsletter_lname_placeholder'] = sanitize_text_field($data['somdn_newsletter_lname_placeholder'] ?? '');

  // Make Last Name Required
  $out['somdn_newsletter_lname_required'] = !empty($data['somdn_newsletter_lname_required']) ? 'on' : '';

  // Last Name Error Message
  $out['somdn_newsletter_lname_error'] = sanitize_text_field($data['somdn_newsletter_lname_error'] ?? '');

  // Add Last Name to Email Notification
  $out['somdn_newsletter_lname_email'] = !empty($data['somdn_newsletter_lname_email']) ? 'on' : '';

  // ==========================================================================
  // TELEPHONE FIELDS
  // ==========================================================================
  
  // Enable Telephone Field
  $out['somdn_newsletter_tel'] = !empty($data['somdn_newsletter_tel']) ? 'on' : '';

  // Telephone Placeholder
  $out['somdn_newsletter_tel_placeholder'] = sanitize_text_field($data['somdn_newsletter_tel_placeholder'] ?? '');

  // Make Telephone Required
  $out['somdn_newsletter_tel_required'] = !empty($data['somdn_newsletter_tel_required']) ? 'on' : '';

  // Telephone Error Message
  $out['somdn_newsletter_tel_error'] = sanitize_text_field($data['somdn_newsletter_tel_error'] ?? '');

  // Add Telephone to Email Notification
  $out['somdn_newsletter_tel_email'] = !empty($data['somdn_newsletter_tel_email']) ? 'on' : '';

  // ==========================================================================
  // COMPANY FIELDS
  // ==========================================================================
  
  // Enable Company Field
  $out['somdn_newsletter_company'] = !empty($data['somdn_newsletter_company']) ? 'on' : '';

  // Company Placeholder
  $out['somdn_newsletter_company_placeholder'] = sanitize_text_field($data['somdn_newsletter_company_placeholder'] ?? '');

  // Make Company Required
  $out['somdn_newsletter_company_required'] = !empty($data['somdn_newsletter_company_required']) ? 'on' : '';

  // Company Error Message
  $out['somdn_newsletter_company_error'] = sanitize_text_field($data['somdn_newsletter_company_error'] ?? '');

  // Add Company to Email Notification
  $out['somdn_newsletter_company_email'] = !empty($data['somdn_newsletter_company_email']) ? 'on' : '';

  // ==========================================================================
  // WEBSITE FIELDS
  // ==========================================================================
  
  // Enable Website Field
  $out['somdn_newsletter_website'] = !empty($data['somdn_newsletter_website']) ? 'on' : '';

  // Website Placeholder
  $out['somdn_newsletter_website_placeholder'] = sanitize_text_field($data['somdn_newsletter_website_placeholder'] ?? '');

  // Make Website Required
  $out['somdn_newsletter_website_required'] = !empty($data['somdn_newsletter_website_required']) ? 'on' : '';

  // Website Error Message
  $out['somdn_newsletter_website_error'] = sanitize_text_field($data['somdn_newsletter_website_error'] ?? '');

  // Add Website to Email Notification
  $out['somdn_newsletter_website_email'] = !empty($data['somdn_newsletter_website_email']) ? 'on' : '';

  // ==========================================================================
  // CONFIRMATIONS
  // ==========================================================================
  
  // Receive Confirmation Emails
  $out['somdn_newsletter_confirmations'] = !empty($data['somdn_newsletter_confirmations']) ? 'on' : '';

  // Receive Error Notifications
  $out['somdn_newsletter_confirmations_errors'] = !empty($data['somdn_newsletter_confirmations_errors']) ? 'on' : '';

  return $out;
}, 10, 2);

