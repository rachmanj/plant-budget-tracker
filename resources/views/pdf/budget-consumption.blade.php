<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Budget Consumption</title></head>
<body>
<h1>Budget Consumption Report</h1>
@if(!empty($data['summary']))
    <h2>Ringkasan Proyek — {{ $data['summary']['project_code'] ?? '' }}</h2>
    <table border="1" cellpadding="4">
        <tr><th>Pagu</th><th>Committed</th><th>Actual</th><th>Remaining</th><th>Utilization %</th></tr>
        <tr>
            <td>{{ $data['summary']['pagu'] ?? '' }}</td>
            <td>{{ $data['summary']['committed'] ?? '' }}</td>
            <td>{{ $data['summary']['actual'] ?? '' }}</td>
            <td>{{ $data['summary']['remaining'] ?? '' }}</td>
            <td>{{ $data['summary']['utilization_pct'] ?? '' }}</td>
        </tr>
    </table>
@endif
<h2>Rincian per Unit</h2>
<table border="1" cellpadding="4">
    <tr><th>Unit</th><th>Jumlah Permintaan</th><th>Total Nilai</th><th>Status Terakhir</th></tr>
    @foreach($data['units'] ?? [] as $row)
    <tr>
        <td>{{ $row['unit_code'] ?? '' }}</td>
        <td>{{ $row['request_count'] ?? '' }}</td>
        <td>{{ $row['total_estimated'] ?? '' }}</td>
        <td>{{ $row['last_status'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
</body>
</html>
