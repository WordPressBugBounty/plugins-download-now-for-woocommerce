<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Abstracts;

use SOM\FreeDownloads\Project;

abstract class AbstractSingletonPlugin extends AbstractPlugin
{
    private static ?self $instance = null;

    public static function instance(?Project $project = null): ?self
    {
        if (is_null(self::$instance) && $project !== null) {
            self::$instance = new static($project);
        }
        return self::$instance;
    }

    public function maybeInit(): void
    {
        if (! $this->meetsRequirements()) {
            return;
        }

        $this->build();
    }
}
