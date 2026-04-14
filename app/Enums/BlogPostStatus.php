<?php

namespace App\Enums;

enum BlogPostStatus: string
{
    case Draft     = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }
}
