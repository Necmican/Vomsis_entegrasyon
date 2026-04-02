<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yapay Zeka Finansal Analiz</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fc; padding: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .filter-container { margin-bottom: 20px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; min-width: 250px; font-size: 14px; cursor: pointer; }
        .currency-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .currency-tab { padding: 8px 20px; border-radius: 6px; border: 2px solid #dee2e6; background: white; font-weight: bold; cursor: pointer; font-size: 14px; transition: all 0.2s; }
        .currency-tab.active-TL  { background: #4e73df; color: white; border-color: #4e73df; }
        .currency-tab.active-USD { background: #1cc88a; color: white; border-color: #1cc88a; }
        .currency-tab.active-EUR { background: #f6c23e; color: #333; border-color: #f6c23e; }
        .chart-section { display: none; }
        .chart-section.active { display: block; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } .currency-tabs { flex-wrap: wrap; } }
    </style>
</head>
<body>

    <h2>📊 Yapay Zeka Finansal Analiz ve Nakit Akışı</h2>

    <div class="filter-container">
        <form method="GET" action="{{ route('analytics.index') }}" id="filterForm">
            <label for="account_id" style="font-weight: bold; margin-right: 10px;">Banka Hesabı Seçin:</label>
            <select name="account_id" id="account_id" onchange="document.getElementById('filterForm').submit()">
                <option value="">🌟 Tüm Hesaplar (Konsolide)</option>
                @if(isset($accounts) && count($accounts) > 0)
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ (isset($selectedAccountId) && $selectedAccountId == $account->id) ? 'selected' : '' }}>
                            {{ $account->bank_name ?? $account->banka_adi ?? 'Banka' }} - {{ $account->iban ?? $account->account_name ?? $account->name ?? 'Hesap ' . $account->id }}
                        </option>
                    @endforeach
                @endif
            </select>
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
                <h3>📈 TL — 15 Günlük Nakit Akışı Tahmini (Holt-Winters Model)</h3>
                <canvas id="cashFlowChart-TL" height="100"></canvas>
            </div>
            <div class="card">
                <h3>🍩 TL — Gider Dağılımı (Etiketlere Göre)</h3>
                <canvas id="expensePieChart-TL" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- USD GRAFİKLERİ --}}
    <div class="chart-section" id="section-USD">
        <div class="grid">
            <div class="card">
                <h3>📈 USD — 15 Günlük Nakit Akışı Tahmini</h3>
                <canvas id="cashFlowChart-USD" height="100"></canvas>
            </div>
            <div class="card">
                <h3>🍩 USD — Gider Dağılımı (Etiketlere Göre)</h3>
                <canvas id="expensePieChart-USD" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- EUR GRAFİKLERİ --}}
    <div class="chart-section" id="section-EUR">
        <div class="grid">
            <div class="card">
                <h3>📈 EUR — 15 Günlük Nakit Akışı Tahmini</h3>
                <canvas id="cashFlowChart-EUR" height="100"></canvas>
            </div>
            <div class="card">
                <h3>🍩 EUR — Gider Dağılımı (Etiketlere Göre)</h3>
                <canvas id="expensePieChart-EUR" height="200"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // PHP'den gelen veriler
        const historyTL   = @json($historyTL ?? []);
        const historyUSD  = @json($historyUSD ?? []);
        const historyEUR  = @json($historyEUR ?? []);
        
        const forecastTL  = @json($forecastTL ?? []);
        const forecastUSD = @json($forecastUSD ?? []);
        const forecastEUR = @json($forecastEUR ?? []);
        
        const tagStats    = @json($tagStats ?? []);

        // Sekme geçişi
        let activeTab = 'TL';
        const tabs = ['TL', 'USD', 'EUR'];

        function showCurrency(cur) {
            tabs.forEach(c => {
                document.getElementById('section-' + c).classList.remove('active');
                document.querySelectorAll('.currency-tab').forEach((btn, i) => {
                    if (tabs[i] === c) btn.className = 'currency-tab';
                });
            });
            document.getElementById('section-' + cur).classList.add('active');
            const idx = tabs.indexOf(cur);
            document.querySelectorAll('.currency-tab')[idx].className = 'currency-tab active-' + cur;
            activeTab = cur;
        }

        // Grafik çizici yardımcı fonksiyon
        function drawCashFlowChart(canvasId, historyData, forecast, currency) {
            const symbol = currency === 'TL' ? '₺' : (currency === 'USD' ? '$' : '€');
            const color  = currency === 'TL' ? '#4e73df' : (currency === 'USD' ? '#1cc88a' : '#f6c23e');

            const historyDates    = Object.keys(historyData);
            const historyBalances = Object.values(historyData);

            const forecastDates    = forecast.map(f => f.date);
            const forecastBalances = forecast.map(f => f.predicted_balance);

            const allDates      = [...historyDates, ...forecastDates];
            const pastPoints    = [...historyBalances, ...Array(forecastDates.length).fill(null)];
            
            const lastBal       = historyBalances.length > 0 ? historyBalances[historyBalances.length - 1] : 0;
            const futurePoints  = [...Array(historyDates.length > 0 ? historyDates.length - 1 : 0).fill(null), lastBal, ...forecastBalances];

            const datasets = [{
                label: 'Gerçekleşen Bakiye (' + symbol + ')',
                data: pastPoints,
                borderColor: color,
                backgroundColor: color + '1a',
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }];

            if (forecastDates.length > 0) {
                datasets.push({
                    label: 'Yapay Zeka Tahmini (' + symbol + ')',
                    data: futurePoints,
                    borderColor: '#1cc88a',
                    borderWidth: 3,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.3
                });
            }

            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            new Chart(canvas, {
                type: 'line',
                data: { labels: allDates, datasets },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: { y: { beginAtZero: false } }
                }
            });
        }

        function drawPieChart(canvasId, stats) {
            const labels = stats.map(t => t.name);
            const values = stats.map(t => t.total);

            new Chart(document.getElementById(canvasId), {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#e74a3b','#f6c23e','#36b9cc','#4e73df','#1cc88a','#858796','#fd7e14','#6f42c1'],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Grafikleri çiz
        drawCashFlowChart('cashFlowChart-TL',  historyTL,  forecastTL,  'TL');
        drawCashFlowChart('cashFlowChart-USD', historyUSD, forecastUSD, 'USD');
        drawCashFlowChart('cashFlowChart-EUR', historyEUR, forecastEUR, 'EUR');

        // Pasta grafikleri — şimdilik tüm tag stats aynı (hesap seçilmemişse genel)
        drawPieChart('expensePieChart-TL',  tagStats);
        drawPieChart('expensePieChart-USD', tagStats);
        drawPieChart('expensePieChart-EUR', tagStats);
    </script>

</body>
</html>
