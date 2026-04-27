<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiziksel POS Terminalleri | Vomsis Yönetim</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 p-8">

    <div class="max-w-7xl mx-auto">
        <div class="mb-4">
            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-800 text-sm font-bold inline-flex items-center transition-colors bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200">
                <i class="fas fa-arrow-left mr-2"></i> Dashboard'a Dön
            </a>
        </div>

        <div class="flex justify-between items-center mb-8 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-800 flex items-center gap-3">
                    <i class="fas fa-cash-register text-blue-600"></i> Fiziksel POS Terminalleri
                </h2>
                <p class="text-gray-500 mt-2">Mağazalarınızdaki fiziksel pos cihazlarının listesi, komisyon oranları ve durumları.</p>
            </div>
            
            @if(auth()->user()->role === 'admin')
            <a href="{{ route('physical_pos.sync') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-md transition-all flex items-center gap-2">
                <i class="fas fa-sync-alt"></i>
                Vomsis'ten Senkronize Et
            </a>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6 shadow-sm font-medium">
                <i class="fas fa-check-circle mr-2"></i> {!! session('success') !!}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 shadow-sm font-medium">
                <i class="fas fa-exclamation-triangle mr-2"></i> {!! session('error') !!}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Banka Detayı</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">İşyeri Adı / Özel İsim</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">İşyeri No / Terminal No</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Komisyon / Kur</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Cihaz Durumu</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($poses as $pos)
                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 border border-blue-200 rounded-full flex items-center justify-center text-blue-600 font-bold shadow-inner">
                                        {{ strtoupper(substr($pos->bank_title, 0, 1)) }}
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-bold text-gray-900">{{ $pos->bank_title }}</div>
                                        <div class="text-xs text-gray-500 uppercase">{{ $pos->bank_name }}</div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 font-medium">{{ $pos->workplace_name }}</div>
                                <div class="text-xs text-gray-500">{{ $pos->custom_name ?? 'İsimsiz Cihaz' }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">İşyeri: <span class="font-mono bg-gray-100 px-1 rounded">{{ $pos->workplace_no }}</span></div>
                                <div class="text-sm text-gray-500 mt-1">Terminal: <span class="font-mono bg-gray-100 px-1 rounded">{{ $pos->station_no }}</span></div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 font-bold bg-green-50 text-green-700 px-2 py-1 rounded inline-block">
                                    %{{ $pos->commission_rate }}
                                </div>
                                <div class="text-xs font-bold text-gray-500 mt-1 ml-1">{{ $pos->transaction_currency }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($pos->status)
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-green-100 text-green-800 border border-green-200">
                                        <i class="fas fa-check mr-1 mt-0.5"></i> Aktif
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-red-100 text-red-800 border border-red-200">
                                        <i class="fas fa-times mr-1 mt-0.5"></i> Pasif
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2">
                                    
                                    <a href="{{ route('physical_pos.transactions', $pos->id) }}" class="text-gray-500 hover:text-blue-700 bg-gray-100 hover:bg-blue-50 px-3 py-2 rounded transition-colors" title="İşlemleri Görüntüle">
                                         <i class="fas fa-list"></i>
                                    </a>

                                    @if(auth()->user()->role === 'admin' || auth()->user()->can_view_physical_pos)
                                        <a href="{{ route('physical_pos.sync_transactions', $pos->id) }}" class="text-white bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded transition-colors shadow-sm" title="İşlemleri Çek">
                                            <i class="fas fa-cloud-download-alt mr-1"></i> Çek
                                        </a>
                                    @endif
                                    
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-gray-500">
                                <i class="fas fa-cash-register text-5xl mb-4 text-gray-300"></i>
                                <p class="text-xl font-medium text-gray-600">Sistemde henüz kayıtlı Fiziksel POS bulunmuyor.</p>
                                @if(auth()->user()->role === 'admin')
                                    <p class="text-sm mt-2 text-gray-400">Lütfen sağ üstteki <b class="text-blue-500">"Vomsis'ten Senkronize Et"</b> butonuna tıklayarak cihazlarınızı içeri aktarın.</p>
                                @else
                                    <p class="text-sm mt-2 text-gray-400">Görüntülenecek cihaz bulunamadı. Lütfen yöneticinize başvurun.</p>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>