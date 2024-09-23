<?php
/**
 * Plugin Requirements checker
 * Allows a plugin to specify and check for other plugins that it requires.
 * For example if a plugin requires WooCommerce in order to function.
 * 
 * @author Wp Enhanced
 * @version  0.0.3
 */

namespace SOM\FreeDownloads\Lib\Requirements;

use SOM\FreeDownloads\Interfaces\SystemNoticeInterface;
use SOM\FreeDownloads\Notices\ErrorNotice;

/**
 * Main Plugin Class.
 *
 * @since 0.0.2
 */
class Requirements
{
    private string $plugin_file;

    private string $plugin_name;

    private string $error_title;

    private string $error_message;

    private array $requirements;

    private array $requirements_data;

    public bool $requirements_met = true;

    private array $data;

    private array $missing_requirements;

    /**
     * Class constructor.
     *
     * @param array    $data     Required array of data as follows:
     * String    $data['plugin_file']        The __FILE__ of the main plugin
     * String    $data['plugin_name']        The actual name of the plugin
     * Array     $data['requirements']     Plugin requirements array including names, file names, and type. eg "WordPress Plugin"
     * String    $data['error_message']    The error message to show in the admin notices, preferably escaped and translated
     */
    public function __construct(array $data)
    {
        if (empty($data) || !is_array($data))
            return;

        if (!isset($data['requirements']))
            return;

        if (empty($data['requirements']))
            return;

        if (!is_array($data['requirements']))
            return;

        $this->plugin_file = !empty($data['plugin_file']) ? esc_html($data['plugin_file']) : NULL;
        $this->plugin_name = !empty($data['plugin_name']) ? esc_html($data['plugin_name']) : NULL;
        $this->error_title = !empty($data['error_title']) ? esc_html($data['error_title']) : $this->get_default_title();
        $this->error_message = !empty($data['error_message']) ? esc_html($data['error_message']) : $this->get_default_error_message();
        $this->requirements = !empty($data['requirements']) ? $data['requirements'] : NULL;

        $checked_data = [
            'plugin_file'   => $this->plugin_file,
            'plugin_name'   => $this->plugin_name,
            'error_title'   => $this->error_title,
            'error_message' => $this->error_message,
            'requirements'  => $this->requirements
        ];

        foreach ($checked_data as $entry) {
            if (empty($entry)) {
                // If any of the entries are empty just bail
                return;
            }
        }

        $this->data = $checked_data;

        $this->check_requirements();
    }

    private function check_requirements()
    {
        foreach ($this->requirements as $requirement_array) {

            $requirement = null;

            $type  = ($requirement_array['type_id'] ?? '');
            $name  = ($requirement_array['name'] ?? '');
            $url   = ($requirement_array['url'] ?? '');
            $value = ($requirement_array['value'] ?? '');

            switch ($type) {
                case 'wp_plugin':
                    $requirement = new WordPressPluginRequirement($name, $url, $value);
                    break;

                case 'php_min_ver':
                    $requirement = new PhpMinVersionRequirement($name, $url, $value);
                    break;
                
                default:
                    break;
            }

            if (is_null($requirement)) {
                continue;
            }

            $has_requirement = $requirement->requirementMet();

            if ($has_requirement == false) {
                $this->missing_requirements[] = $requirement;
                $this->requirements_met = false;
            }

        }
    }

    function get_error_message(): string
    {
        $error_message = $this->error_message;
        $plugin_name = $this->plugin_name;
        $error_message = str_replace("{plugin_name}", '<strong>' . $plugin_name . '</strong>', $error_message);
        return $error_message;
    }

    public function missing_requirements(): void
    {
        if (empty($this->missing_requirements)) {
            return;
        }
        if (!is_array($this->missing_requirements)) {
            return;
        }
        //deactivate_plugins(plugin_basename($this->data['plugin_file']), true);
        if (!is_admin()) {
            return;
        }
        $this->missing_requirements_notice();
        //add_action('admin_notices', [$this, 'missing_requirements_notice']);
    }

    private function get_default_title(): string
    {
        return 'Missing Requirements';
    }

    private function get_default_error_message(): string
    {
        return '{plugin_name} will not work without the following requirements:';
    }

    public function missing_requirements_notice(): void
    {
        $missing_requirements = $this->missing_requirements;
        $error_title = esc_html($this->error_title);

        ob_start(); ?>

        <h4><?php echo $error_title; ?></h4>
        <p><?php echo $this->get_error_message(); ?></p>
        <?php if (!empty($missing_requirements)) {
            echo '<ul>';
            foreach ($missing_requirements as $requirement) {
                $type = esc_html($requirement->type());
                $name = esc_html($requirement->name());
                $url = esc_url($requirement->url());
                printf('<li>' . $type . ': <a rel="nofollow" href="%2$s" target="_blank">%1$s</a></li>', $name, $url);
            }
            echo '</ul>';
        } ?>

        <?php

        $content = (string) ob_get_clean();

        $notice = new ErrorNotice($content, $error_title);
        $notice->format();
        $notice->action();

        //echo $content;
    }

    /**
     * Add a notice to the WP dashboard advising of any missing requirements
     */
    public function missing_requirements_error(): void
    {
        $missing_requirements = $this->missing_requirements;
        $error_title = esc_html($this->error_title);
        $this_plugin = esc_html($this->plugin_name);

        ob_start(); ?>

        <div class="notice notice-error">
            <h4><?php echo $error_title; ?></h4>
            <p><?php echo $this->get_error_message(); ?></p>
            <?php if (!empty($missing_requirements)) {
                echo '<ul>';
                foreach ($missing_requirements as $requirement) {
                    $type = esc_html($requirement['type_name']);
                    $name = esc_html($requirement['name']);
                    $url = esc_url($requirement['url']);
                    printf('<li>' . $type . ': <a rel="nofollow" href="%2$s" target="_blank">%1$s</a></li>', $name, $url);
                }
                echo '</ul>';
            } ?>
        </div>

        <?php

        $content = ob_get_clean();

        wp_die($content, $error_title . ' - ' . $this_plugin, ['back_link' => true]);
        exit;
    }
}
