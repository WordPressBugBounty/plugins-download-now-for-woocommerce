<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Abstracts;

use SOM\FreeDownloads\Project;

abstract class AbstractSingletonPlugin extends AbstractPlugin
{
    /**
     * The single instance of the class.
     */
    private static ?self $instance = null;

    /**
     * Main Class Instance.
     *
     * Ensures only one instance of this class is loaded or can be loaded.
     */
    public static function instance(?Project $project): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($project);
            if (self::$instance->meetsRequirements() == false) {
                return NULL;
            }
            self::$instance->build();
        }
        return self::$instance;
    }
}
