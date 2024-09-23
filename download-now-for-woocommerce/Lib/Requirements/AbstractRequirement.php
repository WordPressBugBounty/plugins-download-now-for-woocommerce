<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Lib\Requirements;

abstract class AbstractRequirement implements RequirementInterface
{
    private string $type = 'Default';

    private string $name = '';

    private string $url = '';

    private $value = '';

    public function __construct(string $name, string $url, string $value)
    {
        $this->name  = $name;
        $this->url   = $url;
        $this->value = $value;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function value()
    {
        return $this->value;
    }

    abstract public function requirementMet(): bool;

    abstract public function errorMessage(): string;
}
