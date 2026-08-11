<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplaintFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * REGRESSION: the public /track/{id} route must work WITHOUT auth.
     * The frontend track page was wrongly calling /complaints/{id} (auth:sanctum)
     * and getting 401 for anonymous visitors.
     */
    public function test_public_track_route_returns_complaint_without_auth(): void
    {
        $complaint = Complaint::factory()->create();

        $this->getJson("/api/track/{$complaint->complaint_id}")
            ->assertOk()
            ->assertJsonPath('complaint_id', $complaint->complaint_id)
            ->assertJsonStructure(['complaint_id', 'status', 'evidence_files', 'status_logs']);
    }

    public function test_complaints_detail_route_requires_auth(): void
    {
        $complaint = Complaint::factory()->create();

        $this->getJson("/api/complaints/{$complaint->complaint_id}")
            ->assertUnauthorized(); // 401
    }

    public function test_authenticated_citizen_can_view_complaint_detail(): void
    {
        $user      = User::factory()->create();
        $complaint = Complaint::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/complaints/{$complaint->complaint_id}")
            ->assertOk()
            ->assertJsonPath('complaint_id', $complaint->complaint_id);
    }

    public function test_track_unknown_id_returns_404(): void
    {
        $this->getJson('/api/track/JS-2026-NOPENOPE')->assertNotFound();
    }

    /**
     * REGRESSION: complaint submission used to 500 because it hard-coded the
     * (uninstalled, unconfigured) s3 disk. It must now store evidence on the
     * configured disk and return 201.
     */
    public function test_submit_complaint_stores_evidence_and_returns_201(): void
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->create(['daily_report_count' => 0]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/complaints', [
            'vehicle_number' => 'DL01AB1234',
            'violation_type' => 'signal_jump',
            'reported_at'    => now()->subDay()->format('Y-m-d\TH:i'),
            'lat'            => 28.6139,
            'lng'            => 77.2090,
            'evidence'       => [UploadedFile::fake()->image('evidence.jpg')],
        ]);

        $response->assertCreated() // 201
            ->assertJsonStructure(['complaint_id', 'status', 'quota' => ['used', 'max', 'remaining']]);

        $cid = $response->json('complaint_id');
        $this->assertDatabaseHas('complaints', ['complaint_id' => $cid]);
        $this->assertDatabaseHas('evidence_files', ['file_type' => 'photo']);

        // The file actually landed on the configured disk.
        $stored = \App\Models\EvidenceFile::first();
        Storage::disk('public')->assertExists($stored->s3_path);
    }

    public function test_submitted_evidence_is_retrievable_via_public_track(): void
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $cid = $this->postJson('/api/complaints', [
            'vehicle_number' => 'DL01AB1234',
            'violation_type' => 'signal_jump',
            'reported_at'    => now()->subDay()->format('Y-m-d\TH:i'),
            'lat'            => 28.6139,
            'lng'            => 77.2090,
            'evidence'       => [UploadedFile::fake()->image('e.jpg')],
        ])->json('complaint_id');

        // Anonymous track returns a usable evidence URL (not null, no 500).
        $resp = $this->getJson("/api/track/{$cid}")
            ->assertOk()
            ->assertJsonPath('evidence_files.0.file_type', 'photo');

        $this->assertIsString($resp->json('evidence_files.0.file_url'));
    }

    public function test_submit_rejects_invalid_vehicle_number(): void
    {
        Storage::fake('public');
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/complaints', [
            'vehicle_number' => 'INVALID',
            'violation_type' => 'signal_jump',
            'reported_at'    => now()->subDay()->format('Y-m-d\TH:i'),
            'lat'            => 28.6139,
            'lng'            => 77.2090,
            'evidence'       => [UploadedFile::fake()->image('e.jpg')],
        ])->assertStatus(422)->assertJsonValidationErrors(['vehicle_number']);
    }
}
