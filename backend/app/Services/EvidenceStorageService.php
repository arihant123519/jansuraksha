<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Centralizes evidence file storage and URL generation so the rest of the
 * app is agnostic to whether evidence lives on S3 (production) or a local
 * disk (development). Disk is configured via config/evidence.php.
 */
class EvidenceStorageService
{
    private string $disk;
    private int $ttlMinutes;

    public function __construct()
    {
        $this->disk       = config('evidence.disk', 'public');
        $this->ttlMinutes = (int) config('evidence.url_ttl_minutes', 120);
    }

    public function disk(): string
    {
        return $this->disk;
    }

    /**
     * Persist an uploaded evidence file and return its storage key.
     *
     * @throws \RuntimeException when the underlying disk fails to write.
     */
    public function store(UploadedFile $file, int $complaintId): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $key = "evidence/{$complaintId}/" . Str::uuid() . ".{$ext}";

        try {
            $ok = Storage::disk($this->disk)->put($key, file_get_contents($file->getRealPath()));
        } catch (\Throwable $e) {
            Log::error('Evidence upload failed', [
                'disk'         => $this->disk,
                'complaint_id' => $complaintId,
                'error'        => $e->getMessage(),
            ]);
            throw new \RuntimeException('Evidence storage failed: ' . $e->getMessage(), 0, $e);
        }

        if ($ok === false) {
            Log::error('Evidence upload returned false', [
                'disk' => $this->disk, 'complaint_id' => $complaintId, 'key' => $key,
            ]);
            throw new \RuntimeException('Evidence storage failed (disk rejected the write).');
        }

        return $key;
    }

    /**
     * Build a viewable URL for a stored evidence path. Uses a time-limited
     * pre-signed URL when the disk supports it (s3), otherwise falls back to
     * the disk's permanent public URL. Never throws — returns null on failure
     * so a single bad file can't break a whole complaint response.
     */
    public function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        try {
            $storage = Storage::disk($this->disk);

            if (method_exists($storage, 'providesTemporaryUrls') && $storage->providesTemporaryUrls()) {
                return $storage->temporaryUrl($path, now()->addMinutes($this->ttlMinutes));
            }

            return $storage->url($path);
        } catch (\Throwable $e) {
            Log::warning('Evidence URL generation failed', [
                'disk' => $this->disk, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
