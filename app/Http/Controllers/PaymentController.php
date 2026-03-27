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
        if (!auth()->user()->can_view_pos) {
            return redirect()->route('dashboard')->with('error', 'Sanal POS raporlarını görüntüleme yetkiniz bulunmamaktadır.');
        }

        $islemler = PosTransaction::orderBy('transaction_date', 'desc')->paginate(10);
        return view('payment.list', compact('islemler'));
    }

    /**
     * 3. BİN CHECK FONKSİYONU (Kartın Bankasını ve Taksitleri Bulur)
     */
    public function binCheck(Request $request)
    {
        if (!auth()->user()->can_view_pos) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sanal POS sorgulama yetkiniz bulunmamaktadır.'
            ], 403);
        }

        $request->validate([
            'cc_number' => 'required|numeric|digits:6',
        ]);

        $vomsisService = app(\App\Services\VomsisService::class);
        $token = $vomsisService->getPosToken();

        if (!$token) {
            $hata = Cache::get('vomsis_token_error', 'Bilinmeyen Hata');
            return response()->json([
                'status' => 'error', 
                'message' => 'Token Alınamadı. Detay: ' . $hata
            ]);
        }

        $response = Http::withToken($token)
            ->acceptJson() 
            ->post('https://uygulama.vomsis.com/api/vpos/v3/bin-check', [
                'cc_number' => (int) $request->cc_number,
            ]);

        $httpKodu = $response->status(); 
        $hamCevap = $response->body();   

        if (!$response->successful() || empty($hamCevap)) {
            return response()->json([
                'status' => 'error',
                'message' => "Vomsis API Hatası -> HTTP: {$httpKodu} | Mesaj: {$hamCevap}"
            ]);
        }

        $vomsisCevap = $response->json();

        if (is_null($vomsisCevap)) {
            return response()->json([
                'status' => 'error',
                'message' => "Veri Okunamadı. Ham metin: {$hamCevap}"
            ]);
        }

        if (!isset($vomsisCevap['data']) || is_null($vomsisCevap['data'])) {
            return response()->json([
                'status' => 'error',
                'message' => isset($vomsisCevap['message']) ? $vomsisCevap['message'] : "Banka bilgisi bulunamadı."
            ]);
        }

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
        if (!auth()->user()->can_view_pos) {
            return redirect()->route('dashboard')->with('error', 'Sanal POS üzerinden ödeme alma yetkiniz bulunmamaktadır.');
        }

        $request->validate([
            'card_name'    => 'required|string',
            'card_number'  => 'required|numeric|digits:16',
            'expire_month' => 'required|numeric|digits:2',
            'expire_year'  => 'required|numeric|digits:4',
            'cvv'          => 'required|numeric|digits_between:3,4',
            'amount'       => 'required|numeric|min:1',
        ]);

        $vomsisService = app(\App\Services\VomsisService::class);
        $token = $vomsisService->getPosToken();

        if (!$token) {
            return back()->withErrors(['Bağlantı Hatası' => 'Ödeme altyapısına ulaşılamıyor.']);
        }

        $orderId = 'VOM-' . strtoupper(Str::random(8));
        $cardNumber = $request->card_number;
        $cardMask = substr($cardNumber, 0, 6) . '******' . substr($cardNumber, -4);

        $virtualPos = \App\Models\VirtualPos::first();
        
        if (!$virtualPos) {
            $banka = \App\Models\Bank::first();
            $virtualPos = \App\Models\VirtualPos::forceCreate([
                'bank_id'     => $banka ? $banka->id : 1, 
                'name'        => 'Vomsis Sistem POS', 
                'merchant_id' => '0', 
                'api_key'     => '0', 
                'is_active'   => true
            ]);
        }

        // --- DESCRIPTION SÜTUNU BURADAN KALDIRILDI ---
        \App\Models\PosTransaction::create([
            'virtual_pos_id'   => $virtualPos->id, 
            'order_id'         => $orderId,
            'amount'           => $request->amount,
            'currency'         => 'TL',
            'installments'     => $request->installment ?? 1,
            'card_mask'        => $cardMask,
            'status'           => 'pending', 
            'response_code'    => '3D_WAIT', 
            'transaction_date' => now()
        ]);

        $payload = [
            'referanceNo'           => $orderId,
            'creditCardHolderName'  => $request->card_name,
            'creditCardPan'         => $request->card_number,
            'creditCardExpiryMonth' => $request->expire_month,
            'creditCardExpiryYear'  => $request->expire_year,
            'creditCardCvc'         => $request->cvv,
            'installment'           => (int) ($request->installment ?? 1),
            'installment_ratio'     => $request->filled('installment_ratio') ? (float) $request->installment_ratio : null,
            'amount'                => (float) $request->amount,
            'currency'              => 'TRY',
            'returnUrl'             => route('payment.callback'), 
            'clientIp'              => $request->ip() === '127.0.0.1' ? '8.8.8.8' : $request->ip(), 
            'securePayment'         => true, 
        ];

        if ($payload['installment_ratio'] === null) {
            unset($payload['installment_ratio']);
        }

        $maskedPayloadForLog = $payload;
        $maskedPayloadForLog['creditCardPan'] = $cardMask;
        $maskedPayloadForLog['creditCardCvc'] = '***';

        \Log::info('Vomsis VPOS Payment Request', [
            'order_id' => $orderId,
            'payload' => $maskedPayloadForLog,
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post('https://uygulama.vomsis.com/api/vpos/v3/payment', $payload);
            
        if ($response->successful()) {
            $responseData = $response->json();

            \Log::info('Vomsis VPOS Payment Response', [
                'order_id' => $orderId,
                'http_status' => $response->status(),
                'response' => $responseData,
            ]);

            $otomatikFormUret = function($gateway, $inputs) {
                $html = '<form id="vomsis3dForm" action="'.$gateway.'" method="POST">';
                foreach ($inputs as $key => $value) {
                    $html .= '<input type="hidden" name="'.$key.'" value="'.$value.'">';
                }
                $html .= '</form><script>document.getElementById("vomsis3dForm").submit();</script>';
                return $html;
            };

            // YAKALAYICI 1
            if (isset($responseData['formData'])) {
                if (isset($responseData['formData']['htmlContent'])) {
                    return $responseData['formData']['htmlContent'];
                }
                if (isset($responseData['formData']['gateway']) && isset($responseData['formData']['inputs'])) {
                    return $otomatikFormUret($responseData['formData']['gateway'], $responseData['formData']['inputs']);
                }
            }

            // YAKALAYICI 2
            if (isset($responseData['htmlContent'])) {
                return $responseData['htmlContent'];
            }
            if (isset($responseData['gateway']) && isset($responseData['inputs'])) {
                return $otomatikFormUret($responseData['gateway'], $responseData['inputs']);
            }

            // YAKALAYICI 3
            if (isset($responseData['status']) && $responseData['status'] === true && !empty($responseData['data'])) {
                return $responseData['data'];
            }

            // İSTİSNA (NULL DATA) --- DESCRIPTION KALDIRILDI ---
            if (isset($responseData['status']) && $responseData['status'] === true && array_key_exists('data', $responseData) && is_null($responseData['data'])) {
                 // NULL data durumunda işlemi Vomsis'ten tekrar sorgulayarak root-cause yakalamaya çalış
                 try {
                     $findResponse = Http::withToken($token)
                         ->acceptJson()
                         ->get('https://uygulama.vomsis.com/api/vpos/v3/transaction/find', [
                             'referanceNo' => $orderId,
                         ]);

                     \Log::warning('Vomsis VPOS Payment NULL_DATA; transaction/find response', [
                         'order_id' => $orderId,
                         'http_status' => $findResponse->status(),
                         'response' => $findResponse->json(),
                         'raw' => $findResponse->body(),
                     ]);
                 } catch (\Throwable $e) {
                     \Log::error('Vomsis VPOS transaction/find failed after NULL_DATA', [
                         'order_id' => $orderId,
                         'error' => $e->getMessage(),
                     ]);
                 }

                 $msg = $responseData['message'] ?? 'Vomsis bankadan 3D yönlendirme verisi döndürmedi (data: null).';
                 \App\Models\PosTransaction::where('order_id', $orderId)->update([
                     'status' => 'failed',
                     'response_code' => 'NULL_DATA',
                     'error_message' => $msg,
                     'description' => $msg,
                 ]);
                 return back()->withErrors(['3D Hatası' => $msg]);
            }

            // REDDEDİLME DURUMU --- DESCRIPTION KALDIRILDI ---
            if (isset($responseData['status']) && $responseData['status'] === false) {
                 $hataMesaji = $responseData['message'] ?? 'İşlem banka tarafından reddedildi.';
                 \App\Models\PosTransaction::where('order_id', $orderId)->update([
                     'status' => 'failed',
                     'response_code' => 'REJECTED'
                 ]);
                 return back()->withErrors(['Ödeme Hatası' => $hataMesaji]);
            }

            return back()->withErrors(['Hata' => 'Banka yönlendirme verisi anlaşılamadı. Yanıt: ' . json_encode($responseData)]);
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
        \Log::info('Vomsis 3D Dönüşü (Callback) Geldi:', $request->all());

        // Banka/ACS/Vomsis farklı alan adları gönderebilir; olabildiğince esnek yakala
        $orderId = $request->input('order_id')
            ?? $request->input('referanceNo')
            ?? $request->input('referenceNo')
            ?? $request->input('referans_kodu')
            ?? $request->input('orderId');

        $status = $request->input('status')
            ?? $request->input('transaction_status')
            ?? $request->input('result')
            ?? $request->input('success');

        $responseCode = $request->input('response_code')
            ?? $request->input('procReturnCode')
            ?? $request->input('mdStatus')
            ?? $request->input('Response')
            ?? $request->input('respCode');

        $errorMessage = $request->input('error_message')
            ?? $request->input('message')
            ?? $request->input('ErrMsg')
            ?? $request->input('ERROR_MESSAGE')
            ?? 'Bilinmeyen Banka Reddi';

        $transaction = PosTransaction::where('order_id', $orderId)->first();

        if (!$transaction) {
            \Log::warning('Kayıp Sipariş Numarası Geldi: ' . $orderId);
            return redirect('/odeme-yap')->withErrors(['Kritik Hata' => 'Sistemde böyle bir işlem bulunamadı.']);
        }

        $normalizedStatus = is_string($status) ? strtolower($status) : $status;
        $isApproved =
            $normalizedStatus === 'success' ||
            $normalizedStatus === 'approved' ||
            $normalizedStatus === 'ok' ||
            $normalizedStatus === '1' ||
            $responseCode === '00' ||
            $responseCode === 0 ||
            $responseCode === '0' ||
            (is_numeric($responseCode) && (int) $responseCode === 1);

        if ($isApproved) {
            // BAŞARILI --- DESCRIPTION KALDIRILDI ---
            $transaction->update([
                'status' => 'success'
            ]);

            return redirect('/odeme-yap')->with('mesaj', "Harika! Ödemeniz başarıyla alındı. Çekilen Tutar: " . number_format($transaction->amount, 2) . " TL. (Sipariş No: {$orderId})");
            
        } else {
            // BAŞARISIZ --- DESCRIPTION KALDIRILDI ---
            $transaction->update([
                'status' => 'failed'
            ]);

            return redirect('/odeme-yap')->withErrors(['Ödeme Reddedildi' => "İşlem banka tarafından onaylanmadı. Sebep: " . $errorMessage]);
        }
    }

    /**
     * 6. SANAL POS İŞLEMLERİNİ VOMSİS'TEN ÇEK (SENKRONİZASYON)
     */
    public function syncTransactions()
    {
        if (!auth()->user()->can_view_pos) {
            return redirect()->route('dashboard')->with('error', 'Sanal POS verilerini senkronize etme yetkiniz yok.');
        }

        try {
            $vomsisService = app(\App\Services\VomsisService::class);
            $mesaj = $vomsisService->syncPosTransactions(); 

            return redirect()->route('payment.list')->with('success', $mesaj);
        } catch (\Exception $e) {
            \Log::error('Sanal POS İşlem Çekme Hatası: ' . $e->getMessage());
            return redirect()->route('payment.list')->with('error', 'Veri çekilirken hata oluştu: ' . $e->getMessage());
        }
    }
}