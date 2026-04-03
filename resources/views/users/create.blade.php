<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Ekle | Yönetim</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">

    <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md">
        
        <div class="flex justify-between items-center mb-6 border-b pb-2">
            <h2 class="text-2xl font-bold text-gray-800">Yeni Personel Ekle ve Yetkilendir</h2>
            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-800 text-sm font-bold transition-colors">
                &larr; Dashboard'a Dön
            </a>
        </div>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('users.store') }}" method="POST">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Ad Soyad</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="border rounded w-full py-2 px-3 text-gray-700 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">E-Posta</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="border rounded w-full py-2 px-3 text-gray-700 focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Geçici Şifre Belirle</label>
                    <input type="password" name="password" required class="border rounded w-full py-2 px-3 text-gray-700 focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">Sistem Veri Tipi (Gerçek/Demo)</h3>
            
            <div class="mb-6 bg-blue-50 p-4 rounded border border-blue-200">
                <p class="text-sm text-gray-600 mb-3 font-medium">Bu kullanıcı sisteme girdiğinde hangi verileri kullanacak?</p>
                <div class="flex space-x-6">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="radio" name="is_real_data" value="1" class="form-radio h-5 w-5 text-blue-600 focus:ring-blue-500" required>
                        <span class="text-gray-800 font-bold">1 Yıllık Gerçek Veriler (1)</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="radio" name="is_real_data" value="0" class="form-radio h-5 w-5 text-gray-600 focus:ring-gray-500" required>
                        <span class="text-gray-800 font-bold">Eski Demo Veriler (0)</span>
                    </label>
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">Özel Yetkiler</h3>

            <div class="mb-4 bg-gray-50 p-4 rounded border">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="can_view_pos" value="1" class="form-checkbox h-5 w-5 text-blue-600 rounded focus:ring-blue-500">
                    <span class="text-gray-700 font-medium">Bu personel "Sanal POS" sayfalarını görebilir ve web üzerinden ödeme alabilir.</span>
                </label>
            </div>

            <div class="mb-6 bg-gray-50 p-4 rounded border">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="can_view_physical_pos" value="1" class="form-checkbox h-5 w-5 text-green-600 rounded focus:ring-green-500">
                    <span class="text-gray-700 font-medium">Bu personel "Fiziksel POS" terminallerini (Mağaza Cihazlarını) ve raporlarını görebilir.</span>
                </label>
            </div>

            <div class="mb-6 bg-yellow-50 p-4 rounded border border-yellow-200">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="can_create_tags" value="1" id="can_create_tags" class="form-checkbox h-5 w-5 text-yellow-600 rounded focus:ring-yellow-500">
                    <div>
                        <span class="text-gray-800 font-bold block">🏷️ Sistemde Yeni Etiket Üretebilir</span>
                        <span class="text-gray-500 text-sm block mt-1">Bu yetki verilmezse kullanıcı sadece aşağıdaki listeden ona atadığınız etiketleri görebilir ve kullanabilir.</span>
                    </div>
                </label>
            </div>

            <details class="mb-4 border rounded-md group">
                <summary class="font-bold bg-gray-50 p-4 cursor-pointer select-none outline-none group-open:border-b flex justify-between items-center">
                    <span>Hangi Bankaların Hesap Hareketlerini Görebilir?</span>
                    <span class="text-blue-500 text-sm font-normal">Tıkla ve Bankaları Seç ▼</span>
                </summary>
                
                <div class="p-4 bg-white">
                    @if($bankalar->count() > 0)
                        <div class="mb-4 pb-4 border-b border-gray-100">
                            <label class="flex items-center space-x-3 p-3 bg-blue-50 hover:bg-blue-100 rounded-lg cursor-pointer border border-blue-200 transition-colors">
                                <input type="checkbox" id="selectAllBanks" class="form-checkbox h-5 w-5 text-blue-700 rounded focus:ring-blue-500">
                                <span class="text-blue-800 font-bold">Tüm Bankaları Seç / Kaldır</span>
                            </label>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($bankalar as $banka)
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer border">
                                <input type="checkbox" name="allowed_banks[]" value="{{ $banka->id }}" class="bank-checkbox form-checkbox h-4 w-4 text-blue-600">
                                <span class="text-gray-700">{{ $banka->bank_name }}</span>
                            </label>
                        @empty
                            <div class="col-span-2 text-gray-500 text-sm">Sistemde henüz hiç banka bulunmuyor. Önce bankaları senkronize edin.</div>
                        @endforelse
                    </div>
                </div>
            </details>

            <details class="mb-6 border rounded-md group">
                <summary class="font-bold bg-gray-50 p-4 cursor-pointer select-none outline-none group-open:border-b flex justify-between items-center">
                    <span>Hangi Etiketleri Görebilir ve Kullanabilir?</span>
                    <span class="text-blue-500 text-sm font-normal">Tıkla ve Etiketleri Seç ▼</span>
                </summary>
                
                <div class="p-4 bg-white">
                    @if(isset($tags) && $tags->count() > 0)
                        <div class="mb-4 pb-4 border-b border-gray-100">
                            <label class="flex items-center space-x-3 p-3 bg-blue-50 hover:bg-blue-100 rounded-lg cursor-pointer border border-blue-200 transition-colors">
                                <input type="checkbox" id="selectAllTags" class="form-checkbox h-5 w-5 text-blue-700 rounded focus:ring-blue-500">
                                <span class="text-blue-800 font-bold">Tüm Etiketleri Seç / Kaldır</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($tags as $tag)
                                <label class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded cursor-pointer border transition-colors">
                                    <input type="checkbox" name="allowed_tags[]" value="{{ $tag->id }}" class="tag-checkbox form-checkbox h-4 w-4 text-blue-600">
                                    <span class="px-2 py-1 text-xs font-bold rounded shadow-sm text-white" style="background-color: {{ $tag->color }};">
                                        {{ $tag->name }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="text-gray-500 text-sm p-2">Sistemde henüz oluşturulmuş hiçbir etiket bulunmuyor.</div>
                    @endif
                </div>
            </details>

            <div class="flex items-center justify-end border-t pt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-800 text-white font-bold py-3 px-8 rounded shadow-lg transition duration-200">
                    Kullanıcıyı Kaydet ve Yetkilendir
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // BANKA SEÇİMLERİ İÇİN JAVASCRIPT
            const selectAllBanks = document.getElementById('selectAllBanks');
            const bankCheckboxes = document.querySelectorAll('.bank-checkbox');

            if (selectAllBanks) {
                selectAllBanks.addEventListener('change', function() {
                    bankCheckboxes.forEach(checkbox => checkbox.checked = selectAllBanks.checked);
                });

                bankCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(bankCheckboxes).every(c => c.checked);
                        selectAllBanks.checked = allChecked;
                    });
                });
            }

            // ETİKET SEÇİMLERİ İÇİN JAVASCRIPT
            const selectAllTags = document.getElementById('selectAllTags');
            const tagCheckboxes = document.querySelectorAll('.tag-checkbox');
            
            // Eğer yeni etiket üretebilir tıklandıysa, alttaki tüm etiketleri de otomatik seçme efekti (Opsiyonel konfor)
            const canCreateTagsBtn = document.getElementById('can_create_tags');

            if (selectAllTags) {
                selectAllTags.addEventListener('change', function() {
                    tagCheckboxes.forEach(checkbox => checkbox.checked = selectAllTags.checked);
                });

                tagCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(tagCheckboxes).every(c => c.checked);
                        selectAllTags.checked = allChecked;
                    });
                });
            }

            if(canCreateTagsBtn && selectAllTags) {
                canCreateTagsBtn.addEventListener('change', function() {
                    if(this.checked) {
                        selectAllTags.checked = true;
                        tagCheckboxes.forEach(checkbox => checkbox.checked = true);
                    }
                });
            }
        });
    </script>
</body>
</html>