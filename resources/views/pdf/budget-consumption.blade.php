<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Budget Consumption</title></head>
<body>
<h1>Budget Consumption Report</h1>
<table border="1" cellpadding="4">
    <tr><th>Unit</th><th>Allocated</th><th>Committed</th><th>Actual</th></tr>
    @foreach($data as $row)
    <tr>
        <td>{{ $row['unit_code'] ?? '' }}</td>
        <td>{{ $row['allocated'] ?? '' }}</td>
        <td>{{ $row['committed'] ?? '' }}</td>
        <td>{{ $row['actual'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
</body>
</html>
