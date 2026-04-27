<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İşlem Detayı</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #0d6efd; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .table th { background-color: #f8f9fa; width: 30%; }
        .amount { font-size: 20px; font-weight: bold; }
        .text-success { color: green; }
        .text-danger { color: red; }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">Vomsis FinTech</div>
        <p>Hesap Hareket Detayı Dekontu</p>
    </div>

    <table class="table">
        <tr>
            <th>İşlem No / Referans</th>
            <td>#{{ $transaction->id }}</td>
        </tr>
        <tr>
            <th>İşlem Tarihi</th>
            <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d.m.Y H:i:s') }}</td>
        </tr>
        <tr>
            <th>Banka & Hesap</th>
            <td>{{ $transaction->bankAccount->bank->bank_name }} - {{ $transaction->bankAccount->account_name }}</td>
        </tr>
        <tr>
            <th>İşlem Tipi</th>
            <td>{{ $transaction->transactionType->name ?? 'Diğer' }}</td>
        </tr>
        <tr>
            <th>Açıklama</th>
            <td>{{ $transaction->description }}</td>
        </tr>
        <tr>
            <th>İşlem Tutarı</th>
            <td class="amount {{ $transaction->amount > 0 ? 'text-success' : 'text-danger' }}">
                {{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount, 2, ',', '.') }} {{ $transaction->bankAccount->currency }}
            </td>
        </tr>
        <tr>
            <th>Sonraki Bakiye</th>
            <td>{{ number_format($transaction->balance, 2, ',', '.') }} {{ $transaction->bankAccount->currency }}</td>
        </tr>
        <tr>
            <th>Etiketler</th>
            <td>
                @forelse($transaction->tags as $tag)
                    [{{ $tag->name }}] 
                @empty
                    Etiket yok.
                @endforelse
            </td>
        </tr>
    </table>

    <p style="text-align: center; margin-top: 40px; font-size: 12px; color: #777;">
        Bu belge bilgi amaçlıdır, mali değeri yoktur. <br>
        Oluşturulma Tarihi: {{ now()->format('d.m.Y H:i:s') }}
    </p>

</body>
</html>