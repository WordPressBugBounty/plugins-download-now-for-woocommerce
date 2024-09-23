<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Lib\Requirements;

class PhpMinVersionRequirement extends AbstractRequirement
{
    private string $type = 'PHP Minimum Version';

    public function requirementMet(): bool
    {
        return (version_compare(PHP_VERSION, $this->value(), '>='));
    }

    public function errorMessage(): string
    {
        return '';
    }
}
