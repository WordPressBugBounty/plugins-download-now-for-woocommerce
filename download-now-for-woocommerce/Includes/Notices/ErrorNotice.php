<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Notices;

use SOM\FreeDownloads\Interfaces\SystemNoticeInterface;

final class ErrorNotice extends WordPressNotice
{
    protected string $notice_class = 'error';
}
