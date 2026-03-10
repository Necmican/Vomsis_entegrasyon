<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\TransactionType;

class VomsisService
{
    protected $apiUrl;
    protected $appKey;
    protected $appSecret;

    
    public function __construct()
    {
        $this->apiUrl = env('VOMSIS_API_URL', 'https://developers.vomsis.com/api/v2');
        $this->appKey = env('VOMSIS_APP_KEY');
        $this->appSecret = env('VOMSIS_APP_SECRET');
    }

    
     
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

       
        throw new Exception('Vomsis API Hatası: ' . $response->body());
    }

    
     
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
                    ['vomsis_bank_id' => $bank['id']], // Arama kriterimiz
                    ['bank_name' => $bank['bank_title'] ?? $bank['bank_name'] ?? 'Bilinmeyen Banka'] 
                );
                $kayitSayisi++;
            }

            return $kayitSayisi . " adet banka başarıyla veritabanına kaydedildi!";
        }
        
        

        throw new Exception('Bankalar çekilemedi! Hata: ' . $response->body());
    }

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

                
                if (!$localBank) {
                    continue; 
                }

                
                $hesapAdi = !empty($account['branch_name']) 
                    ? $account['branch_name'] . ' - ' . $account['account_number'] 
                    : 'Hesap No: ' . $account['account_number'];

                
                BankAccount::updateOrCreate(
                    ['vomsis_account_id' => $account['id']], // Arama Kriteri (Kimlik)
                    [
                        'bank_id' => $localBank->id, // BİZİM veritabanımızdaki bankanın ID'si
                        'iban' => !empty($account['iban']) ? $account['iban'] : 'IBAN YOK', // IBAN boş gelirse çökmesin
                        'account_name' => $hesapAdi, // Kendi ürettiğimiz isim
                        'currency' => $account['fec_name'] ?? 'TRY', // Para birimi (Örn: EUR, TL)
                        'balance' => $account['balance'] ?? 0 // Bakiye boşsa 0 yaz
                    ]
                );
                
                $kayitSayisi++;
            }

            return $kayitSayisi . " adet banka hesabı başarıyla veritabanına kaydedildi!";
        }

        throw new Exception('Hesaplar çekilemedi! Hata: ' . $response->body());
    }

    public function syncTransactions()
    {
        $token = $this->getToken();
        


        $beginDate = '20-01-2026'; 
        $endDate   = '26-01-2026';

        
        $response = Http::withToken($token)->get("{$this->apiUrl}/transactions", [
            'beginDate' => $beginDate,
            'endDate'   => $endDate
        ]);


        if ($response->successful()) {
            $data = $response->json();
            
            $islemler = $data['transactions'] ?? ($data['data'] ?? []); 
            $kayitSayisi = 0;

            foreach ($islemler as $trans) {
                
                $localAccount = BankAccount::where('vomsis_account_id', $trans['bank_account_id'])->first();

                if (!$localAccount) {
                    continue; 
                }

                // --- 1. VOMSIS'İN VERDİĞİNİ VEYA BİZİM TAHMİNİMİZİ AL ---
                $typeCode = $trans['transaction_type'] ?? null; 
                $aciklama = mb_strtolower($trans['description'] ?? ''); 

                if (empty($typeCode)) {
                    if (str_contains($aciklama, 'eft')) {
                        $typeCode = 'EFT';
                    } elseif (str_contains($aciklama, 'havale')) {
                        $typeCode = 'HAVALE';
                    } elseif (str_contains($aciklama, 'kredi kart')) {
                        $typeCode = 'KREDİKARTI';
                    } elseif (str_contains($aciklama, 'pos')) {
                        $typeCode = 'POS';
                    } elseif (str_contains($aciklama, 'vergi')) {
                        $typeCode = 'VERGİ';
                    } else {
                        $typeCode = 'DİĞER';
                    }
                }

                // --- 2. TİPLERİ TABLOYA EKLE (FİLTRE İÇİN) ---
                if (!empty($typeCode)) {
                    TransactionType::updateOrCreate(
                        ['vomsis_type_id' => $typeCode],
                        ['name' => ucfirst($typeCode), 'code' => $typeCode]
                    );
                }

                // --- 3. İŞLEMİ KAYDET / GÜNCELLE ---
                Transaction::updateOrCreate(
                    ['vomsis_transaction_id' => $trans['id']],
                    [
                        'bank_account_id'       => $localAccount->id,
                        'description'           => $trans['description'] ?? 'Açıklama Yok',
                        'amount'                => $trans['amount'] ?? 0,
                        'transaction_type_code' => $typeCode, 
                        'type'                  => $trans['type'] ?? 'Bilinmiyor',
                        'balance'               => $trans['current_balance'] ?? 0,
                        'transaction_date'      => $trans['system_date'] ?? now(),
                    ]
                );
                
                $kayitSayisi++;
            }

            return $kayitSayisi . " adet işlem başarıyla çekildi!";
        }

        throw new \Exception('İşlemler çekilemedi! Hata: ' . $response->body());
    }
    public function syncVirtualPoses()
    {
        $token = $this->getToken();

        if (!$token) {
            throw new \Exception("Vomsis Token alınamadı!");
        }

        // Vomsis'ten POS Listesini İstiyoruz
        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://uygulama.vomsis.com/api/v2/pos-rapor/stations'); // Vomsis'in POS listesi ucu

        if ($response->successful()) {
            $poses = $response->json();
            $kaydedilenSayi = 0;

            // Eğer API "data" dizisi dönüyorsa (Vomsis mimarisine göre değişebilir, kontrol ediyoruz)
            $posListesi = isset($poses['data']) ? $poses['data'] : (is_array($poses) ? $poses : []);

            foreach ($posListesi as $pos) {
                // Kendi VirtualPos modelimize kaydediyoruz (Model adın ve sütunların farklıysa burayı güncelle)
                \App\Models\VirtualPos::updateOrCreate(
                    [
                        // Vomsis'ten gelen benzersiz POS ID'si
                        'vomsis_pos_id' => $pos['id'] ?? $pos['station_id'] 
                    ],
                    [
                        'bank_name'   => $pos['bank_name'] ?? $pos['name'] ?? 'Bilinmeyen POS',
                        'merchant_id' => $pos['merchant_id'] ?? 'Yok',
                        'is_active'   => true, // API'den geliyorsa aktiftir
                    ]
                );
                $kaydedilenSayi++;
            }

            return "Başarılı! Vomsis'ten {$kaydedilenSayi} adet Sanal POS çekildi ve veritabanına kaydedildi.";
        }

        throw new \Exception("Sanal POS'lar çekilemedi! HTTP Kodu: " . $response->status() . " | Mesaj: " . $response->body());
    }
}
