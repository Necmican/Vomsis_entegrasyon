<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vomsis Hesap Hareketleri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bank-item:hover { background-color: #f8f9fa; }
        
        .bank-item.active { 
            background-color: #e9ecef !important; 
            border-left: 4px solid #0d6efd !important; 
            font-weight: bold; 
            color: #000 !important; 
        }
        
        .table-row-clickable { cursor: pointer; transition: background 0.2s; }
        .table-row-clickable:hover { background-color: #f1f4f8 !important; }
        .bank-logo { width: 32px; height: 32px; object-fit: contain; border-radius: 4px; background: white; padding: 2px; border: 1px solid #ddd; }
        
        .account-card-link { text-decoration: none; color: inherit; display: block; }
        .account-card:hover { border-color: #0d6efd !important; cursor: pointer; transform: translateY(-1px); transition: all 0.2s; }

    
        .table {
            border-collapse: collapse !important; 
            border: 2px solid #495057 !important; /* Dış çerçeve kalın ve koyu */
            margin-bottom: 0 !important;
        }

        .table th, 
        .table td {
            border: 1px solid #6c757d !important; /* İç hücre çizgileri net ve belirgin */
            vertical-align: middle; 
        }

        .table > thead > tr > th {
            border-bottom: 3px solid #212529 !important; 
            background-color: #e2e6ea !important; 
            color: #212529 !important; 
            font-weight: 700 !important;
        }

        .table-hover tbody tr:hover {
            background-color: #dbe4ef !important; /* Üzerine gelince belirgin renk */
        }
    </style>
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="{{ url('/dashboard') }}">🏢 Vomsis FinTech</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="target" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active" href="{{ url('/dashboard') }}">📊 Hesap Hareketleri</a></li>
                <li class="nav-item"><a class="nav-link text-white-50" href="{{ route('payment.list') }}">💳 Sanal POS İşlemleri</a></li>
            </ul>
            <div class="d-flex">
                <a href="{{ url('/arka-planda-cek') }}" class="btn btn-success btn-sm fw-bold">🔄 Vomsis'ten Verileri Çek</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    @if(session('mesaj'))
    <div class="alert alert-success shadow-sm p-3 mb-4">
        <strong>Harika!</strong> {{ session('mesaj') }}
    </div>
    @endif

    <div class="row mb-4">
        @foreach($totals as $currency => $total)
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 bg-primary text-white h-100">
                <div class="card-body text-center py-3">
                    <h6 class="text-uppercase mb-2 text-white-50">Toplam Bakiye ({{ $currency }})</h6>
                    <h3 class="mb-0">{{ number_format($total, 2, ',', '.') }} {{ $currency }}</h3>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-md-3 col-lg-2 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 text-secondary">
                    🏦 Bankalar Menüsü
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ url('/dashboard') }}" class="list-group-item list-group-item-action py-3 bank-item {{ !request('bank_id') ? 'active' : '' }}">
                        <div class="d-flex align-items-center">
                            <span class="fs-5 me-2">💼</span> Tüm Bankalar
                        </div>
                    </a>

                    @foreach($banks as $bank)
                    <a href="{{ url('/dashboard?bank_id=' . $bank->id) }}" class="list-group-item list-group-item-action py-3 bank-item {{ request('bank_id') == $bank->id ? 'active' : '' }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <img src="{{ asset('logos/' . \Illuminate\Support\Str::slug($bank->bank_name) . '.jpg') }}" 
                                     onerror="this.src='https://via.placeholder.com/32?text=B'" 
                                     class="bank-logo me-2" alt="logo">
                                <span style="font-size: 0.9rem;">{{ $bank->bank_name }}</span>
                            </div>
                            <span class="badge bg-secondary rounded-pill">{{ $bank->bankAccounts->count() }}</span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-md-9 col-lg-10">
            
            @if(isset($activeBank) && $activeBank)
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-3 d-flex align-items-center border-bottom-0">
                    <img src="{{ asset('logos/' . \Illuminate\Support\Str::slug($activeBank->bank_name) . '.jpg') }}" 
                         onerror="this.src='https://via.placeholder.com/40?text=B'" 
                         style="height: 40px; object-fit: contain;" class="me-3">
                    <h4 class="mb-0 fw-bold text-dark">{{ $activeBank->bank_name }} Detayları</h4>
                </div>
                
                <div class="card-body px-4 pb-4">
                    <div class="row">
                        <div class="col-md-5 border-end pe-4">
                            <h6 class="text-muted fw-bold mb-3">Kur Özetleri</h6>
                            @foreach($activeBankSummary as $curr => $data)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <span class="fw-bold fs-5">{{ $curr }}</span> 
                                    <small class="text-muted ms-1">[{{ $data['count'] }} hesap]</small>
                                </div>
                                <span class="fw-bold fs-5 text-primary">{{ number_format($data['total'], 2, ',', '.') }} {{ $curr }}</span>
                            </div>
                            @endforeach
                        </div>
                        
                        <div class="col-md-7 ps-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fw-bold mb-0">Hesap Listesi (Filtrelemek için tıklayın)</h6>
                                @if(request('account_id'))
                                    <a href="{{ url('/dashboard?bank_id=' . $activeBank->id) }}" class="badge bg-danger text-decoration-none">Filtreyi Kaldır ✕</a>
                                @endif
                            </div>
                            
                            @foreach($activeBankAccounts as $acc)
                            <a href="{{ request()->fullUrlWithQuery(['account_id' => $acc->id]) }}" class="account-card-link">
                                <div class="card border {{ request('account_id') == $acc->id ? 'border-primary border-2 shadow' : 'border-light shadow-sm bg-light' }} mb-2 rounded-3 account-card">
                                    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="{{ request('account_id') == $acc->id ? 'text-primary' : 'text-secondary' }} fw-bold me-2">
                                                {{ $acc->account_name }}
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <strong class="fs-6 {{ request('account_id') == $acc->id ? 'text-primary' : 'text-dark' }}">
                                                {{ number_format($acc->balance, 2, ',', '.') }} {{ $acc->currency }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($filteredSummaries) && $filteredSummaries->count() > 0 && (request('bank_id') || request('search') || request('type_code')))
            <div class="row mb-4">
                @foreach($filteredSummaries as $summary)
                <div class="col-md-6 col-lg-4 mb-2">
                    <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                        <div class="card-body py-2">
                            <h6 class="text-muted text-uppercase mb-2 fw-bold" style="font-size: 0.8rem;">
                                <span class="badge bg-info text-dark me-1">{{ $summary->currency }}</span> İşlem Özeti (Bu Liste)
                            </h6>
                            <div class="d-flex justify-content-between">
                                <div><small class="text-success d-block fw-bold">+{{ number_format($summary->total_income, 2, ',', '.') }}</small></div>
                                <div><small class="text-danger d-block fw-bold">{{ number_format($summary->total_expense, 2, ',', '.') }}</small></div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body bg-white py-2">
                    <form action="{{ url('/dashboard') }}" method="GET" class="row g-2 align-items-center">
                        @if(request('bank_id'))
                        <input type="hidden" name="bank_id" value="{{ request('bank_id') }}">
                        @endif
                        
                        @if(request('account_id'))
                        <input type="hidden" name="account_id" value="{{ request('account_id') }}">
                        @endif
                        
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Açıklamada ara..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="currency" class="form-select form-select-sm">
                                <option value="">Tüm Kurlar</option>
                                <option value="TL" {{ request('currency') == 'TL' ? 'selected' : '' }}>TL</option>
                                <option value="USD" {{ request('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                                <option value="EUR" {{ request('currency') == 'EUR' ? 'selected' : '' }}>EUR</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="type_code" class="form-select form-select-sm">
                                <option value="">Tüm İşlem Tipleri</option>
                                @foreach($transactionTypes as $type)
                                    <option value="{{ $type->vomsis_type_id }}" {{ request('type_code') == $type->vomsis_type_id ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filtrele</button>
                            @if(request('search') || request('currency') || request('type_code') || request('account_id'))
                                <a href="{{ url('/dashboard') }}{{ request('bank_id') ? '?bank_id='.request('bank_id') : '' }}" class="btn btn-outline-secondary btn-sm w-100">Temizle</a>
                            @endif
                        </div>
                    </form>

                    <div class="row mt-3 border-top pt-3">
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('export.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm fw-bold">
                                <i class="fas fa-file-pdf"></i> PDF İNDİR
                            </a>
                            
                            <button type="button" class="btn btn-outline-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#excelExportModal">
                                <i class="fas fa-file-excel"></i> EXCEL ÇIKART
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Banka & Hesap</th>
                                <th>İşlem Tipi</th> 
                                <th>Açıklama</th>
                                <th class="text-end pe-4">Tutar & Bakiye</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $islem)
                            <tr class="table-row-clickable" data-bs-toggle="modal" data-bs-target="#islemModal{{ $islem->id }}">
                                <td>{{ \Carbon\Carbon::parse($islem->transaction_date)->format('d.m.Y H:i') }}</td>
                                <td>
                                    <strong>{{ ucfirst($islem->bankAccount->bank->bank_name ?? 'Bilinmeyen') }}</strong> <br>
                                    <small class="text-muted">{{ $islem->bankAccount->account_name }} ({{ $islem->bankAccount->currency }})</small>
                                </td>
                                <td><span class="badge bg-secondary">{{ $islem->transactionType->name ?? 'Diğer' }}</span></td>
                                <td class="text-truncate" style="max-width: 250px;">{{ $islem->description }}</td>
                                <td class="text-end pe-4">
                                    <span class="fw-bold {{ $islem->amount > 0 ? 'text-success' : 'text-danger' }}" style="font-size: 1.05rem;">
                                        {{ $islem->amount > 0 ? '+' : '' }}{{ number_format($islem->amount, 2, ',', '.') }}
                                    </span><br>
                                    <small class="text-muted" style="font-size: 0.8rem;">Bakiye: {{ number_format($islem->balance, 2, ',', '.') }} {{ $islem->bankAccount->currency }}</small>
                                </td>
                            </tr>

                            <div class="modal fade" id="islemModal{{ $islem->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header bg-dark text-white border-0">
                                            <h5 class="modal-title">İşlem Detayları</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                        </div>
                                        <div class="modal-body bg-light">
                                            <div class="small text-muted mb-3 pb-2 border-bottom fw-bold text-uppercase">
                                                🏦 Banka #{{ $islem->bankAccount->bank->id }} > 💳 Hesap #{{ $islem->bankAccount->id }} > 📄 İşlem #{{ $islem->id }}
                                            </div>
                                            <ul class="list-group list-group-flush rounded shadow-sm border">
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Banka:</span>
                                                    <strong class="text-end">{{ $islem->bankAccount->bank->bank_name }}</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Hesap Adı:</span>
                                                    <strong class="text-end">{{ $islem->bankAccount->account_name }}</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Tarih:</span>
                                                    <strong>{{ \Carbon\Carbon::parse($islem->transaction_date)->format('d.m.Y H:i:s') }}</strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">İşlem Yönü:</span>
                                                    @if($islem->amount > 0)
                                                        <span class="badge bg-success fs-6 px-3 py-2">GELEN (ALACAKLI)</span>
                                                    @else
                                                        <span class="badge bg-danger fs-6 px-3 py-2">GİDEN (BORÇLU)</span>
                                                    @endif
                                                </li>
                                                <li class="list-group-item py-3">
                                                    <span class="text-muted d-block mb-1">Açıklama:</span>
                                                    <div class="p-2 bg-light border rounded text-dark">{{ $islem->description }}</div>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-white">
                                                    <span class="text-muted fs-5">İşlem Tutarı:</span>
                                                    <strong class="fs-4 {{ $islem->amount > 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ $islem->amount > 0 ? '+' : '' }}{{ number_format($islem->amount, 2, ',', '.') }} {{ $islem->bankAccount->currency }}
                                                    </strong>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">İşlem bulunamadı.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($transactions->hasPages())
                <div class="card-footer bg-white pt-3 pb-1 border-0 d-flex justify-content-center">
                    {{ $transactions->links() }}
                </div>
                @endif
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="excelExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-bottom-0 pb-0 mt-2">
                <h5 class="modal-title w-100 text-center fw-bold" style="font-size: 1.1rem; color: #333;">Şablon Seçin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-5 pb-5">
                
                <form action="{{ url('/export/excel') }}" method="POST">
                    @csrf
                    <input type="hidden" name="bank_id" value="{{ request('bank_id') }}">
                    <input type="hidden" name="account_id" value="{{ request('account_id') }}">
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    <input type="hidden" name="currency" value="{{ request('currency') }}">
                    <input type="hidden" name="type_code" value="{{ request('type_code') }}">

                    <div class="mb-4 mt-2">
                        <select class="form-select text-muted bg-light border-light shadow-sm">
                            <option value="default">Sistem Varsayılan</option>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="separate_banks" value="1" id="sepBanks" style="transform: scale(1.2); margin-right: 8px;">
                        <label class="form-check-label text-dark" for="sepBanks" style="font-size: 0.95rem;">
                            Bankaları ayrı Excel sayfalarına ayır.
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="separate_accounts" value="1" id="sepAccounts" style="transform: scale(1.2); margin-right: 8px;">
                        <label class="form-check-label text-dark" for="sepAccounts" style="font-size: 0.95rem;">
                            Banka hesaplarına göre ayrı Excel sayfalarına ayır.
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="incSafes" disabled style="transform: scale(1.2); margin-right: 8px;">
                        <label class="form-check-label text-muted" for="incSafes" style="font-size: 0.95rem;">
                            Kasaları dahil et. <small>(Yakında)</small>
                        </label>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="send_email" value="1" id="sendEmail" style="transform: scale(1.2); margin-right: 8px;">
                        <label class="form-check-label text-dark" for="sendEmail" style="font-size: 0.95rem;">
                            E-posta olarak gönder
                        </label>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted d-block mb-3" style="font-size: 0.85rem;">Excel sütunlarını özelleştirmek istiyorsanız şablon oluşturun.</small>
                        <button type="submit" class="btn text-white fw-bold px-5 py-2 shadow-sm" style="background-color: #3fb0ff; border-radius: 8px; font-size: 0.95rem;">
                            EXCEL ÇIKART
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>