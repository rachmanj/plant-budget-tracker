<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Equipment Cost</title></head>
<body>
<h1>Equipment Cost Report</h1>
<table border="1" cellpadding="4">
    <tr><th>Equipment ID</th><th>Cost/Hour</th><th>Total Spend</th><th>Delta HM</th><th>Stale Data</th></tr>
    @foreach($data as $row)
    <tr>
        <td>{{ $row['equipment_id'] ?? '' }}</td>
        <td>{{ $row['cost_per_hour'] ?? '' }}</td>
        <td>{{ $row['total_spend'] ?? '' }}</td>
        <td>{{ $row['delta_hm'] ?? '' }}</td>
        <td>{{ ! empty($row['stale']) ? 'Yes' : 'No' }}</td>
    </tr>
    @endforeach
</table>
</body>
</html>
