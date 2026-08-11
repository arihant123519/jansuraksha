<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>JanSuraksha Complaints</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#1a1a1a;margin:0}
.header{background:#1e3a8a;color:#fff;padding:14px 20px;margin-bottom:16px}
.header h1{margin:0;font-size:17px}
.header p{margin:4px 0 0;font-size:9px;opacity:.8}
.filters{background:#f1f5f9;padding:8px 20px;margin:0 20px 14px;border-radius:6px;font-size:10px}
.page{page-break-after:always;padding:0 20px 20px}
.page:last-child{page-break-after:avoid}
.chead{border-bottom:2px solid #1e3a8a;padding-bottom:8px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center}
.cid{font-size:14px;font-weight:bold;color:#1e3a8a}
.badge{padding:2px 8px;border-radius:10px;font-size:9px;font-weight:bold}
.s-submitted{background:#dbeafe;color:#1e40af}
.s-actioned{background:#dcfce7;color:#166534}
.s-rejected{background:#fee2e2;color:#991b1b}
.s-duplicate{background:#f3f4f6;color:#374151}
.s-pending{background:#fef9c3;color:#854d0e}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px}
.lbl{font-size:9px;color:#6b7280;text-transform:uppercase}
.val{font-size:11px;font-weight:600;margin-top:2px}
.map-link{font-size:10px;color:#1d4ed8;display:block;margin-top:6px}
.log{margin-top:10px;border-top:1px solid #e5e7eb;padding-top:8px}
.log-row{font-size:9px;margin-bottom:3px}
.flag{background:#fef2f2;border:1px solid #fca5a5;border-radius:5px;padding:5px 10px;margin-bottom:8px;font-size:10px;color:#991b1b}
</style>
</head>
<body>
<div class="header">
  <h1>JanSuraksha — Traffic Violation Reports</h1>
  <p>Generated: {{ $generated_at }} · Officer: {{ $officer->name ?? 'N/A' }} ({{ $officer->badge_number ?? '' }}) · Total: {{ $total_count }}</p>
</div>

@if(!empty($filters))
<div class="filters">
  <strong>Filters:</strong>
  @foreach($filters as $k=>$v) <strong>{{ $k }}:</strong> {{ $v }} &nbsp; @endforeach
</div>
@endif

@foreach($complaints as $c)
<div class="page">
  @if($c->is_flagged)
  <div class="flag">⚠ FLAGGED — Same vehicle reported 5+ times in 7 days. Verify before acting.</div>
  @endif
  <div class="chead">
    <div class="cid">{{ $c->complaint_id }}</div>
    <span class="badge s-{{ $c->status }}">{{ strtoupper($c->status) }}</span>
  </div>
  <div class="grid">
    <div><div class="lbl">Vehicle</div><div class="val" style="font-family:monospace;color:#1e3a8a">{{ $c->vehicle_number }}</div></div>
    <div><div class="lbl">Violation</div><div class="val">{{ ucwords(str_replace('_',' ',$c->violation_type)) }}</div></div>
    <div><div class="lbl">Date &amp; Time</div><div class="val">{{ $c->reported_at->setTimezone('Asia/Kolkata')->format('d M Y, H:i IST') }}</div></div>
    <div><div class="lbl">Location</div><div class="val">{{ $c->area_district ?? 'Unknown' }}, {{ $c->area_state ?? '' }}</div></div>
    <div><div class="lbl">GPS</div><div class="val" style="font-family:monospace">{{ $c->location_lat }}, {{ $c->location_lng }}</div></div>
    <div><div class="lbl">Citizen ID (hashed)</div><div class="val" style="font-family:monospace;font-size:9px">{{ substr(hash('sha256',$c->user_id),0,16) }}…</div></div>
  </div>
  <a class="map-link" href="https://www.google.com/maps?q={{ $c->location_lat }},{{ $c->location_lng }}">📍 View on Google Maps</a>

  @if($c->evidenceFiles->isNotEmpty())
  <div style="margin-top:10px;font-size:10px;color:#6b7280;text-transform:uppercase;margin-bottom:4px">Evidence ({{ $c->evidenceFiles->count() }})</div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    @foreach($c->evidenceFiles as $ev)
      @if($ev->temp_url && $ev->file_type==='photo')
        <img src="{{ $ev->temp_url }}" style="width:80px;height:60px;object-fit:cover;border-radius:4px;border:1px solid #e5e7eb">
      @endif
    @endforeach
  </div>
  @endif

  @if($c->statusLogs->isNotEmpty())
  <div class="log">
    <div style="font-size:9px;color:#6b7280;text-transform:uppercase;margin-bottom:4px">Status history</div>
    @foreach($c->statusLogs as $l)
    <div class="log-row">
      · <strong>{{ ucfirst($l->old_status) }} → {{ ucfirst($l->new_status) }}</strong>
      @if($l->reason) — {{ $l->reason }} @endif
      <span style="color:#9ca3af"> · {{ \Carbon\Carbon::parse($l->changed_at)->setTimezone('Asia/Kolkata')->format('d M Y H:i') }} IST</span>
    </div>
    @endforeach
  </div>
  @endif
</div>
@endforeach
</body>
</html>