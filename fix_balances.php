<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

echo "=== BAKİYE DÜZELTME BAŞLIYOR ===\n\n";

// 1. Aynı transaction_date'e sahip tüm (bank_account_id, transaction_date) gruplarını bul
$dupeGroups = DB::table('transactions')
    ->selectRaw('bank_account_id, transaction_date, COUNT(*) as cnt')
    ->groupBy('bank_account_id', 'transaction_date')
    ->having('cnt', '>', 1)
    ->orderBy('bank_account_id')
    ->orderBy('transaction_date')
    ->get();

echo "Etkilenen grup sayısı: " . $dupeGroups->count() . "\n\n";

$duzeltilen = 0;
$atlanan = 0;

foreach ($dupeGroups as $group) {
    // O gruptaki tüm işlemleri ID'ye göre artan sırayla al
    $txns = Transaction::where('bank_account_id', $group->bank_account_id)
        ->where('transaction_date', $group->transaction_date)
        ->orderBy('id', 'asc')
        ->get();

    // VOMSIS'te balance kümülatif (running balance) olmalı.
    // Doğru sıra: her işlemden sonraki balance bir öncekinden amount kadar farklı olmalı.
    // Grubu amount sırasıyla değil, balance tutarlılığıyla doğrulayalım.
    //
    // Kural: Grupta hangi kayıt gerçek "son bakiye" yi taşıyor?
    // VOMSIS API'sinde current_balance = o işlem sonrası hesap bakiyesi.
    // Birbirine bağlı zincir: balance[n] = balance[n-1] + amount[n]
    //
    // Grubu bir önceki geçerli bakiyeden (grup öncesi son işlem) iteratif yeniden hesapla.

    // Grup öncesi bu hesabın gerçek son bakiyesini bul
    $prevTxn = Transaction::where('bank_account_id', $group->bank_account_id)
        ->where('transaction_date', '<', $group->transaction_date)
        ->orderBy('transaction_date', 'desc')
        ->orderBy('id', 'desc')
        ->first();

    $prevBalance = $prevTxn ? $prevTxn->balance : null;

    // Eğer önceki bakiye yoksa (ilk işlemler) ve grupta tek bir tutarlı zincir varsa atla
    if ($prevBalance === null) {
        // İlk işlem grubunda düzeltme yapamazsak atla
        $atlanan++;
        continue;
    }

    // Grubun içindeki işlemleri amount ile sıralamayı dene:
    // balance[i] = prevBalance + amount[i] olmalı
    // Hangi sıralama tutarlı zincir oluşturuyor?

    // Önce mevcut sıranın (id asc) tutarlı olup olmadığını kontrol et
    $tutarli = true;
    $runningBalance = $prevBalance;
    foreach ($txns as $txn) {
        $expected = round($runningBalance + $txn->amount, 2);
        $actual   = round($txn->balance, 2);
        if (abs($expected - $actual) > 0.05) {
            $tutarli = false;
            break;
        }
        $runningBalance = $txn->balance;
    }

    if ($tutarli) {
        // Zaten doğru sıralı, düzeltme gerekmez
        continue;
    }

    // Tutarsız — doğru sırayı bul: tüm permütasyonları dene (max 6 kayıt)
    // 6'dan fazlaysa amount-ascending sırasını dene (genellikle çalışır)
    $txnArray = $txns->all();
    $n = count($txnArray);
    $dogruSira = null;

    if ($n <= 6) {
        // Tüm permütasyonları dene
        $indices = range(0, $n - 1);
        foreach (permutations($indices) as $perm) {
            $running = $prevBalance;
            $gecerli = true;
            foreach ($perm as $idx) {
                $t = $txnArray[$idx];
                $exp = round($running + $t->amount, 2);
                $act = round($t->balance, 2);
                if (abs($exp - $act) > 0.05) {
                    $gecerli = false;
                    break;
                }
                $running = $t->balance;
            }
            if ($gecerli) {
                $dogruSira = $perm;
                break;
            }
        }
    }

    if ($dogruSira === null) {
        // Permütasyon bulunamadı — balance'ı amount zincirinden yeniden hesapla
        // amount'a göre sırala ve balance'ı yeniden yaz
        usort($txnArray, function($a, $b) {
            // Gideri önce, geliri sonra dene
            return $a->amount <=> $b->amount;
        });
        $running = $prevBalance;
        $allMatch = true;
        foreach ($txnArray as $t) {
            $computed = round($running + $t->amount, 2);
            // Bu sırayla balance tutarlı mı?
            if (abs($computed - round($t->balance, 2)) > 0.05) {
                $allMatch = false;
            }
            $running = $t->balance;
        }

        if (!$allMatch) {
            // Son çare: balance'ı amount zincirinden sıfırdan hesapla
            $running = $prevBalance;
            foreach ($txnArray as $t) {
                $newBalance = round($running + $t->amount, 2);
                if (round($t->balance, 2) !== $newBalance) {
                    Transaction::where('id', $t->id)->update(['balance' => $newBalance]);
                    $duzeltilen++;
                    echo "  HESAPLANDI acc={$group->bank_account_id} id={$t->id} eski={$t->balance} yeni={$newBalance}\n";
                }
                $running = $newBalance;
            }
            continue;
        }
        $dogruSira = array_keys($txnArray);
    }

    // Doğru sıra bulundu — son elemanın balance'ı zaten doğru,
    // ancak diğer elemanların balance'larını zincirden yeniden hesapla
    $running = $prevBalance;
    foreach ($dogruSira as $idx) {
        $t = $txnArray[$idx];
        $computed = round($running + $t->amount, 2);
        if (round($t->balance, 2) !== $computed) {
            Transaction::where('id', $t->id)->update(['balance' => $computed]);
            $duzeltilen++;
            echo "  DÜZELTİLDİ acc={$group->bank_account_id} id={$t->id} eski={$t->balance} yeni={$computed}\n";
        }
        $running = $computed;
    }
}

echo "\n=== SONUÇ ===\n";
echo "Düzeltilen kayıt: $duzeltilen\n";
echo "Önceki bakiye olmadığı için atlanan grup: $atlanan\n";

// Permütasyon üretici yardımcı fonksiyon
function permutations(array $items): Generator {
    $n = count($items);
    if ($n <= 1) {
        yield $items;
        return;
    }
    foreach ($items as $key => $item) {
        $rest = array_values(array_filter($items, fn($k) => $k !== $key, ARRAY_FILTER_USE_KEY));
        foreach (permutations($rest) as $perm) {
            yield array_merge([$item], $perm);
        }
    }
}
