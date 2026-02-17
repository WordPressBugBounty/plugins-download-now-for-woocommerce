<?php
/**
 * Divi Engine Settings Framework
 * 
 * Handles the shared settings page for all Divi Engine plugins.
 * Supports multiple plugins registering their settings pages at runtime.
 * 
 * @package Divi_Engine_Settings
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Framework version - increment this with each release
if ( ! defined( 'DE_SETTINGS_FRAMEWORK_VERSION' ) ) {
    define( 'DE_SETTINGS_FRAMEWORK_VERSION', '2.0.0' );
}

// Define path constant, if not already defined
if ( ! defined( 'DE_WPE_SETTINGS_PATH' ) ) {
    define( 'DE_WPE_SETTINGS_PATH', plugin_dir_path( __FILE__ ) );
}

// Define URL constant, if not already defined
if ( ! defined( 'DE_WPE_SETTINGS_URL' ) ) {
    define( 'DE_WPE_SETTINGS_URL', plugin_dir_url( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) );
}

// Load the REST endpoints
require_once DE_WPE_SETTINGS_PATH . 'rest-endpoints/class-rest-endpoint.php';
require_once DE_WPE_SETTINGS_PATH . 'rest-endpoints/class-license-rest-endpoint.php';
require_once DE_WPE_SETTINGS_PATH . 'rest-endpoints/class-settings-endpoint.php';

// Load shared REST endpoints (from common folder)
$common_endpoints_path = dirname( dirname( DE_WPE_SETTINGS_PATH ) ) . '/common/rest-endpoints/';
if ( file_exists( $common_endpoints_path . 'class-error-logs-endpoint.php' ) ) {
	require_once $common_endpoints_path . 'class-error-logs-endpoint.php';
}

/**
 * Plugin Registry for Settings Framework
 * 
 * Allows plugins to register themselves for the settings page.
 */
if ( ! class_exists( 'DE_Settings_Plugin_Registry' ) ) {
    
    class DE_Settings_Plugin_Registry {
        
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
            global $de_registered_settings_plugins;
            
            if ( ! isset( $de_registered_settings_plugins ) ) {
                $de_registered_settings_plugins = array();
            }
            
            $slug = sanitize_key( $config['slug'] ?? '' );
            if ( empty( $slug ) ) {
                return;
            }
            
            $de_registered_settings_plugins[ $slug ] = array(
                'slug'              => $slug,
                'label'             => sanitize_text_field( $config['label'] ?? $slug ),
                'color'             => sanitize_hex_color( $config['color'] ?? '' ),
                'script_url'        => esc_url( $config['script_url'] ?? '' ),
                'version'           => sanitize_text_field( $config['version'] ?? '1.0.0' ),
                'framework_version' => sanitize_text_field( $config['framework_version'] ?? '1.0.0' ),
            );
        }
    }
}

/**
 * Plugin Registry for Settings Framework
 * 
 * Allows plugins to register themselves for the settings page.
 */
if ( ! class_exists( 'DE_Settings_Plugin_Registry' ) ) {
    
    class DE_Settings_Plugin_Registry {
        
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
            global $de_registered_settings_plugins;
            
            if ( ! isset( $de_registered_settings_plugins ) ) {
                $de_registered_settings_plugins = array();
            }
            
            $slug = sanitize_key( $config['slug'] ?? '' );
            if ( empty( $slug ) ) {
                return;
            }
            
            $de_registered_settings_plugins[ $slug ] = array(
                'slug'              => $slug,
                'label'             => sanitize_text_field( $config['label'] ?? $slug ),
                'color'             => sanitize_hex_color( $config['color'] ?? '' ),
                'script_url'        => esc_url( $config['script_url'] ?? '' ),
                'version'           => sanitize_text_field( $config['version'] ?? '1.0.0' ),
                'framework_version' => sanitize_text_field( $config['framework_version'] ?? '1.0.0' ),
            );
        }
    }
}

/**
 * Class Divi_Engine_Settings
 * 
 * Main settings framework class. Handles:
 * - Admin menu registration
 * - Script enqueueing with version comparison
 * - Plugin page bundle loading
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'Divi_Engine_Settings' ) ) {

    class Divi_Engine_Settings {
        
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
            $this->framework_version = DE_SETTINGS_FRAMEWORK_VERSION;
            $this->framework_url = DE_WPE_SETTINGS_URL . 'includes/settings/';
            
            // Register this framework version IMMEDIATELY (not via hook)
            // This ensures it's registered even if class is instantiated after plugins_loaded
            $this->register_framework_version();
            
            // Fire hook for plugins to register on admin_init (after all plugins loaded their settings.php)
            add_action( 'admin_init', array( $this, 'fire_registration_hook' ), 5 );
            
            // Standard admin hooks
            add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            
            // Enqueue scripts (priority 15 = after plugin registrations)
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 15 );
            
            add_action( 'admin_head', array( $this, 'admin_head_style' ) );
            register_activation_hook( __FILE__, array( $this, 'on_plugin_activation' ) );
        }
        
        /**
         * Register this framework version in the global registry
         */
        public function register_framework_version() {
            global $de_settings_frameworks;
            
            if ( ! isset( $de_settings_frameworks ) ) {
                $de_settings_frameworks = array();
            }
            
            // Only register if this version isn't already registered or is newer
            if ( ! isset( $de_settings_frameworks[ $this->framework_version ] ) ) {
                $de_settings_frameworks[ $this->framework_version ] = array(
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
             * Action: de_settings_register_plugin
             * 
             * Plugins should use this hook to register with the settings framework.
             * 
             * @param DE_Settings_Plugin_Registry $registry The registry instance
             * 
             * @example
             * add_action( 'de_settings_register_plugin', function( $registry ) {
             *     $registry->add( array(
             *         'slug'              => 'divi-ajax-filter',
             *         'label'             => 'Divi Ajax Filter',
             *         'script_url'        => plugin_dir_url( __FILE__ ) . 'includes/settings/dist/divi-ajax-filter-pages.js',
             *         'version'           => '1.0.0',
             *         'framework_version' => '2.0.0',
             *     ) );
             * } );
             */
            do_action( 'de_settings_register_plugin', new DE_Settings_Plugin_Registry() );
        }
        
        /**
         * Get the highest registered framework version
         * 
         * @return string|null Highest version or null if none registered
         */
        private function get_highest_framework_version() {
            global $de_settings_frameworks;
            
            if ( empty( $de_settings_frameworks ) ) {
                return null;
            }
            
            $versions = array_keys( $de_settings_frameworks );
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
        public function add_settings_page() {
            $icon = DE_WPE_SETTINGS_URL . 'includes/settings/organization/divi-engine/images/dash-icon.svg';
            
            add_menu_page(
                __( 'Divi Engine Settings', '__DE_SETTINGS_TD__' ),
                __( 'Divi Engine', '__DE_SETTINGS_TD__' ),
                'manage_options',
                'divi-engine',
                array( $this, 'settings_page_content' ),
                $icon,
                100
            );
        }

        /**
         * Register settings for the plugin
         */
        public function register_settings() {
            register_setting( 
                'divi_engine_options_group', 
                'divi_engine_options',
                array(
                    'sanitize_callback' => array( $this, 'sanitize_settings' )
                )
            );
        }

        /**
         * Sanitize settings before saving
         *
         * @param array $input The input array to sanitize.
         * @return array The sanitized array.
         */
        public function sanitize_settings( $input ) {
            if ( ! is_array( $input ) ) {
                return array();
            }

            $sanitized = array();
            
            foreach ( $input as $key => $value ) {
                $key = sanitize_key( $key );
                
                if ( is_array( $value ) ) {
                    $sanitized[ $key ] = $this->sanitize_settings( $value );
                } else {
                    if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                        $sanitized[ $key ] = esc_url_raw( $value );
                    } elseif ( is_numeric( $value ) ) {
                        $sanitized[ $key ] = is_float( $value ) ? floatval( $value ) : intval( $value );
                    } else {
                        $sanitized[ $key ] = sanitize_text_field( $value );
                    }
                }
            }
            
            return $sanitized;
        }

        /**
         * Render the settings page content
         */
        public function settings_page_content() {
            $this->check_and_update_achievements();

            $endpoints = array(
                'home' => admin_url( 'admin.php?page=divi-engine' ),
            );
            ?>
            <div class="wrap">
                <div id="divi-engine-settings"></div>
                <?php foreach ( $endpoints as $key => $url ) : ?>
                    <input type="hidden" id="de-endpoint-<?php echo esc_attr( $key ); ?>" value="<?php echo esc_url( $url ); ?>" />
                <?php endforeach; ?>
            </div>
            <?php
        }

        /**
         * Check and update achievements in the database
         */
        public function check_and_update_achievements() {
            $incomplete_key = 'de_incomplete_achievements';
            $complete_key   = 'de_complete_achievements';

            $default_achievement_ids = array(
                'plugin-setup',
                'customization',
            );

            $incomplete_achievements = get_option( $incomplete_key, array() );
            $complete_achievements   = get_option( $complete_key, array() );

            if ( ! is_array( $incomplete_achievements ) ) {
                $incomplete_achievements = array();
            }
            if ( ! is_array( $complete_achievements ) ) {
                $complete_achievements = array();
            }

            foreach ( $default_achievement_ids as $achievement_id ) {
                if ( ! in_array( $achievement_id, $complete_achievements, true ) && 
                     ! in_array( $achievement_id, $incomplete_achievements, true ) ) {
                    $incomplete_achievements[] = $achievement_id;
                }
            }

            update_option( $incomplete_key, $incomplete_achievements );
        }

        /**
         * Hook into plugin activation
         */
        public function on_plugin_activation() {
            $this->check_and_update_achievements();
        }

        /**
         * Add admin head style for menu icon
         */
        public function admin_head_style() {
            echo '<style>
                .toplevel_page_divi-engine img {
                    max-width: 16px;
                    width: 100%;
                    left: 9px;
                    position: absolute;
                }
                #adminmenu .toplevel_page_divi-engine .wp-menu-image img {
                    width: 20px;
                    padding: 0 !important;
                    position: absolute;
                    top: 50%;
                    transform: translateY(-50%);
                    left: 8px;
                }
            </style>';
        }

        /**
         * Enqueue scripts and styles for the settings page
         * 
         * @param string $hook Current admin page hook
         */
        public function enqueue_scripts( $hook ) {
            global $de_registered_settings_plugins;
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
            
            if ( $current_page !== 'divi-engine' ) {
                return;
            }
            
            // Only load core if we're the highest version
            if ( $this->should_load_core() ) {
                $this->enqueue_core_framework();
            }
            
            // Each plugin loads its own pages bundle
            // (This happens regardless of which framework version is highest)
            $this->enqueue_plugin_bundles();
        }
        
        /**
         * Enqueue the core framework bundle
         */
        private function enqueue_core_framework() {
            global $de_registered_settings_plugins;
            
            $core_url = $this->framework_url . 'dist/settings-app.js';
            
            wp_enqueue_script(
                'de-settings-core',
                $core_url,
                array( 'react', 'react-dom' ),
                $this->framework_version,
                true
            );
            
            // Pass WP data to the script
            wp_localize_script(
                'de-settings-core',
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
            $typesense_configs = apply_filters( 'de_typesense_configs', array() );
            $plugins_data      = ! empty( $de_registered_settings_plugins ) 
                ? array_values( $de_registered_settings_plugins ) 
                : array();
            
            wp_add_inline_script(
                'de-settings-core',
                'window.diviEngineSettingsObject = window.diviEngineSettingsObject || {};'
                . 'window.diviEngineSettingsObject.organization = "divi-engine";'
                . 'window.diviEngineSettingsObject.frameworkVersion = ' . wp_json_encode( $this->framework_version ) . ';'
                . 'window.diviEngineSettingsObject.registeredPlugins = ' . wp_json_encode( $plugins_data ) . ';'
                . 'window.diviEngineSettingsObject.typesenseConfigs = ' . wp_json_encode( $typesense_configs ) . ';',
                'before'
            );
        }
        
        /**
         * Enqueue plugin-specific page bundles
         */
        private function enqueue_plugin_bundles() {
            global $de_registered_settings_plugins;
            
            if ( empty( $de_registered_settings_plugins ) ) {
                return;
            }
            
            foreach ( $de_registered_settings_plugins as $plugin ) {
                $script_url = $plugin['script_url'] ?? '';
                
                // Skip if no script URL (plugin using bundled pages mode)
                if ( empty( $script_url ) ) {
                    continue;
                }
                
                $handle  = 'de-settings-' . $plugin['slug'];
                $version = $plugin['version'] ?? '1.0.0';
                
                wp_enqueue_script(
                    $handle,
                    $script_url,
                    array( 'de-settings-core' ), // Depends on core framework
                    $version,
                    true
                );
            }
        }
    }

    // Instantiate the class
    new Divi_Engine_Settings();
}
