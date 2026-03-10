<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VirtualPos;
use App\Models\PosTransaction; 
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    /**
     * 1. VİTRİNİ GÖSTEREN FONKSİYON (Ödeme Ekranı)
     */
    public function index()
    { 
        // GÜVENLİK DUVARI: Sanal POS yetkisi var mı?
        if (!auth()->user()->can_view_pos) {
            return redirect()->route('dashboard')->with('error', 'Sanal POS işlemlerini görüntüleme yetkiniz bulunmamaktadır.');
        }

        $activePos = VirtualPos::where('is_active', true)->first();
        return view('payment.index', compact('activePos'));
    }

    /**
     * 2. SANAL POS İŞLEMLERİ LİSTESİ
     */
    public function transactionsList()
    {
        // GÜVENLİK DUVARI
        if (!auth()->user()->can_view_pos) {
            return redirect()->route('dashboard')->with('error', 'Sanal POS raporlarını görüntüleme yetkiniz bulunmamaktadır.');
        }

        // Artık sadece senin Sanal POS tablonu çekiyoruz! Karmaşa bitti.
        $islemler = PosTransaction::orderBy('transaction_date', 'desc')->paginate(10);

        return view('payment.list', compact('islemler'));
    }

    /**
     * 3. BİN CHECK FONKSİYONU (Kartın Bankasını ve Taksitleri Bulur)
     */
    public function binCheck(Request $request)
    {
        // GÜVENLİK DUVARI: Arka plandan (AJAX) gelen yetkisiz istekleri engelle
        if (!auth()->user()->can_view_pos) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sanal POS sorgulama yetkiniz bulunmamaktadır.'
            ], 403);
        }

        // 1. Sadece 6 haneli rakam gelmesini garantiye alıyoruz
        $request->validate([
            'cc_number' => 'required|numeric|digits:6',
        ]);

        // 2. Vomsis Token'ı Akıllı Zekadan Çekiyoruz
        $token = $this->getVomsisToken();

        if (!$token) {
            $hata = Cache::get('vomsis_token_error', 'Bilinmeyen Hata');
            return response()->json([
                'status' => 'error', 
                'message' => 'Token Alınamadı. Detay: ' . $hata
            ]);
        }

        // 3. Vomsis Sanal POS API'sine İstek Atıyoruz
        $response = Http::withToken($token)
            ->acceptJson() 
            ->post('https://uygulama.vomsis.com/api/vpos/v3/bin-check', [
                'cc_number' => (int) $request->cc_number // Dokümandaki gibi zorunlu Integer
            ]);

        // 4. HAM VERİYİ VE HTTP KODUNU ALIYORUZ
        $httpKodu = $response->status(); 
        $hamCevap = $response->body();   

        // Vomsis'ten 200 (Başarılı) dönmediyse:
        if (!$response->successful() || empty($hamCevap)) {
            return response()->json([
                'status' => 'error',
                'message' => "Vomsis API Hatası -> HTTP: {$httpKodu} | Mesaj: {$hamCevap}"
            ]);
        }

        // 5. Gelen Kusursuz Yanıtı JSON'a Çeviriyoruz
        $vomsisCevap = $response->json();

        // Eğer JSON formatı bozuksa:
        if (is_null($vomsisCevap)) {
            return response()->json([
                'status' => 'error',
                'message' => "Veri Okunamadı. Ham metin: {$hamCevap}"
            ]);
        }

        // Vomsis "data" alanını göndermediyse (Kart bulunamadı vs.):
        if (!isset($vomsisCevap['data']) || is_null($vomsisCevap['data'])) {
            return response()->json([
                'status' => 'error',
                'message' => isset($vomsisCevap['message']) ? $vomsisCevap['message'] : "Banka bilgisi bulunamadı."
            ]);
        }

        // 6. ZAFER: Her şey kusursuz, banka ve taksit bilgilerini UI'a (Önyüze) gönder!
        return response()->json([
            'status' => 'success',
            'data' => $vomsisCevap['data']
        ]);
    }

    /**
     * 4. Müşteriden gelen kart bilgileriyle 3D Secure işlemini başlatır
     */
    public function process(Request $request)
    {
        // GÜVENLİK DUVARI: Ödeme yetkisi kontrolü
        if (!auth()->user()->can_view_pos) {
            return redirect()->route('dashboard')->with('error', 'Sanal POS üzerinden ödeme alma yetkiniz bulunmamaktadır.');
        }

        // 1. Gelen Verileri Doğrula
        $request->validate([
            'card_name'    => 'required|string',
            'card_number'  => 'required|numeric|digits:16',
            'expire_month' => 'required|numeric|digits:2',
            'expire_year'  => 'required|numeric|digits:2',
            'cvv'          => 'required|numeric|digits_between:3,4',
            'amount'       => 'required|numeric|min:1',
        ]);

        $token = $this->getVomsisToken();

        if (!$token) {
            return back()->withErrors(['Bağlantı Hatası' => 'Ödeme altyapısına ulaşılamıyor.']);
        }

        // 2. Sipariş No Üret ve Kartı Maskele
        $orderId = 'VOM-' . strtoupper(Str::random(8));
        $cardNumber = $request->card_number;
        $cardMask = substr($cardNumber, 0, 6) . '******' . substr($cardNumber, -4);

        // --- VERİTABANI HATASINI OTOMATİK ÇÖZEN KISIM ---
        $virtualPos = \App\Models\VirtualPos::first();
        
        if (!$virtualPos) {
            $banka = \App\Models\Bank::first();
            $virtualPos = \App\Models\VirtualPos::forceCreate([
                'bank_id'     => $banka ? $banka->id : 1, 
                'name'        => 'Vomsis Sistem POS', // İŞTE YENİ BULUNAN ZORUNLU SÜTUN!
                'merchant_id' => '0', 
                'api_key'     => '0', 
                'is_active'   => true
            ]);
        }

        // 3. Veritabanına İşlemi Kaydet
        \App\Models\PosTransaction::create([
            'virtual_pos_id'   => $virtualPos->id, // Hata vermemesi için ekledik
            'order_id'         => $orderId,
            'amount'           => $request->amount,
            'currency'         => 'TL',
            'installments'     => $request->installment ?? 1,
            'card_mask'        => $cardMask,
            'status'           => 'pending', 
            'response_code'    => '3D_WAIT', 
            'transaction_date' => now(),
            'description'      => 'Müşteri 3D Secure onay ekranına yönlendirildi.'
        ]);

        // 4. VOMSİS DOKÜMANTASYONUNA BİREBİR UYGUN PAYLOAD
        $payload = [
            'referanceNo'           => $orderId,
            'creditCardHolderName'  => $request->card_name,
            'creditCardPan'         => $request->card_number,
            'creditCardExpiryMonth' => $request->expire_month,
            'creditCardExpiryYear'  => $request->expire_year,
            'creditCardCvc'         => $request->cvv,
            'installment'           => (int) ($request->installment ?? 1),
            'amount'                => (float) $request->amount,
            'currency'              => 'TRY',
            'returnUrl'             => route('payment.callback'), // Dönüş adresimiz
            'clientIp'              => $request->ip() === '127.0.0.1' ? '8.8.8.8' : $request->ip(), // Lokal testi bozmaması için IP hilesi eklendi!
            'securePayment'         => true, // 3D Secure istiyoruz
        ];

        // 5. Dokümandaki URL'ye İstek Atıyoruz
        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://uygulama.vomsis.com/api/vpos/v3/payment', $payload); // Endpoint güncellendi

        // 6. Yanıtı İşle (Dokümanda yazan htmlContent veya gateway dönüşü)
        if ($response->successful()) {
            $responseData = $response->json();

            // Doküman Örnek 3: Hazır HTML Form dönüyorsa direkt ekrana bas
            if (isset($responseData['htmlContent'])) {
                return $responseData['htmlContent'];
            }
            
            // Doküman Örnek 2: Gateway ve Inputlar dönüyorsa, otomatik submit olan bir form yarat
            if (isset($responseData['gateway']) && isset($responseData['inputs'])) {
                $html = '<form id="vomsis3dForm" action="'.$responseData['gateway'].'" method="POST">';
                foreach ($responseData['inputs'] as $key => $value) {
                    $html .= '<input type="hidden" name="'.$key.'" value="'.$value.'">';
                }
                $html .= '</form><script>document.getElementById("vomsis3dForm").submit();</script>';
                return $html;
            }

            return back()->withErrors(['Hata' => 'Banka yönlendirme verisi alınamadı. Yanıt: ' . json_encode($responseData)]);
        }

        return back()->withErrors([
            'API Hatası' => 'HTTP ' . $response->status() . ' | Vomsis: ' . $response->body()
        ]);
    }

    /**
     * 5. BANKADAN/VOMSİS'TEN DÖNEN SONUCU KARŞILAMA (CALLBACK)
     */
    public function callback(Request $request)
    {
        // GÜVENLİK DUVARI: 3D Secure dönüşünde de yetki kontrolü yapalım (Kullanıcının tarayıcısı üzerinden post ediliyor)
        if (auth()->check() && !auth()->user()->can_view_pos) {
            return redirect()->route('dashboard')->with('error', 'Bu işlemi tamamlama yetkiniz bulunmamaktadır.');
        }

        \Log::info('Vomsis 3D Dönüşü (Callback) Geldi:', $request->all());

        // 2. Vomsis'in Bize Gönderdiği Parametreleri Çekiyoruz
        $orderId      = $request->input('order_id'); // Bizim ürettiğimiz VOM-XXXXXX numarası
        $status       = $request->input('status'); // İşlem durumu: 'success' veya 'failed'
        $responseCode = $request->input('response_code'); // Banka dönüş kodu
        
        $errorMessage = $request->input('error_message') ?? $request->input('message') ?? 'Bilinmeyen Banka Reddi';

        // YENİ MİMARİ: İşlemi vomsis_transaction_id sütunundan arıyoruz
       $transaction = PosTransaction::where('order_id', $orderId)->first();

        if (!$transaction) {
            \Log::warning('Kayıp Sipariş Numarası Geldi: ' . $orderId);
            return redirect('/odeme-yap')->withErrors(['Kritik Hata' => 'Sistemde böyle bir işlem bulunamadı.']);
        }

        // 4. EĞER PARA BAŞARIYLA ÇEKİLDİYSE!
        if ($status === 'success' || $status === 'approved') {
            
            // Veritabanını 'Başarılı' olarak mühürle
            $transaction->update([
                'type'             => 'POS_SUCCESS',
                'description'   => '3D SMS Onayı alındı, ödeme başarıyla tahsil edildi. (Sipariş: '.$orderId.')'
            ]);

            // Müşteriye o tatlı yeşil başarı mesajını göster
            return redirect('/odeme-yap')->with('mesaj', "Harika! Ödemeniz başarıyla alındı. Çekilen Tutar: " . number_format($transaction->amount, 2) . " TL. (Sipariş No: {$orderId})");
            
        } 
        
        // 5. EĞER PARA ÇEKİLEMEDİYSE (Limit yetersiz, SMS yanlış vb.)
        else {
            // Veritabanını 'Hata' olarak mühürle
            $transaction->update([
                'type'             => 'POS_FAILED',
                'description'   => 'İşlem Başarısız. Banka Mesajı: ' . $errorMessage . ' (Sipariş: '.$orderId.')'
            ]);

            // Müşteriye kırmızı hata mesajını göster ve NEYİ YANLIŞ YAPTIĞINI söyle
            return redirect('/odeme-yap')->withErrors(['Ödeme Reddedildi' => "İşlem banka tarafından onaylanmadı. Sebep: " . $errorMessage]);
        }
    }

    /**
     * Vomsis Yetkilendirme (Token) İşlemi
     */
    private function getVomsisToken()
    {
        // 1. Hafızada (Cache) token varsa onu kullan
        if (Cache::has('vomsis_api_token')) {
            return Cache::get('vomsis_api_token');
        }

        // 2. Vomsis'in doğru çalıştığı v2 servisine APP_KEY ile gidiyoruz!
        $response = Http::post('https://developers.vomsis.com/api/v2/authenticate', [
            'app_key'    => env('VOMSIS_APP_KEY'),
            'app_secret' => env('VOMSIS_APP_SECRET')
        ]);

        if ($response->successful()) {
            $token = $response->json('token'); 
            
            if ($token) {
                Cache::put('vomsis_api_token', $token, now()->addHours(23));
                return $token;
            }
        }

        // Hata durumunda (Örn: IP izni yoksa vs.) logluyoruz
        Cache::put('vomsis_token_error', "HTTP: " . $response->status() . " | " . $response->body(), 60);
        return null;
    }
}