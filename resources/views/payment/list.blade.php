<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sanal POS İşlemleri | Vomsis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="{{ url('/dashboard') }}">🏢 Vomsis FinTech</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ url('/dashboard') }}">📊 Hesap Hareketleri</a></li>
                <li class="nav-item"><a class="nav-link active fw-bold" href="{{ route('payment.list') }}">💳 Sanal POS İşlemleri</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('payment.index') }}" target="_blank">🛒 Ödeme Ekranına Git</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-credit-card text-primary me-2"></i>Sanal POS Tahsilat Raporu</h5>
            
            <a href="{{ route('payment.sync') }}" 
               class="btn btn-outline-primary btn-sm fw-bold shadow-sm" 
               onclick="this.innerHTML='<i class=\'fas fa-sync-alt fa-spin me-1\'></i> Veriler Çekiliyor...'; this.classList.add('disabled');">
                <i class="fas fa-cloud-download-alt me-1"></i> Verileri Vomsis'ten Çek
            </a>
            </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary">
                    <tr>
                        <th>Tarih</th>
                        <th>Sipariş No</th>
                        <th>Kullanılan POS</th>
                        <th>Maskeli Kart</th>
                        <th>Durum</th>
                        <th class="text-end pe-4">Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($islemler as $islem)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($islem->transaction_date)->format('d.m.Y H:i') }}</td>
                        <td class="fw-bold text-secondary">{{ $islem->order_id }}</td>
                        <td>{{ $islem->virtualPos->name ?? 'Bilinmiyor' }}</td>
                        <td><span class="badge bg-light text-dark border"><i class="far fa-credit-card me-1"></i> {{ $islem->card_mask }}</span></td>
                        <td>
                            @if($islem->status == 'success')
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Başarılı ({{ $islem->response_code }})</span>
                            @else
                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Hata</span>
                            @endif
                        </td>
                        <td class="text-end pe-4 fw-bold text-success fs-5">
                            +{{ number_format($islem->amount, 2, ',', '.') }} {{ $islem->currency }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">Henüz hiç Sanal POS tahsilatı yapılmamış.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($islemler->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center pt-3">
            {{ $islemler->links() }}
        </div>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>