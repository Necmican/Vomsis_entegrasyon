<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Yapay Zeka Finansal Analiz</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fc;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .filter-container {
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            min-width: 250px;
            font-size: 14px;
            cursor: pointer;
        }

        .currency-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .currency-tab {
            padding: 8px 20px;
            border-radius: 6px;
            border: 2px solid #dee2e6;
            background: white;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .currency-tab.active-TL {
            background: #4e73df;
            color: white;
            border-color: #4e73df;
        }

        .currency-tab.active-USD {
            background: #1cc88a;
            color: white;
            border-color: #1cc88a;
        }

        .currency-tab.active-EUR {
            background: #f6c23e;
            color: #333;
            border-color: #f6c23e;
        }

        .chart-section {
            display: none;
        }

        .chart-section.active {
            display: block;
        }

        .accuracy-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
            vertical-align: middle;
        }

        .accuracy-high {
            background: #d4edda;
            color: #155724;
        }

        .accuracy-medium {
            background: #fff3cd;
            color: #856404;
        }

        .accuracy-low {
            background: #f8d7da;
            color: #721c24;
        }

        /* Grafik Katman Toggle Butonları */
        .chart-toggles {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .chart-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            border: 2px solid #dee2e6;
            background: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            user-select: none;
        }

        .chart-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-toggle .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .chart-toggle.active {
            color: white;
        }

        .chart-toggle.active .dot {
            background: white !important;
        }

        /* Toggle renkleri */
        .toggle-balance.active {
            background: #4e73df;
            border-color: #4e73df;
        }

        .toggle-income.active {
            background: #1cc88a;
            border-color: #1cc88a;
        }

        .toggle-expense.active {
            background: #e74a3b;
            border-color: #e74a3b;
        }

        .toggle-forecast.active {
            background: #36b9cc;
            border-color: #36b9cc;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .currency-tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;">📊 Yapay Zeka Finansal Analiz ve Nakit Akışı</h2>
        <a href="{{ route('dashboard') }}"
            style="display: inline-flex; align-items: center; gap: 8px; background-color: #4e73df; color: white; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s;">
            <span>&larr;</span> Ana Ekrana Dön
        </a>
    </div>
    @if(isset($virmanCount) && $virmanCount > 0)
        <div
            style="background: #e8f4fd; border-left: 4px solid #2196F3; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; font-size: 14px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <span style="font-size: 18px;">🔄</span>
                <span>
                    <strong>{{ number_format($virmanCount) }}</strong> adet hesaplar arası transfer (virman) işlemi tespit
                    edildi.
                    Gelir/gider analizinden, pasta grafiğinden ve yapay zeka tahmin modelinden
                    <strong>çıkarılmıştır</strong>.
                </span>
            </div>
            @if(!empty($virmanVolumes))
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-left: 28px; margin-top: 4px;">
                    @foreach($virmanVolumes as $cur => $vol)
                        @php
                            $symbol = $cur === 'TL' ? '₺' : ($cur === 'USD' ? '$' : '€');
                            $bgColor = $cur === 'TL' ? '#4e73df' : ($cur === 'USD' ? '#1cc88a' : '#f6c23e');
                            $textColor = $cur === 'EUR' ? '#333' : '#fff';
                        @endphp
                        <span
                            style="background: {{ $bgColor }}; color: {{ $textColor }}; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                            {{ $cur }}: {{ $vol['count'] }} işlem · {{ $symbol }}{{ number_format($vol['income'], 0, ',', '.') }}
                            giren · {{ $symbol }}{{ number_format($vol['expense'], 0, ',', '.') }} çıkan
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
    <div class="filter-container">
        <form method="GET" action="{{ route('analytics.index') }}" id="filterForm">
            <input type="hidden" name="active_tab" id="active_tab" value="{{ request('active_tab', 'TL') }}">

            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <div>
                    <label for="account_id" style="font-weight: bold; margin-right: 10px;">Banka Hesabı Seçin:</label>
                    <select name="account_id" id="account_id" onchange="document.getElementById('filterForm').submit()">
                        <option value="">🌟 Tüm Hesaplar (Konsolide)</option>
                        @if(isset($accounts) && count($accounts) > 0)
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ (isset($selectedAccountId) && $selectedAccountId == $account->id) ? 'selected' : '' }}>
                                    {{ $account->bank_name ?? $account->banka_adi ?? 'Banka' }} -
                                    {{ $account->iban ?? $account->account_name ?? $account->name ?? 'Hesap ' . $account->id }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div>
                    <label for="timeframe" style="font-weight: bold; margin-right: 10px;">Zaman Aralığı:</label>
                    <select name="timeframe" id="timeframe" onchange="document.getElementById('filterForm').submit()">
                        <option value="15_days" {{ request('timeframe', '15_days') === '15_days' ? 'selected' : '' }}>Son
                            15 Gün</option>
                        <option value="1_month" {{ request('timeframe') === '1_month' ? 'selected' : '' }}>Son 1 Ay
                        </option>
                        <option value="3_months" {{ request('timeframe') === '3_months' ? 'selected' : '' }}>Son 3 Ay
                        </option>
                        <option value="6_months" {{ request('timeframe') === '6_months' ? 'selected' : '' }}>Son 6 Ay
                        </option>
                        <option value="1_year" {{ request('timeframe') === '1_year' ? 'selected' : '' }}>Son 1 Yıl
                        </option>
                        <option value="all" {{ request('timeframe') === 'all' ? 'selected' : '' }}>Tüm Zamanlar</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    {{-- Para Birimi Sekmeleri --}}
    <div class="currency-tabs">
        <button class="currency-tab active-TL" onclick="showCurrency('TL')">🇹🇷 Türk Lirası (₺)</button>
        <button class="currency-tab" onclick="showCurrency('USD')">🇺🇸 Dolar ($)</button>
        <button class="currency-tab" onclick="showCurrency('EUR')">🇪🇺 Euro (€)</button>
    </div>

    {{-- TL GRAFİKLERİ --}}
    <div class="chart-section active" id="section-TL">
        <div class="grid">
            <div class="card">
                <h3 id="chartTitle-TL">📈 TL — Nakit Akışı Tahmini (Prophet AI)</h3>
                <div class="chart-toggles" id="toggles-TL">
                    <button class="chart-toggle toggle-balance active" data-layer="balance" data-currency="TL"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#4e73df"></span> Bakiye
                    </button>
                    <button class="chart-toggle toggle-income" data-layer="income" data-currency="TL"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#1cc88a"></span> Gelir
                    </button>
                    <button class="chart-toggle toggle-expense" data-layer="expense" data-currency="TL"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#e74a3b"></span> Gider
                    </button>
                    <button class="chart-toggle toggle-forecast active" data-layer="forecast" data-currency="TL"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#36b9cc"></span> AI Tahmini
                    </button>
                </div>
                <canvas id="cashFlowChart-TL" height="100"></canvas>
            </div>
            <div class="card">
                <h3> TL — İşlem Dağılımı (Etiketlere Göre)</h3>
                <div class="chart-toggles" style="justify-content: center; margin-top: 10px;">
                    <button class="chart-toggle toggle-income" onclick="togglePieLayer('TL', 'income', this)">
                        <span class="dot" style="background:#1cc88a"></span> Gelir Dağılımı
                    </button>
                    <button class="chart-toggle toggle-expense active" onclick="togglePieLayer('TL', 'expense', this)">
                        <span class="dot" style="background:#e74a3b"></span> Gider Dağılımı
                    </button>
                </div>
                <canvas id="expensePieChart-TL" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- USD GRAFİKLERİ --}}
    <div class="chart-section" id="section-USD">
        <div class="grid">
            <div class="card">
                <h3 id="chartTitle-USD">📈 USD — Nakit Akışı Tahmini</h3>
                <div class="chart-toggles" id="toggles-USD">
                    <button class="chart-toggle toggle-balance active" data-layer="balance" data-currency="USD"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#1cc88a"></span> Bakiye
                    </button>
                    <button class="chart-toggle toggle-income" data-layer="income" data-currency="USD"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#1cc88a"></span> Gelir
                    </button>
                    <button class="chart-toggle toggle-expense" data-layer="expense" data-currency="USD"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#e74a3b"></span> Gider
                    </button>
                    <button class="chart-toggle toggle-forecast active" data-layer="forecast" data-currency="USD"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#36b9cc"></span> AI Tahmini
                    </button>
                </div>
                <canvas id="cashFlowChart-USD" height="100"></canvas>
            </div>
            <div class="card">
                <h3> USD — İşlem Dağılımı (Etiketlere Göre)</h3>
                <div class="chart-toggles" style="justify-content: center; margin-top: 10px;">
                    <button class="chart-toggle toggle-income" onclick="togglePieLayer('USD', 'income', this)">
                        <span class="dot" style="background:#1cc88a"></span> Gelir Dağılımı
                    </button>
                    <button class="chart-toggle toggle-expense active" onclick="togglePieLayer('USD', 'expense', this)">
                        <span class="dot" style="background:#e74a3b"></span> Gider Dağılımı
                    </button>
                </div>
                <canvas id="expensePieChart-USD" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- EUR GRAFİKLERİ --}}
    <div class="chart-section" id="section-EUR">
        <div class="grid">
            <div class="card">
                <h3 id="chartTitle-EUR">📈 EUR — Nakit Akışı Tahmini</h3>
                <div class="chart-toggles" id="toggles-EUR">
                    <button class="chart-toggle toggle-balance active" data-layer="balance" data-currency="EUR"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#f6c23e"></span> Bakiye
                    </button>
                    <button class="chart-toggle toggle-income" data-layer="income" data-currency="EUR"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#1cc88a"></span> Gelir
                    </button>
                    <button class="chart-toggle toggle-expense" data-layer="expense" data-currency="EUR"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#e74a3b"></span> Gider
                    </button>
                    <button class="chart-toggle toggle-forecast active" data-layer="forecast" data-currency="EUR"
                        onclick="toggleLayer(this)">
                        <span class="dot" style="background:#36b9cc"></span> AI Tahmini
                    </button>
                </div>
                <canvas id="cashFlowChart-EUR" height="100"></canvas>
            </div>
            <div class="card">
                <h3> EUR — İşlem Dağılımı (Etiketlere Göre)</h3>
                <div class="chart-toggles" style="justify-content: center; margin-top: 10px;">
                    <button class="chart-toggle toggle-income" onclick="togglePieLayer('EUR', 'income', this)">
                        <span class="dot" style="background:#1cc88a"></span> Gelir Dağılımı
                    </button>
                    <button class="chart-toggle toggle-expense active" onclick="togglePieLayer('EUR', 'expense', this)">
                        <span class="dot" style="background:#e74a3b"></span> Gider Dağılımı
                    </button>
                </div>
                <canvas id="expensePieChart-EUR" height="200"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // ─── Controller'dan gelen PHP verileri JS değişkenlerine aktarılıyor ───
        // Geçmiş bakiye/gelir/gider verileri (gün bazında)
        const historyTL = @json($historyTL ?? []);
        const historyUSD = @json($historyUSD ?? []);
        const historyEUR = @json($historyEUR ?? []);

        // Prophet AI'dan dönen 15 günlük tahmin verileri
        const forecastTL = @json($forecastTL ?? []);
        const forecastUSD = @json($forecastUSD ?? []);
        const forecastEUR = @json($forecastEUR ?? []);

        // Backtesting ile hesaplanan model doğruluk yüzdeleri
        const accuracyTL = @json($accuracyTL ?? null);
        const accuracyUSD = @json($accuracyUSD ?? null);
        const accuracyEUR = @json($accuracyEUR ?? null);

        // Etiket bazlı gelir/gider toplamları (pasta grafiği için)
        const tagStats = @json($tagStats ?? []);

        // Chart instance referansları
        const chartInstances = {};
        const pieChartInstances = {};

        // Dataset index haritası: hangi layer hangi dataset index'i
        const datasetIndexMap = {};

        // Sekme geçişi
        let activeTab = '{{ request('active_tab', 'TL') }}';
        const tabs = ['TL', 'USD', 'EUR'];

        // Tıklanan kur sekmesini aktif yap, diğerlerini gizle
        function showCurrency(cur) {
            // Tüm sekmeleri sıfırla
            tabs.forEach(c => {
                document.getElementById('section-' + c).classList.remove('active');
                document.querySelectorAll('.currency-tab').forEach((btn, i) => {
                    if (tabs[i] === c) btn.className = 'currency-tab';
                });
            });
            // Seçilen sekmeyi göster ve rengini aktif yap
            document.getElementById('section-' + cur).classList.add('active');
            const idx = tabs.indexOf(cur);
            document.querySelectorAll('.currency-tab')[idx].className = 'currency-tab active-' + cur;
            activeTab = cur;
            document.getElementById('active_tab').value = cur;
        }

        showCurrency(activeTab);

        /**
         * Katman toggle — sayfa yenilemeden grafik datasını açıp kapatır
         */
        function toggleLayer(btn) {
            const layer = btn.dataset.layer;
            const currency = btn.dataset.currency;
            const chart = chartInstances[currency];
            if (!chart) return;

            // Buton durumunu değiştir
            btn.classList.toggle('active');
            const isActive = btn.classList.contains('active');

            // Bu layer'a ait dataset index'lerini bul
            const indices = datasetIndexMap[currency]?.[layer] || [];
            indices.forEach(idx => {
                if (chart.data.datasets[idx]) {
                    chart.data.datasets[idx].hidden = !isActive;
                }
            });

            chart.update();
        }

        /**
         * Ana çizgi+bar grafik çizici.
         * Geçmiş bakiye (çizgi), gelir/gider (bar) ve AI tahmini (kesikli çizgi) katmanlarını oluşturur.
         */
        function drawCashFlowChart(canvasId, historyData, forecast, currency, accuracy) {
            // Kura göre para sembolü ve renk belirleme
            const symbol = currency === 'TL' ? '₺' : (currency === 'USD' ? '$' : '€');
            const balanceColor = currency === 'TL' ? '#4e73df' : (currency === 'USD' ? '#1cc88a' : '#f6c23e');
            const incomeColor = '#1cc88a';
            const expenseColor = '#e74a3b';
            const forecastColor = '#36b9cc';

            const historyDates = Object.keys(historyData);

            // Geriye uyumlu veri çıkarma
            const historyBalances = Object.values(historyData).map(v =>
                (typeof v === 'object' && v !== null) ? v.balance : v
            );
            const historyIncomes = Object.values(historyData).map(v =>
                (typeof v === 'object' && v !== null) ? (v.income || 0) : 0
            );
            const historyExpenses = Object.values(historyData).map(v =>
                (typeof v === 'object' && v !== null) ? (v.expense || 0) : 0
            );

            const forecastDates = forecast.map(f => f.date);
            const forecastBalances = forecast.map(f => f.predicted_balance);
            const forecastUpper = forecast.map(f => f.upper_bound);
            const forecastLower = forecast.map(f => f.lower_bound);

            const allDates = [...historyDates, ...forecastDates];
            const pastPoints = [...historyBalances, ...Array(forecastDates.length).fill(null)];

            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            // Düz boş çizgi fix
            if (historyBalances.length > 0 && historyBalances.every(v => parseFloat(v) === 0)) {
                canvas.parentElement.innerHTML += '<div style="text-align: center; color: #888; padding: 30px;">Bu zaman aralığında hiç finansal hareket bulunamadı.</div>';
                canvas.style.display = 'none';
                return;
            }

            // Gelir/Gider bar verileri (sadece history bölümünde, forecast bölümü null)
            const incomePoints = [...historyIncomes, ...Array(forecastDates.length).fill(null)];
            const expensePoints = [...historyExpenses.map(v => -v), ...Array(forecastDates.length).fill(null)];

            // Tahmin çizgisi: geçmiş verinin son noktasından başlayıp gelecek 15 güne uzanır
            const lastBal = historyBalances.length > 0 ? historyBalances[historyBalances.length - 1] : 0;
            const nullPad = Array(historyDates.length > 0 ? historyDates.length - 1 : 0).fill(null);
            const futurePoints = [...nullPad, lastBal, ...forecastBalances];
            const upperPoints = [...nullPad, lastBal, ...forecastUpper];
            const lowerPoints = [...nullPad, lastBal, ...forecastLower];

            // Her dataset'in index'ini kaydet (toggle butonlarıyla açıp kapatmak için)
            let dsIdx = 0;
            const indexMap = { balance: [], income: [], expense: [], forecast: [] };

            const datasets = [];

            // 0: Bakiye çizgisi
            datasets.push({
                label: 'Bakiye (' + symbol + ')',
                data: pastPoints,
                borderColor: balanceColor,
                backgroundColor: balanceColor + '1a',
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointRadius: historyDates.length > 60 ? 0 : 3,
                type: 'line',
                yAxisID: 'y',
                order: 1,
            });
            indexMap.balance.push(dsIdx++);

            // 1: Gelir bar
            datasets.push({
                label: 'Günlük Gelir (' + symbol + ')',
                data: incomePoints,
                backgroundColor: incomeColor + '66',
                borderColor: incomeColor,
                borderWidth: 1,
                type: 'bar',
                yAxisID: 'y2',
                order: 3,
                hidden: true,  // Başta kapalı
            });
            indexMap.income.push(dsIdx++);

            // 2: Gider bar (negatif)
            datasets.push({
                label: 'Günlük Gider (' + symbol + ')',
                data: expensePoints,
                backgroundColor: expenseColor + '66',
                borderColor: expenseColor,
                borderWidth: 1,
                type: 'bar',
                yAxisID: 'y2',
                order: 3,
                hidden: true,  // Başta kapalı
            });
            indexMap.expense.push(dsIdx++);

            // 3,4,5: Tahmin + güven bandı
            if (forecastDates.length > 0) {
                datasets.push({
                    label: 'AI Tahmini (' + symbol + ')',
                    data: futurePoints,
                    borderColor: forecastColor,
                    borderWidth: 3,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.3,
                    pointRadius: 4,
                    type: 'line',
                    yAxisID: 'y',
                    order: 0,
                });
                indexMap.forecast.push(dsIdx++);

                datasets.push({
                    label: 'Üst Sınır (%80)',
                    data: upperPoints,
                    borderColor: 'transparent',
                    backgroundColor: forecastColor + '1f',
                    fill: '+1',
                    pointRadius: 0,
                    tension: 0.3,
                    type: 'line',
                    yAxisID: 'y',
                    order: 2,
                });
                indexMap.forecast.push(dsIdx++);

                datasets.push({
                    label: 'Alt Sınır (%80)',
                    data: lowerPoints,
                    borderColor: 'transparent',
                    backgroundColor: 'transparent',
                    fill: false,
                    pointRadius: 0,
                    tension: 0.3,
                    type: 'line',
                    yAxisID: 'y',
                    order: 2,
                });
                indexMap.forecast.push(dsIdx++);
            }

            // Doğruluk skoru badge
            if (accuracy !== null && accuracy !== undefined && accuracy > 0) {
                const titleEl = document.getElementById('chartTitle-' + currency);
                if (titleEl && !titleEl.querySelector('.accuracy-badge')) {
                    let badgeClass = 'accuracy-low';
                    if (accuracy >= 80) badgeClass = 'accuracy-high';
                    else if (accuracy >= 50) badgeClass = 'accuracy-medium';
                    titleEl.innerHTML += ` <span class="accuracy-badge ${badgeClass}">Tahmin Doğruluğu: %${accuracy}</span>`;
                }
            }

            const chart = new Chart(canvas, {
                type: 'bar',  // base type = bar, ama her dataset kendi type'ını override eder
                data: { labels: allDates, datasets },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: false,
                            title: { display: true, text: 'Bakiye (' + symbol + ')' },
                        },
                        y2: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            display: false,
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Gelir / Gider' },
                        },
                        x: {
                            ticks: { maxTicksAllowed: 20, maxRotation: 45 }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y;
                                    if (value === null || value === undefined) return '';
                                    const formatted = new Intl.NumberFormat('tr-TR').format(Math.round(Math.abs(value)));
                                    const prefix = value < 0 ? '-' : '';
                                    return label + ': ' + prefix + formatted + ' ' + symbol;
                                }
                            }
                        },
                        legend: {
                            display: false  // Kendi toggle butonlarımızı kullanıyoruz
                        }
                    }
                }
            });

            // Referansları sakla
            chartInstances[currency] = chart;
            datasetIndexMap[currency] = indexMap;
        }

        /**
         * Pasta (doughnut) grafik çizici.
         * Etiketlere göre gelir veya gider dağılımını görselleştirir.
         */
        function initPieChart(canvasId, currency) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            // Sayfa ilk yüklendiğinde varsayılan olarak gider dağılımını göster
            let stats = [];
            if (tagStats[currency] && tagStats[currency]['expense']) {
                stats = tagStats[currency]['expense'];
            }

            const labels = stats.map(t => t.name);
            const values = stats.map(t => t.total);

            // Eğer hiç veri yoksa boş görünebilir
            const chart = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#e74a3b', '#f6c23e', '#36b9cc', '#4e73df', '#1cc88a', '#858796', '#fd7e14', '#6f42c1'],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const val = context.parsed;
                                    const symbol = currency === 'TL' ? '₺' : (currency === 'USD' ? '$' : '€');
                                    return ' ' + new Intl.NumberFormat('tr-TR').format(Math.round(val)) + ' ' + symbol;
                                }
                            }
                        }
                    }
                }
            });

            pieChartInstances[currency] = chart;
        }

        /**
         * Pasta grafiğinde gelir/gider geçişi.
         * Kullanıcı "Gelir Dağılımı" veya "Gider Dağılımı" butonuna tıklayınca
         * Chart.js datasını güncelleyerek yeni verileri çizer.
         */
        function togglePieLayer(currency, type, btnEl) {
            const chart = pieChartInstances[currency];
            if (!chart) return;

            // Diğer butonu pasif yap
            const parent = btnEl.parentElement;
            parent.querySelectorAll('.chart-toggle').forEach(b => b.classList.remove('active'));
            // Tıklananı aktif yap
            btnEl.classList.add('active');

            // Yeni veriyi al
            let stats = [];
            if (tagStats[currency] && tagStats[currency][type]) {
                stats = tagStats[currency][type];
            }

            const labels = stats.map(t => t.name);
            const values = stats.map(t => t.total);

            // Renk paleti ayarı (gelirler için mavi/yeşil ağırlıklı)
            let colors = ['#e74a3b', '#f6c23e', '#36b9cc', '#4e73df', '#1cc88a', '#858796', '#fd7e14', '#6f42c1'];
            if (type === 'income') {
                colors = ['#1cc88a', '#4e73df', '#36b9cc', '#f6c23e', '#fd7e14', '#e74a3b'];
            }

            // Chart datasını güncelle
            chart.data.labels = labels;
            chart.data.datasets[0].data = values;
            chart.data.datasets[0].backgroundColor = colors;

            chart.update();
        }

        // ─── Sayfa yüklendiğinde tüm grafikleri oluştur ───
        // Her kur için ayrı çizgi grafiği (bakiye + tahmin)
        drawCashFlowChart('cashFlowChart-TL', historyTL, forecastTL, 'TL', accuracyTL);
        drawCashFlowChart('cashFlowChart-USD', historyUSD, forecastUSD, 'USD', accuracyUSD);
        drawCashFlowChart('cashFlowChart-EUR', historyEUR, forecastEUR, 'EUR', accuracyEUR);

        // Her kur için ayrı pasta grafiği (etiket dağılımı)
        initPieChart('expensePieChart-TL', 'TL');
        initPieChart('expensePieChart-USD', 'USD');
        initPieChart('expensePieChart-EUR', 'EUR');
    </script>

</body>

</html>