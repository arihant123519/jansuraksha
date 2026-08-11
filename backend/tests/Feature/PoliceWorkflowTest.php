<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\PoliceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PoliceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_police_login_returns_token(): void
    {
        $officer = PoliceUser::factory()->create(['username' => 'officer_x']);

        $this->postJson('/api/auth/police/login', [
            'username' => 'officer_x',
            'password' => 'Police@1234',
        ])->assertOk()
          ->assertJsonStructure(['token', 'user' => ['id', 'name', 'role', 'jurisdiction_state']]);
    }

    public function test_police_login_rejects_bad_credentials(): void
    {
        PoliceUser::factory()->create(['username' => 'officer_x']);

        $this->postJson('/api/auth/police/login', [
            'username' => 'officer_x',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_citizen_token_cannot_access_police_routes(): void
    {
        // EnsureRole must reject a non-PoliceUser principal (403).
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/police/complaints')->assertForbidden(); // 403
    }

    public function test_officer_only_sees_own_jurisdiction(): void
    {
        $officer = PoliceUser::factory()->create([
            'jurisdiction_state'    => 'Delhi',
            'jurisdiction_district' => 'Central Delhi',
        ]);
        Complaint::factory()->create(['area_state' => 'Delhi',       'area_district' => 'Central Delhi']);
        Complaint::factory()->create(['area_state' => 'Maharashtra', 'area_district' => 'Mumbai City']);

        Sanctum::actingAs($officer);

        $this->getJson('/api/police/complaints')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.area_state', 'Delhi');
    }

    public function test_valid_status_transition_creates_audit_log(): void
    {
        Queue::fake();
        $officer   = PoliceUser::factory()->create();
        $complaint = Complaint::factory()->create(['status' => 'submitted']);

        Sanctum::actingAs($officer);

        $this->patchJson("/api/police/complaints/{$complaint->complaint_id}", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('complaints', [
            'complaint_id' => $complaint->complaint_id, 'status' => 'pending',
        ]);
        $this->assertDatabaseHas('complaint_status_logs', [
            'complaint_id' => $complaint->id,
            'changed_by'   => $officer->id,
            'old_status'   => 'submitted',
            'new_status'   => 'pending',
        ]);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $officer   = PoliceUser::factory()->create();
        $complaint = Complaint::factory()->create(['status' => 'pending']);

        Sanctum::actingAs($officer);

        // pending -> submitted is not an allowed forward transition.
        $this->patchJson("/api/police/complaints/{$complaint->complaint_id}", ['status' => 'submitted'])
            ->assertStatus(422);
    }

    public function test_rejection_requires_reason(): void
    {
        $officer   = PoliceUser::factory()->create();
        $complaint = Complaint::factory()->create(['status' => 'pending']);

        Sanctum::actingAs($officer);

        $this->patchJson("/api/police/complaints/{$complaint->complaint_id}", ['status' => 'rejected'])
            ->assertStatus(422);
    }

    /**
     * REGRESSION: PDF export used to 500 (barryvdh/laravel-dompdf missing).
     */
    public function test_pdf_export_returns_a_pdf(): void
    {
        Storage::fake('public');
        $officer = PoliceUser::factory()->create();
        Complaint::factory()->count(2)->create(['area_state' => 'Delhi', 'area_district' => 'Central Delhi']);

        Sanctum::actingAs($officer);

        $response = $this->get('/api/police/export/pdf');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_csv_export_streams_csv(): void
    {
        $officer = PoliceUser::factory()->create();
        Complaint::factory()->create(['area_state' => 'Delhi', 'area_district' => 'Central Delhi']);

        Sanctum::actingAs($officer);

        $response = $this->get('/api/police/export/csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }
}
