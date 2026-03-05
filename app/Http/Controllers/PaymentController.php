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
    // 1. VİTRİNİ GÖSTEREN FONKSİYON
    public function index()
    {
        
        $activePos = VirtualPos::where('is_active', true)->first();

        
        if (!$activePos) {
            return redirect('/dashboard')->with('error', 'Sistemde aktif bir Sanal POS bulunamadı!');
        }

        return view('payment.index', compact('activePos'));
    }

    public function process(Request $request)
    {
        // 1. Gelen Verileri Doğrula
        $request->validate([
            'card_name'    => 'required|string',
            'card_number'  => 'required|numeric|digits:16',
            'expire_month' => 'required|numeric|digits:2',
            'expire_year'  => 'required|numeric|digits:2',
            'cvv'          => 'required|numeric|digits_between:3,4',
            'amount'       => 'required|numeric|min:1',
        ]);

        // 2. Vomsis Token'ı Al
        $token = $this->getVomsisToken();

        if (!$token) {
            return back()->withErrors(['Bağlantı Hatası' => 'Ödeme altyapısına ulaşılamıyor.']);
        }

        // 3. Benzersiz Sipariş Numarası Üret
        $orderId = 'VOM-' . strtoupper(Str::random(8));

        // 4. MÜŞTERİNİN KARTINI MASKELE (Senin Harika Tespitin!)
        $cardNumber = $request->card_number;
        $cardMask = substr($cardNumber, 0, 6) . '******' . substr($cardNumber, -4);

        PosTransaction::create([
            'virtual_pos_id'   => $request->virtual_pos_id,
            'order_id'         => $orderId,
            'amount'           => $request->amount,
            'currency'         => 'TL',
            'installments'     => $request->installment ?? 1,
            'card_mask'        => $cardMask,
            'status'           => 'pending', // DURUM: BEKLİYOR
            'response_code'    => '3D_WAIT', 
            'transaction_date' => now(),
            'description'      => 'Müşteri 3D Secure onay ekranına yönlendirildi.'
        ]);

        // 6. Vomsis 3D Secure API'sine Gönderilecek Paketi Hazırla
        $payload = [
            'amount' => (string) $request->amount,
            'currency' => 'TL',
            'installment' => $request->installment ?? "1", 
            'order_id' => $orderId,
            'card_name' => $request->card_name,
            'card_number' => $request->card_number,
            'card_month' => $request->expire_month,
            'card_year' => $request->expire_year,
            'card_cvv' => $request->cvv,
            
            // İşlem bitince (başarılı veya başarısız) bizi bu rotaya geri fırlatacak
            'success_url' => route('payment.callback'), 
            'fail_url' => route('payment.callback'),
        ];

        // 7. Vomsis'e İstek Atıyoruz
        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://uygulama.vomsis.com/api/vpos/v3/payment/3d', $payload);

        // 8. Gelen Cevabı Analiz Et ve Müşteriyi Bankaya Fırlat!
        if ($response->successful()) {
            $responseData = $response->json();

            // Vomsis'in gönderdiği Banka HTML formunu ekrana basıyoruz
            if (isset($responseData['data']['3d_html'])) {
                return $responseData['data']['3d_html'];
            }
            
            return back()->withErrors(['Hata' => 'Banka yönlendirme verisi alınamadı.']);
        }

        return back()->withErrors([
        'API Hatası' => 'HTTP ' . $response->status() . ' | Vomsis: ' . $response->body()
    ]);
    }

    public function transactionsList()
    {
    
        $islemler = PosTransaction::with('virtualPos')->orderBy('created_at', 'desc')->paginate(10);

        return view('payment.list', compact('islemler'));
    }

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

    /**
     * BİN CHECK FONKSİYONU (TAM VE KUSURSUZ HALİ)
     */
    public function binCheck(Request $request)
    {
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

        // 4. HAM VERİYİ VE HTTP KODUNU ALIYORUZ (Hata ayıklama kalkanımız)
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
                // Eğer Vomsis'in kendi hata mesajı ("message") varsa onu göster, yoksa ham cevabı bas
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
     * ADIM 2: BANKADAN/VOMSİS'TEN DÖNEN SONUCU KARŞILAMA (CALLBACK)
     */
    public function callback(Request $request)
    {
      
        \Log::info('Vomsis 3D Dönüşü (Callback) Geldi:', $request->all());

        // 2. Vomsis'in Bize Gönderdiği Parametreleri Çekiyoruz
        $orderId      = $request->input('order_id'); // Bizim ürettiğimiz VOM-XXXXXX numarası
        $status       = $request->input('status'); // İşlem durumu: 'success' veya 'failed'
        $responseCode = $request->input('response_code'); // Banka dönüş kodu (Örn: 00 Başarılı, 51 Yetersiz Bakiye)
        
        
        $errorMessage = $request->input('error_message') ?? $request->input('message') ?? 'Bilinmeyen Banka Reddi';

        // 3. VESTİYER MANTIĞI: Veritabanımızdan bu işlemi buluyoruz
        $transaction = PosTransaction::where('order_id', $orderId)->first();

       
        if (!$transaction) {
            \Log::warning('Kayıp Sipariş Numarası Geldi: ' . $orderId);
            return redirect('/odeme-yap')->withErrors(['Kritik Hata' => 'Sistemde böyle bir işlem bulunamadı.']);
        }

        // 4. EĞER PARA BAŞARIYLA ÇEKİLDİYSE!
        if ($status === 'success' || $status === 'approved') {
            
            // Veritabanını 'Başarılı' olarak mühürle
            $transaction->update([
                'status'        => 'success',
                'response_code' => $responseCode ?? '00',
                'description'   => '3D SMS Onayı alındı, ödeme başarıyla tahsil edildi.'
            ]);

            // Müşteriye o tatlı yeşil başarı mesajını göster
            return redirect('/odeme-yap')->with('mesaj', "Harika! Ödemeniz başarıyla alındı. Çekilen Tutar: " . number_format($transaction->amount, 2) . " TL. (Sipariş No: {$orderId})");
            
        } 
        
        // 5. EĞER PARA ÇEKİLEMEDİYSE (Limit yetersiz, SMS yanlış vb.)
        else {
            
            // Veritabanını 'Hata' olarak mühürle
            $transaction->update([
                'status'        => 'failed',
                'response_code' => $responseCode ?? 'ERR',
                'description'   => 'İşlem Başarısız. Banka Mesajı: ' . $errorMessage
            ]);

            // Müşteriye kırmızı hata mesajını göster ve NEYİ YANLIŞ YAPTIĞINI söyle
            return redirect('/odeme-yap')->withErrors(['Ödeme Reddedildi' => "İşlem banka tarafından onaylanmadı. Sebep: " . $errorMessage]);
        }
    }
    
    
    
    
    
    
    }