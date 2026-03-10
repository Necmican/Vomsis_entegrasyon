<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sisteme Giriş | Vomsis Yönetim</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 flex items-center justify-center h-screen">

    <div class="bg-white p-10 rounded-xl shadow-lg w-full max-w-md border border-gray-100">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold text-gray-800">🏢 Vomsis FinTech</h2>
            <p class="text-gray-500 mt-2 text-sm">Yönetim Paneline Giriş Yapın</p>
        </div>

        @if ($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
                <ul class="list-disc pl-5 text-sm font-medium">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf
            
            <div class="mb-5">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">E-Posta Adresi</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                       class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all">
            </div>

            <div class="mb-8">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Şifre</label>
                <input type="password" name="password" id="password" required
                       class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition-colors duration-200">
                Sisteme Giriş Yap
            </button>
        </form>
    </div>

</body>
</html>