<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PhysicalPos;
use App\Models\PhysicalPosTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PhysicalPosController extends Controller
{
    /**
     * 1. FİZİKSEL POS LİSTESİNİ GÖSTERME (Arayüz)
     */
    public function index()
    {
        // TEK VE DOĞRU GÜVENLİK DUVARI: Admin değilse VE Fiziksel POS yetkisi yoksa engelle
        if (auth()->user()->role !== 'admin' && !auth()->user()->can_view_physical_pos) {
            return redirect()->route('dashboard')->with('error', 'Fiziksel POS terminallerini görüntüleme yetkiniz bulunmamaktadır.');
        }

        // Veritabanımızdaki senkronize edilmiş cihazları banka adına göre alfabetik çekiyoruz
        $poses = PhysicalPos::orderBy('bank_title')->get();
        
        return view('physical_pos.index', compact('poses'));
    }

    /**
     * 2. VOMSİS API'DEN VERİLERİ ÇEKİP VERİTABANINA SENKRONİZE ETME MOTORU
     */
    public function sync()
    {
        // SADECE ADMIN'LER SENKRONİZE EDEBİLİR (Personel sadece görüntülesin istiyorsak bu kalmalı)
        // Eğer personelin de cihaz listesini Vomsis'ten çekmesini istiyorsan buraya da can_view_physical_pos ekleyebilirsin.
        if (auth()->user()->role !== 'admin') {
            return redirect()->route('dashboard')->with('error', 'Senkronizasyon yapma yetkiniz bulunmamaktadır.');
        }

        $token = $this->getVomsisToken();

        if (!$token) {
            return back()->with('error', 'Vomsis API bağlantısı kurulamadı. Lütfen API anahtarlarınızı kontrol edin.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://developers.vomsis.com/api/v2/pos-rapor/stations');

        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData['success']) && $responseData['success'] == "true" && isset($responseData['data'])) {
                $senkronizeEdilenSayi = 0;

                foreach ($responseData['data'] as $posData) {
                    PhysicalPos::updateOrCreate(
                        ['vomsis_id' => $posData['id']],
                        [
                            'bank_name'            => $posData['bank_name'],
                            'bank_title'           => $posData['bank_title'],
                            'status'               => $posData['status'] == 1,
                            'workplace_no'         => $posData['workplace_no'],
                            'station_no'           => $posData['station_no'],
                            'workplace_name'       => $posData['workplace_name'],
                            'transaction_currency' => $posData['transaction_currency'],
                            'custom_name'          => $posData['custom_name'],
                            'commission_rate'      => $posData['commission_rate'] ?? 0.00,
                        ]
                    );
                    $senkronizeEdilenSayi++;
                }
                return back()->with('success', "Harika! Toplam {$senkronizeEdilenSayi} adet Fiziksel POS terminali başarıyla senkronize edildi.");
            }
            return back()->with('error', 'Vomsis API yanıt verdi ancak beklenen cihaz listesi (data) bulunamadı.');
        }
        return back()->with('error', 'API Hatası: HTTP ' . $response->status() . ' - ' . $response->body());
    }

    /**
     * 3. BELİRLİ BİR CİHAZIN HESAP HAREKETLERİNİ (TRANSACTIONS) ÇEKME MOTORU
     */
    public function syncTransactions($id)
    {
        // GÜVENLİK DUVARI: Sadece Admin veya Fiziksel POS yetkisi olanlar işlemi çekebilir
        if (auth()->user()->role !== 'admin' && !auth()->user()->can_view_physical_pos) {
            return redirect()->route('dashboard')->with('error', 'Bu işlemi yapma yetkiniz bulunmamaktadır.');
        }

        $pos = PhysicalPos::findOrFail($id);

        $token = $this->getVomsisToken();
        if (!$token) {
            return back()->with('error', 'Vomsis API bağlantısı kurulamadı.');
        }

        $beginDate = '10-01-2026';
        $endDate   = '16-01-2026';

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://developers.vomsis.com/api/v2/pos-rapor/stations/{$pos->vomsis_id}/transactions", [
                'beginDate' => $beginDate,
                'endDate'   => $endDate,
            ]);

        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData['status']) && $responseData['status'] === 'success' && isset($responseData['transactions'])) {
                $senkronizeEdilenSayi = 0;

                foreach ($responseData['transactions'] as $tx) {
                    PhysicalPosTransaction::updateOrCreate(
                        [
                            'physical_pos_id'       => $pos->id, 
                            'vomsis_transaction_id' => $tx['id']
                        ],
                        [
                            'transaction_key'       => $tx['key'] ?? null,
                            'card_number'           => $tx['card_number'] ?? null,
                            'card_type'             => $tx['card_type'] ?? null,
                            'sub_card_type'         => $tx['sub_card_type'] ?? null,
                            'gross_amount'          => $tx['gross_amount'] ?? 0,
                            'commission'            => $tx['commission'] ?? 0,
                            'commission_rate'       => $tx['commission_rate'] ?? 0,
                            'net_amount'            => $tx['net_amount'] ?? 0,
                            'exchange'              => $tx['exchange'] ?? 'TL',
                            'installments_count'    => $tx['installments_count'] ?? 0,
                            'transaction_type'      => $tx['transaction_type'] ?? null,
                            'description'           => $tx['description'] ?? null,
                            'confirmation_number'   => $tx['confirmation_number'] ?? null,
                            'provision_no'          => $tx['provision_no'] ?? null,
                            'reference_number'      => $tx['reference_number'] ?? null,
                            'batchn'                => $tx['batchn'] ?? null,
                            'workplace'             => $tx['workplace'] ?? null,
                            'station'               => $tx['station'] ?? null,
                            'valor'                 => $tx['valor'] ?? null,
                            'system_date'           => isset($tx['system_date']) ? \Carbon\Carbon::parse($tx['system_date']) : null,
                        ]
                    );
                    $senkronizeEdilenSayi++;
                }
                return back()->with('success', "Harika! <b>{$pos->bank_title}</b> cihazına ait son 14 günlük toplam <b>{$senkronizeEdilenSayi}</b> adet işlem başarıyla çekildi.");
            }
            return back()->with('error', 'Vomsis API işlem listesini boş döndürdü veya yetkisiz erişim.');
        }
        return back()->with('error', 'API Hatası: HTTP ' . $response->status() . ' - ' . $response->body());
    }

    /**
     * Vomsis Yetkilendirme (Token) İşlemi
     */
    private function getVomsisToken()
    {
        if (Cache::has('vomsis_api_token')) {
            return Cache::get('vomsis_api_token');
        }

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
        return null;
    }

    /**
     * 4. FİZİKSEL POS İŞLEMLERİNİ EKRANA BASMA (GÖRÜNTÜLEME)
     */
    public function showTransactions($id)
    {
        // GÜVENLİK DUVARI: Admin veya Fiziksel POS yetkisi olanlar
        if (auth()->user()->role !== 'admin' && !auth()->user()->can_view_physical_pos) {
            return redirect()->route('dashboard')->with('error', 'Bu sayfayı görüntüleme yetkiniz bulunmamaktadır.');
        }

        $pos = PhysicalPos::findOrFail($id);

        $transactions = PhysicalPosTransaction::where('physical_pos_id', $pos->id)
            ->orderBy('system_date', 'desc')
            ->paginate(20);

        return view('physical_pos.show', compact('pos', 'transactions'));
    }
}