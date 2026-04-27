<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\VirtualPos;

class VomsisService
{
    protected $apiUrl;
    protected $posApiUrl;
    protected $appKey;
    protected $appSecret;

    public function __construct()
    {
        // V2: Açık Bankacılık (Hesaplar ve Fiziksel POS)
        $this->apiUrl = env('VOMSIS_API_URL', 'https://developers.vomsis.com/api/v2');
        
        // V3: Sanal POS (Ödeme ve Taksitler)
        $this->posApiUrl = env('VOMSIS_POS_API_URL', 'https://uygulama.vomsis.com/api/vpos/v3');
        
        $this->appKey = env('VOMSIS_APP_KEY');
        $this->appSecret = env('VOMSIS_APP_SECRET');
    }

    // ========================================================================
    // 1. AÇIK BANKACILIK (V2) TOKEN ÜRETİCİ
    // ========================================================================
    public function getToken()
    {
        if (Cache::has('vomsis_access_token')) {
            return Cache::get('vomsis_access_token'); 
        }

        $response = Http::post("{$this->apiUrl}/authenticate", [
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret,
        ]);

        if ($response->successful() && $response->json('status') === 'success') {
            $token = $response->json('token'); 
            Cache::put('vomsis_access_token', $token, 82800); 
            return $token;
        }

        throw new Exception('V2 Token Alınamadı! Vomsis Hatası: ' . $response->body());
    }

    // ========================================================================
    // 2. SANAL POS (V3) ÖZEL TOKEN ÜRETİCİ
    // ========================================================================
    public function getPosToken()
    {
        if (Cache::has('vomsis_pos_token')) {
            return Cache::get('vomsis_pos_token'); 
        }//

        $response = Http::post("{$this->posApiUrl}/auth/token", [
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret,
        ]);

        if ($response->successful()) {
            $token = $response->json('token') ?? $response->json('access_token'); 
            
            if ($token) {
                Cache::put('vomsis_pos_token', $token, 82800); 
                return $token;
            }
        }

        throw new Exception('V3 POS Token Alınamadı! HTTP: ' . $response->status() . ' | Vomsis: ' . $response->body());
    }
 
    // ========================================================================
    // 3. SANAL POS GEÇMİŞ İŞLEMLERİNİ ÇEK 
    // ========================================================================
    public function syncPosTransactions()
    {
        $token = $this->getPosToken();

        // Vomsis "transactions-list" dokümanında sadece `status` parametresi var.
        // Daha önce eklediğimiz beginDate/endDate bazı sistemlerde boş dönüşe sebep olabiliyor.
        $query = ['status' => ''];
        // Bazı sistemlerde boş string query parametresi farklı yorumlanabiliyor; tamamen kaldır.
        if ($query['status'] === '') {
            unset($query['status']);
        }

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->acceptJson()
            ->get("{$this->posApiUrl}/transactions-list", $query);

        // Otomatik İyileştirme
        if ($response->status() === 401) {
            \Illuminate\Support\Facades\Cache::forget('vomsis_pos_token');
            throw new \Exception("Sanal POS anahtarı süresi dolmuş. Sistem temizledi, lütfen tekrar tıklayın!");
        }
        

        if ($response->successful()) {
            $data = $response->json();
            $rawBody = $response->body();

            Log::info('Vomsis transactions-list raw response', [
                'http_status' => $response->status(),
                'root_keys' => is_array($data) ? array_keys($data) : [],
                'success' => $data['success'] ?? $data['status'] ?? null,
                'message' => $data['message'] ?? null,
                'raw_prefix' => mb_substr($rawBody, 0, 2000),
            ]);

            // HTTP 200 olsa bile API "success:false" dönebilir.
            if ((isset($data['success']) && $data['success'] === false) || (isset($data['status']) && $data['status'] === false)) {
                $msg = $data['message'] ?? 'Vomsis transactions-list success=false döndü.';
                throw new \Exception($msg . ' | Raw: ' . mb_substr($rawBody, 0, 500));
            }

            $rawData = $data['data'] ?? [];

            // Response formatı bazen nested dönebiliyor; mümkün olan yerlerden işlem listesi çıkarıyoruz.
            if (is_array($rawData) && array_values($rawData) === $rawData) {
                $islemler = $rawData;
            } elseif (is_array($rawData) && isset($rawData['data']) && is_array($rawData['data'])) {
                $islemler = $rawData['data'];
            } elseif (is_array($rawData) && isset($rawData['transactions']) && is_array($rawData['transactions'])) {
                $islemler = $rawData['transactions'];
            } else {
                $islemler = [];
            }

            $kaydedilenSayi = 0;

            Log::info('Vomsis transactions-list sync', [
                'http_status' => $response->status(),
                'items' => count($islemler),
                'data_type' => is_object($rawData) ? 'object' : (is_array($rawData) ? 'array' : gettype($rawData)),
                // hassas içerik olmaması için sadece ilk öğenin anahtarlarını logluyoruz
                'sample_keys' => isset($islemler[0]) && is_array($islemler[0]) ? array_keys($islemler[0]) : [],
            ]);

            foreach ($islemler as $islem) {
                if (!is_array($islem)) {
                    continue;
                }

                // Vomsis alan adları değişebildiği için order_id'yi mümkün olanlardan buluyoruz.
                $orderId =
                    $islem['order_id']
                    ?? $islem['referans_kodu']
                    ?? $islem['referanceNo']
                    ?? $islem['referenceNo']
                    ?? null;

                if (!$orderId) {
                    continue;
                }

                // 1. İŞLEMİN ÇEKİLDİĞİ POS CİHAZINI DİNAMİK BUL
                // Dokümandan gelen "pos_name" (Örn: VOMSİS NKOLAY) verisini alıyoruz
                $posName = $islem['pos_name'] ?? 'Vomsis API POS';
                
                $kullanilanPos = \App\Models\VirtualPos::firstOrCreate(
                    ['name' => $posName], // İsmine göre arıyoruz
                    [
                        'merchant_id' => '0', 
                        'api_key'     => '0', 
                        'is_active'   => true,
                        'bank_id'     => 1 // Veritabanı hatası vermemesi için varsayılan
                    ]
                );

                // 2. İŞLEM DURUMUNU BELİRLE (Dokümanda 1 = Başarılı)
                $durum = $islem['durum'] ?? null;
                $dbStatus = ($durum == 1 || $durum === true || strtolower((string) $durum) === 'successful') ? 'success' : 'failed';

                $amount = $islem['tutar'] ?? $islem['amount'] ?? 0;
                $currency = $islem['para_birimi'] ?? $islem['currency'] ?? 'TRY';
                $installments = $islem['taksit'] ?? $islem['installment'] ?? 1;
                $cardMask = $islem['kredi_kart_no'] ?? $islem['maskedPan'] ?? 'Bilinmiyor';

                // 3. VERİTABANINA KAYDET VEYA GÜNCELLE (Eşleştirme)
                \App\Models\PosTransaction::updateOrCreate(
                    [
                        // Benzersiz işlem numaramız
                        'order_id' => $orderId,
                    ],
                    [
                        'virtual_pos_id'   => $kullanilanPos->id, // Dinamik bulduğumuz cihaz
                        'amount'           => $amount,
                        'currency'         => $currency,
                        'installments'     => $installments,
                        'card_mask'        => $cardMask,
                        'status'           => $dbStatus,
                        'response_code'    => $islem['hata_kodu'] ?? $islem['errorCode'] ?? 'API',
                        
                        // İsteğe bağlı açıklama ve kart bankasını birleştiriyoruz
                        'description'      => ($islem['aciklama'] ?? $islem['description'] ?? 'API İşlemi') . ' - ' . ($islem['kredi_kart_banka'] ?? $islem['creditCardBank'] ?? ''),
                        
                        'transaction_date' => $islem['islem_tarihi'] ?? $islem['transactionDate'] ?? $islem['created_at'] ?? now(),
                    ]
                );
                $kaydedilenSayi++;
            }

            return "Başarılı! Vomsis'ten geçmişe dönük {$kaydedilenSayi} adet Sanal POS işlemi çekildi.";
        }

        throw new \Exception("Sanal POS işlemleri çekilemedi! HTTP: " . $response->status() . " | Hata: " . $response->body());
    }

    // ========================================================================
    // 4. BANKALARI ÇEK (V2)
    // ========================================================================
    public function syncBanks()
    {
        $token = $this->getToken();
        $response = Http::withToken($token)->get("{$this->apiUrl}/banks");

        if ($response->successful()) {
            $banksData = $response->json(); 
            $banksList = $banksData['banks'] ?? [];
            $kayitSayisi = 0;

            foreach ($banksList as $bank) {
                Bank::updateOrCreate(
                    ['vomsis_bank_id' => $bank['id']],
                    ['bank_name' => $bank['bank_title'] ?? $bank['bank_name'] ?? 'Bilinmeyen Banka'] 
                );
                $kayitSayisi++;
            }

            return $kayitSayisi . " adet banka başarıyla veritabanına kaydedildi!";
        }

        throw new Exception('Bankalar çekilemedi! Hata: ' . $response->body());
    }

    // ========================================================================
    // 5. HESAPLARI ÇEK (V2)
    // ========================================================================
    public function syncAccounts()
    {
        $token = $this->getToken();
        $response = Http::withToken($token)->get("{$this->apiUrl}/accounts");

        if ($response->successful()) {
            $accountsData = $response->json(); 
            $accountsList = $accountsData['accounts'] ?? [];
            $kayitSayisi = 0;

            foreach ($accountsList as $account) {
                $localBank = Bank::where('vomsis_bank_id', $account['bank_id'])->first();

                if (!$localBank) continue; 

                $hesapAdi = !empty($account['branch_name']) 
                    ? $account['branch_name'] . ' - ' . $account['account_number'] 
                    : 'Hesap No: ' . $account['account_number'];

                BankAccount::updateOrCreate(
                    ['vomsis_account_id' => $account['id']],
                    [
                        'bank_id' => $localBank->id,
                        'iban' => !empty($account['iban']) ? $account['iban'] : 'IBAN YOK',
                        'account_name' => $hesapAdi,
                        'currency' => $account['fec_name'] ?? 'TRY',
                        'balance' => $account['balance'] ?? 0
                    ]
                );
                $kayitSayisi++;
            }

            return $kayitSayisi . " adet banka hesabı başarıyla kaydedildi!";
        }

        throw new Exception('Hesaplar çekilemedi! Hata: ' . $response->body());
    }

    // ========================================================================
    // 6. İŞLEMLERİ ÇEK (V2)
    // ========================================================================
    public function syncTransactions($beginDate = null, $endDate = null)
    {
        $token = $this->getToken();
        
        // Eğer dışarıdan tarih gelmezse, direkt deney aralığına (Aralık-Şubat) sabitlendi.
        if (!$beginDate || !$endDate) {
            $beginDate = '2025-12-09';
            $endDate   = '2026-02-09';
        }

        $startCarbon = \Carbon\Carbon::parse($beginDate);
        $endCarbon = \Carbon\Carbon::parse($endDate);
        
        $currentStart = $startCarbon->copy();
        $kayitSayisi = 0;

        // Vomsis API genellikle maksimum 7 günlük aralıklara izin verdiği için, 
        // verilen upuzun tarihi 7'şer günlük pencerelere bölerek arka arkaya API istekleri atıyoruz.
        // DÜZELTME: API'nin YYYY-MM-DD formatını reddedip sürekli 20-26 Ocak default aralığını döndüğünü tespit ettik. 
        // Vomsis API'si kesinlikle DD-MM-YYYY (d-m-Y) formatı bekliyor!
        while ($currentStart->lessThanOrEqualTo($endCarbon)) {
            $currentEnd = $currentStart->copy()->addDays(6);
            if ($currentEnd->greaterThan($endCarbon)) {
                $currentEnd = $endCarbon->copy();
            }

            $response = Http::withToken($token)->get("{$this->apiUrl}/transactions", [
                'beginDate' => $currentStart->format('d-m-Y'),
                'endDate'   => $currentEnd->format('d-m-Y')
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $islemler = $data['transactions'] ?? ($data['data'] ?? []); 

                foreach ($islemler as $trans) {
                    $localAccount = BankAccount::where('vomsis_account_id', $trans['bank_account_id'])->first();

                    if (!$localAccount) continue; 

                    $typeCode = $trans['transaction_type'] ?? null; 
                    $aciklama = mb_strtolower($trans['description'] ?? ''); 

                    if (empty($typeCode)) {
                        if (str_contains($aciklama, 'eft')) $typeCode = 'EFT';
                        elseif (str_contains($aciklama, 'havale')) $typeCode = 'HAVALE';
                        elseif (str_contains($aciklama, 'kredi kart')) $typeCode = 'KREDİKARTI';
                        elseif (str_contains($aciklama, 'pos')) $typeCode = 'POS';
                        elseif (str_contains($aciklama, 'vergi')) $typeCode = 'VERGİ';
                        else $typeCode = 'DİĞER';
                    }

                    if (!empty($typeCode)) {
                        TransactionType::updateOrCreate(
                            ['vomsis_type_id' => $typeCode],
                            ['name' => mb_strtoupper($typeCode, 'UTF-8'), 'code' => $typeCode]
                        );
                    }

                    $islemTarihi = $trans['date'] 
                        ?? $trans['transaction_date'] 
                        ?? $trans['system_date'] 
                        ?? $currentStart->format('Y-m-d H:i:s');

                    Transaction::updateOrCreate(
                        ['vomsis_transaction_id' => $trans['id']],
                        [
                            'bank_account_id'       => $localAccount->id,
                            'description'           => $trans['description'] ?? 'Açıklama Yok',
                            'amount'                => $trans['amount'] ?? 0,
                            'transaction_type_code' => $typeCode, 
                            'type'                  => $trans['type'] ?? 'Bilinmiyor',
                            'balance'               => $trans['current_balance'] ?? 0,
                            'transaction_date'      => $islemTarihi, 
                        ]
                    );
                    $kayitSayisi++;
                }
            } else {
                 \Illuminate\Support\Facades\Log::error("Vomsis chunk api hatası: " . $response->body());
            }

            // Bir sonraki adıma geçmeden API limitlerine takılmamak için 1 saniye beklet (Rate-Limit koruması)
            usleep(500000); // 0.5 saniye

            $currentStart = $currentEnd->copy()->addDay();
        }

        return $kayitSayisi . " adet işlem başarıyla çekildi!";
    }

    /**
     * İşlem Tiplerini Vomsis'ten (veya mevcut işlemlerden) senkronize et.
     */
    public function syncTransactionTypes()
    {
        // Bu aslında syncTransactions içinde yapılıyor ama route için ayrı metod ekliyoruz
        return $this->syncTransactions(); 
    }

    /**
     * Sanal POS (Virtual POS) cihazlarını senkronize et.
     */
    public function syncVirtualPoses()
    {
        $token = $this->getPosToken();
        $response = \Illuminate\Support\Facades\Http::withToken($token)->get("{$this->posApiUrl}/pos-list");

        if ($response->successful()) {
            $data = $response->json();
            $posList = $data['data'] ?? [];
            foreach ($posList as $pos) {
                \App\Models\VirtualPos::updateOrCreate(
                    ['name' => $pos['pos_name'] ?? 'Bilinmeyen POS'],
                    [
                        'merchant_id' => $pos['merchant_id'] ?? '0',
                        'is_active'   => true,
                    ]
                );
            }
            return count($posList) . " adet Sanal POS cihazı güncellendi.";
        }
        return "Sanal POS listesi çekilemedi.";
    }
}