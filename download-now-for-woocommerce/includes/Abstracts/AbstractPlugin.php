<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Abstracts;

use SOM\FreeDownloads\{
    Project,
    Lib\Requirements\Requirements
};

abstract class AbstractPlugin
{
    protected Project $project;

    protected string $name = '';

    protected string $version = '';

    protected string $main_directory;

    /**
     * The array of requirement data for this plugin.
     */
    private array $requirements_data;

    /**
     * The array of requirements for this plugin.
     */
    protected array $requirements;

    /**
     * Whether the plugin requirements have been met. Default = True
     */
    private bool $requirements_met = true;

    /**
     * Builds the plugin.
     * 
     * @return void
     */
    abstract protected function build(): void;

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function getDbSetting(): string
    {
        return $this->db_setting;
    }

    public function getMainFile(): string
    {
        return $this->file;
    }

    protected function meetsRequirements(): bool
    {
        // Check if plugin requirements are met before loading anything else
        $this->setRequirementsData();
        $requirements_check = new Requirements($this->requirements_data);
        if ($requirements_check->requirements_met == false) {
            $this->requirements_met = false;
            $requirements_check->missing_requirements();
        }
        return $this->requirements_met;
    }

    private function setRequirementsData(): void
    {
        $this->requirements_data = [
            'plugin_name'   => $this->name,
            'plugin_file'   => $this->file,
            'requirements'  => $this->requirements,
            'error_title'   => __('Missing Requirements', 'somdn-pro'),
            'error_message' => _x('{plugin_name} will not work without the following requirements:', 'This message is explaining that this plugin, called {plugin_name}, will not work without a list of requirements or dependencies.', 'somdn-pro')
        ];
    }
}
