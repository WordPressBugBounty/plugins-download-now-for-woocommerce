<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Interfaces;

interface SystemNoticeInterface
{
    public function format(): void;
    
    public function action(): void;

    public function output(): void;
}
