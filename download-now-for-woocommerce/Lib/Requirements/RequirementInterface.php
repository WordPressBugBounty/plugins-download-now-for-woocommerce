<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Lib\Requirements;

interface RequirementInterface
{
    public function type(): string;
    
    public function name(): string;

    public function url(): string;

    public function value();
}
