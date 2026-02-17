<?php
/**
 * Sanitizer for Membership Limit Settings (somdn_pro_membership_limit_settings)
 * 
 * PRO ONLY - This sanitizer only runs when PRO is active.
 * These settings are only applicable when WooCommerce Memberships is active.
 */

add_filter('wpe_settings_sanitize_free-downloads-membership-limits', function($sanitized, $data) {
  $out = [];
  
  // Only save if Pro is active (security check)
  if (!defined('SOMDN_PRO_VERSION')) {
    return $out;
  }

  // Exclude Memberships from Limitations
  $out['somdn_pro_limit_member_exclude'] = !empty($data['somdn_pro_limit_member_exclude']) ? 'on' : '';

  // Refresh on Subscribe Date
  $out['somdn_pro_limit_refresh_on_subscribe_date'] = !empty($data['somdn_pro_limit_refresh_on_subscribe_date']) ? 'on' : '';

  return $out;
}, 10, 2);

