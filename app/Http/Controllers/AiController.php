<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\BankAccount;
use Carbon\Carbon;

class AiController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $userMessage = $request->input('message');

        // ====================================================================
        // RAG MOTORU: Yapay Zekaya Gönderilecek "Şirket Özeti"ni Hazırla
        // ====================================================================
        $systemPrompt = $this->buildRAGContext();

        // --------------------------------------------------------------------
        // ŞU ANKİ DURUM (MOCK - SAHTE YANIT): Arayüzü test etmek için
        // --------------------------------------------------------------------
        sleep(1); // Düşünme efekti
        
        // Sadece test için, hazırladığımız o devasa RAG metnini konsola veya loglara basabilirsin
        \Log::info("YAPAY ZEKAYA GİDECEK GİZLİ VERİ:\n" . $systemPrompt);

        return response()->json([
            'status' => 'success',
            'reply' => "Merhaba! Sorduğun soru: *'{$userMessage}'*. \n\nŞu an laptopundayız. Arka planda veritabanını tarayıp şu anki kasanın durumunu yapay zeka için mükemmel bir metne dönüştürdüm (Log dosyasına bakabilirsin). Evdeki sunucuya bağlandığımızda bu metni modele fırlatacağız!"
        ]);

        // --------------------------------------------------------------------
        // EVE GİDİNCE AÇILACAK GERÇEK BAĞLANTI KODU
        // --------------------------------------------------------------------
        /*
        $pythonApiUrl = 'http://100.85.x.x:8000/api/chat'; 
        
        $response = Http::timeout(30)->post($pythonApiUrl, [
            // Kullanıcının mesajı
            'user_message' => $userMessage, 
            
            // İşte RAG burası! Yapay zekaya Vomsis'in güncel durumunu öğretiyoruz
            'system_context' => $systemPrompt 
        ]);

        return response()->json(['status' => 'success', 'reply' => $response->json('reply')]);
        */
    }

    /**
     * RAG (Retrieval-Augmented Generation) Bağlam Üreticisi
     * Veritabanındaki ham rakamları, yapay zekanın okuyup anlayabileceği 
     * düz bir metne (String) dönüştürür.
     */
    private function buildRAGContext()
    {
        $bugun = Carbon::now()->format('d.m.Y H:i');
        
        // 1. KİMLİK VE KURALLAR İNŞASI
        $context = "Sen Vomsis şirketinin resmi, zeki ve analitik finansal asistanısın.\n";
        $context .= "Şu anki tarih ve saat: {$bugun}.\n";
        $context .= "Sana kullanıcının sorusuyla birlikte şirketin anlık finansal durumunu veriyorum.\n";
        $context .= "KURALLAR:\n";
        $context .= "- Sadece sana verdiğim bu verilere dayanarak cevap ver. Asla hayal ürünü veri (halüsinasyon) üretme.\n";
        $context .= "- Rakamları okurken Gelirlerin (pozitif) ve Giderlerin (negatif) ayrımını iyi yap.\n\n";

        // 2. KASA (BAKİYE) DURUMU
        $context .= "--- GÜNCEL KASA DURUMU ---\n";
        $accounts = BankAccount::where('is_visible', true)->get();
        $totals = [];
        
        foreach ($accounts as $acc) {
            if (!isset($totals[$acc->currency])) {
                $totals[$acc->currency] = 0;
            }
            $totals[$acc->currency] += $acc->balance;
        }

        if (empty($totals)) {
            $context .= "Şu an kasada hiç aktif hesap bulunmuyor.\n";
        } else {
            foreach ($totals as $currency => $total) {
                $context .= "- Toplam {$currency} Bakiyesi: " . number_format($total, 2, ',', '.') . "\n";
            }
        }
        $context .= "\n";

        // 3. SON İŞLEMLER (Yapay zekanın yakın geçmişi bilmesi için)
        $context .= "--- SON 10 HESAP HAREKETİ ---\n";
        $recentTransactions = Transaction::with(['bankAccount.bank', 'tags'])
                                ->orderBy('transaction_date', 'desc')
                                ->limit(10)
                                ->get();

        if ($recentTransactions->isEmpty()) {
            $context .= "Sistemde henüz hiç hesap hareketi yok.\n";
        } else {
            foreach ($recentTransactions as $index => $txn) {
                $sira = $index + 1;
                $tarih = Carbon::parse($txn->transaction_date)->format('d.m.Y');
                $banka = $txn->bankAccount->bank->bank_name ?? 'Bilinmeyen Banka';
                $yon = $txn->amount > 0 ? 'GELİR (+)' : 'GİDER (-)';
                $tutar = number_format(abs($txn->amount), 2, ',', '.') . ' ' . $txn->bankAccount->currency;
                $aciklama = $txn->description;
                $etiketler = $txn->tags->pluck('name')->implode(', ');
                $etiketMetni = $etiketler ? " [Etiketler: {$etiketler}]" : " [Etiketsiz]";

                $context .= "{$sira}. Tarih: {$tarih} | Banka: {$banka} | Tür: {$yon} | Tutar: {$tutar} | Açıklama: '{$aciklama}' {$etiketMetni}\n";
            }
        }

        return $context;
    }
}