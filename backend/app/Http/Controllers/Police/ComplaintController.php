<?php

namespace App\Http\Controllers\Police;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintStatusLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    // Valid forward-only status transitions
    private const ALLOWED_TRANSITIONS = [
        'submitted' => ['pending', 'duplicate'],
        'pending'   => ['actioned', 'rejected', 'duplicate'],
        'actioned'  => [],
        'rejected'  => [],
        'duplicate' => [],
    ];

    // GET /api/police/complaints
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'lat'       => ['nullable', 'numeric', 'between:-90,90'],
            'lng'       => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'between:1,50'],
            'per_page'  => ['nullable', 'integer', 'between:10,200'],
        ]);

        if ($request->date_from && $request->date_to) {
            $diff = Carbon::parse($request->date_from)->diffInDays(Carbon::parse($request->date_to));
            if ($diff > 90) {
                return response()->json(['message' => 'Date range cannot exceed 90 days.'], 422);
            }
        }

        $officer = $request->user();
        $isAdmin = $officer->role === 'admin';

        $query = Complaint::with('evidenceFiles')
            // FIX #7: Auto-scope to officer jurisdiction unless admin
            ->when(!$isAdmin && $officer->jurisdiction_state,
                fn($q) => $q->where('area_state', $officer->jurisdiction_state))
            ->when(!$isAdmin && $officer->jurisdiction_district,
                fn($q) => $q->where('area_district', $officer->jurisdiction_district))
            // Allow further filter within jurisdiction
            ->when($request->state && $isAdmin,    fn($q) => $q->where('area_state', $request->state))
            ->when($request->district && $isAdmin, fn($q) => $q->where('area_district', $request->district))
            ->when($request->taluka,               fn($q) => $q->where('area_taluka', $request->taluka))
            ->when($request->date_from,            fn($q) => $q->where('reported_at', '>=', $request->date_from . ' 00:00:00'))
            ->when($request->date_to,              fn($q) => $q->where('reported_at', '<=', $request->date_to . ' 23:59:59'))
            ->when($request->violation_type,       fn($q) => $q->where('violation_type', $request->violation_type))
            ->when($request->vehicle_number,       fn($q) => $q->where('vehicle_number', 'LIKE', strtoupper($request->vehicle_number) . '%'))
            ->when(
                $request->status && $request->status !== 'all',
                fn($q) => $q->where('status', $request->status)
            )
            ->when(
                $request->filled('lat') && $request->filled('lng'),
                fn($q) => $q->whereRaw(
                    'ST_Distance_Sphere(location, ST_GeomFromText(?)) <= ?',
                    ["POINT({$request->lng} {$request->lat})", ($request->radius_km ?? 10) * 1000]
                )
            )
            ->orderBy('reported_at', 'desc');

        $results = $query->paginate((int)($request->per_page ?? 50));

        return response()->json([
            'data' => $results->map(fn($c) => [
                'id'             => $c->id,
                'complaint_id'   => $c->complaint_id,
                'vehicle_number' => $c->vehicle_number,
                'violation_type' => $c->violation_type,
                'status'         => $c->status,
                'is_flagged'     => $c->is_flagged,
                'reported_at'    => $c->reported_at->toIso8601String(),
                'area_state'     => $c->area_state,
                'area_district'  => $c->area_district,
                'location_lat'   => $c->location_lat,
                'location_lng'   => $c->location_lng,
                'evidence_count' => $c->evidenceFiles->count(),
            ]),
            'meta' => [
                'total'        => $results->total(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'per_page'     => $results->perPage(),
            ],
        ]);
    }

    // PATCH /api/police/complaints/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,actioned,rejected,duplicate'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->status === 'rejected' && empty($request->reason)) {
            return response()->json(['message' => 'Rejection reason is required.'], 422);
        }

        // FIX #6: Lookup only by complaint_id (no numeric IDOR)
        $complaint = Complaint::where('complaint_id', $id)->firstOrFail();

        // FIX #8: Status transition guard
        $allowed = self::ALLOWED_TRANSITIONS[$complaint->status] ?? [];
        if (!in_array($request->status, $allowed, true)) {
            return response()->json([
                'message' => "Cannot transition from '{$complaint->status}' to '{$request->status}'.",
            ], 422);
        }

        // FIX #7: Ensure officer can only update complaints in their jurisdiction
        $officer = $request->user();
        if ($officer->role !== 'admin' &&
            $officer->jurisdiction_state &&
            $complaint->area_state !== $officer->jurisdiction_state) {
            return response()->json(['message' => 'Outside your jurisdiction.'], 403);
        }

        DB::transaction(function () use ($complaint, $request, $officer) {
            ComplaintStatusLog::create([
                'complaint_id'    => $complaint->id,
                'changed_by'      => $officer->id,   // FIX #8: PoliceUser ID — ensure FK → police_users
                'old_status'      => $complaint->status,
                'new_status'      => $request->status,
                'reason'          => $request->reason,
                'changed_at'      => now(),
            ]);
            $complaint->update(['status' => $request->status]);
            dispatch(new \App\Jobs\SendStatusUpdateNotification($complaint));
        });

        return response()->json([
            'message'      => 'Status updated.',
            'complaint_id' => $complaint->complaint_id,
            'status'       => $request->status,
        ]);
    }
}