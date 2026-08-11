<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Evidence Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used to store complaint evidence (photos/videos).
    | In production this should be 's3'. In local development we default to
    | the 'public' disk so the app works without AWS credentials or the
    | S3 flysystem adapter installed. Configure via EVIDENCE_DISK in .env.
    |
    */

    'disk' => env('EVIDENCE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Signed URL Lifetime (minutes)
    |--------------------------------------------------------------------------
    |
    | How long temporary/pre-signed evidence URLs remain valid. Only applies
    | to disks that support temporary URLs (e.g. s3). For local disks the
    | permanent public URL is returned instead.
    |
    */

    'url_ttl_minutes' => (int) env('EVIDENCE_URL_TTL_MINUTES', 120),

];
