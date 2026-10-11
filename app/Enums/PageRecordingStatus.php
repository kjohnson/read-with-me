<?php

namespace App\Enums;

enum PageRecordingStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
