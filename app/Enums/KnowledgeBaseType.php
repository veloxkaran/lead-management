<?php

namespace App\Enums;

enum KnowledgeBaseType: string
{
    case Document = 'document';
    case Pdf = 'pdf';
    case Image = 'image';
    case Video = 'video';
    case Link = 'link';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Document => 'bi-file-earmark-word',
            self::Pdf => 'bi-file-earmark-pdf',
            self::Image => 'bi-file-earmark-image',
            self::Video => 'bi-file-earmark-play',
            self::Link => 'bi-link-45deg',
        };
    }
}
