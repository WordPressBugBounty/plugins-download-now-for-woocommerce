<?php

declare(strict_types=1);

namespace SOM\FreeDownloads\Notices;

use SOM\FreeDownloads\Interfaces\SystemNoticeInterface;

abstract class WordPressNotice implements SystemNoticeInterface
{
    protected string $content = '';

    protected string $title;
    
    protected string $message;

    protected string $notice_class = '';

    public function __construct(string $message, string $title = '')
    {
        $this->title = $title;
        $this->message = $message;
    }

    public function format(): void
    {
        $this->content = '<div class="notice notice-'.esc_attr($this->notice_class).'">';
        $this->content .= $this->message;
        $this->content .= '</div>';
    }

    public function action(): void
    {
        add_action('admin_notices', [$this, 'output']);
    }

    public function output(): void
    {
        echo $this->content;
    }
}
