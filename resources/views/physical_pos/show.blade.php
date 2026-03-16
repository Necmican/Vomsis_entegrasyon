<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pos->bank_title }} İşlemleri | Vomsis Yönetim</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 p-8">

    <div class="max-w-7xl mx-auto">
        <div class="mb-6 flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <div>
                <a href="{{ route('physical_pos.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-bold mb-2 inline-block transition-colors">
                    <i class="fas fa-arrow-left mr-1"></i> Cihaz Listesine Dön
                </a>
                <h2 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2 mt-1">
                    <span class="bg-blue-100 text-blue-700 w-10 h-10 rounded-full flex items-center justify-center text-lg">
                        {{ strtoupper(substr($pos->bank_title, 0, 1)) }}
                    </span>
                    {{ $pos->bank_title }} Cihazı İşlem Geçmişi
                </h2>
                <div class="text-gray-500 text-sm mt-2 ml-12">
                    <span class="mr-3"><i class="fas fa-store mr-1"></i> İşyeri: <b>{{ $pos->workplace_no }}</b></span>
                    <span><i class="fas fa-hashtag mr-1"></i> Terminal: <b>{{ $pos->station_no }}</b></span>
                </div>
            </div>
            
            <div class="text-right">
                <span class="block text-sm text-gray-500 mb-1">Cihaz Komisyonu</span>
                <span class="bg-green-100 text-green-800 font-bold px-3 py-1 rounded text-lg">%{{ $pos->commission_rate }}</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">İşlem / Valör Tarihi</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Kart & Taksit</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Detay (Prov / Ref)</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Brüt Tutar</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Komisyon</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Net Bakiye</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($transactions as $tx)
                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">{{ $tx->system_date ? $tx->system_date->format('d.m.Y H:i') : '-' }}</div>
                                <div class="text-xs text-gray-500 mt-1">Valör: <span class="font-bold">{{ $tx->valor ?? '-' }}</span></div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900 font-mono">{{ $tx->card_number ?? 'Bilinmiyor' }}</div>
                                <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                                    <span class="bg-gray-100 px-2 py-0.5 rounded">{{ $tx->card_type ?? 'KART' }}</span>
                                    @if($tx->installments_count > 0)
                                        <span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded font-bold">{{ $tx->installments_count }} Taksit</span>
                                    @else
                                        <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded">Peşin</span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 font-medium">{{ $tx->description ?? $tx->transaction_type }}</div>
                                <div class="text-xs text-gray-400 mt-1 font-mono">P: {{ $tx->provision_no ?? '-' }} | R: {{ $tx->reference_number ?? '-' }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-bold text-gray-600">{{ number_format($tx->gross_amount, 2, ',', '.') }} {{ $tx->exchange }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-bold text-red-500">-{{ number_format($tx->commission, 2, ',', '.') }} {{ $tx->exchange }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-base font-extrabold text-green-600 bg-green-50 px-3 py-1 rounded inline-block">
                                    {{ number_format($tx->net_amount, 2, ',', '.') }} {{ $tx->exchange }}
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-gray-500">
                                <i class="fas fa-receipt text-5xl mb-4 text-gray-300"></i>
                                <p class="text-xl font-medium text-gray-600">Bu cihaza ait hiç işlem bulunamadı.</p>
                                <p class="text-sm mt-2 text-gray-400">Ana sayfaya dönüp "Çek" butonuna basarak verileri senkronize edebilirsiniz.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($transactions->hasPages())
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                {{ $transactions->links() }}
            </div>
            @endif
        </div>
    </div>

</body>
</html>