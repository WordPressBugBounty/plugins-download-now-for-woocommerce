<?php

/*
 * This file is part of Free Downloads.
 *
 * Copyright (c) Richard Webster
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace SOM\FreeDownloads;

use SOM\FreeDownloads\Abstracts\{
    AbstractSingletonPlugin
};

final class Plugin extends AbstractSingletonPlugin
{
    /**
     * Base version (woocommerce or edd).
     */
    protected string $base = 'woocommerce';

    /**
     * Plugin database version setting.
     */
    protected string $db_setting = 'somdn_woo_pro_plugin_db_version';


    /**
     * Plugin setting that would not be set if this was a clean install.
     */
    private string $fresh_install_setting = 'somdn_gen_settings';

    /**
     * Plugin file.
     */
    protected string $file = SOMDN_FILE;

    /**
     * Stores the product IDs for products that have been checked for free download validity
     * 
     * @var CheckedProducts
     */
    private CheckedProducts $checked_products;

    protected function __construct(Project $project)
    {
        $this->project = $project;
        $this->name = $project->getName();
        $this->version = $project->getVersion();
        $this->requirements = $project->getRequirements();
        $this->main_directory = $project->getMainDirectory();
    }

    protected function build(): void
    {
        $this->includes();
        $this->initHooks();
        $this->loadModules();
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    private function includes(): void
    {
        do_action('somdn_after_file_loader');
    }

    /**
     * Hook into actions and filters.
     */
    private function initHooks(): void
    {
        register_activation_hook($this->file, array($this, 'somdnActivated'));
        register_deactivation_hook($this->file, array($this, 'somdnDeactivated'));
        add_action('plugins_loaded', array($this, 'onPluginsLoaded'), -1);
    }

    public function somdnActivated(): void
    {
        do_action('somdn_on_activate');
        do_action('somdn_pro_activated');
    }

    public function somdnDeactivated(): void
    {
        do_action('somdn_on_deactivate');
        do_action('somdn_pro_deactivated');
    }

    /**
     * Plugins that add new modules to Free Downloads WooCommerce can
     * use the 'somdn_load_modules' action to hook into this plugin.
     */
    private function loadModules(): void
    {
        do_action('somdn_load_modules');
    }

    /**
     * When WP has loaded all plugins, trigger the `somdn_loaded` hook.
     *
     * This ensures `somdn_loaded` is called only after all other plugins
     * are loaded, to avoid issues caused by plugin directory naming changing
     * the load order.
     *
     * @since 3.1.7
     */
    public function onPluginsLoaded(): void
    {
        do_action('somdn_loaded');
        $this->updatePlugin();
    }

    private function updatePlugin(): void
    {
        (new DbUpdater(
            $this->version,
            $this->db_setting,
            $this->fresh_install_setting
        ));
    }

    public function checkedProducts(): CheckedProducts
    {
        if (empty($this->checked_products)) {
            $this->checked_products = new CheckedProducts();
        }
        return $this->checked_products;
    }
}
