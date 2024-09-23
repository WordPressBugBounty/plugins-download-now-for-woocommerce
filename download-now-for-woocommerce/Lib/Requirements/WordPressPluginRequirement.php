<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Lib\Requirements;

class WordPressPluginRequirement extends AbstractRequirement
{
    private string $type = 'WordPress Plugin';

    public function requirementMet(): bool
    {
        return ($this->isPluginActive($this->value()));
    }

    public function errorMessage(): string
    {
        return '';
    }

    private function isPluginActive(string $plugin): bool
    {
        return in_array($plugin, (array) get_option('active_plugins', []), true) || $this->isPluginActiveNetwork($plugin);
    }

    private function isPluginActiveNetwork(string $plugin): bool
    {
        if (!is_multisite()) {
            return false;
        }
     
        $plugins = get_site_option('active_sitewide_plugins');
        return (isset($plugins[$plugin]));
    }
}
