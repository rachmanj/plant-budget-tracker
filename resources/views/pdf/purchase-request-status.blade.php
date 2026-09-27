<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>PR Status Report</title></head>
<body>
<h1>Purchase Request Status</h1>
<p>Period: {{ $data['filters']['from'] ?? '' }} — {{ $data['filters']['to'] ?? '' }}
@if(!empty($data['filters']['project_code']))
 | Project: {{ $data['filters']['project_code'] }}
@endif
@if(!empty($data['filters']['dept_code']))
 | Dept: {{ $data['filters']['dept_code'] }}
@endif
</p>
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
            <td>{{ $row['pr_status'] ?? '' }}</td>
            <td>{{ $row['closed_status'] ?? '' }}</td>
            <td>{{ $row['document_count'] ?? '' }}</td>
            <td>{{ $row['total_amount'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
