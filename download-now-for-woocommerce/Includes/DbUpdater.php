<?php

/**
 * Free Downloads - Database Updater
 * 
 * @version    3.1.92
 */

namespace SOM\FreeDownloads;

defined('ABSPATH') || exit;

/**
 * Main Updater Class.
 *
 * @since 3.1.7
 */
final class DbUpdater
{
    private $new_version;
    private $old_version;
    private $setting_name;
    private $fresh_install_setting = '';
    private $updates = array();

    /**
     * Class constructor.
     *
     * @param string  $version                The current/latest version of the plugin.
     * @param string  $setting_name           The setting name for the version number stored in the database.
     * @param string  $fresh_install_setting  The setting name to check for if this is a clean installation.
     */
    public function __construct($version = '', $setting_name = '', $fresh_install_setting = '')
    {
        if (empty($version) || empty($setting_name)) {
            return;// bail
        }
        $this->new_version = $this->clean_string($version);

        $this->setting_name = $this->clean_string($setting_name);

        $this->old_version = !empty(get_option($this->setting_name)) ?
        $this->clean_string(get_option($this->setting_name))
        : '0.0.1' ;

        if (!empty($fresh_install_setting)) {
            $this->fresh_install_setting = $this->clean_string($fresh_install_setting);
        }

        if ($this->is_clean_install() === true) {
            update_option($this->setting_name, $this->new_version);
            return;
        }

        if ($this->is_current_version() === true) {
            return;
        }

        // Run the update procedures
        $this->init();
        $this->run();
        $this->update_complete();

    }

    private function update_complete()
    {
        $updated = update_option($this->setting_name, $this->new_version);
        do_action('somdn_update_complete');
    }

    private function is_current_version()
    {
        if (version_compare($this->new_version, $this->old_version, '=')) {
            // Version numbers are the same
            return true;
        } else {
            return false;
        }
    }

    private function is_clean_install()
    {
        $setting_to_check = $this->setting_name;
        if (!empty($this->fresh_install_setting)) {
            $setting_to_check = $this->fresh_install_setting;
        }
        if (empty(get_option(sanitize_text_field($setting_to_check)))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set up filters to pull in any updates needed from other files.
     *
     * @uses apply_filters()
     *
     * @return void
     */
    public function init()
    {
        $this->updates = apply_filters('somdn_plugin_updates_array', $this->get_updates_array());
    }

    public function get_updates_array()
    {
        return array(
            'default_updates' => array(
                '2.1.0' => array(
                    'somdn_plugin_update_to_2_1_0'
             ),
                '3.1.5' => array(
                    array($this, 'somdn_plugin_update_to_3_1_5')
             )
         )
        );
    }

    public function run()
    {
        $updates = $this->updates;

        $cleaned_args = array(
            'db_setting' => $this->setting_name,
            'new_version' => $this->new_version
        );

        // Now we run through each update index which contains a stack of version numbers and callback functions
        // For each of these stacks we check the version number and if it's lower than the current one we run the callback
        // function which will perform the necessary tasks to update the plugin.

        // Split out each stack of updates, which starts with 'default_updates' and then include ones returned from the filter 'somdn_plugin_updates_array'
        foreach ($updates as $stack) {

            foreach ($stack as $version => $update_callbacks) {
                if (version_compare($this->new_version, $version, '<')) {
                    // If for some reason the version number inside the update is greater than the current
                    // plugin version, just skip to the next one in the list.
                    continue;
                }
                if (version_compare($this->old_version, $version, '<')) {
                    foreach ($update_callbacks as $update_callback) {
                        if (is_callable($update_callback)) {

                            // Call the function to process the update for each version
                            $update_result = call_user_func($update_callback, $cleaned_args);

                            if (is_array($update_result)) {
                                $new_error = array();
                                // Something went wrong with the update, an error has been returned (array of values) instead of a true boolean
                                $update_title = $update_result['title'];
                                $update_error = $update_result['error'];
                                $new_error['title'] = $update_title;
                                $new_error['error'] = $update_error;
                                array_push($_REQUEST['somdn_db_update_errors'], $new_error);
                            }

                        }
                    }
                }
            }

        }
        do_action('somdn_update_run');
    }

    public function somdn_plugin_update_to_3_1_5($update_args)
    {
        $result = '';

        // Delete the old file download temp folder
        $upload_dir = wp_upload_dir();
        $old_zip_path = $upload_dir['basedir'] . '/download-now-uploads';
        if (file_exists($old_zip_path)) {
            array_map('unlink', glob($upload_dir['basedir'] . '/download-now-uploads/*'));
            rmdir($old_zip_path);
        }

        // No error to pass back for this update

        $result = true;

        return $result;
    }

    private function clean_string($string)
    {
        return esc_html(trim($string));
    }
}

/*
 * Template for adding new updates to the updates array by filtering
 */
/*
add_filter('somdn_plugin_updates_array', 'somdn_plugin_updates_array_default');
function somdn_plugin_updates_array_default($updates)
{
    $default_updates = array(
        'default_updates' => array(
            '2.1.0' => array(
                'somdn_plugin_update_to_2_1_0'
         ),
            '3.1.5' => array(
                'somdn_plugin_update_to_3_1_5'
         )
     )
    );
    $new_updates_array = array_merge($updates, $default_updates);
    return $new_updates_array;
}
*/
/*
Template for update errors:
    $result = array(
        'title' => 'Update {version number} Error',
        'error' => 'A description of the error.'
    );
*/
