<?php
/**
 * WP Enhanced Settings Framework
 * 
 * Handles the shared settings page for all WP Enhanced plugins.
 * Supports multiple plugins registering their settings pages at runtime.
 * 
 * @package WP_Enhanced_Settings
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Framework version - increment this with each release
if ( ! defined( 'WPE_SETTINGS_FRAMEWORK_VERSION' ) ) {
    define( 'WPE_SETTINGS_FRAMEWORK_VERSION', '2.0.0' );
}

// Define path constant, if not already defined
if ( ! defined( 'DE_WPE_SETTINGS_PATH' ) ) {
    define( 'DE_WPE_SETTINGS_PATH', plugin_dir_path( __FILE__ ) );
}

// Define URL constant, if not already defined
if ( ! defined( 'DE_WPE_SETTINGS_URL' ) ) {
    define( 'DE_WPE_SETTINGS_URL', plugin_dir_url( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) );
}

/**
 * Plugin Registry for WP Enhanced Settings Framework
 * 
 * Allows plugins to register themselves for the settings page.
 */
if ( ! class_exists( 'WPE_Settings_Plugin_Registry' ) ) {
    
    class WPE_Settings_Plugin_Registry {
        
        /**
         * Add a plugin to the registry
         * 
         * @param array $config Plugin configuration
         *   - slug: (string) Unique plugin identifier
         *   - label: (string) Display name for navigation
         *   - script_url: (string) URL to the plugin's pages bundle JS
         *   - version: (string) Plugin version for cache busting
         *   - framework_version: (string) Version of framework this plugin ships with
         */
        public function add( $config ) {
            global $wpe_registered_settings_plugins;
            
            if ( ! isset( $wpe_registered_settings_plugins ) ) {
                $wpe_registered_settings_plugins = array();
            }
            
            $slug = sanitize_key( $config['slug'] ?? '' );
            if ( empty( $slug ) ) {
                return;
            }
            
            $wpe_registered_settings_plugins[ $slug ] = array(
                'slug'              => $slug,
                'label'             => sanitize_text_field( $config['label'] ?? $slug ),
                'script_url'        => esc_url( $config['script_url'] ?? '' ),
                'version'           => sanitize_text_field( $config['version'] ?? '1.0.0' ),
                'framework_version' => sanitize_text_field( $config['framework_version'] ?? '1.0.0' ),
            );
        }
    }
}

/**
 * Class WP_Enhanced_Settings
 * 
 * Main settings framework class for WP Enhanced plugins.
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'WP_Enhanced_Settings' ) ) {

    class WP_Enhanced_Settings {
        
        /**
         * Framework version for this instance
         * 
         * @var string
         */
        private $framework_version;
        
        /**
         * URL to this framework's directory
         * 
         * @var string
         */
        private $framework_url;
        
        /**
         * Constructor
         */
        public function __construct() {
            $this->framework_version = WPE_SETTINGS_FRAMEWORK_VERSION;
            $this->framework_url = DE_WPE_SETTINGS_URL . 'includes/settings/';
            
            // Define legacy constants for backwards compatibility
            if ( ! defined( 'WPE_SETTINGS_PATH' ) ) {
                define( 'WPE_SETTINGS_PATH', plugin_dir_path( __FILE__ ) );
            }
            if ( ! defined( 'WPE_SETTINGS_URL' ) ) {
                define( 'WPE_SETTINGS_URL', plugin_dir_url( __FILE__ ) );
            }
            
            // Register this framework version (priority 5 = early)
            add_action( 'plugins_loaded', array( $this, 'register_framework_version' ), 5 );
            
            // Fire hook for plugins to register (priority 10)
            add_action( 'plugins_loaded', array( $this, 'fire_registration_hook' ), 10 );
            
            // Standard admin hooks
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            
            // Enqueue scripts (priority 15 = after plugin registrations)
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 15 );
            
            add_action( 'admin_head', array( $this, 'admin_head_style' ) );

            add_action( 'wp_ajax_wpe_settings_get_post_list', array( $this, 'ajax_get_post_list' ) );
            
			// Load REST endpoints
			require_once WPE_SETTINGS_PATH . 'rest-endpoints/class-settings-endpoint.php';
			require_once WPE_SETTINGS_PATH . 'rest-endpoints/class-license-rest-endpoint.php';
			require_once WPE_SETTINGS_PATH . 'rest-endpoints/class-rest-endpoint.php';

			// Load shared REST endpoints (from common folder)
			$common_endpoints_path = dirname( dirname( WPE_SETTINGS_PATH ) ) . '/common/rest-endpoints/';
			if ( file_exists( $common_endpoints_path . 'class-error-logs-endpoint.php' ) ) {
				require_once $common_endpoints_path . 'class-error-logs-endpoint.php';
			}
		}
        
        /**
         * Register this framework version in the global registry
         */
        public function register_framework_version() {
            global $wpe_settings_frameworks;
            
            if ( ! isset( $wpe_settings_frameworks ) ) {
                $wpe_settings_frameworks = array();
            }
            
            // Only register if this version isn't already registered
            if ( ! isset( $wpe_settings_frameworks[ $this->framework_version ] ) ) {
                $wpe_settings_frameworks[ $this->framework_version ] = array(
                    'version'   => $this->framework_version,
                    'core_url'  => $this->framework_url . 'dist/settings-app.js',
                    'style_url' => $this->framework_url . 'dist/assets/',
                    'loader'    => $this,
                );
            }
        }
        
        /**
         * Fire the hook for plugins to register themselves
         */
        public function fire_registration_hook() {
            /**
             * Action: wpe_settings_register_plugin
             * 
             * Plugins should use this hook to register with the settings framework.
             * 
             * @param WPE_Settings_Plugin_Registry $registry The registry instance
             * 
             * @example
             * add_action( 'wpe_settings_register_plugin', function( $registry ) {
             *     $registry->add( array(
             *         'slug'              => 'free-downloads-woocommerce',
             *         'label'             => 'Free Downloads',
             *         'script_url'        => plugin_dir_url( __FILE__ ) . 'includes/settings/dist/free-downloads-pages.js',
             *         'version'           => '1.0.0',
             *         'framework_version' => '2.0.0',
             *     ) );
             * } );
             */
            do_action( 'wpe_settings_register_plugin', new WPE_Settings_Plugin_Registry() );
        }
        
        /**
         * Get the highest registered framework version
         * 
         * @return string|null Highest version or null if none registered
         */
        private function get_highest_framework_version() {
            global $wpe_settings_frameworks;
            
            if ( empty( $wpe_settings_frameworks ) ) {
                return null;
            }
            
            $versions = array_keys( $wpe_settings_frameworks );
            usort( $versions, 'version_compare' );
            
            return end( $versions );
        }
        
        /**
         * Check if this instance should load the core framework
         * 
         * @return bool True if this is the highest version
         */
        private function should_load_core() {
            $highest = $this->get_highest_framework_version();
            return $highest === $this->framework_version;
        }

        /**
         * Add settings page to the admin menu
         */
        public function admin_menu() {
            $icon = DE_WPE_SETTINGS_URL . 'includes/settings/organization/wp-enhanced/images/dash-icon.svg';
            
            add_menu_page(
                __( 'WP Enhanced Settings', 'download-now-for-woocommerce' ),
                __( 'WP Enhanced', 'download-now-for-woocommerce' ),
                'manage_options',
                'wp-enhanced',
                array( $this, 'admin_page' ),
                $icon,
                100
            );
        }

        /**
         * Render the admin page content
         */
        public function admin_page() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'wp-enhanced' ) {
                return;
            }

            echo '<div class="wrap">';
            echo '<div id="wp-enhanced-settings" data-organization="wp-enhanced"></div>';
            echo '</div>';
        }

        /**
         * Enqueue scripts and styles
         * 
         * @param string $hook Current admin page hook
         */
        public function admin_enqueue_scripts( $hook ) {
            global $wpe_registered_settings_plugins;
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
            
            if ( $current_page !== 'wp-enhanced' ) {
                return;
            }
            
            // Add admin style for menu icon
            wp_enqueue_style( 'wp-enhanced-admin-style', false );
            $custom_css = '.toplevel_page_wp-enhanced img { max-width: 16px; width: 100%; }';
            wp_add_inline_style( 'wp-enhanced-admin-style', $custom_css );
            
            // Only load core if we're the highest version
            if ( $this->should_load_core() ) {
                $this->enqueue_core_framework();
            }
            
            // Each plugin loads its own pages bundle
            $this->enqueue_plugin_bundles();
        }
        
        /**
         * Enqueue the core framework bundle
         */
        private function enqueue_core_framework() {
            global $wpe_registered_settings_plugins;
            
            $core_url = $this->framework_url . 'dist/settings-app.js';
            
            wp_enqueue_script(
                'wpe-settings-core',
                $core_url,
                array( 'wp-element' ),
                $this->framework_version,
                true
            );
            
            // Ensure React/ReactDOM are available (WordPress provides via wp-element)
            wp_add_inline_script(
                'wpe-settings-core',
                'window.React = window.React || ( window.wp && window.wp.element ); '
                . 'window.ReactDOM = window.ReactDOM || ( window.wp && window.wp.element );',
                'before'
            );
            
            // Pass WP data to the script
            wp_localize_script(
                'wpe-settings-core',
                'diviEngineApiSettings',
                array(
                    'nonce'            => wp_create_nonce( 'wp_rest' ),
                    'restUrl'          => esc_url_raw( rest_url() ),
                    'hasWoo'           => class_exists( 'WooCommerce' ),
                    'settingsUrl'      => DE_WPE_SETTINGS_URL,
                    'frameworkVersion' => $this->framework_version,
                )
            );
            
            // Pass registered plugins and organization info
            $typesense_configs = apply_filters( 'wpe_typesense_configs', array() );
            $plugins_data      = ! empty( $wpe_registered_settings_plugins ) 
                ? array_values( $wpe_registered_settings_plugins ) 
                : array();
            
            wp_add_inline_script(
                'wpe-settings-core',
                'window.diviEngineSettingsObject = window.diviEngineSettingsObject || {};'
                . 'window.diviEngineSettingsObject.organization = "wp-enhanced";'
                . 'window.diviEngineSettingsObject.frameworkVersion = ' . wp_json_encode( $this->framework_version ) . ';'
                . 'window.diviEngineSettingsObject.registeredPlugins = ' . wp_json_encode( $plugins_data ) . ';'
                . 'window.diviEngineSettingsObject.typesenseConfigs = ' . wp_json_encode( $typesense_configs ) . ';',
                'before'
            );

            $post_list_fallback = array(
                'url'    => admin_url( 'admin-ajax.php' ),
                'action' => 'wpe_settings_get_post_list',
                'nonce'  => wp_create_nonce( 'wpe_settings_post_list' ),
            );
            wp_add_inline_script(
                'wpe-settings-core',
                'window.wpeSettingsPostListFallback = ' . wp_json_encode( $post_list_fallback ) . ';',
                'before'
            );
        }

        /**
         * AJAX handler: return pages or posts list for post-type-select dropdowns.
         * Used by all plugins using the settings framework; one request per postType shared across fields.
         */
        public function ajax_get_post_list() {
            check_ajax_referer( 'wpe_settings_post_list', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array( 'message' => __( 'Forbidden.', 'download-now-for-woocommerce' ) ), 403 );
            }

            $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'pages';
            if ( $post_type === 'pages' ) {
                $post_type = 'page';
            } elseif ( $post_type === 'posts' ) {
                $post_type = 'post';
            } else {
                $post_type = sanitize_key( $post_type );
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized
            $page     = isset( $_GET['page'] ) ? absint( wp_unslash( $_GET['page'] ) ) : 1;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized
            $per_page = isset( $_GET['per_page'] ) ? min( 100, max( 1, absint( wp_unslash( $_GET['per_page'] ) ) ) ) : 100;

            $query = new WP_Query(
                array(
                    'post_type'      => $post_type,
                    'post_status'    => 'publish',
                    'posts_per_page' => $per_page,
                    'paged'          => $page,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'no_found_rows'  => false,
                )
            );

            $total       = (int) $query->found_posts;
            $total_pages = (int) $query->max_num_pages;
            $items       = array();
            foreach ( $query->posts as $post_obj ) {
                $items[] = array(
                    'id'    => $post_obj->ID,
                    'title' => array(
                        'rendered' => $post_obj->post_title,
                    ),
                );
            }

            header( 'X-WP-Total: ' . $total );
            header( 'X-WP-TotalPages: ' . $total_pages );
            wp_send_json_success( $items );
        }
        
        /**
         * Enqueue plugin-specific page bundles
         */
        private function enqueue_plugin_bundles() {
            global $wpe_registered_settings_plugins;
            
            if ( empty( $wpe_registered_settings_plugins ) ) {
                return;
            }
            
            foreach ( $wpe_registered_settings_plugins as $plugin ) {
                $script_url = $plugin['script_url'] ?? '';
                
                // Skip if no script URL (plugin using bundled pages mode)
                if ( empty( $script_url ) ) {
                    continue;
                }
                
                $handle  = 'wpe-settings-' . $plugin['slug'];
                $version = $plugin['version'] ?? '1.0.0';
                
                wp_enqueue_script(
                    $handle,
                    $script_url,
                    array( 'wpe-settings-core' ), // Depends on core framework
                    $version,
                    true
                );
            }
        }

        /**
         * Add admin head style for menu icon
         */
        public function admin_head_style() {
            echo '<style>
                .toplevel_page_wp-enhanced img {
                    max-width: 16px;
                    width: 100%;
                    left: 9px;
                    position: absolute;
                }
                #adminmenu .toplevel_page_wp-enhanced .wp-menu-image img {
                    width: 20px;
                    padding: 0 !important;
                    position: absolute;
                    top: 50%;
                    transform: translateY(-50%);
                    left: 8px;
                }
            </style>';
        }
    }

    // Instantiate the class
    new WP_Enhanced_Settings();
}
