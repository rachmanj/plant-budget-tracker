<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>PO Trend Report</title></head>
<body>
<h1>Purchase Order Trend</h1>
<p>Period: {{ $data['filters']['from'] ?? '' }} — {{ $data['filters']['to'] ?? '' }}
@if(!empty($data['filters']['project_code']))
 | Project: {{ $data['filters']['project_code'] }}
@endif
</p>
<p>PO count: {{ $data['summary']['document_count'] ?? 0 }} | Total: {{ $data['summary']['total_amount'] ?? '0.00' }}
| PMB: {{ $data['summary']['pmb_count'] ?? 0 }} | SAP: {{ $data['summary']['sap_count'] ?? 0 }}</p>
<table border="1" cellpadding="4">
    <tr>
        @foreach($data['csv_headers'] ?? [] as $header)
            <th>{{ $header }}</th>
        @endforeach
    </tr>
    @foreach($data['rows'] ?? [] as $row)
        <tr>
            <td>{{ $row['period'] ?? '' }}</td>
            <td>{{ $row['document_count'] ?? '' }}</td>
            <td>{{ $row['total_amount'] ?? '' }}</td>
            <td>{{ $row['pmb_count'] ?? '' }}</td>
            <td>{{ $row['sap_count'] ?? '' }}</td>
            <td>{{ $row['pmb_amount'] ?? '' }}</td>
            <td>{{ $row['sap_amount'] ?? '' }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
