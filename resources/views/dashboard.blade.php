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
            border: 2px solid #495057 !important;
            margin-bottom: 0 !important;
        }

        .table th, 
        .table td {
            border: 1px solid #6c757d !important;
            vertical-align: middle; 
        }

        .table > thead > tr > th {
            border-bottom: 3px solid #212529 !important; 
            background-color: #e2e6ea !important; 
            color: #212529 !important; 
            font-weight: 700 !important;
        }

        .table-hover tbody tr:hover {
            background-color: #dbe4ef !important; 
        }
        
        /* Checkbox hücresi için tıklama efekti */
        .action-checkbox-cell { cursor: pointer; }

        /* YENİ: Chatbot Genel Ayarları */
        #vomsis-ai-widget {
            position: fixed; bottom: 30px; right: 30px; z-index: 9999;
            font-family: system-ui, -apple-system, sans-serif;
        }
        
        /* Yüzen Buton (Tetikleyici) */
        #ai-trigger-btn {
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            color: white; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            font-size: 24px; cursor: pointer; transition: transform 0.3s;
            display: flex; align-items: center; justify-content: center;
        }
        #ai-trigger-btn:hover { transform: scale(1.1); }

        /* Chat Penceresi (Varsayılan olarak gizli) */
        #ai-chat-window {
            display: none; width: 360px; height: 500px;
            background: #fff; border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            flex-direction: column; overflow: hidden;
            position: absolute; bottom: 80px; right: 0;
            border: 1px solid #e9ecef;
        }
        
        /* Chat Başlığı */
        .ai-header {
            background: linear-gradient(135deg, #212529, #343a40); color: #fff;
            padding: 15px 20px; display: flex; justify-content: space-between;
            align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Mesajların Aktığı Alan */
        .ai-body {
            flex: 1; padding: 15px; overflow-y: auto; background: #f8f9fa;
            display: flex; flex-direction: column; gap: 10px;
        }
        
        /* Mesaj Baloncukları */
        .chat-msg { max-width: 85%; padding: 10px 15px; border-radius: 16px; font-size: 0.9rem; line-height: 1.4; word-wrap: break-word; }
        .msg-user { background: #0d6efd; color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-ai { background: #fff; color: #212529; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #dee2e6; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        
        /* Yazıyor... Efekti */
        .typing-indicator { display: none; padding: 10px 15px; background: #fff; border-radius: 16px; align-self: flex-start; font-size: 0.8rem; color: #6c757d; border: 1px solid #dee2e6; }
        
        /* Mesaj Gönderme Çubuğu */
        .ai-footer {
            padding: 15px; background: #fff; border-top: 1px solid #e9ecef;
            display: flex; gap: 10px;
        }
        .ai-footer input {
            flex: 1; padding: 10px 15px; border: 1px solid #dee2e6;
            border-radius: 20px; outline: none; font-size: 0.9rem; transition: border 0.3s;
        }
        .ai-footer input:focus { border-color: #0d6efd; }
        .ai-footer button {
            background: #0d6efd; color: #fff; border: none; border-radius: 50%;
            width: 40px; height: 40px; cursor: pointer; display: flex;
            align-items: center; justify-content: center; transition: background 0.2s;
        }
        .ai-footer button:hover { background: #0b5ed7; }
    </style>
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="{{ url('/dashboard') }}">🏢 Vomsis FinTech</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active" href="{{ url('/dashboard') }}">📊 Hesap Hareketleri</a></li>
                
                @if(auth()->user()->can_view_pos)
                    <li class="nav-item"><a class="nav-link text-white-50" href="{{ route('payment.list') }}">💳 Sanal POS İşlemleri</a></li>
                @endif

                @if(auth()->user()->role === 'admin' || auth()->user()->can_view_physical_pos)
                    <li class="nav-item"><a class="nav-link text-white-50" href="{{ route('physical_pos.index') }}">📠 Fiziksel POS</a></li>
                @endif
            </ul>
            
            <div class="d-flex align-items-center gap-2">
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('users.create') }}" class="btn btn-warning btn-sm fw-bold">
                        👤 Personel Ekle
                    </a>
                    
                    <button type="button" class="btn btn-outline-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#bankSettingsModal">
                        ⚙️ Kasa Yönetimi
                    </button>
                @endif

                @if(auth()->user()->role === 'admin' || auth()->user()->can_create_tags)
                <button type="button" class="btn btn-outline-light btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#createTagModal">
                    🏷️ Yeni Etiket
                </button>
                @endif

                <a href="{{ url('/arka-planda-cek') }}" class="btn btn-success btn-sm fw-bold">🔄 Verileri Çek</a>

                <div class="dropdown ms-2">
                    <button class="btn btn-secondary btn-sm dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown">
                        👋 {{ auth()->user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger fw-bold">
                                    <i class="fas fa-sign-out-alt me-2"></i> Çıkış Yap
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    @if(session('mesaj'))
    <div class="alert alert-success shadow-sm p-3 mb-4">
        <strong>Harika!</strong> {!! session('mesaj') !!}
    </div>
    @endif
    
    @if(session('error'))
    <div class="alert alert-danger shadow-sm p-3 mb-4 fw-bold border-0 border-start border-danger border-4">
        ⚠️ {!! session('error') !!}
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger shadow-sm p-3 mb-4">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
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

            @if(isset($filteredSummaries) && $filteredSummaries->count() > 0 && (request('bank_id') || request('search') || request('type_code') || request('tag_id')))
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
                        
                        <div class="col-md-3">
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
                        <div class="col-md-2">
                            <select name="type_code" class="form-select form-select-sm">
                                <option value="">İşlem Tipleri</option>
                                @foreach($transactionTypes as $type)
                                    <option value="{{ $type->vomsis_type_id }}" {{ request('type_code') == $type->vomsis_type_id ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="tag_id" class="form-select form-select-sm">
                                <option value="">Tüm Etiketler</option>
                                @if(isset($allTags))
                                    @foreach($allTags as $tag)
                                        <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                                            🏷️ {{ $tag->name }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filtrele</button>
                            @if(request('search') || request('currency') || request('type_code') || request('account_id') || request('tag_id'))
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

            <div id="bulkActionContainer" class="d-none mb-3 p-3 bg-white shadow-sm rounded border border-primary d-flex justify-content-between align-items-center" style="transition: all 0.3s ease;">
                <div class="d-flex align-items-center">
                    <span class="fs-5 me-2">⚙️</span>
                    <span id="selectedCountText" class="fw-bold text-primary" style="font-size: 1.1rem;">0 işlem seçildi</span>
                    <button type="button" id="clearSelectionBtn" class="btn btn-sm btn-link text-danger ms-3 fw-bold text-decoration-none">❌ Temizle</button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-danger fw-bold shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#bulkRemoveTagModal">
                        🗑️ Etiket Çıkar
                    </button>
                    <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#bulkTagModal">
                        🏷️ Etiket Ekle
                    </button>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="text-center action-checkbox-cell">
                                    <input type="checkbox" id="selectAllTransactions" class="form-check-input shadow-sm" style="transform: scale(1.2); cursor: pointer;" onclick="event.stopPropagation();">
                                </th>
                                <th>Tarih</th>
                                <th>Banka & Hesap</th>
                                <th>İşlem Tipi</th> 
                                <th>Açıklama</th>
                                <th class="text-end pe-4">Tutar & Bakiye</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $islem)
                            <tr class="table-row-clickable" data-modal-id="islemModal{{ $islem->id }}" data-id="{{ $islem->id }}">
                                
                                <td class="text-center action-checkbox-cell">
                                    <input type="checkbox" value="{{ $islem->id }}" class="form-check-input transaction-checkbox shadow-sm" style="transform: scale(1.2); cursor: pointer;" onclick="event.stopPropagation();">
                                </td>

                                <td>{{ \Carbon\Carbon::parse($islem->transaction_date)->format('d.m.Y H:i') }}</td>
                                <td>
                                    <strong>{{ ucfirst($islem->bankAccount->bank->bank_name ?? 'Bilinmeyen') }}</strong> <br>
                                    <small class="text-muted">{{ $islem->bankAccount->account_name }} ({{ $islem->bankAccount->currency }})</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $islem->transactionType->name ?? 'Diğer' }}</span>
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        @foreach($islem->tags as $tag)
                                            <span class="badge" style="background-color: {{ $tag->color }}; font-size: 0.7rem;">{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                </td>
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
                                                
                                                <li class="list-group-item py-3 bg-white">
                                                    <span class="text-muted d-block mb-2 fw-bold">🏷️ İşlem Etiketleri:</span>
                                                    
                                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                                        @forelse($islem->tags as $tag)
                                                            <span class="badge d-flex align-items-center py-2 px-2" style="background-color: {{ $tag->color }}; font-size: 0.85rem;">
                                                                {{ $tag->name }}
                                                                <form action="{{ url('/islem/'.$islem->id.'/etiket-cikar/'.$tag->id) }}" method="POST" class="ms-2 m-0 p-0 d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="btn-close btn-close-white" style="font-size: 0.5rem;" title="Bu etiketi kaldır"></button>
                                                                </form>
                                                            </span>
                                                        @empty
                                                            <span class="text-muted small">Bu işleme henüz etiket atanmamış.</span>
                                                        @endforelse
                                                    </div>

                                                    <form action="{{ url('/islem/'.$islem->id.'/etiket-ekle') }}" method="POST" class="d-flex gap-2">
                                                        @csrf
                                                        <select name="tag_id" class="form-select form-select-sm" required>
                                                            <option value="">-- Etiket Ekle --</option>
                                                            @if(isset($allTags))
                                                                @foreach($allTags as $tag)
                                                                    @if(!$islem->tags->contains($tag->id))
                                                                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                                                    @endif
                                                                @endforeach
                                                            @endif
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary text-nowrap fw-bold shadow-sm">+ Ekle</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">İşlem bulunamadı.</td></tr>
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

<div id="vomsis-ai-widget">
    <div id="ai-chat-window">
        <div class="ai-header">
            <div>
                <h6 class="m-0 fw-bold">🤖 Vomsis AI Asistan</h6>
                <small class="text-white-50" style="font-size:0.7rem;">Finansal Veri Yorumlayıcı</small>
            </div>
            <button id="ai-close-btn" class="btn-close btn-close-white" style="font-size: 0.8rem;"></button>
        </div>
        
        <div class="ai-body" id="ai-messages-container">
            <div class="chat-msg msg-ai">
                Merhaba {{ auth()->user()->name }}! Ben Vomsis Yapay Zeka Asistanı. Hesap hareketleri veya finansal özetler hakkında bana her şeyi sorabilirsin.
            </div>
            <div class="typing-indicator" id="ai-typing">Asistan düşünüyor...</div>
        </div>

        <form class="ai-footer" id="ai-chat-form">
            <input type="text" id="ai-input-field" placeholder="Bir soru sorun..." required autocomplete="off">
            <button type="submit"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>

    <button id="ai-trigger-btn" title="Yapay Zeka Asistanı">
        <i class="fas fa-robot"></i>
    </button>
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

@if(auth()->user()->role === 'admin')
<div class="modal fade" id="bankSettingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form action="{{ route('bank.settings.update') }}" method="POST" class="modal-content border-0 shadow-lg">
        @csrf 
        <div class="modal-header bg-dark text-white border-0">
          <h5 class="modal-title fw-bold">⚙️ Banka ve Kasa Yönetimi</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
        </div>
        <div class="modal-body bg-light p-4">
            <div class="alert alert-warning border-0 shadow-sm mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i> Kapattığınız banka veya hesaplar listeden ve hesaplamalardan (Gelir/Gider/Kasa) tamamen düşer.
            </div>

            <div class="accordion shadow-sm" id="bankAccordion">
                @if(isset($allSettingsBanks))
                    @foreach($allSettingsBanks as $bank)
                    <div class="accordion-item border-0 mb-2 rounded">
                        <h2 class="accordion-header" id="heading{{ $bank->id }}">
                            <div class="d-flex align-items-center justify-content-between w-100 bg-white border rounded px-3 py-2">
                                <button class="accordion-button collapsed shadow-none bg-transparent p-0 m-0 w-75 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $bank->id }}">
                                    🏦 {{ $bank->bank_name }}
                                </button>
                                <div class="form-check form-switch m-0 d-flex align-items-center w-25 justify-content-end">
                                    <label class="form-check-label small text-muted me-2" style="font-size:0.75rem;">Listede Göster</label>
                                    <input class="form-check-input" type="checkbox" name="banks[{{ $bank->id }}][is_visible]" value="1" {{ $bank->is_visible ? 'checked' : '' }} style="transform: scale(1.3); cursor: pointer;">
                                </div>
                            </div>
                        </h2>
                        <div id="collapse{{ $bank->id }}" class="accordion-collapse collapse" data-bs-parent="#bankAccordion">
                            <div class="accordion-body bg-white pt-0 pb-3">
                                <table class="table table-sm table-borderless m-0">
                                    <thead class="text-muted small border-bottom">
                                        <tr>
                                            <th>Hesap Adı</th>
                                            <th class="text-center">Listede Göster</th>
                                            <th class="text-center">Ana Kasaya Dahil Et</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bank->bankAccounts as $acc)
                                        <tr class="border-bottom">
                                            <td class="align-middle fw-bold text-secondary">
                                                💳 {{ $acc->account_name }} ({{ $acc->currency }})
                                            </td>
                                            <td class="align-middle text-center">
                                                <div class="form-check form-switch d-inline-block m-0">
                                                    <input class="form-check-input" type="checkbox" name="accounts[{{ $acc->id }}][is_visible]" value="1" {{ $acc->is_visible ? 'checked' : '' }} style="transform: scale(1.2); cursor: pointer;">
                                                </div>
                                            </td>
                                            <td class="align-middle text-center">
                                                <div class="form-check form-switch d-inline-block m-0">
                                                    <input class="form-check-input bg-success border-success" type="checkbox" name="accounts[{{ $acc->id }}][include_in_totals]" value="1" {{ $acc->include_in_totals ? 'checked' : '' }} style="transform: scale(1.2); cursor: pointer;">
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>

        </div>
        <div class="modal-footer border-0 bg-white">
          <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">💾 Ayarları Kaydet</button>
        </div>
    </form>
  </div>
</div>
@endif

@if(auth()->user()->role === 'admin' || auth()->user()->can_create_tags)
<div class="modal fade" id="createTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form action="{{ route('tags.store') }}" method="POST">
        @csrf 
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-dark" id="createTagModalLabel">🏷️ Yeni Etiket Üret</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">Etiket Adı</label>
                <input type="text" name="name" class="form-control" placeholder="Örn: İade Edildi" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">Etiket Rengi</label>
                <input type="color" name="color" class="form-control form-control-color w-100" value="#0d6efd" title="Renginizi seçin" required style="height: 45px;">
            </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">Etiketi Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

<div class="modal fade" id="bulkTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form action="{{ url('/islem/toplu-etiket-ekle') }}" method="POST" id="bulkTagForm">
        @csrf
        
        <div id="hiddenTransactionInputs"></div>

        <div class="modal-header bg-primary text-white border-0">
          <h5 class="modal-title fw-bold">🏷️ Toplu Etiket Ekle</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
        </div>
        <div class="modal-body bg-light">
            <div class="alert alert-info py-2 mb-4 shadow-sm border-0">
                <i class="fas fa-info-circle me-2"></i> <strong id="modalSelectedCount">0</strong> adet işleme aşağıdaki etiketler eklenecektir.
            </div>

            <label class="form-label text-muted small fw-bold mb-2">Eklenecek Etiketleri Seçin (Çoklu Seçim Yapabilirsiniz)</label>
            
            <div class="bg-white p-3 rounded border shadow-sm" style="max-height: 250px; overflow-y: auto;">
                @if(isset($allTags) && $allTags->count() > 0)
                    @foreach($allTags as $tag)
                        <div class="form-check mb-2 custom-control custom-checkbox">
                            <input class="form-check-input" type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" id="bulkTag_{{ $tag->id }}" style="cursor: pointer; transform: scale(1.1);">
                            <label class="form-check-label ms-2 w-100" for="bulkTag_{{ $tag->id }}" style="cursor: pointer;">
                                <span class="badge py-2 px-3 shadow-sm w-100 text-start" style="background-color: {{ $tag->color }}; font-size: 0.85rem;">
                                    {{ $tag->name }}
                                </span>
                            </label>
                        </div>
                    @endforeach
                @else
                    <div class="text-muted small text-center py-3">Sistemde yetkiniz olan hiçbir etiket bulunmuyor.</div>
                @endif
            </div>
            <small class="text-muted d-block mt-2" style="font-size: 0.75rem;">Seçtiğiniz işlemlerden bazılarında bu etiketler zaten varsa, sistem akıllıca davranıp çift kayıt oluşturmaz.</small>
        </div>
        <div class="modal-footer border-0 bg-light pt-0">
          <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary fw-bold shadow-sm px-4">Uygula</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="bulkRemoveTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form action="{{ url('/islem/toplu-etiket-cikar') }}" method="POST" id="bulkRemoveTagForm">
        @csrf
        
        <div id="hiddenRemoveTransactionInputs"></div>

        <div class="modal-header bg-danger text-white border-0">
          <h5 class="modal-title fw-bold">🗑️ Toplu Etiket Çıkar</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
        </div>
        <div class="modal-body bg-light">
            <div class="alert alert-warning py-2 mb-4 shadow-sm border-0">
                <i class="fas fa-exclamation-triangle me-2"></i> <strong id="modalRemoveSelectedCount">0</strong> adet işlemden aşağıdaki etiketler silinecektir.
            </div>

            <label class="form-label text-muted small fw-bold mb-2">Çıkarılacak Etiketleri Seçin</label>
            
            <div class="bg-white p-3 rounded border shadow-sm" style="max-height: 250px; overflow-y: auto;">
                @if(isset($allTags) && $allTags->count() > 0)
                    @foreach($allTags as $tag)
                        <div class="form-check mb-2 custom-control custom-checkbox">
                            <input class="form-check-input" type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" id="bulkRemoveTag_{{ $tag->id }}" style="cursor: pointer; transform: scale(1.1);">
                            <label class="form-check-label ms-2 w-100" for="bulkRemoveTag_{{ $tag->id }}" style="cursor: pointer;">
                                <span class="badge py-2 px-3 shadow-sm w-100 text-start" style="background-color: {{ $tag->color }}; font-size: 0.85rem;">
                                    {{ $tag->name }}
                                </span>
                            </label>
                        </div>
                    @endforeach
                @else
                    <div class="text-muted small text-center py-3">Sistemde etiket bulunmuyor.</div>
                @endif
            </div>
        </div>
        <div class="modal-footer border-0 bg-light pt-0">
          <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-danger fw-bold shadow-sm px-4">Seçili Etiketleri Çıkar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<ul id="customContextMenu" class="dropdown-menu shadow" style="display:none; position:absolute; z-index:9999; cursor: pointer;">
    <li>
        <a class="dropdown-item text-danger fw-bold py-2" href="#" id="downloadPdfBtn" target="_blank">
            <i class="fas fa-file-pdf me-2"></i> PDF Olarak İndir
        </a>
    </li>
    <li>
        <a class="dropdown-item text-info fw-bold py-2" href="#" id="viewBtn" target="_blank">
            <i class="fas fa-eye me-2"></i> Görüntüle
        </a>
    </li>
    <li>
        <a class="dropdown-item text-success fw-bold py-2" href="#" id="printBtn" target="_blank">
            <i class="fas fa-print me-2"></i> Yazdır
        </a>
    </li>
    <li><hr class="dropdown-divider m-0"></li>
    <li>
        <a class="dropdown-item text-primary fw-bold py-2" href="#" id="sendEmailBtn">
            <i class="fas fa-envelope me-2"></i> E-posta ile Gönder
        </a>
    </li>
</ul>

<div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form id="emailForm" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold">📧 Dekont Gönder</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body bg-light">
                    <label class="form-label text-muted small fw-bold">Alıcı E-posta Adresi</label>
                    <input type="email" name="email" class="form-control" placeholder="ornek@firma.com" required>
                    <small class="text-muted" style="font-size: 0.75rem;">Dekont PDF olarak eke eklenecektir.</small>
                </div>
                <div class="modal-footer border-0 bg-light pt-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">🚀 Hemen Gönder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const customMenu = document.getElementById("customContextMenu");
        const pdfBtn = document.getElementById("downloadPdfBtn");
        const viewBtn = document.getElementById("viewBtn"); 
        const printBtn = document.getElementById("printBtn");
        const sendEmailBtn = document.getElementById("sendEmailBtn");
        const emailForm = document.getElementById("emailForm");
        
        let selectedTransactionId = null;

        // Sağ tık menüsünü tetikleme
        document.querySelectorAll(".table-row-clickable").forEach(row => {
            row.addEventListener("contextmenu", function(e) {
                e.preventDefault(); 

                selectedTransactionId = this.getAttribute("data-id");
                
                // Linkleri işlemi ID'sine göre dinamik ayarla
                pdfBtn.href = "{{ url('/islem') }}/" + selectedTransactionId + "/pdf";
                viewBtn.href = "{{ url('/islem') }}/" + selectedTransactionId + "/goruntule"; 
                printBtn.href = "{{ url('/islem') }}/" + selectedTransactionId + "/yazdir";

                customMenu.style.display = "block";
                customMenu.style.left = e.pageX + "px";
                customMenu.style.top = e.pageY + "px";
            });
        });

        // Başka yere tıklanınca sağ tık menüsünü gizle
        document.addEventListener("click", function(e) {
            customMenu.style.display = "none";
        });

        // E-posta gönder butonuna basılınca
        sendEmailBtn.addEventListener("click", function(e) {
            e.preventDefault();
            customMenu.style.display = "none"; 
            
            // Post adresini ayarla
            emailForm.action = "{{ url('/islem') }}/" + selectedTransactionId + "/eposta-gonder";
            
            // Modalı aç
            var emailModal = new bootstrap.Modal(document.getElementById('emailModal'));
            emailModal.show();
        });

        // ==========================================
        // YENİ: HAFIZALI (SEPET MANTIKLI) TOPLU SEÇİM MOTORU
        // ==========================================
        const selectAllBtn = document.getElementById('selectAllTransactions');
        const transactionCheckboxes = document.querySelectorAll('.transaction-checkbox');
        const bulkActionContainer = document.getElementById('bulkActionContainer');
        const selectedCountText = document.getElementById('selectedCountText');
        const modalSelectedCount = document.getElementById('modalSelectedCount');
        const hiddenTransactionInputs = document.getElementById('hiddenTransactionInputs');
        const bulkTagForm = document.getElementById('bulkTagForm');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');

        // 1. Tarayıcı hafızasındaki (Sepetteki) ID'leri çek (Yoksa boş dizi oluştur)
        let selectedTransactions = JSON.parse(sessionStorage.getItem('vomsis_selected_tx')) || [];

        // 2. Arayüzü ve Çubuğu Güncelleyen Fonksiyon
        function updateBulkActionState() {
            const count = selectedTransactions.length;

            if (count > 0) {
                bulkActionContainer.classList.remove('d-none');
                selectedCountText.innerText = count + " işlem seçildi (Tüm Sayfalardan)";
                
                // Hem Ekleme hem Çıkarma modallarındaki rakamları güncelle
                if(modalSelectedCount) modalSelectedCount.innerText = count;
                const modalRemoveCount = document.getElementById('modalRemoveSelectedCount');
                if(modalRemoveCount) modalRemoveCount.innerText = count;
                
            } else {
                bulkActionContainer.classList.add('d-none');
                if (selectAllBtn) selectAllBtn.checked = false;
            }
        }

        // 3. Sayfa yüklendiğinde, eğer bu sayfedaki işlemler sepette varsa kutucuklarını otomatik tikle
        transactionCheckboxes.forEach(cb => {
            if (selectedTransactions.includes(cb.value)) {
                cb.checked = true;
            }
        });
        
        // Tümünü seç butonunun durumunu ayarla (Bu sayfadakilerin hepsi seçiliyse tikle)
        if (selectAllBtn && transactionCheckboxes.length > 0) {
            selectAllBtn.checked = Array.from(transactionCheckboxes).every(c => c.checked);
        }
        
        updateBulkActionState(); // Çubuğu göster/gizle

        // 4. Kutucuklara Tıklanma Olayı (Hafızaya Yaz/Sil)
        transactionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    // Sepette yoksa ekle
                    if (!selectedTransactions.includes(this.value)) {
                        selectedTransactions.push(this.value);
                    }
                } else {
                    // Tiki kaldırıldıysa sepetten sil
                    selectedTransactions = selectedTransactions.filter(id => id !== this.value);
                }
                
                // Sepeti tarayıcı hafızasına kaydet
                sessionStorage.setItem('vomsis_selected_tx', JSON.stringify(selectedTransactions));

                // "Tümünü Seç" butonunu güncelle
                if (selectAllBtn) {
                    selectAllBtn.checked = Array.from(transactionCheckboxes).every(c => c.checked);
                }
                updateBulkActionState();
            });
        });

        // 5. "Tümünü Seç" Butonuna Tıklanma Olayı
        if (selectAllBtn) {
            const selectAllCell = selectAllBtn.closest('.action-checkbox-cell');
            if (selectAllCell) {
                selectAllCell.addEventListener('click', function(e) {
                    if (e.target.tagName.toLowerCase() !== 'input') {
                        selectAllBtn.checked = !selectAllBtn.checked;
                        selectAllBtn.dispatchEvent(new Event('change'));
                    }
                });
            }

            selectAllBtn.addEventListener('change', function() {
                transactionCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllBtn.checked;
                    
                    // Sepete Ekle veya Sil
                    if (checkbox.checked) {
                        if (!selectedTransactions.includes(checkbox.value)) {
                            selectedTransactions.push(checkbox.value);
                        }
                    } else {
                        selectedTransactions = selectedTransactions.filter(id => id !== checkbox.value);
                    }
                });
                
                sessionStorage.setItem('vomsis_selected_tx', JSON.stringify(selectedTransactions));
                updateBulkActionState();
            });
        }

        // 6. Satıra (Boşluğa) Tıklanınca Kutucuğu İşaretleme veya Modalı Açma
        document.querySelectorAll('.table-row-clickable').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.action-checkbox-cell')) {
                    if (e.target.tagName.toLowerCase() !== 'input') {
                        const checkbox = this.querySelector('.transaction-checkbox');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change')); // Değişimi tetikle ki hafızaya yazılsın
                        }
                    }
                    return; 
                }

                const modalId = this.getAttribute('data-modal-id');
                const modalElement = document.getElementById(modalId);
                if (modalElement) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                    modal.show();
                }
            });
        });

        // 7. "Temizle" Butonuna Tıklayınca Hafızayı Sıfırla
        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', function() {
                selectedTransactions = []; // Sepeti boşalt
                sessionStorage.removeItem('vomsis_selected_tx'); // Hafızayı sil
                
                // Ekrandaki tikleri kaldır
                transactionCheckboxes.forEach(cb => cb.checked = false);
                if (selectAllBtn) selectAllBtn.checked = false;
                
                updateBulkActionState();
            });
        }

        // 8. Form Gönderilmeden Hemen Önce (Hafızadaki Tüm ID'leri Forma Zımbala)
        if(bulkTagForm) {
            bulkTagForm.addEventListener('submit', function(e) {
                hiddenTransactionInputs.innerHTML = '';
                
                // Sadece ekrandakileri değil, HAFIZADAKİ tüm ID'leri gönder
                selectedTransactions.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'transaction_ids[]';
                    input.value = id;
                    hiddenTransactionInputs.appendChild(input);
                });

                // Form başarıyla gittikten sonra bir dahaki sefere sepet boş olsun diye hafızayı temizle
                sessionStorage.removeItem('vomsis_selected_tx');
            });
        }

        // YENİ: Toplu Çıkarma Formu Gönderilmeden Önce Çalışacak Motor
        const bulkRemoveTagForm = document.getElementById('bulkRemoveTagForm');
        const hiddenRemoveTransactionInputs = document.getElementById('hiddenRemoveTransactionInputs');

        if(bulkRemoveTagForm) {
            bulkRemoveTagForm.addEventListener('submit', function(e) {
                hiddenRemoveTransactionInputs.innerHTML = '';
                
                // Hafızadaki tüm ID'leri gönder
                selectedTransactions.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'transaction_ids[]';
                    input.value = id;
                    hiddenRemoveTransactionInputs.appendChild(input);
                });

                sessionStorage.removeItem('vomsis_selected_tx');
            });
        }

        // ==========================================
        // YENİ: YAPAY ZEKA ASİSTANI (CHATBOT) MOTORU
        // ==========================================
        const triggerBtn = document.getElementById('ai-trigger-btn');
        const closeBtn = document.getElementById('ai-close-btn');
        const chatWindow = document.getElementById('ai-chat-window');
        const chatForm = document.getElementById('ai-chat-form');
        const inputField = document.getElementById('ai-input-field');
        const messagesContainer = document.getElementById('ai-messages-container');
        const typingIndicator = document.getElementById('ai-typing');

        // Pencereyi Aç / Kapat
        if (triggerBtn && closeBtn && chatWindow) {
            triggerBtn.addEventListener('click', () => {
                chatWindow.style.display = chatWindow.style.display === 'flex' ? 'none' : 'flex';
                if(chatWindow.style.display === 'flex') inputField.focus();
            });
            closeBtn.addEventListener('click', () => chatWindow.style.display = 'none');
        }

        // Mesajı Ekrana Çizen Fonksiyon
        function appendMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('chat-msg', sender === 'user' ? 'msg-user' : 'msg-ai');
            msgDiv.innerHTML = text.replace(/\n/g, '<br>'); // Satır atlamalarını HTML'e çevir
            
            // Mesajı "Yazıyor..." uyarısının hemen üstüne ekle
            messagesContainer.insertBefore(msgDiv, typingIndicator);
            messagesContainer.scrollTop = messagesContainer.scrollHeight; // En alta kaydır
        }

        // Form Gönderildiğinde (Enter'a basılınca)
        if (chatForm) {
            chatForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const message = inputField.value.trim();
                if (!message) return;

                // 1. Kullanıcı mesajını ekrana bas ve inputu temizle
                appendMessage(message, 'user');
                inputField.value = '';
                inputField.disabled = true; // Çifte gönderimi engelle
                
                // 2. "Yazıyor..." efektini göster
                typingIndicator.style.display = 'block';
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                try {
                    // 3. Laravel (AiController) sunucusuna AJAX isteği at
                    const response = await fetch('{{ route("ai.ask") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ message: message })
                    });

                    const data = await response.json();
                    
                    // 4. Yazıyor efektini gizle ve AI cevabını ekrana bas
                    typingIndicator.style.display = 'none';
                    
                    if(data.status === 'success') {
                        appendMessage(data.reply, 'ai');
                    } else {
                        appendMessage('❌ Bir hata oluştu: ' + data.reply, 'ai');
                    }

                } catch (error) {
                    typingIndicator.style.display = 'none';
                    appendMessage('❌ Sunucuya bağlanılamadı. Lütfen internet bağlantınızı kontrol edin.', 'ai');
                } finally {
                    inputField.disabled = false;
                    inputField.focus();
                }
            });
        }
    });
</script>

</body>
</html>