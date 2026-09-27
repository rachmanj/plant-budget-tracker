<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Approval Turnaround</title></head>
<body>
<h1>Approval Turnaround (PR → PO)</h1>
<p>Period: {{ $data['filters']['from'] ?? '' }} — {{ $data['filters']['to'] ?? '' }}</p>
<p>Documents: {{ $data['summary']['document_count'] ?? 0 }}
| Avg days: {{ $data['summary']['average_days'] ?? '0.00' }}
| Median: {{ $data['summary']['median_days'] ?? '0.00' }}</p>
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
            <td>{{ $row['document_count'] ?? '' }}</td>
            <td>{{ $row['average_days'] ?? '' }}</td>
            <td>{{ $row['median_days'] ?? '' }}</td>
            <td>{{ $row['buckets']['0_3'] ?? '' }}</td>
            <td>{{ $row['buckets']['4_7'] ?? '' }}</td>
            <td>{{ $row['buckets']['8_14'] ?? '' }}</td>
            <td>{{ $row['buckets']['over_14'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
