<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\EvidenceFile;
use App\Services\RateLimitService;
use App\Services\GeocodingService;
use App\Services\EvidenceStorageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    private const VIOLATION_TYPES = [
        'signal_jump', 'wrong_side', 'no_helmet', 'triple_riding',
        'phone_while_driving', 'no_seatbelt', 'overloading', 'others',
    ];

    public function __construct(
        private RateLimitService $rateLimitService,
        private GeocodingService $geocodingService,
        private EvidenceStorageService $evidenceStorage,
    ) {}

    // POST /api/complaints
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'vehicle_number' => ['required', 'string', 'regex:/^[A-Z]{2}\d{2}[A-Z]{1,2}\d{4}$/'],
            'violation_type' => ['required', 'string', 'in:' . implode(',', self::VIOLATION_TYPES)],
            'reported_at'    => ['required', 'date', 'before_or_equal:now'],
            'lat'            => ['required', 'numeric', 'between:-90,90'],
            'lng'            => ['required', 'numeric', 'between:-180,180'],
            'evidence'       => ['required', 'array', 'min:1', 'max:5'],
            'evidence.*'     => ['file', 'mimes:jpg,jpeg,png,mp4', 'max:51200'],
        ]);

        try {
            return DB::transaction(function () use ($request, $user, $validated) {
            // FIX #5: Quota check + increment inside transaction (atomic)
            $quota = $this->rateLimitService->quota($user);
            if ($quota['remaining'] === 0) {
                return response()->json([
                    'message' => 'Daily limit of 10 reports reached. Resets at midnight IST.',
                    'quota'   => $quota,
                ], 429);
            }

            $geo         = $this->geocodingService->reverse($validated['lat'], $validated['lng']);
            $complaintId = 'JS-' . now()->format('Y') . '-' . strtoupper(Str::random(8));

            // FIX #4: Cast lat/lng to float — no raw string interpolation
            $lat = (float) $validated['lat'];
            $lng = (float) $validated['lng'];

            $complaint = Complaint::create([
                'complaint_id'   => $complaintId,
                'user_id'        => $user->id,
                'vehicle_number' => strtoupper($validated['vehicle_number']),
                'violation_type' => $validated['violation_type'],
                'reported_at'    => Carbon::parse($validated['reported_at']),
                'location'       => DB::raw("ST_GeomFromText('POINT({$lng} {$lat})')"),
                'location_lat'   => $lat,
                'location_lng'   => $lng,
                'area_state'     => $geo['state']    ?? null,
                'area_district'  => $geo['district'] ?? null,
                'area_taluka'    => $geo['taluka']   ?? null,
                'status'         => 'submitted',
                'is_flagged'     => false,
            ]);

            foreach ($request->file('evidence', []) as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $key = $this->evidenceStorage->store($file, $complaint->id);

                EvidenceFile::create([
                    'complaint_id'      => $complaint->id,
                    'file_type'         => $ext === 'mp4' ? 'video' : 'photo',
                    's3_path'           => $key,
                    'thumbnail_s3_path' => null,
                    'uploaded_at'       => now(),
                ]);
            }

            $this->rateLimitService->increment($user);

            dispatch(new \App\Jobs\SendSubmissionConfirmation($complaint, $user));
            dispatch(new \App\Jobs\CheckDuplicateComplaint($complaint));
            dispatch(new \App\Jobs\CheckVehicleAbuse($complaint));

            return response()->json([
                'complaint_id' => $complaintId,
                'status'       => 'submitted',
                'quota'        => $this->rateLimitService->quota($user),
            ], 201);
            });
        } catch (\RuntimeException $e) {
            // Evidence storage failed — transaction is rolled back, so no
            // orphaned complaint or quota increment is persisted.
            \Illuminate\Support\Facades\Log::error('Complaint submission failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Could not store evidence files. Please try again.',
            ], 502);
        }
    }

    // GET /api/complaints/{id}
    // FIX #6: Only allow lookup by opaque complaint_id string (no numeric IDOR)
    public function show(string $id): JsonResponse
    {
        $complaint = Complaint::with(['evidenceFiles', 'statusLogs'])
            ->where('complaint_id', $id)
            ->firstOrFail();

        return response()->json([
            'id'             => $complaint->id,
            'complaint_id'   => $complaint->complaint_id,
            'vehicle_number' => $complaint->vehicle_number,
            'violation_type' => $complaint->violation_type,
            'status'         => $complaint->status,
            'reported_at'    => $complaint->reported_at->toIso8601String(),
            'location_lat'   => $complaint->location_lat,
            'location_lng'   => $complaint->location_lng,
            'area_state'     => $complaint->area_state,
            'area_district'  => $complaint->area_district,
            'evidence_files' => $complaint->evidenceFiles->map(fn($e) => [
                'id'            => $e->id,
                'file_type'     => $e->file_type,
                'thumbnail_url' => $this->evidenceStorage->url($e->thumbnail_s3_path),
                'file_url'      => $this->evidenceStorage->url($e->s3_path),
                'uploaded_at'   => $e->uploaded_at->toIso8601String(),
            ]),
            'status_logs' => $complaint->statusLogs->map(fn($l) => [
                'old_status' => $l->old_status,
                'new_status' => $l->new_status,
                'reason'     => $l->reason,
                'changed_at' => Carbon::parse($l->changed_at)->toIso8601String(),
            ]),
        ]);
    }

    // GET /api/me/quota
    public function quota(Request $request): JsonResponse
    {
        return response()->json($this->rateLimitService->quota($request->user()));
    }
}