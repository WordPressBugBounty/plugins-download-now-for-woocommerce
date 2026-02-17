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

namespace SOM\FreeDownloads\Loaders;

use function file_exists;

/**
 * Used for manually including files that can't be auto loaded, since the Autoloader class is PSR-4 compliant. For example files containing only functions must be manually included.
 * 
 * Accepts a variadic array of strings to enforce a typed array of strings.
 * 
 * Example usage:
 * 
 * ```php
 * $files = [
 *     'File1.php',
 *     'Files2.php'
 * ];
 * 
 * $file_loader = new FileLoader(...$files);
 * $file_loader->loadFiles();
 * ```
 */
final class FileLoader
{
    /**
     * The files to be loaded.
     * 
     * @var string[]
     */
    private array $files;

    /**
     * Accepts an variadic array of strings as file paths to load.
     * 
     * @param string[] $filepaths
     * 
     * @return void
     */
    public function __construct(string ...$filepaths)
    {
        $this->files = $filepaths;
    }

    /**
     * Iterates through $files and passes each to requireFile().
     * 
     * @uses FileLoader::$files
     * @uses FileLoader::requireFile()
     * 
     * @return void
     */
    public function loadFiles(): void
    {
        foreach ($this->files as $file) {
            $this->requireFile($file);
        }
    }

    /**
     * Checks if a file exists then requires it.
     * 
     * @param string $file
     * 
     * @uses \file_exists
     * @uses \require_once
     * 
     * @return void
     */
    private function requireFile(string $file): void
    {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
