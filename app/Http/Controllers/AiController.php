<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate(['message' => 'required|string|max:1000']);
        $userMessage = $request->input('message');

        $systemPrompt = $this->buildRAGContext(); 

        try {
            $response = Http::timeout(120)->post('http://host.docker.internal:11434/api/chat', [
                'model' => 'llama3',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt], 
                    ['role' => 'user', 'content' => $userMessage] 
                ],
                'stream' => false,
                'format' => 'json' 
            ]);

            if ($response->successful()) {
                $aiData = json_decode($response->json('message')['content'], true);
                
                // ====================================================================
                // GÜVENLİK DUVARI: PHP SANSÜR MOTORU (TÜRKÇE KARAKTER DESTEKLİ)
                // ====================================================================
                if (isset($aiData['action']) && $aiData['action'] === 'filter' && isset($aiData['filters'])) {
                    
                    // 1. TAM TÜRKÇE KÜÇÜK HARFE ÇEVİRME İŞLEMİ
                    $buyukHarfler = ['I', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
                    $kucukHarfler = ['ı', 'i', 'ğ', 'ü', 'ş', 'ö', 'ç'];
                    
                    $mesajKucuk = str_replace($buyukHarfler, $kucukHarfler, $userMessage);
                    $mesajKucuk = mb_strtolower($mesajKucuk, 'UTF-8');
                    
                    // 1. Kullanıcı GELİR/GİDER demediyse uydurmasını engelle
                    if (!preg_match('/(gelir|gider|harcama|ödeme|gelen|giden)/i', $mesajKucuk)) {
                        $aiData['filters']['type_name'] = null;
                    }
                    
                    // 2. Kullanıcı DÖVİZ demediyse uydurmasını engelle
                    if (!preg_match('/(tl|lira|dolar|usd|euro|eur)/i', $mesajKucuk)) {
                        $aiData['filters']['currency'] = null;
                    }
                    
                    // 3. Kullanıcı TARİH demediyse uydurmasını engelle
                    if (!preg_match('/(gün|dün|bugün|hafta|ay|yıl|ocak|şubat|mart|nisan|mayıs|haziran|temmuz|ağustos|eylül|ekim|kasım|aralık)/i', $mesajKucuk)) {
                        $aiData['filters']['start_date'] = null;
                        $aiData['filters']['end_date'] = null;
                    }

                    // 4. YALAN DEDEKTÖRÜ (ETİKET UYDURMA KORUMASI)
                    if (!empty($aiData['filters']['tag_name'])) {
                        $aiEtiket = str_replace($buyukHarfler, $kucukHarfler, $aiData['filters']['tag_name']);
                        $aiUydurduguEtiket = mb_strtolower($aiEtiket, 'UTF-8');
                        
                        if (mb_strpos($mesajKucuk, $aiUydurduguEtiket) === false) {
                            $aiData['filters']['tag_name'] = null;
                        }
                    }
                }
                // ====================================================================

                return response()->json([
                    'status' => 'success',
                    'reply'  => $aiData['reply'] ?? 'Cevap alınamadı.',
                    'action' => $aiData['action'] ?? null,
                    'filters'=> $aiData['filters'] ?? null 
                ]);
            }
            return response()->json(['status' => 'error', 'reply' => 'Yanıt alınamadı.']);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'reply' => 'Bağlantı hatası: ' . $e->getMessage()]);
        }
    }

    /**
     * RAG (Retrieval-Augmented Generation) Bağlam Üreticisi
     * Dashboard'daki TÜM verileri, son 1 haftalık hareketleri ve Fiziksel POS verilerini AI için hazırlar.
     */
    private function buildRAGContext()
    {
        // Tüm aralık için RAG bağlam tarihlerini güncelliyoruz (9 Aralık 2025 - 9 Şubat 2026)
        $bugun = \Carbon\Carbon::create(2026, 2, 9, 23, 59, 59); 
        $birHaftaOnce = \Carbon\Carbon::create(2025, 12, 9, 0, 0, 0);
        
        // 1. KİMLİK VE KURALLAR İNŞASI 
        $context = "Sen Vomsis şirketinin resmi, zeki ve analitik finansal asistanısın.\n";
        $context .= "Şu anki tarih ve saat: {$bugun->format('Y-m-d')}.\n";
        $context .= "Aşağıda şirketin detaylı kasa durumu ve ({$birHaftaOnce->format('d.m.Y')} - {$bugun->format('d.m.Y')}) tarihleri arasındaki tüm hesap ve POS hareketleri bulunmaktadır.\n";
        $context .= "KURALLAR:\n";
        $context .= "- Sadece sana verdiğim bu verilere dayanarak cevap ver. Asla hayal ürünü veri üretme.\n";
        $context .= "- Analiz yaparken, işlemlerin 'Kalan Bakiye' (işlem sonrası bakiye) bilgisini göz önünde bulundur.\n\n";

        // 2. DETAYLI KASA VE BANKA DURUMU
        $context .= "--- DETAYLI BANKA VE HESAP DURUMU ---\n";
        $banks = \App\Models\Bank::with(['bankAccounts' => function($q) {
            $q->where('is_visible', true);
        }])->where('is_visible', true)->get();

        $totals = [];
        
        foreach ($banks as $bank) {
            $context .= "🏦 {$bank->bank_name}:\n";
            foreach ($bank->bankAccounts as $acc) {
                // Sadece statik tabloyu değil, çekilen son işlemi sorguluyoruz
                $latestTxn = \App\Models\Transaction::where('bank_account_id', $acc->id)
                    ->orderBy('transaction_date', 'desc')
                    ->first();
                $gercekBakiye = $latestTxn ? $latestTxn->balance : $acc->balance;
                
                $bakiye = number_format($gercekBakiye, 2, ',', '.');
                $dahilMi = $acc->include_in_totals ? "Ana Kasaya Dahil" : "Ana Kasaya Dahil Değil";
                
                $context .= "   - Hesap: {$acc->account_name} | Bakiye: {$bakiye} {$acc->currency} ({$dahilMi})\n";
                
                if ($acc->include_in_totals) {
                    if (!isset($totals[$acc->currency])) {
                        $totals[$acc->currency] = 0;
                    }
                    $totals[$acc->currency] += $gercekBakiye;
                }
            }
        }

        $context .= "\n--- ANA KASA TOPLAMLARI (KULLANILABİLİR LİKİDİTE) ---\n";
        if (empty($totals)) {
            $context .= "Ana kasaya dahil edilmiş hesap bulunmuyor.\n";
        } else {
            foreach ($totals as $currency => $total) {
                $context .= "👉 Toplam {$currency}: " . number_format($total, 2, ',', '.') . "\n";
            }
        }
        $context .= "\n";

        // 3. İŞLEMLER
        $context .= "--- HESAP HAREKETLERİ ({$birHaftaOnce->format('d.m.Y')} - {$bugun->format('d.m.Y')}) ---\n";
        
        $recentTransactions = \App\Models\Transaction::with(['bankAccount.bank', 'tags', 'transactionType'])
                                ->where('transaction_date', '>=', $birHaftaOnce)
                                ->where('transaction_date', '<=', $bugun)
                                ->orderBy('transaction_date', 'desc')
                                ->get();

        if ($recentTransactions->isEmpty()) {
            $context .= "Sistemde bu tarihlere ait hiçbir hesap hareketi yok.\n";
        } else {
            foreach ($recentTransactions as $index => $txn) {
                $sira = $index + 1;
                $tarih = \Carbon\Carbon::parse($txn->transaction_date)->format('d.m.Y H:i');
                $banka = $txn->bankAccount->bank->bank_name ?? 'Banka';
                $hesap = $txn->bankAccount->account_name ?? 'Hesap';
                $kur = $txn->bankAccount->currency ?? 'TL';
                $tip = $txn->transactionType->name ?? 'Standart';
                $yon = $txn->amount > 0 ? 'GELİR (+)' : 'GİDER (-)';
                
                $tutar = number_format(abs($txn->amount), 2, ',', '.') . ' ' . $kur;
                $islemSonrasiBakiye = number_format($txn->balance, 2, ',', '.') . ' ' . $kur;
                $aciklama = trim(preg_replace('/\s+/', ' ', $txn->description)); 
                $etiketler = $txn->tags->pluck('name')->implode(', ');
                $etiketMetni = $etiketler ? " [Etiket: {$etiketler}]" : "";

                $context .= "{$sira}. Tarih: {$tarih} | Yer: {$banka}-{$hesap} | İşlem: {$tip} | Tür: {$yon} | Tutar: {$tutar} | Kalan Bakiye: {$islemSonrasiBakiye} | Açıklama: '{$aciklama}'{$etiketMetni}\n";
            }
        }
        $context .= "\n";

        // 4. POS İŞLEMLERİ
        $context .= "--- FİZİKSEL POS İŞLEMLERİ ({$birHaftaOnce->format('d.m.Y')} - {$bugun->format('d.m.Y')}) ---\n";
        
        if (class_exists(\App\Models\PhysicalPosTransaction::class)) {
            $posGecmisi = \App\Models\PhysicalPosTransaction::where('created_at', '>=', $birHaftaOnce)
                                ->where('created_at', '<=', $bugun)
                                ->orderBy('created_at', 'desc')
                                ->get();

            if ($posGecmisi->isEmpty()) {
                $context .= "Bu tarihler arasında Fiziksel POS cihazlarından geçirilmiş bir işlem bulunmuyor.\n";
            } else {
                foreach ($posGecmisi as $index => $pos) {
                    $sira = $index + 1;
                    $tarih = \Carbon\Carbon::parse($pos->created_at)->format('d.m.Y H:i');
                    $cihaz = $pos->device_name ?? 'Bilinmeyen Cihaz'; 
                    $tutar = number_format($pos->amount, 2, ',', '.') . ' ' . ($pos->currency ?? 'TL');
                    $komisyon = isset($pos->commission_rate) ? "%" . $pos->commission_rate . " Komisyon" : "Komisyonsuz";
                    $taksit = isset($pos->installments) && $pos->installments > 1 ? $pos->installments . " Taksit" : "Tek Çekim";
                    $durum = $pos->status ?? 'Başarılı';

                    $context .= "{$sira}. Tarih: {$tarih} | POS Cihazı: {$cihaz} | Tutar: {$tutar} | Çekim Tipi: {$taksit} | Kesinti: {$komisyon} | Durum: {$durum}\n";
                }
            }
        } else {
             $context .= "Sistemde Fiziksel POS verileri bulunamadı (Model yok).\n";
        }

        // ====================================================================
        // 5. NİHAİ JSON AI BLOĞU
        // ====================================================================
        $context .= "\n\n=== SİSTEM EMRİ: KATI JSON PARSER ===\n";
        $context .= "Sen sadece bir 'Metin Ayıklama' algoritmasısın. ASLA TAHMİN ETME, SADECE KULLANICININ AÇIKÇA YAZDIĞI KELİMELERİ JSON'A EKLE. Bahsedilmeyen her şey 'null' olmalıdır.\n";
        $context .= "Şu anki sistem tarihi: {$bugun->format('Y-m-d')}\n\n";
        
        $context .= "--- 5 ALTIN KURAL ---\n";
        $context .= "1. 'search': Firma isimleri (Örn: Kardeşler Gıda, Demir Çelik A.Ş.), Kişi adları (Örn: Ahmet Yılmaz), Banka adları (Örn: Akbank) veya aranacak özel kelime öbekleri EKSİKSİZ VE BÖLÜNMEDEN buraya yazılır. Kelimeleri asla ayırma!\n";
        $context .= "2. 'tag_name': 'Etiket', 'etiketli' kelimeleri geçerse veya kategori (iade, fatura, vergi) belirtilirse buraya yazılır.\n";
        $context .= "3. 'currency': SADECE 'TL', 'USD', 'Dolar', 'EUR', 'Euro' kelimeleri geçerse yazılır.\n";
        $context .= "4. 'type_name': SADECE 'gelir', 'gider', 'harcama', 'ödeme' kelimeleri geçerse 'gelir' veya 'gider' yazılır. 'İşlem' kelimesi yön belirtmez, null bırakılır.\n";
        $context .= "5. 'start_date' / 'end_date': Yalnızca zaman belirtilirse (dün, geçen hafta, ocak) YYYY-MM-DD formatında yazılır.\n\n";

        $context .= "--- EĞİTİM ÖRNEKLERİ (BUNLARI KESİNLİKLE UYGULA) ---\n\n";

        $context .= "ÖRNEK 1: (Sadece Banka Araması)\n";
        $context .= "Girdi: 'Albaraka türk işlemlerini filtrele'\n";
        $context .= 'Çıktı: {"reply": "Albaraka Türk işlemleri listelendi.", "action": "filter", "filters": {"search": "Albaraka türk", "start_date": null, "end_date": null, "currency": null, "tag_name": null, "type_name": null}}' . "\n\n";

        $context .= "ÖRNEK 2: (Sadece Etiket Araması)\n";
        $context .= "Girdi: 'İade etiketli işlemleri göster'\n";
        $context .= 'Çıktı: {"reply": "İade etiketli işlemler listelendi.", "action": "filter", "filters": {"search": null, "start_date": null, "end_date": null, "currency": null, "tag_name": "iade", "type_name": null}}' . "\n\n";

        $context .= "ÖRNEK 3: (Sadece Kur ve Yön Araması)\n";
        $context .= "Girdi: 'Dolar harcamalarımı sırala'\n";
        $context .= 'Çıktı: {"reply": "Dolar (USD) harcamalarınız listelendi.", "action": "filter", "filters": {"search": null, "start_date": null, "end_date": null, "currency": "USD", "tag_name": null, "type_name": "gider"}}' . "\n\n";

        $context .= "ÖRNEK 4: (Sadece Tarih Araması)\n";
        $context .= "Girdi: 'Geçen haftaki işlemleri bul'\n";
        $context .= 'Çıktı: {"reply": "Geçen haftaki işlemler listelendi.", "action": "filter", "filters": {"search": null, "start_date": "2026-01-19", "end_date": "2026-01-26", "currency": null, "tag_name": null, "type_name": null}}' . "\n\n";

        $context .= "ÖRNEK 5: (Tüm Parametrelerin Kombinasyonu)\n";
        $context .= "Girdi: 'Geçen hafta Ziraat bankasındaki fatura etiketli TL giderlerimi listele'\n";
        $context .= 'Çıktı: {"reply": "Geçen haftaki Ziraat Bankası TL fatura giderleri listelendi.", "action": "filter", "filters": {"search": "Ziraat", "start_date": "2026-01-19", "end_date": "2026-01-26", "currency": "TL", "tag_name": "fatura", "type_name": "gider"}}' . "\n\n";

        $context .= "ÖRNEK 6: (Firma/Kişi/Kelime Araması - Kelimeler Bölünmez!)\n";
        $context .= "Girdi: 'Kardeşler gıda ödemelerini filtrele' veya 'exet listele'\n";
        $context .= 'Çıktı: {"reply": "İlgili kayıtlar listelendi.", "action": "filter", "filters": {"search": "Kardeşler gıda", "start_date": null, "end_date": null, "currency": null, "tag_name": null, "type_name": "gider"}}' . "\n\n";

        $context .= "Kullanıcıdan gelen bankası,tarihli,etiketli vb kelimeleri search olarak jsona eklme bunlar belirteç olarak kullan ve hangi değere müdahale edileceğini belirlemek için kullan arama kısmına eklme yapma." ;

        $context .= "GÖREVİN: Kullanıcının sana vereceği yeni metni SADECE yukarıdaki kurallara ve örneklere göre JSON formatında filtrele. Başka hiçbir açıklama yazma.\n";
        
        return $context;
    }
}