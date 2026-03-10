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
        <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">Yeni Personel Ekle ve Yetkilendir</h2>

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

            <h3 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">Özel Yetkiler</h3>

            <div class="mb-6 bg-gray-50 p-4 rounded border">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="can_view_pos" value="1" class="form-checkbox h-5 w-5 text-blue-600">
                    <span class="text-gray-700 font-medium">Bu personel "Sanal POS" sayfalarını görebilir ve ödeme alabilir.</span>
                </label>
            </div>

            <details class="mb-6 border rounded-md group">
                <summary class="font-bold bg-gray-50 p-4 cursor-pointer select-none outline-none group-open:border-b flex justify-between items-center">
                    <span>Hangi Bankaların Hesap Hareketlerini Görebilir?</span>
                    <span class="text-blue-500 text-sm font-normal">Tıkla ve Bankaları Seç ▼</span>
                </summary>
                
                <div class="p-4 bg-white grid grid-cols-1 md:grid-cols-2 gap-4">
                    @forelse($bankalar as $banka)
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer border">
                            <input type="checkbox" name="allowed_banks[]" value="{{ $banka->id }}" class="form-checkbox h-4 w-4 text-blue-600">
                            <span class="text-gray-700">{{ $banka->bank_name }}</span>
                        </label>
                    @empty
                        <div class="col-span-2 text-gray-500 text-sm">Sistemde henüz hiç banka bulunmuyor. Önce bankaları senkronize edin.</div>
                    @endforelse
                </div>
            </details>

            <div class="flex items-center justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-800 text-white font-bold py-2 px-6 rounded shadow-lg transition duration-200">
                    Personeli Kaydet ve Yetkilendir
                </button>
            </div>
        </form>
    </div>

</body>
</html>