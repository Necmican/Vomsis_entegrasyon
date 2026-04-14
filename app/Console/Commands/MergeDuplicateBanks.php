<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDuplicateBanks extends Command
{
    /**
     * Termilden çalıştırılacak komut tetikleyicisi
     */
    protected $signature = 'vomsis:merge-banks';
    protected $description = 'Veritabanındaki farklı yazılmış kopya bankaları asıl bankanın üzerine birleştirir.';

    // HARİTA: [Yeni (Olması Gereken) İsim => Silinecek/Taşınacak Hatalı İsimler]
    protected $map = [
        'Anadolubank' => ['Anadolu Bank', 'Anadolu Bankası'],
        'Denizbank' => ['Deniz Bank', 'Deniz Bankası'],
        'Garanti BBVA' => ['Garanti', 'Garanti Bankası'],
        'Kuveyt Türk' => ['Kuveyt Türk Bankası'],
        'Şekerbank' => ['Şeker Bank', 'Şeker Bankası'],
        'Vakıf Katılım' => ['Vakıf Katılım Bankası'],
        'Yapı Kredi' => ['Yapı Kredi Bankası', 'Yapi Kredi']
    ];

    public function handle()
    {
        $this->info("==========================================");
        $this->info("Banka Birleştirme (Merge) Aracı Başlıyor..");
        $this->info("==========================================");
        
        // İşlemin yarıda kesilip veritabanının yozlaşmasını engelleyen (Ya hep ya hiç) sistemi.
        DB::transaction(function () {
            foreach ($this->map as $goodName => $badNames) {
                // Asıl (Hedef) bankayı bul
                $targetBank = Bank::where('bank_name', $goodName)->first();
                
                // Hatalı isimdeki bankaları çek
                $badBanksQuery = Bank::whereIn('bank_name', $badNames);
                if ($targetBank) {
                    $badBanksQuery->where('id', '!=', $targetBank->id);
                }
                $badBanks = $badBanksQuery->get();

                if ($badBanks->isEmpty()) {
                    continue; // Birleştirilecek hatalı banka yok
                }

                // Eğer targetBank (Ana Banka) daha önce hiç Vomsis'ten gelmemişse, 
                // hatalı bankalardan ilkini hedef yapalım ve ismini "Doğru İsim" olarak değiştirelim.
                if (!$targetBank) {
                    $targetBank = $badBanks->first();
                    $eskiIsim = $targetBank->bank_name;
                    $targetBank->update(['bank_name' => $goodName]);
                    $this->warn("Hedef '$goodName' yoktu. '$eskiIsim' isimli kayıt isim değiştirilerek ana hedef yapıldı.");
                    // Hedef yapıldığı için birleştirilecekler (slinicekler) listesinden çıkaralım
                    $badBanks = $badBanks->reject(fn($b) => $b->id === $targetBank->id);
                }

                foreach ($badBanks as $badBank) {
                    $this->line("-> Taşıma: <fg=red>{$badBank->bank_name} (ID: {$badBank->id})</> ===> <fg=green>{$goodName} (ID: {$targetBank->id})</>");

                    // 1. Hesapları (bank_accounts) taşı
                    $accCount = DB::table('bank_accounts')
                        ->where('bank_id', $badBank->id)
                        ->update(['bank_id' => $targetBank->id]);

                    // 2. Etiket Kurallarını taşı
                    $ruleCount = DB::table('auto_tag_rules')
                        ->where('bank_id', $badBank->id)
                        ->update(['bank_id' => $targetBank->id]);

                    // 3. POS Verilerini (Varsa) taşı
                    $vPosCount = 0; $pPosCount = 0;
                    if (Schema::hasColumn('virtual_poses', 'bank_id')) {
                        $vPosCount = DB::table('virtual_poses')
                            ->where('bank_id', $badBank->id)
                            ->update(['bank_id' => $targetBank->id]);
                    }
                    if (Schema::hasColumn('physical_poses', 'bank_id')) {
                        $pPosCount = DB::table('physical_poses')
                            ->where('bank_id', $badBank->id)
                            ->update(['bank_id' => $targetBank->id]);
                    }

                    // 4. Müşteri (User) menü yetkilerini taşı (JSON)
                    $userCount = 0;
                    $users = User::all();
                    foreach ($users as $user) {
                        $allowed = $user->allowed_banks ?? [];
                        if (in_array($badBank->id, $allowed)) {
                            // Eski yetkiyi sil
                            $allowed = array_filter($allowed, fn($id) => $id != $badBank->id);
                            // Hedefi ekle (Eğer daha önceden eklendiyse tekrar ekleme - duplication olmasın)
                            if (!in_array($targetBank->id, $allowed)) {
                                $allowed[] = $targetBank->id;
                            }
                            // JSON dizisinin indexlerini (Key) 0,1,2 diye yeniden sıralıyoruz ki veritabanı JSON objesi yerine Dizi (Array) olarak kaydetsin.
                            $user->allowed_banks = array_values($allowed);
                            $user->save();
                            $userCount++;
                        }
                    }

                    $this->info("   Başarılı: $accCount hesap, $ruleCount kural, $vPosCount sanal pos, $userCount user yetkisi taşındı.");

                    // 5. İçleri birleştirilip tamamen boşaltılan HAYALET bankayı yok et!
                    $badBank->delete();
                }
            }
        });
        
        $this->info("==========================================");
        $this->info("Tebrikler! Veritabanındaki tüm bankalar başarıyla optimize edildi.");
    }
}
