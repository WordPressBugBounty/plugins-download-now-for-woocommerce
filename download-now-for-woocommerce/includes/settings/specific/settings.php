<?php
/**
 * Plugin-specific settings registration for Free Downloads WooCommerce.
 * 
 * This file contains all plugin-specific code that should NOT be overwritten
 * when syncing from the settings-framework. It includes:
 * - Option name filters for this plugin's settings pages
 * - Settings sanitization filters (via separate files)
 * - Plugin-specific REST endpoints
 * - Typesense search configuration
 * - Pro detection localization
 * 
 * The common settings endpoint in organization/wp-enhanced/rest-endpoints/
 * remains plugin-agnostic and can be safely synced from settings-framework.
 */

// =============================================================================
// LOAD PLUGIN-SPECIFIC REST ENDPOINTS
// =============================================================================

// Configuration check endpoint (auto-initializes)
require_once __DIR__ . '/class-config-check-endpoint.php';

// Integration test endpoint (auto-initializes)
require_once __DIR__ . '/class-integration-test-endpoint.php';

// Stats REST API endpoints (Pro feature)
if (defined('SOMDN_PRO_VERSION')) {
    require_once __DIR__ . '/rest-endpoints/class-stats-endpoint.php';
    \SOM\FreeDownloads\REST\Stats_Endpoint::init();
    // Addon license endpoints (get/validate/deactivate); addons register via filter somdn_addon_licenses
    require_once __DIR__ . '/rest-endpoints/class-addon-license-endpoint.php';
}

// =============================================================================
// SETTINGS FRAMEWORK PLUGIN REGISTRATION
// =============================================================================

add_action('wpe_settings_register_plugin', 'somdn_register_settings_plugin');

function somdn_register_settings_plugin($registry) {
    // Build URL to the pages bundle
    // __FILE__ is in /includes/settings/specific/settings.php
    // We need URL to /includes/settings/dist/free-downloads-woocommerce-pages.js
    //
    // plugin_dir_url(__FILE__) returns URL to /includes/settings/specific/
    // So we go up one level (../) to get to /includes/settings/ then into dist/
    $script_url = plugin_dir_url(__FILE__) . '../dist/free-downloads-woocommerce-pages.js';

    // Use plugin version + filemtime of built script for cache busting when the JS file changes
    $base_version = defined('SOMDN_VERSION') ? SOMDN_VERSION : '1.0.1';
    $script_path  = dirname(__FILE__) . '/../dist/free-downloads-woocommerce-pages.js';
    $script_ver   = file_exists($script_path) ? $base_version . '.' . filemtime($script_path) : $base_version;

    $registry->add(array(
        'slug'              => 'free-downloads-woocommerce',
        'label'             => 'Free Downloads WooCommerce',
        'script_url'        => $script_url,
        'version'           => $script_ver,
        'framework_version' => '2.0.0',
    ));
}

// =============================================================================
// LOCAL DEV: bust core settings-app.js cache (Vite watch + static framework ver)
// =============================================================================
// npm run start:free / start:pro writes .dev/free-mode; core bundle still uses a
// fixed wp_enqueue_script version otherwise, so the browser keeps old JS.

add_action( 'admin_enqueue_scripts', 'somdn_bust_settings_core_script_for_local_dev', 999 );

function somdn_bust_settings_core_script_for_local_dev() {
    $plugin_root = dirname( __FILE__, 4 );
    if ( ! is_readable( $plugin_root . '/.dev/free-mode' ) ) {
        return;
    }
    $dist_path = dirname( __FILE__, 2 ) . '/dist/settings-app.js';
    if ( ! is_readable( $dist_path ) ) {
        return;
    }
    $ver = (string) filemtime( $dist_path );
    $wp_scripts = wp_scripts();
    foreach ( array( 'wpe-settings-core', 'de-settings-core' ) as $handle ) {
        if ( isset( $wp_scripts->registered[ $handle ] ) ) {
            $wp_scripts->registered[ $handle ]->ver = $ver;
        }
    }
}

// =============================================================================
// ADMIN SUBMENU REGISTRATION
// =============================================================================

add_action( 'admin_menu', 'somdn_add_submenu', 30 );

function somdn_add_submenu() {
    global $submenu;

    // Ensure the main menu exists first
    if ( ! isset( $submenu['wp-enhanced'] ) ) {
        return;
    }

    // Add submenu under WP Enhanced with custom URL including hash
    // Structure: [0] = Menu title, [1] = Capability, [2] = URL
    $submenu['wp-enhanced'][] = array(
        'Free Downloads Woo',
        'manage_options',
        'admin.php?page=wp-enhanced#free-downloads-woocommerce'
    );
}

// =============================================================================
// TYPESENSE SEARCH CONFIGURATION
// =============================================================================

add_filter('wpe_typesense_configs', 'somdn_register_typesense_config', 10, 1);

function somdn_register_typesense_config($configs) {
  $configs[] = array(
    'searchOnlyApiKey' => 'q6o1PCxsm5f0awphPnTpFPJEyejgNE6r',
    'nodes' => array(
      array('host' => 'search.wpenhanced.com', 'port' => 443, 'protocol' => 'https'),
    ),
    'collection' => 'free-downloads-woocommerce',
  );
  return $configs;
}

// =============================================================================
// PRO DETECTION LOCALIZATION
// =============================================================================

// Use admin_head to output the script BEFORE any other scripts load
// This ensures window.somdn_settings is available when React initializes
add_action('admin_head', 'somdn_localize_pro_status_in_head', 5);

function somdn_localize_pro_status_in_head() {
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
  if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'wp-enhanced' ) {
    return;
  }

  // Check for WooCommerce Memberships
  $has_memberships = false;
  if (function_exists('somdn_memberships')) {
    $has_memberships = somdn_memberships();
  }

  // Check if Pro is actually active and licensed
  // Note: SOMDN_PRO_VERSION is always defined in this repo (Pro version)
  // So we must check license status to determine if Pro features should be enabled
  $is_pro = false;
  
  if (defined('SOMDN_PRO_VERSION')) {
    // First check old license status format
    $license_status = get_option('somdn_pro_license_status', '');
    if ($license_status === 'valid') {
      $is_pro = true;
    } else {
      // Check new WP Enhanced license format
      // Only set to true if license key exists AND has been validated
      $wpe_licenses = get_option('wp_enhanced_licenses', array());
      $plugin_slug = 'free-downloads-woocommerce-pro';
      
      // Check if license exists in new format
      if (isset($wpe_licenses[$plugin_slug]) && !empty(trim($wpe_licenses[$plugin_slug]))) {
        // License key exists - check if it's been validated
        // The license system validates server-side, so if key exists we assume it's valid
        // until proven otherwise by the license validation endpoint
        $is_pro = true;
      }
    }
  }
  
  // If no valid license found, is_pro remains false (no Pro badges will show)

  // Output script directly in head to ensure it loads before React.
  // rest_path is path-only (e.g. /free-downloads-woocommerce/wp-json/) so the frontend
  // can always build URLs as origin + rest_path and never double the host (localhost, etc.).
  $rest_url = rest_url();
  $rest_path = wp_parse_url($rest_url, PHP_URL_PATH);
  if ($rest_path === null || $rest_path === false) {
    $rest_path = '/wp-json/';
  }
  $rest_path = '/' . trim($rest_path, '/') . '/';

  // Addon sections: allow plugins (e.g. CRM addon) to inject a nav item + script.
  $addon_sections = apply_filters('somdn_settings_addon_sections', array());

  // Newsletter subscription dropdown: same filter as legacy Tracking UI so addons (e.g. CRM) can add options.
  $newsletter_subscribe_options = array(
    array( 'label' => '— None —', 'value' => '0' ),
  );
  if ( $is_pro ) {
    $sub_options = apply_filters( 'somdn_sub_options', array( 'manual' => 'Manual', 'mailchimp' => 'MailChimp' ) );
    if ( is_array( $sub_options ) ) {
      foreach ( $sub_options as $value => $label ) {
        $newsletter_subscribe_options[] = array(
          'label' => is_string( $label ) ? $label : (string) $label,
          'value' => is_string( $value ) ? $value : (string) $value,
        );
      }
    }
  }

  $settings_data = wp_json_encode(array(
    'is_pro' => $is_pro,
    'has_memberships' => $has_memberships,
    'rest_url' => esc_url_raw($rest_url),
    'rest_path' => $rest_path,
    'nonce' => wp_create_nonce('wp_rest'),
    'export_url' => admin_url('admin-post.php?action=somdn_stats_export'),
    'export_nonce' => wp_create_nonce('somdn_stats_export'),
    'addon_sections' => $addon_sections,
    'newsletter_subscribe_options' => $newsletter_subscribe_options,
    'can_delete_logs' => current_user_can('manage_options'),
  ));

  echo '<script id="somdn-settings-data">window.somdn_settings = ' . $settings_data . ';</script>' . "\n";

  // Enqueue addon scripts so they can mount into their placeholder divs.
  foreach ($addon_sections as $addon) {
    if ( ! empty($addon['script_url']) ) {
      wp_enqueue_script(
        'somdn-addon-' . sanitize_key($addon['key']),
        esc_url_raw($addon['script_url']),
        array(),
        isset($addon['version']) ? $addon['version'] : '1.0.1',
        true
      );
    }
  }
}

// =============================================================================
// LICENSE FRAMEWORK REGISTRATION (PRO ONLY)
// =============================================================================

// Always register filters (they check for Pro internally if needed)
// This ensures the plugin appears in License Management even if Pro constant isn't defined yet

/**
 * Register this plugin with the WP Enhanced license framework.
 * This makes it appear in the License page of the settings.
 */
add_filter('wp_enhanced_plugins', 'somdn_register_plugin_for_license', 10, 1);
function somdn_register_plugin_for_license($plugins) {
  // Debug: Log registration attempt
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[SOMDN License] Registration called. SOMDN_PRO_VERSION defined: ' . (defined('SOMDN_PRO_VERSION') ? 'YES (' . SOMDN_PRO_VERSION . ')' : 'NO'));
  }
  
  // Only register if Pro version is available
  if (defined('SOMDN_PRO_VERSION')) {
    $plugins['free-downloads-woocommerce-pro'] = 'Free Downloads WooCommerce Pro';
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[SOMDN License] Plugin registered successfully');
    }
  } else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[SOMDN License] Plugin NOT registered - SOMDN_PRO_VERSION not defined');
    }
  }
  return $plugins;
}

/**
 * Register the plugin's item ID for license validation.
 * This ID must match the product ID on the wpenhanced.com store.
 */
add_filter('wp_enhanced_plugin_ids', 'somdn_register_plugin_id_for_license', 10, 1);
function somdn_register_plugin_id_for_license($plugin_ids) {
  // Debug: Log ID registration attempt
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[SOMDN License] ID registration called. SOMDN_PRO_VERSION defined: ' . (defined('SOMDN_PRO_VERSION') ? 'YES' : 'NO'));
  }
  
  // Only register if Pro version is available
  if (defined('SOMDN_PRO_VERSION')) {
    // Use the constant if available, otherwise use the known ID
    $item_id = defined('SOM_SOMDN_ITEM_ID') ? SOM_SOMDN_ITEM_ID : 73;
    $plugin_ids['free-downloads-woocommerce-pro'] = $item_id;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[SOMDN License] Plugin ID registered: ' . $item_id);
    }
  } else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[SOMDN License] Plugin ID NOT registered - SOMDN_PRO_VERSION not defined');
    }
  }
  return $plugin_ids;
}

if (defined('SOMDN_PRO_VERSION')) {

  /**
   * Migrate existing license key from old format to new WP Enhanced format.
   * This runs once to ensure existing users don't lose their license.
   */
  add_action('admin_init', 'somdn_migrate_license_to_wpe_format', 5);
  function somdn_migrate_license_to_wpe_format() {
    // Check if migration has already been done
    $migration_done = get_option('somdn_license_migrated_to_wpe', false);
    if ($migration_done) {
      return;
    }

    // Get the old license key
    $old_license_key = get_option('somdn_pro_license_key', '');
    $old_license_status = get_option('somdn_pro_license_status', '');

    // Migrate if there's a license key (even if status isn't 'valid')
    // This ensures the license appears in License Management so user can reactivate if needed
    if (!empty($old_license_key)) {
      $wpe_licenses = get_option('wp_enhanced_licenses', array());
      
      // Only add if not already present in new format
      if (empty($wpe_licenses['free-downloads-woocommerce-pro'])) {
        $wpe_licenses['free-downloads-woocommerce-pro'] = $old_license_key;
        update_option('wp_enhanced_licenses', $wpe_licenses);
      }
    }

    // Mark migration as complete (even if no license to migrate)
    update_option('somdn_license_migrated_to_wpe', true);
  }

  /**
   * Keep old license key in sync with new format for backward compatibility.
   * This ensures the updater continues to work.
   */
  add_action('update_option_wp_enhanced_licenses', 'somdn_sync_license_to_old_format', 10, 2);
  function somdn_sync_license_to_old_format($old_value, $new_value) {
    if (isset($new_value['free-downloads-woocommerce-pro'])) {
      $license_key = $new_value['free-downloads-woocommerce-pro'];
      
      if (!empty($license_key)) {
        // Update the old option for backward compatibility with updater
        update_option('somdn_pro_license_key', $license_key);
        
        // Also update status if we can validate it
        // The updater will handle actual validation
        $current_status = get_option('somdn_pro_license_status', '');
        if (empty($current_status) || $current_status !== 'valid') {
          // Don't auto-validate, just keep the key in sync
          // The updater will validate on next check
        }
      }
    }
  }

}

// =============================================================================
// OPTION NAME FILTERS - FREE
// =============================================================================

add_filter('wpe_option_name_free-downloads-gen', function() {
  return 'somdn_gen_settings';
});

add_filter('wpe_option_name_free-downloads-single', function() {
  return 'somdn_single_settings';
});

add_filter('wpe_option_name_free-downloads-multi', function() {
  return 'somdn_multi_settings';
});

add_filter('wpe_option_name_free-downloads-owned', function() {
  return 'somdn_owned_settings';
});

add_filter('wpe_option_name_free-downloads-docviewer', function() {
  return 'somdn_docviewer_settings';
});

add_filter('wpe_option_name_free-downloads-debug', function() {
  return 'somdn_debug_settings';
});

add_filter('wpe_option_name_free-downloads-quickview', function() {
  return 'somdn_woo_quickview_settings';
});

// =============================================================================
// OPTION NAME FILTERS - PRO
// =============================================================================

if (defined('SOMDN_PRO_VERSION')) {

  add_filter('wpe_option_name_free-downloads-limits', function() {
    return 'somdn_pro_basic_limit_settings';
  });

  add_filter('wpe_option_name_free-downloads-membership-limits', function() {
    return 'somdn_pro_membership_limit_settings';
  });

  add_filter('wpe_option_name_free-downloads-tracking', function() {
    return 'somdn_pro_track_settings';
  });

  add_filter('wpe_option_name_free-downloads-newsletter', function() {
    return 'somdn_pro_newsletter_general_settings';
  });

  add_filter('wpe_option_name_free-downloads-mailchimp', function() {
    return 'somdn_pro_newsletter_mailchimp_settings';
  });

  add_filter('wpe_option_name_free-downloads-download-type', function() {
    return 'somdn_download_type_settings';
  });

  add_filter('wpe_option_name_free-downloads-include-products', function() {
    return 'somdn_pro_include_product_settings';
  });

  add_filter('wpe_option_name_free-downloads-include-cats', function() {
    return 'somdn_pro_include_cat_settings';
  });

  add_filter('wpe_option_name_free-downloads-include-tags', function() {
    return 'somdn_pro_include_tag_settings';
  });

  add_filter('wpe_option_name_free-downloads-emails', function() {
    return 'somdn_email_settings';
  });

}

// =============================================================================
// SETTINGS SANITIZATION FILTERS - Load from separate files
// =============================================================================

$sanitizers_dir = __DIR__ . '/sanitizers/';

// FREE sanitizers
require_once $sanitizers_dir . 'general-sanitizer.php';
require_once $sanitizers_dir . 'single-files-sanitizer.php';
require_once $sanitizers_dir . 'multiple-files-sanitizer.php';
require_once $sanitizers_dir . 'owned-products-sanitizer.php';
require_once $sanitizers_dir . 'pdf-sanitizer.php';
require_once $sanitizers_dir . 'quick-view-sanitizer.php';
require_once $sanitizers_dir . 'debug-sanitizer.php';

// PRO sanitizers - only load when PRO is active
if (defined('SOMDN_PRO_VERSION')) {
  require_once $sanitizers_dir . 'limits-sanitizer.php';
  require_once $sanitizers_dir . 'membership-limits-sanitizer.php';
  require_once $sanitizers_dir . 'tracking-sanitizer.php';
  require_once $sanitizers_dir . 'newsletter-sanitizer.php';
  require_once $sanitizers_dir . 'mailchimp-sanitizer.php';
  require_once $sanitizers_dir . 'download-type-sanitizer.php';
  require_once $sanitizers_dir . 'include-products-sanitizer.php';
  require_once $sanitizers_dir . 'include-cats-sanitizer.php';
  require_once $sanitizers_dir . 'include-tags-sanitizer.php';
  require_once $sanitizers_dir . 'emails-sanitizer.php';
}
