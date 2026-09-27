<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Top Suppliers</title></head>
<body>
<h1>Top Suppliers</h1>
<p>Period: {{ $data['filters']['from'] ?? '' }} — {{ $data['filters']['to'] ?? '' }}</p>
<p>Suppliers: {{ $data['summary']['supplier_count'] ?? 0 }} | PO total: {{ $data['summary']['total_amount'] ?? '0.00' }}</p>
<table border="1" cellpadding="4">
    <tr>
        @foreach($data['csv_headers'] ?? [] as $header)
            <th>{{ $header }}</th>
        @endforeach
    </tr>
    @foreach($data['rows'] ?? [] as $row)
        <tr>
            <td>{{ $row['rank'] ?? '' }}</td>
            <td>{{ $row['vendor_code'] ?? '' }}</td>
            <td>{{ $row['vendor_name'] ?? '' }}</td>
            <td>{{ $row['document_count'] ?? '' }}</td>
            <td>{{ $row['total_amount'] ?? '' }}</td>
            <td>{{ $row['share_pct'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
