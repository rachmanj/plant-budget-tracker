<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>PR by Department</title></head>
<body>
<h1>Purchase Requests by Department</h1>
<p>Period: {{ $data['filters']['from'] ?? '' }} — {{ $data['filters']['to'] ?? '' }}</p>
<p>Documents: {{ $data['summary']['document_count'] ?? 0 }} | Total: {{ $data['summary']['total_amount'] ?? '0.00' }}</p>
<table border="1" cellpadding="4">
    <tr>
        @foreach($data['csv_headers'] ?? [] as $header)
            <th>{{ $header }}</th>
        @endforeach
    </tr>
    @foreach($data['rows'] ?? [] as $row)
        <tr>
            <td>{{ $row['department_code'] ?? '' }}</td>
            <td>{{ $row['department_name'] ?? '' }}</td>
            <td>{{ $row['project_code'] ?? '' }}</td>
            <td>{{ $row['document_count'] ?? '' }}</td>
            <td>{{ $row['total_amount'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
