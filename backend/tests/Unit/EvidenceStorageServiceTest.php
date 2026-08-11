<?php

namespace Tests\Unit;

use App\Services\EvidenceStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceStorageServiceTest extends TestCase
{
    public function test_store_persists_file_and_returns_key(): void
    {
        config(['evidence.disk' => 'public']);
        Storage::fake('public');

        $service = new EvidenceStorageService();
        $key     = $service->store(UploadedFile::fake()->image('e.jpg'), 42);

        $this->assertStringStartsWith('evidence/42/', $key);
        Storage::disk('public')->assertExists($key);
    }

    public function test_url_returns_public_url_for_local_disk_without_throwing(): void
    {
        // A local/public disk does not support temporaryUrl(); the service must
        // fall back to a permanent url() instead of throwing (the old bug).
        config(['evidence.disk' => 'public']);
        Storage::fake('public');

        $service = new EvidenceStorageService();
        $key     = $service->store(UploadedFile::fake()->image('e.jpg'), 7);

        $url = $service->url($key);
        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    public function test_url_returns_null_for_null_path(): void
    {
        config(['evidence.disk' => 'public']);
        $this->assertNull((new EvidenceStorageService())->url(null));
    }
}
