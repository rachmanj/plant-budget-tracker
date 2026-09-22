<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Kinerja Vendor</title></head>
<body>
<h1>Laporan Kinerja Vendor</h1>
<table border="1" cellpadding="4">
    <tr><th>Kode Vendor</th><th>Nama Vendor</th><th>Indent %</th></tr>
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
