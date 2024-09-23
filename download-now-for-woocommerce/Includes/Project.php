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

use DIRECTORY_SEPARATOR;

final class Project
{
    private string $config_path;

    private string $main_directory;

    private array $config = [];

    private array $autoload_sources = [];

    private array $file_sources = [];

    private string $name = '';

    private string $version = '';

    private array $requirements = [];

    private string $sep = DIRECTORY_SEPARATOR;

    public function __construct(string $config_path, string $main_directory)
    {
        $this->config_path = $config_path;
        $this->main_directory = $main_directory;
    }

    public function buildProject(): void
    {
        if (file_exists($this->config_path)) {
            $this->config = json_decode(file_get_contents($this->config_path), true);
            $this->setName();
            $this->setVersion();
            $this->setRequirements();
            $this->setAutoloaderSources();
            $this->setFileSources();
        }
    }

    public function getMainDirectory(): string
    {
        return $this->main_directory;
    }

    private function setName(): void
    {
        $this->name = (string) ($this->config['name'] ?? '');
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function setVersion(): void
    {
        $this->version = (string) ($this->config['version'] ?? '');
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    private function setRequirements(): void
    {
        $this->requirements = (array) ($this->config['requirements'] ?? []);
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    private function setAutoloaderSources(): void
    {
        $autoload = [];

        if (
            isset($this->config['sources'])
            && isset($this->config['sources']['autoload'])
        ) {
            $autoload = (array) $this->config['sources']['autoload'];
        }

        foreach ($autoload as $namespace => $path) {
            $this->autoload_sources[$namespace] = $this->main_directory . $this->sep . $path;
        }
    }

    private function setFileSources(): void
    {
        $files = [];
        
        if (
            isset($this->config['sources'])
            && isset($this->config['sources']['files'])
        ) {
            $files = (array) $this->config['sources']['files'];
        }

        foreach ($files as $file) {
            $this->file_sources[] = $this->formatSlashes($this->main_directory . $this->sep . $file);
        }
    }

    public function getAutoloaderSources(): array
    {
        return $this->autoload_sources;
    }

    public function getFileSources(): array
    {
        return $this->file_sources;
    }

    /**
     * Normalises forward and/or backward slashes to the system directory separator.
     * 
     * @param string $path
     * 
     * @return string The normalised path
     */
    private function formatSlashes(string $path): string
    {
        return(str_replace(["\\", "/"], $this->sep, $path));
    }
}
