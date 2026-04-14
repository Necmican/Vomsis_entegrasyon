<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oto-Etiket — Vomsis</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --bg: #f8f9fa; --surface: #ffffff; --border: #e9ecef;
            --text: #212529; --muted: #6c757d; --accent: #0d6efd; --primary: #0d6efd;
            --danger: #dc3545; --success: #198754; --warn: #ffc107; --radius: 12px;
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); padding-bottom: 60px; }
        a { text-decoration: none; color: inherit; }

        .container { max-width: 1250px; margin: 0 auto; padding: 0 20px; }
        
        .section {
            background: var(--surface); border: 0;
            border-radius: var(--radius); padding: 24px; margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .section-header { margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #343a40; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 12px; }

        .form-grid { display: flex; flex-wrap: wrap; gap: 16px; }
        .form-group { flex: 1; min-width: 150px; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; }
        .input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border);
            border-radius: 8px; font-size: 13px; color: var(--text); outline: none; transition: .2s;
        }
        .input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

        .btn {
            background: var(--surface); border: 1px solid var(--border); color: var(--text);
            padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 500;
            cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn:hover { background: var(--bg); }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: var(--success); border-color: var(--success); color: #fff; }
        .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 6px; }

        .cluster-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .cluster-card {
            border: 1px solid var(--border); border-radius: 12px; padding: 16px;
            display: flex; flex-direction: column; gap: 12px; transition: opacity .3s;
        }
        .cluster-rep { font-size: 13px; font-weight: 500; word-break: break-all; }
        .cluster-meta { font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; }
        .cluster-updating { opacity: .4; pointer-events: none; }
        .cluster-loading-banner {
            text-align: center; padding: 24px; color: var(--muted); font-size: 13px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .spin { display: inline-block; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Keyword Pills */
        .kw-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .kw-pill {
            background: #f1f5f9; padding: 5px 10px 5px 8px; border-radius: 6px;
            font-size: 11px; color: var(--muted); cursor: pointer;
            border: 1px solid transparent; user-select: none;
            transition: background .15s, color .15s, border-color .15s, transform .15s;
            display: inline-flex; align-items: center; gap: 4px; position: relative;
        }
        .kw-pill:not(.excluded):not(.loading):hover {
            background: #fee2e2; color: var(--danger); border-color: #fca5a5;
            transform: scale(1.05);
        }
        .kw-pill.excluded {
            background: #fef3c7; color: #92400e; border-color: #fbbf24;
            cursor: default; font-style: italic;
        }
        .kw-pill.excluded::after { content: ' ✕'; font-size: 9px; opacity: .6; }
        .kw-pill.loading {
            opacity: .45; pointer-events: none;
        }
        .kw-pill.loading::after { content: ''; }
        .kw-pill .xic {
            font-size: 10px; opacity: 0; transition: opacity .15s;
            margin-left: 2px; color: var(--danger);
        }
        .kw-pill:not(.excluded):not(.loading):hover .xic { opacity: 1; }
        /* Undo row inside toast */
        .toast-undo { margin-left: auto; background: rgba(255,255,255,.15); border: none;
            color: #f8fafc; font-size: 11px; cursor: pointer; border-radius: 4px;
            padding: 3px 8px; font-family: inherit; white-space: nowrap; }
        .toast-undo:hover { background: rgba(255,255,255,.25); }
        /* Excl badge */
        .badge { display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; background: var(--accent); color: #fff;
            border-radius: 99px; font-size: 11px; font-weight: 600; padding: 0 6px; }

        .tag-form { display: flex; gap: 8px; margin-top: auto; }

        .rule-list, .excl-list { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; }
        .pill-item {
            border: 1px solid var(--border); padding: 6px 12px; border-radius: 99px;
            display: flex; align-items: center; gap: 8px; transition: opacity .2s, transform .2s;
        }
        .pill-item.removing { opacity: 0; transform: scale(.8); }
        .del-btn { background: none; border: none; color: var(--danger); cursor: pointer; font-size: 15px; line-height: 1; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }

        /* Toast */
        #toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column-reverse; gap: 10px; }
        .toast {
            background: #1e293b; color: #f8fafc; padding: 12px 18px; border-radius: 10px;
            font-size: 13px; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,.25);
            display: flex; align-items: center; gap: 10px; min-width: 220px;
            animation: slideUp .25s ease;
        }
        .toast.s { border-left: 3px solid var(--success); }
        .toast.e { border-left: 3px solid var(--danger); }
        .toast.i { border-left: 3px solid var(--accent); }
        .toast.out { animation: slideDown .3s ease forwards; }
        @keyframes slideUp { from { opacity:0; transform:translateY(10px);} to { opacity:1; transform:translateY(0);} }
        @keyframes slideDown { to { opacity:0; transform:translateY(10px);} }

        /* Hızlı Etiketleme Çarpı İkonu */
        .qt-exclude-btn {
            cursor: pointer;
            color: #94a3b8; /* var(--muted) gibi */
            font-size: 11px;
            margin-left: 8px;
            transition: color 0.15s ease, transform 0.15s;
            display: inline-block;
        }
        .qt-exclude-btn:hover {
            color: var(--danger);
            transform: scale(1.1);
        }

        /* Scope Pills */
        .scope-pill {
            display: inline-block;
            padding: 6px 14px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
            margin: 0 6px 6px 0;
            user-select: none;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .scope-pill:hover { border-color: var(--accent); }
        .scope-pill.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .bank-accounts-wrap {
            margin-top: 12px;
            padding: 12px;
            background: var(--bg);
            border-radius: 8px;
            display: none;
        }
    </style>
</head>
<body>

<div id="toast-wrap"></div>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="#">Oto-Etiket Asistanı</a>
        <div class="ms-auto d-flex">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm fw-bold">
                <i class="fas fa-arrow-left me-1"></i> Dashboard'a Dön
            </a>
        </div>
    </div>
</nav>

<div class="container mt-2">
    @if(session('mesaj')) <div class="alert alert-success">{{ session('mesaj') }}</div> @endif
    @if(session('error'))  <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <!-- FİLTRELER / ANALİZ PARAMETRELERİ -->
    <div class="section">
        <div class="section-header">Analiz Parametreleri</div>
        
        <div style="margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--muted)">1. Banka Seçimi</div>
        <div id="bank-list">
            <span class="scope-pill active" data-bank-id="null" onclick="selectBank(null)">Tüm Bankalar</span>
            @foreach($banks as $b)
                <span class="scope-pill" id="bank-pill-{{ $b->id }}" data-bank-id="{{ $b->id }}" onclick="selectBank({{ $b->id }})">{{ $b->bank_name }}</span>
            @endforeach
        </div>

        <div id="accounts-container" class="bank-accounts-wrap">
            <div style="margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--muted)">2. Hesap Seçimi</div>
            <div id="account-list"></div>
        </div>

        <!-- Ek Filtreler -->
        <div style="margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; border-top: 1px solid var(--border); padding-top: 16px;">
            <div class="form-group">
                <label style="font-size: 11px; color: var(--muted); text-transform: uppercase;">Min / Max Tutar</label>
                <div style="display:flex;gap:8px;">
                    <input type="number" id="filter-min-amount" class="input" style="padding:6px 10px;" placeholder="Min" value="{{ request('min_amount') }}">
                    <input type="number" id="filter-max-amount" class="input" style="padding:6px 10px;" placeholder="Max" value="{{ request('max_amount') }}">
                </div>
            </div>
            <div class="form-group">
                <label style="font-size: 11px; color: var(--muted); text-transform: uppercase;">Tarih Aralığı</label>
                <div style="display:flex;gap:8px;">
                    <input type="date" id="filter-start-date" class="input" style="padding:6px 10px;" value="{{ request('start_date') }}">
                    <input type="date" id="filter-end-date" class="input" style="padding:6px 10px;" value="{{ request('end_date') }}">
                </div>
            </div>
            <div class="form-group">
                <label style="font-size: 11px; color: var(--muted); text-transform: uppercase;">Küme Sayısı</label>
                <input type="number" id="filter-n-clusters" class="input" style="padding:6px 10px;" placeholder="20" value="{{ request('n_clusters', 20) }}">
            </div>
            <div style="display:flex;align-items:flex-end;">
                <button type="button" onclick="refreshClusters()" class="btn btn-primary shadow-sm" style="width:100%; height:40px; justify-content:center;">⚡ Analizi Güncelle</button>
            </div>
        </div>
    </div>

    <!-- KURALLAR ÖZETİ -->
    <div class="form-grid" style="align-items:flex-start;">
        <div class="section" style="flex:1;">
            <div class="section-header">
                Kayıtlı Kurallar
                <form method="POST" action="{{ route('auto-tag.apply') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">🚀 Geçmişe Uygula</button>
                </form>
            </div>
            
            @php
                $genelKurallar = $rules->whereNull('bank_id')->whereNull('bank_account_id');
                $ozelKurallar  = $rules->filter(fn($r) => !is_null($r->bank_id) || !is_null($r->bank_account_id));
            @endphp

            <div style="display:flex; gap: 16px; margin-top: 12px;">
                <!-- GENEL KURALLAR -->
                <div style="flex:1; border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: var(--bg);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; font-weight: bold;">🌍 Genel Kurallar ({{ $genelKurallar->count() }})</div>
                    <div class="rule-list" id="rule-list-genel">
                        @forelse($genelKurallar as $rule)
                        <div class="pill-item" id="rule-{{ $rule->id }}" style="font-size: 12px; display:flex; justify-content:space-between;">
                            <span><strong>{{ $rule->keyword }}</strong> ➝ {{ $rule->tag->name ?? '?' }}</span>
                            <button class="del-btn" onclick="deleteRule({{ $rule->id }})">×</button>
                        </div>
                        @empty
                        <span id="rules-empty-genel" style="color:var(--muted);font-size:12px;">Henüz kural yok.</span>
                        @endforelse
                    </div>
                </div>

                <!-- FİLTRELİ KURALLAR -->
                <div style="flex:1; border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: var(--bg);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; font-weight: bold;">🔒 Filtreli Kurallar ({{ $ozelKurallar->count() }})</div>
                    <div class="rule-list" id="rule-list-ozel">
                        @forelse($ozelKurallar as $rule)
                        <div class="pill-item" id="rule-{{ $rule->id }}" style="font-size: 12px; display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                            <div style="width:100%; display:flex; justify-content:space-between;">
                                <span><strong>{{ $rule->keyword }}</strong> ➝ {{ $rule->tag->name ?? '?' }}</span>
                                <button class="del-btn" style="align-self:flex-start" onclick="deleteRule({{ $rule->id }})">×</button>
                            </div>
                            <div style="font-size:10px; color:var(--muted); background:var(--card); padding:2px 6px; border-radius:4px; max-width:90%; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">
                                🏦 {{ $rule->bank->bank_name ?? 'Banka' }} {{ $rule->bankAccount ? ' > ' . $rule->bankAccount->account_name : '' }}
                            </div>
                        </div>
                        @empty
                        <span id="rules-empty-ozel" style="color:var(--muted);font-size:12px;">Henüz filtrelenmiş bir kural yok.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- STOP-WORDS -->
        <div class="section" style="flex:1;">
            <div class="section-header">
                Yoksayılan Kelimeler <span class="badge" id="excl-count">{{ $exclusions->count() }}</span>
            </div>
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <input type="text" id="excl-input" class="input" placeholder="Kelime yaz, Enter'a bas…" style="padding:8px 12px;">
                <button class="btn btn-sm" onclick="addExclusion()">+ Ekle</button>
            </div>
            <div class="excl-list" id="excl-list">
                @forelse($exclusions as $ex)
                <div class="pill-item" id="ex-{{ $ex->id }}">
                    {{ $ex->keyword }}
                    <button class="del-btn" onclick="deleteExclusion({{ $ex->id }}, '{{ $ex->keyword }}')">×</button>
                </div>
                @empty
                <span id="excl-empty" style="color:var(--muted);font-size:12px;">Henüz yoksayılan yok.</span>
                @endforelse
            </div>
        </div>
    </div>

    <!-- HIZLI ETİKETLEME PANELİ -->
    <div class="section" id="quick-tag-section">
        <div class="section-header">
            ⚡ Hızlı Etiketleme
            <span style="font-size:12px;color:var(--muted);font-weight:400">Anahtar kelime ile işlemleri bul & etiketle</span>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:2;min-width:200px;">
                <label>Anahtar Kelime <span style="color:var(--muted)">(örn: SHELL, ENERJİSA, BLOKE)</span></label>
                <div style="position:relative;">
                    <input type="text" id="qt-keyword" class="input" placeholder="Kelime yaz…" autocomplete="off"
                           style="text-transform:uppercase;padding-right:40px;">
                    <span id="qt-count-badge" style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);
                          background:var(--accent);color:#fff;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:600;"></span>
                </div>
            </div>
            <div class="form-group" style="flex:2;min-width:200px;">
                <label>Etiket Adı <span style="color:var(--muted)">(var olanı seç veya yeni yaz)</span></label>
                <input type="text" id="qt-tag-name" class="input" placeholder="Etiket adı…"
                       list="qt-tag-datalist" autocomplete="off">
                <datalist id="qt-tag-datalist">
                    @foreach($tags as $tag)
                    <option value="{{ $tag->name }}">
                    @endforeach
                </datalist>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary" onclick="quickSearch()" id="qt-search-btn">🔍 Ara</button>
                <button class="btn btn-success" onclick="quickTag()" id="qt-tag-btn" style="display:none;">🏷️ Etiketle</button>
                <button class="btn" onclick="deleteMatching()" id="qt-del-btn" style="display:none;color:var(--danger);border-color:#fca5a5;"
                        title="Eşleşen işlemleri kaldır">🗑️ Sil</button>
            </div>
        </div>

        <!-- Önizleme tablosu -->
        <div id="qt-preview" style="margin-top:16px;display:none;">
            <div style="font-size:12px;color:var(--muted);margin-bottom:8px;" id="qt-summary"></div>
            <div style="overflow-x:auto;max-height:320px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;" id="qt-table">
                    <thead style="position:sticky;top:0;background:var(--bg);">
                        <tr>
                            <th style="padding:8px 12px;text-align:left;color:var(--muted);font-weight:500;border-bottom:1px solid var(--border);">Tarih</th>
                            <th style="padding:8px 12px;text-align:left;color:var(--muted);font-weight:500;border-bottom:1px solid var(--border);">Açıklama</th>
                            <th style="padding:8px 12px;text-align:right;color:var(--muted);font-weight:500;border-bottom:1px solid var(--border);">Tutar</th>
                        </tr>
                    </thead>
                    <tbody id="qt-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- KÜMELER -->
    <div class="section">
        <div class="section-header">İşlem Kümeleri ({{ count($clusters) }} Grup)</div>
        @if(isset($clusterError) && $clusterError)
            <div class="alert alert-danger">{{ $clusterError }}</div>
        @endif
        <div class="cluster-grid" id="cluster-grid">
            @foreach($clusters as $cluster)
            <div class="cluster-card">
                <div class="cluster-meta">
                    <strong>{{ $cluster['count'] }} İşlem</strong>
                    <span>%{{ $cluster['tagged_pct'] ?? 0 }} Etiketli</span>
                </div>
                <div class="cluster-rep">"{{ \Illuminate\Support\Str::limit($cluster['representative'] ?? '', 80) }}"</div>

                @if(!empty($cluster['keywords']))
                <div class="kw-list">
                    @foreach(array_slice($cluster['keywords'], 0, 6) as $kw)
                    @php
                        $kwUp = strtoupper($kw);
                        $alreadyExcluded = $exclusions->contains('keyword', $kwUp);
                    @endphp
                    <span
                        class="kw-pill {{ $alreadyExcluded ? 'excluded' : '' }}"
                        data-kw="{{ $kwUp }}"
                        onclick="{{ $alreadyExcluded ? '' : "excludeKeyword(this, '$kwUp')" }}"
                        title="{{ $alreadyExcluded ? 'Zaten yoksayılıyor' : 'Tıkla → Analizden çıkar' }}"
                    >{{ $kwUp }} @if(!$alreadyExcluded)<span class="xic">✕</span>@endif</span>
                    @endforeach
                </div>
                @endif

                <form method="POST" action="{{ route('auto-tag.apply-cluster') }}" class="tag-form">
                    @csrf
                    @foreach($cluster['transaction_ids'] ?? [] as $tid)
                        <input type="hidden" name="transaction_ids[]" value="{{ $tid }}">
                    @endforeach
                    <input type="hidden" name="keyword" value="{{ strtoupper($cluster['keywords'][0] ?? '') }}">
                    <select name="tag_id" class="input" style="padding:6px 10px;" required>
                        <option value="">— Seç —</option>
                        @foreach($tags as $tag)
                            @php
                                $suggested = false;
                                foreach($cluster['keywords'] ?? [] as $ck) {
                                    if (str_contains(strtolower($tag->name), strtolower($ck))) { $suggested = true; break; }
                                }
                            @endphp
                            <option value="{{ $tag->id }}" {{ $suggested ? 'selected' : '' }}>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Etiketle</button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
// Mevcut URL parametrelerini koru (Filtreler için)
const URL_PARAMS = new URLSearchParams(window.location.search);
const CLUSTERS_JSON_URL = "{{ route('auto-tag.clusters-json') }}";
// Blade'den gelen tag listesini JS'e aktar
const ALL_TAGS = @json($tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name]));

// ── TOAST ─────────────────────────────────────────────────────────────────────
function toast(msg, type = 'i', undoFn = null) {
    const wrap = document.getElementById('toast-wrap');
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    const icon = type === 's' ? '✅' : type === 'e' ? '❌' : 'ℹ️';
    let undoBtn = '';
    if (undoFn) {
        undoBtn = `<button class="toast-undo" id="undo-btn">Geri Al</button>`;
    }
    el.innerHTML = `<span>${icon}</span><span style="flex:1">${msg}</span>${undoBtn}`;
    wrap.appendChild(el);
    if (undoFn) {
        el.querySelector('#undo-btn').addEventListener('click', () => {
            undoFn();
            el.classList.add('out');
            setTimeout(() => el.remove(), 320);
        });
    }
    setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 320); }, 3500);
}

// ── BADGE GÜNCELLE ────────────────────────────────────────────────────────────
function updateExclCount(delta) {
    const badge = document.getElementById('excl-count');
    if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) + delta);
}

// ── HIZLI ETİKETLEME ──────────────────────────────────────────────────────────
const QT_PREVIEW_URL   = "{{ route('auto-tag.keyword-preview') }}";
const QT_TAG_URL       = "{{ route('auto-tag.quick-tag') }}";
const QT_DEL_URL       = "{{ route('auto-tag.delete-matching') }}";

let qtCurrentKeyword = '';
let qtIgnoredIds = [];

// API Scopes
let filterBankId = null;
let filterAccountId = null;
const banksData = @json($banks);

function getFilterQueryStr() {
    let q = '';
    if (filterBankId) q += '&bank_id=' + filterBankId;
    if (filterAccountId) q += '&bank_account_id=' + filterAccountId;
    return q;
}

function selectBank(bankId) {
    document.querySelectorAll('#bank-list .scope-pill').forEach(el => el.classList.remove('active'));
    filterAccountId = null;

    if (!bankId) {
        document.querySelector('#bank-list .scope-pill').classList.add('active'); // Tümü
        filterBankId = null;
        document.getElementById('accounts-container').style.display = 'none';
    } else {
        document.getElementById('bank-pill-' + bankId).classList.add('active');
        filterBankId = bankId;
        
        // Show accounts
        const bank = banksData.find(b => b.id === bankId);
        const accList = document.getElementById('account-list');
        accList.innerHTML = `<span class="scope-pill active" onclick="selectAccount(null)">Tüm Hesaplar</span>` +
            (bank?.bank_accounts || []).map(a => 
                `<span class="scope-pill" id="acc-pill-${a.id}" onclick="selectAccount(${a.id})">${a.account_name} (${a.currency})</span>`
            ).join('');
            
        document.getElementById('accounts-container').style.display = 'block';
    }
    refreshClusters();
    if (qtCurrentKeyword) quickSearch();
}

function selectAccount(accountId) {
    document.querySelectorAll('#account-list .scope-pill').forEach(el => el.classList.remove('active'));
    filterAccountId = accountId;
    if (!accountId) {
        document.querySelector('#account-list .scope-pill').classList.add('active');
    } else {
        document.getElementById('acc-pill-' + accountId).classList.add('active');
    }
    refreshClusters();
    if (qtCurrentKeyword) quickSearch();
}

document.getElementById('qt-keyword').addEventListener('keydown', e => {
    if (e.key === 'Enter') quickSearch();
});

function qtIgnoreTxn(txnId) {
    qtIgnoredIds.push(txnId);
    document.getElementById('qt-row-' + txnId).style.display = 'none';
    
    // Badge sayacını 1 azalt (görsel olarak)
    const badge = document.getElementById('qt-count-badge');
    let cnt = parseInt(badge.textContent || '0');
    if (cnt > 0) badge.textContent = cnt - 1;
}

async function quickSearch(loadAll = false) {
    const kw = document.getElementById('qt-keyword').value.trim().toUpperCase();
    if (kw.length < 2) { toast('En az 2 karakter girin', 'e'); return; }

    // Eğer yeni bir kelime aranıyorsa ignored listesini temizle
    if (kw !== qtCurrentKeyword || !loadAll) {
        qtIgnoredIds = [];
    }
    
    qtCurrentKeyword = kw;
    const btn = document.getElementById('qt-search-btn');
    btn.textContent = '⏳ Aranıyor…'; btn.disabled = true;

    try {
        const url = `${QT_PREVIEW_URL}?keyword=${encodeURIComponent(kw)}` + (loadAll ? '&limit=all' : '') + getFilterQueryStr();
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (data.status !== 'success') { toast(data.message || 'Hata', 'e'); return; }

        // Özet
        const summary = document.getElementById('qt-summary');
        const badge   = document.getElementById('qt-count-badge');
        
        let summaryHtml = `"${kw}" için <b>${data.count}</b> işlem bulundu.`;
        if (!loadAll && data.count > 100) {
            summaryHtml += ` (İlk 100 gösteriliyor) <a href="javascript:void(0)" onclick="quickSearch(true)" style="color:var(--primary);text-decoration:underline;">Tümünü Gör</a>`;
        }
        summary.innerHTML = summaryHtml;
        
        badge.textContent   = data.count - qtIgnoredIds.length;
        badge.style.display = data.count ? '' : 'none';

        // Tablo
        const tbody = document.getElementById('qt-tbody');
        tbody.innerHTML = data.transactions
            .filter(t => !qtIgnoredIds.includes(t.id)) // Daha önce gizlenenleri gösterme
            .map(t => `
            <tr id="qt-row-${t.id}" style="border-bottom:1px solid var(--border);">
                <td style="padding:7px 12px;color:var(--muted);white-space:nowrap;">${t.date}</td>
                <td style="padding:7px 12px;">${t.description}</td>
                <td style="padding:7px 12px;text-align:right;font-weight:500;color:${t.direction==='in'?'var(--success)':'var(--danger)'};">
                    ${t.direction === 'in' ? '+' : '-'} ${t.amount} <span style="font-size:10px;opacity:.6;margin-right:12px;">${t.type}</span>
                    <span onclick="qtIgnoreTxn(${t.id})" class="qt-exclude-btn" title="Bu işlemi etiketlemeden hariç tut">✖</span>
                </td>
            </tr>`).join('') || `<tr><td colspan="3" style="padding:20px;text-align:center;color:var(--muted);">Eşleşme bulunamadı.</td></tr>`;

        // Butonları göster
        document.getElementById('qt-preview').style.display = '';
        document.getElementById('qt-tag-btn').style.display = data.count ? '' : 'none';
        document.getElementById('qt-del-btn').style.display = data.count ? '' : 'none';

    } catch { toast('Bağlantı hatası', 'e'); }
    finally { btn.textContent = '🔍 Ara'; btn.disabled = false; }
}

async function quickTag() {
    const kw      = qtCurrentKeyword;
    const tagName = document.getElementById('qt-tag-name').value.trim();

    if (!kw) { toast('Önce arama yapın', 'e'); return; }
    if (!tagName) { toast('Etiket adı girin', 'e'); document.getElementById('qt-tag-name').focus(); return; }

    const btn = document.getElementById('qt-tag-btn');
    btn.textContent = '⏳ Uygulanıyor…'; btn.disabled = true;

    try {
        const res = await fetch(QT_TAG_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ 
                keyword: kw, 
                tag_name: tagName, 
                ignored_ids: qtIgnoredIds,
                bank_id: filterBankId,
                bank_account_id: filterAccountId
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            toast(data.message, 's');
            // Sayfayı reload et ki kurallar kutulara dolsun (AJAX sonrası hemen genel/özel geçişi için garanti)
            setTimeout(() => window.location.reload(), 800);
        } else {
            toast(data.message || 'Hata', 'e');
        }
    } catch { toast('Bağlantı hatası', 'e'); }
    finally { btn.textContent = '🏷️ Etiketle'; btn.disabled = false; }
}

async function deleteMatching() {
    const kw = qtCurrentKeyword;
    if (!kw) { toast('Önce arama yapın', 'e'); return; }

    const count = parseInt(document.getElementById('qt-count-badge').textContent || '0');
    if (!confirm(`⚠️ "${kw}" içeren ${count} işlem silinecek. Bu işlem geri alınamaz!\n\nDevam etmek istiyor musunuz?`)) return;

    const btn = document.getElementById('qt-del-btn');
    btn.textContent = '⏳ Siliniyor…'; btn.disabled = true;

    try {
        const url = QT_DEL_URL + '?' + new URLSearchParams({ keyword: kw }).toString() + getFilterQueryStr();
        const res = await fetch(url, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            toast(data.message, 's');
            // Tabloyu temizle
            document.getElementById('qt-tbody').innerHTML =
                `<tr><td colspan="3" style="padding:20px;text-align:center;color:var(--muted);">İşlemler kaldırıldı.</td></tr>`;
            document.getElementById('qt-count-badge').style.display = 'none';
            document.getElementById('qt-tag-btn').style.display  = 'none';
            document.getElementById('qt-del-btn').style.display  = 'none';
        } else {
            toast(data.message || 'Hata', 'e');
        }
    } catch { toast('Bağlantı hatası', 'e'); }
    finally { btn.textContent = '🗑️ Sil'; btn.disabled = false; }
}

// ── KEYWORD PILL TIKLAMA (Sayfa yenilemeden hariç tut) ────────────────────────
async function excludeKeyword(pill, kw) {
    pill.classList.add('loading');
    try {
        const res = await fetch("{{ route('auto-tag.exclusion.add') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ keyword: kw })
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.querySelectorAll(`.kw-pill[data-kw="${kw}"]`).forEach(p => {
                p.classList.remove('loading');
                p.classList.add('excluded');
                p.onclick = null;
                p.title = '⚠️ Bu kelime yoksayılıyor';
                const x = p.querySelector('.xic');
                if (x) x.remove();
            });
            updateExclCount(+1);
            addExclToDOM(data.exclusion);

            // Kümleri yeniden hesapla
            refreshClusters();

            toast(`"${kw}" analizden çıkarıldı`, 's', () => undoExclusion(data.exclusion.id, kw));
        } else {
            pill.classList.remove('loading');
            toast(data.message || 'Zaten listede.', 'e');
        }
    } catch {
        pill.classList.remove('loading');
        toast('Bağlantı hatası', 'e');
    }
}

// ── UNDO EKSKLÜZİON ──────────────────────────────────────────────────────────
async function undoExclusion(id, kw) {
    try {
        const res = await fetch(`{{ url('/oto-etiket/kelime-haric-tut') }}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById(`ex-${id}`)?.remove();
            activatePills(kw);
            updateExclCount(-1);
            // Kümeleri yeniden hesapla
            refreshClusters();
            toast(`"${kw}" geri alındı`, 'i');
        }
    } catch { toast('Geri alma hatası', 'e'); }
}

// ── STOP-WORD ELLE EKLE ───────────────────────────────────────────────────────
async function addExclusion() {
    const inp = document.getElementById('excl-input');
    const kw = inp.value.trim().toUpperCase();
    if (!kw) return;
    inp.disabled = true;
    try {
        const res = await fetch("{{ route('auto-tag.exclusion.add') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ keyword: kw })
        });
        const data = await res.json();
        if (data.status === 'success') {
            inp.value = '';
            addExclToDOM(data.exclusion);
            updateExclCount(+1);
            document.querySelectorAll(`.kw-pill[data-kw="${kw}"]`).forEach(p => {
                p.classList.add('excluded'); p.onclick = null;
                p.title = '⚠️ Bu kelime yoksayılıyor';
                const x = p.querySelector('.xic'); if (x) x.remove();
            });
            // Elle eklendi → kümeleri de yenile
            refreshClusters();
            toast(`"${kw}" eklendi`, 's', () => undoExclusion(data.exclusion.id, kw));
        } else {
            toast(data.message || 'Eklenemedi', 'e');
        }
    } catch { toast('Bağlantı hatası', 'e'); }
    finally { inp.disabled = false; inp.focus(); }
}

document.getElementById('excl-input').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); addExclusion(); }
});

// ── STOP-WORD SİL ─────────────────────────────────────────────────────────────
async function deleteExclusion(id, kw) {
    const item = document.getElementById(`ex-${id}`);
    if (!item) return;
    item.classList.add('removing');
    try {
        const res = await fetch(`{{ url('/oto-etiket/kelime-haric-tut') }}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            setTimeout(() => item.remove(), 200);
            updateExclCount(-1);
            activatePills(kw);
            // Exclusion silindi → kümeleri yenile
            refreshClusters();
            toast(`"${kw}" listeden kaldırıldı`, 'i');
        }
    } catch { item.classList.remove('removing'); toast('Silme hatası', 'e'); }
}

// ── PILL'LERİ YENİDEN AKTİF ET ───────────────────────────────────────────────
function activatePills(kw) {
    document.querySelectorAll(`.kw-pill[data-kw="${kw}"]`).forEach(p => {
        p.classList.remove('excluded');
        p.title = 'Tıkla → Analizden çıkar';
        p.onclick = () => excludeKeyword(p, kw);
        if (!p.querySelector('.xic')) {
            const x = document.createElement('span');
            x.className = 'xic'; x.textContent = '✕'; p.appendChild(x);
        }
    });
}

// ── KURAL SİL ─────────────────────────────────────────────────────────────────
async function deleteRule(id) {
    const item = document.getElementById(`rule-${id}`);
    if (!item) return;
    item.classList.add('removing');
    try {
        const res = await fetch(`{{ url('/oto-etiket/kural-sil') }}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.status === 'success') {
            setTimeout(() => item.remove(), 220);
            const cnt = document.getElementById('rule-count');
            if (cnt) cnt.textContent = Math.max(0, parseInt(cnt.textContent) - 1);
            toast('Kural silindi', 'i');
        } else {
            item.classList.remove('removing');
            toast('Silinemedi', 'e');
        }
    } catch { item.classList.remove('removing'); toast('Silme hatası', 'e'); }
}

// ── RE-CLUSTERING (Exclusion değişince kümeleri sıfırdan yenile) ──────────────
async function refreshClusters() {
    const grid = document.getElementById('cluster-grid');
    if (!grid) return;

    // Spinner göster
    grid.innerHTML = `<div class="cluster-loading-banner" style="grid-column:span 3">
        <span class="spin">🧠</span> Kümeler yeni exclusion listesiyle yeniden hesaplanıyor…
    </div>`;

    // Mevcut sayfa URL parametrelerini (filtreler) koru
    // Mevcut sayfa URL parametrelerini (filtreler) koru
    const params = new URLSearchParams();
    if (filterBankId) params.set('bank_id', filterBankId);
    if (filterAccountId) params.set('bank_account_id', filterAccountId);
    
    // Ek filtreleri oku
    const min = document.getElementById('filter-min-amount').value;
    const max = document.getElementById('filter-max-amount').value;
    const start = document.getElementById('filter-start-date').value;
    const end = document.getElementById('filter-end-date').value;
    const n = document.getElementById('filter-n-clusters').value;

    if (min) params.set('min_amount', min);
    if (max) params.set('max_amount', max);
    if (start) params.set('start_date', start);
    if (end) params.set('end_date', end);
    if (n) params.set('n_clusters', n);

    try {
        const res = await fetch(`${CLUSTERS_JSON_URL}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (data.status !== 'success') {
            grid.innerHTML = `<div class="cluster-loading-banner" style="color:var(--danger)">
                ⚠️ ${data.message || 'Servis hatası'}
            </div>`;
            return;
        }

        // Mevcut exclusion set'ini title bilgisinden değil DOM'dan oku
        const excludedKws = new Set(
            Array.from(document.querySelectorAll('#excl-list .pill-item'))
                .map(el => el.firstChild?.textContent?.trim())
                .filter(Boolean)
        );

        grid.innerHTML = data.clusters.map(c => renderClusterCard(c, data.tags, excludedKws)).join('');

        // Başlık güncelle
        const header = document.querySelector('.section .section-header');
        if (header && !header.querySelector('form')) {
            header.textContent = `İşlem Kümeleri (${data.clusters.length} Grup)`;
        }
    } catch (e) {
        console.error(e);
        grid.innerHTML = `<div class="cluster-loading-banner" style="color:var(--danger)">⚠️ Bağlantı hatası</div>`;
    }
}

function renderClusterCard(c, tags, excludedKws) {
    const kws = (c.keywords || []).slice(0, 6);
    const pct = c.tagged_pct ?? 0;
    const pctColor = pct >= 70 ? 'var(--success)' : pct >= 30 ? 'var(--warn)' : 'var(--muted)';

    const kwHtml = kws.map(kw => {
        const kwUp = kw.toUpperCase();
        const isExcl = excludedKws.has(kwUp);
        const click = isExcl ? '' : `onclick="excludeKeyword(this,'${kwUp}')"`;
        const xic = isExcl ? '' : '<span class="xic">✕</span>';
        const cls = isExcl ? 'kw-pill excluded' : 'kw-pill';
        const ttl = isExcl ? '⚠️ Yoksayılıyor' : 'Tıkla → Analizden çıkar';
        return `<span class="${cls}" data-kw="${kwUp}" ${click} title="${ttl}">${kwUp}${xic}</span>`;
    }).join('');

    // Auto-suggest: ilk eşleşen etiketi seç
    let suggestedId = '';
    for (const kw of kws) {
        const match = tags.find(t => t.name.toLowerCase().includes(kw.toLowerCase()));
        if (match) { suggestedId = match.id; break; }
    }

    const opts = tags.map(t =>
        `<option value="${t.id}"${t.id == suggestedId ? ' selected' : ''}>${t.name}</option>`
    ).join('');

    const hiddenIds = (c.transaction_ids || []).map(id =>
        `<input type="hidden" name="transaction_ids[]" value="${id}">`
    ).join('');

    return `
    <div class="cluster-card">
        <div class="cluster-meta">
            <strong>${c.count} İşlem</strong>
            <span style="color:${pctColor}">%${pct} Etiketli</span>
        </div>
        <div class="cluster-rep">"${(c.representative || '').slice(0, 80)}…"</div>
        ${kws.length ? `<div class="kw-list">${kwHtml}</div>` : ''}
        <form method="POST" action="{{ route('auto-tag.apply-cluster') }}" class="tag-form">
            <input type="hidden" name="_token" value="${CSRF}">
            ${hiddenIds}
            <input type="hidden" name="keyword" value="${(c.keywords?.[0] ?? '').toUpperCase()}">
            <input type="hidden" name="bank_id" value="${filterBankId || ''}">
            <input type="hidden" name="bank_account_id" value="${filterAccountId || ''}">
            <select name="tag_id" class="input" style="padding:6px 10px;" required>
                <option value="">— Seç —</option>${opts}
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Etiketle</button>
        </form>
    </div>`;
}

// ── DOM'A STOP-WORD EKLE ──────────────────────────────────────────────────────
function addExclToDOM(ex) {
    const list = document.getElementById('excl-list');
    const empty = document.getElementById('excl-empty');
    if (empty) empty.remove();
    // Çift eklemeyi önle
    if (document.getElementById(`ex-${ex.id}`)) return;
    const el = document.createElement('div');
    el.className = 'pill-item'; el.id = `ex-${ex.id}`;
    el.style.animation = 'slideUp .2s ease';
    el.innerHTML = `${ex.keyword} <button class="del-btn" onclick="deleteExclusion(${ex.id}, '${ex.keyword}')">×</button>`;
    list.appendChild(el);
}
</script>
</body>
</html>
