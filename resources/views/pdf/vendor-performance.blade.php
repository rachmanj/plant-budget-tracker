<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Vendor Performance</title></head>
<body>
<h1>Vendor Performance Report</h1>
<table border="1" cellpadding="4">
    <tr><th>Vendor Code</th><th>Vendor Name</th><th>Indent %</th></tr>
    @foreach($data as $row)
    <tr>
        <td>{{ $row['vendor_code'] ?? '' }}</td>
        <td>{{ $row['vendor_name'] ?? '' }}</td>
        <td>{{ $row['indent_pct'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
</body>
</html>
