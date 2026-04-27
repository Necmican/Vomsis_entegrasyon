<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VirmanDetectorService
{
    // Layer 1 — Pair match penceresi (saniye)
    private const PAIR_WINDOW_SECONDS = 300; // 5 dakika

    // Layer 2 — Kural seti (öncelik sırasıyla işlenir, ilk eşleşen kazanır)
    private const CODE_RULES = [
        ['name' => 'HESAPLARIM_ARASI',  'desc_regex' => '/hesaplarim\s+arasi\s+transfer/iu',              'codes' => [],                           'confidence' => 100.0],
        ['name' => 'KKRTTNBV_BLOKE',   'desc_regex' => '/bloke/iu',                                       'codes' => ['KKRTTNBV'],                 'confidence' => 99.0],
        ['name' => 'GUMKART_VIRMAN',   'desc_regex' => '/g[üu]mkart\s+hesab[ıi]na\s+virman/iu',          'codes' => [],                           'confidence' => 98.0],
        ['name' => 'VRM_CODE',         'desc_regex' => null,                                              'codes' => ['VRM'],                      'confidence' => 98.0],
        ['name' => 'BM02_BK19_VIRMAN', 'desc_regex' => '/^v[iı̇]rman\s/iu',                               'codes' => ['BM02', 'BK19'],             'confidence' => 97.0],
        ['name' => 'HESAPTAN_VIRMAN',  'desc_regex' => '/\bhesap(tan|a)\s+virman/iu',                     'codes' => [],                           'confidence' => 95.0],
        ['name' => 'CEK_VIRMAN',       'desc_regex' => '/([çc]ek).{1,40}hesap(tan|a)\s+virman/iu',       'codes' => [],                           'confidence' => 95.0],
        ['name' => 'IH05_HVL_VIRMAN',  'desc_regex' => '/cep\s+[sş]ube.*virman/iu',                      'codes' => ['IH05'],                     'confidence' => 96.0],
    ];

    /**
     * Layer 1: Aynı |tutar|, zıt işaret, farklı hesap, ±5 dk pencere → kesin virman çifti.
     *
     * @param  bool  $dryRun  true = sadece sayım, false = DB'ye yaz
     */
    public function detectPairs(bool $dryRun = true): array
    {
        $pairs = DB::select("
            SELECT
                t1.id  AS id1,
                t2.id  AS id2,
                t1.amount,
                t1.bank_account_id AS acc1,
                t2.bank_account_id AS acc2,
                t1.transaction_date AS date1,
                t2.transaction_date AS date2,
                LEFT(t1.description, 80) AS desc1,
                LEFT(t2.description, 80) AS desc2
            FROM transactions t1
            JOIN transactions t2
                ON  t1.amount = -t2.amount
                AND t1.bank_account_id <> t2.bank_account_id
                AND ABS(TIMESTAMPDIFF(SECOND, t1.transaction_date, t2.transaction_date)) <= :window
                AND t1.id < t2.id
            WHERE t1.is_real = 1
              AND t2.is_real = 1
              AND t1.amount > 0
              AND t1.transfer_pair_id IS NULL
              AND t2.transfer_pair_id IS NULL
        ", ['window' => self::PAIR_WINDOW_SECONDS]);

        $pairCount       = count($pairs);
        $transactionCount = $pairCount * 2;

        if ($dryRun) {
            $samples = array_slice($pairs, 0, 5);
            return [
                'mode'             => 'DRY_RUN',
                'pairs_found'      => $pairCount,
                'transactions'     => $transactionCount,
                'sample_pairs'     => $samples,
            ];
        }

        // EXECUTE: batch CASE-WHEN ile tek sorguda güncelle
        if (empty($pairs)) {
            return ['mode' => 'EXECUTE', 'pairs_found' => 0, 'transactions' => 0];
        }

        // Her çift için id→pair_id haritası
        $id1List  = array_column($pairs, 'id1');
        $id2List  = array_column($pairs, 'id2');
        $allIds   = array_merge($id1List, $id2List);

        // Önce is_virman / method / confidence tek seferde set et
        DB::table('transactions')
            ->whereIn('id', $allIds)
            ->update([
                'is_virman'         => 1,
                'virman_confidence' => 99.00,
                'virman_method'     => 'PAIR',
                'updated_at'        => now(),
            ]);

        // transfer_pair_id: CASE WHEN ile batch
        $whenId1 = implode(' ', array_map(fn($p) => "WHEN {$p->id1} THEN {$p->id2}", $pairs));
        $whenId2 = implode(' ', array_map(fn($p) => "WHEN {$p->id2} THEN {$p->id1}", $pairs));
        $inIds   = implode(',', $allIds);

        DB::statement("
            UPDATE transactions
            SET transfer_pair_id = CASE id {$whenId1} {$whenId2} END
            WHERE id IN ({$inIds})
        ");

        $updated = count($allIds);
        Log::channel('single')->info("VirmanDetector PAIR execute: {$pairCount} çift, {$updated} işlem işaretlendi.");

        return [
            'mode'         => 'EXECUTE',
            'pairs_found'  => $pairCount,
            'transactions' => $updated,
        ];
    }

    /**
     * Layer 2: Banka kodu + açıklama regex kuralları.
     * Layer 1'de zaten işaretlenmiş kayıtlar atlanır.
     *
     * @param  bool  $dryRun
     */
    public function detectByCode(bool $dryRun = true): array
    {
        // Henüz işaretlenmemiş is_real=1 işlemleri
        $candidates = DB::table('transactions')
            ->where('is_real', 1)
            ->where('is_virman', 0)
            ->select('id', 'transaction_type_code', 'description')
            ->get();

        $hits = [];

        foreach ($candidates as $row) {
            $code = strtoupper(trim($row->transaction_type_code ?? ''));
            $desc = $row->description ?? '';

            foreach (self::CODE_RULES as $rule) {
                $codeMatch = empty($rule['codes']) || in_array($code, $rule['codes'], true);
                $descMatch = $rule['desc_regex'] === null || preg_match($rule['desc_regex'], $desc);

                if ($codeMatch && $descMatch) {
                    $hits[] = [
                        'id'         => $row->id,
                        'rule'       => $rule['name'],
                        'confidence' => $rule['confidence'],
                    ];
                    break; // ilk eşleşen kural yeterli
                }
            }
        }

        if ($dryRun) {
            $byRule = [];
            foreach ($hits as $h) {
                $byRule[$h['rule']] = ($byRule[$h['rule']] ?? 0) + 1;
            }
            arsort($byRule);
            return [
                'mode'           => 'DRY_RUN',
                'total_hits'     => count($hits),
                'by_rule'        => $byRule,
                'sample_hits'    => array_slice($hits, 0, 10),
            ];
        }

        // EXECUTE
        foreach ($hits as $h) {
            DB::table('transactions')->where('id', $h['id'])->update([
                'is_virman'         => 1,
                'virman_confidence' => $h['confidence'],
                'virman_method'     => 'CODE',
                'updated_at'        => now(),
            ]);
        }

        Log::channel('single')->info("VirmanDetector CODE execute: " . count($hits) . " işlem işaretlendi.");

        return [
            'mode'       => 'EXECUTE',
            'total_hits' => count($hits),
        ];
    }

    /**
     * Özet istatistik — tüm layerlar tamamlandıktan sonra rapor.
     */
    public function summary(): array
    {
        $rows = DB::select("
            SELECT
                virman_method,
                COUNT(*) AS cnt,
                ROUND(AVG(virman_confidence), 1) AS avg_confidence
            FROM transactions
            WHERE is_real = 1
            GROUP BY virman_method
            ORDER BY cnt DESC
        ");

        $total     = DB::table('transactions')->where('is_real', 1)->count();
        $virman    = DB::table('transactions')->where('is_real', 1)->where('is_virman', 1)->count();
        $notYet    = DB::table('transactions')->where('is_real', 1)->where('is_virman', 0)->where('virman_method', 'NONE')->count();

        return [
            'total_real'     => $total,
            'is_virman_true' => $virman,
            'unprocessed'    => $notYet,
            'by_method'      => $rows,
        ];
    }
}
