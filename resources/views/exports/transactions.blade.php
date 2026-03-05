<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hesap Hareketleri Raporu</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; padding: 0; color: #333; }
        .date { font-size: 10px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f4f4f4; color: #333; font-weight: bold; text-align: left; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; color: #555; }
        .text-right { text-align: right; }
        .text-success { color: green; }
        .text-danger { color: red; }
    </style>
</head>
<body>

    <div class="header">
        <h2>Vomsis - Hesap Hareketleri Raporu</h2>
        <span class="date">Rapor Oluşturulma Tarihi: {{ now()->format('d.m.Y H:i') }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Banka & Hesap</th>
                <th>Açıklama</th>
                <th class="text-right">Tutar</th>
                <th class="text-right">Bakiye</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
            <tr>
                <td>{{ \Carbon\Carbon::parse($t->transaction_date)->format('d.m.Y H:i') }}</td>
                <td>{{ $t->bankAccount->bank->bank_name ?? '-' }} ({{ $t->bankAccount->currency }})</td>
                <td>{{ $t->description }}</td>
                <td class="text-right {{ $t->amount > 0 ? 'text-success' : 'text-danger' }}">
                    {{ $t->amount > 0 ? '+' : '' }}{{ number_format($t->amount, 2, ',', '.') }}
                </td>
                <td class="text-right">{{ number_format($t->balance, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>