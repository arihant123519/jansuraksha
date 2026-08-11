<?php

namespace App\Http\Controllers\Police;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\EvidenceStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __construct(private EvidenceStorageService $evidenceStorage) {}

    // GET /api/police/export/csv
    public function csv(Request $request): StreamedResponse
    {
        $this->validateRange($request);
        $complaints = $this->query($request)->cursor();
        $filename   = 'jansuraksha_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () use ($complaints) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF"); // BOM for Excel
            fputcsv($h, [
                'Complaint ID', 'Date', 'Time', 'Vehicle Number', 'Violation Type',
                'GPS Lat', 'GPS Lng', 'State', 'District', 'Taluka',
                'Status', 'Evidence Count', 'Citizen ID (hashed)',
            ]);

            foreach ($complaints as $c) {
                fputcsv($h, [
                    $c->complaint_id,
                    $c->reported_at->format('d/m/Y'),
                    $c->reported_at->format('H:i'),
                    $c->vehicle_number,
                    ucwords(str_replace('_', ' ', $c->violation_type)),
                    $c->location_lat,
                    $c->location_lng,
                    $c->area_state    ?? '',
                    $c->area_district ?? '',
                    $c->area_taluka   ?? '',
                    ucfirst($c->status),
                    $c->evidenceFiles->count(),
                    hash('sha256', (string)$c->user_id),
                ]);
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Accel-Buffering'   => 'no',
        ]);
    }

    // GET /api/police/export/pdf
    public function pdf(Request $request): Response
    {
        $this->validateRange($request);
        $baseQuery  = $this->query($request);
        $totalCount = $baseQuery->count();
        $complaints = $baseQuery->with(['evidenceFiles', 'statusLogs'])->limit(200)->get();

        $complaints->each(function ($c) {
            $c->evidenceFiles->each(function ($e) {
                $e->temp_url = $this->evidenceStorage->url($e->thumbnail_s3_path);
            });
        });

        $pdf = Pdf::loadView('police.complaints-pdf', [
            'complaints'       => $complaints,
            'generated_at'     => now()->setTimezone('Asia/Kolkata')->format('d M Y, H:i IST'),
            'officer'          => $request->user(),
            'filters'          => array_filter([
                'State'     => $request->state,
                'District'  => $request->district,
                'From'      => $request->date_from,
                'To'        => $request->date_to,
                'Violation' => $request->violation_type,
                'Vehicle'   => $request->vehicle_number,
                'Status'    => $request->status !== 'all' ? $request->status : null,
            ]),
            'total_count'      => $complaints->count(),
            'is_truncated'     => $totalCount > 200,
            'total_available'  => $totalCount,
        ])->setPaper('a4', 'portrait');

        $response = $pdf->download('jansuraksha_complaints_' . now()->format('Ymd_His') . '.pdf');

        // FIX #9: Inform client when results were silently truncated
        if ($totalCount > 200) {
            $response->headers->set('X-Total-Available', $totalCount);
            $response->headers->set('X-Results-Truncated', 'true');
        }

        return $response;
    }

    private function query(Request $request)
    {
        $officer = $request->user();
        $isAdmin = $officer->role === 'admin';

        return Complaint::with('evidenceFiles')
            // FIX #9: Scope export to officer jurisdiction unless admin
            ->when(!$isAdmin && $officer->jurisdiction_state,
                fn($q) => $q->where('area_state', $officer->jurisdiction_state))
            ->when(!$isAdmin && $officer->jurisdiction_district,
                fn($q) => $q->where('area_district', $officer->jurisdiction_district))
            ->when($request->state && $isAdmin,    fn($q) => $q->where('area_state', $request->state))
            ->when($request->district && $isAdmin, fn($q) => $q->where('area_district', $request->district))
            ->when($request->date_from,      fn($q) => $q->where('reported_at', '>=', $request->date_from . ' 00:00:00'))
            ->when($request->date_to,        fn($q) => $q->where('reported_at', '<=', $request->date_to . ' 23:59:59'))
            ->when($request->violation_type, fn($q) => $q->where('violation_type', $request->violation_type))
            ->when($request->vehicle_number, fn($q) => $q->where('vehicle_number', 'LIKE', strtoupper($request->vehicle_number) . '%'))
            ->when(
                $request->status && $request->status !== 'all',
                fn($q) => $q->where('status', $request->status)
            )
            ->orderBy('reported_at', 'desc');
    }

    private function validateRange(Request $request): void
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        if ($request->date_from && $request->date_to) {
            if (Carbon::parse($request->date_from)->diffInDays(Carbon::parse($request->date_to)) > 90) {
                abort(422, 'Date range cannot exceed 90 days.');
            }
        }
    }
}