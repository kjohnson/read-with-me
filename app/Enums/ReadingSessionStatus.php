<?php

namespace App\Enums;

enum ReadingSessionStatus: string
{
    case Recording = 'recording';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Recording => 'Not finished',
            self::Processing => 'Processing',
            self::Ready => 'Ready to play',
            self::Failed => 'Processing failed',
        };
    }
}
