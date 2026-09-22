<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Biaya Peralatan</title></head>
<body>
<h1>Laporan Biaya Peralatan</h1>
<table border="1" cellpadding="4">
    <tr><th>ID Peralatan</th><th>Biaya/Jam</th><th>Total Pengeluaran</th><th>Delta HM</th><th>Data Usang</th></tr>
    @foreach($data as $row)
    <tr>
        <td>{{ $row['equipment_id'] ?? '' }}</td>
        <td>{{ $row['cost_per_hour'] ?? '' }}</td>
        <td>{{ $row['total_spend'] ?? '' }}</td>
        <td>{{ $row['delta_hm'] ?? '' }}</td>
        <td>{{ ! empty($row['stale']) ? 'Ya' : 'Tidak' }}</td>
    </tr>
    @endforeach
</table>
</body>
</html>
