<?php

namespace App\Console\Commands;

use App\Services\VirmanDetectorService;
use Illuminate\Console\Command;

class VirmanDetect extends Command
{
    protected $signature = 'virman:detect
                            {layer=all : Hangi layer çalışsın — pair | code | all}
                            {--dry-run : Sadece sayım yap, DB\'ye yazma}
                            {--execute : DB\'ye yaz (dry-run olmadan)}';

    protected $description = 'Virman hibrit tespit — Layer 1 (pair) ve Layer 2 (kod/regex)';

    public function handle(VirmanDetectorService $service): int
    {
        $layer  = $this->argument('layer');
        $dryRun = !$this->option('execute');

        if ($dryRun) {
            $this->info('MOD: DRY-RUN — hiçbir şey yazılmayacak.');
        } else {
            $this->warn('MOD: EXECUTE — DB\'ye yazılacak.');
        }

        // ── LAYER 1: PAIR MATCH ──────────────────────────────────────
        if (in_array($layer, ['pair', 'all'])) {
            $this->line('');
            $this->info('▶ Layer 1 — Pair Match (±5 dk, zıt tutar, farklı hesap)');

            $result = $service->detectPairs($dryRun);

            $this->table(
                ['Metrik', 'Değer'],
                [
                    ['Bulunan çift sayısı', $result['pairs_found']],
                    ['İşaretlenecek işlem sayısı', $result['transactions']],
                ]
            );

            if ($dryRun && !empty($result['sample_pairs'])) {
                $this->line('Örnek çiftler (ilk 5):');
                foreach ($result['sample_pairs'] as $p) {
                    $this->line(sprintf(
                        '  #%-6d ↔ #%-6d  tutar: %s  hesap: %d ↔ %d',
                        $p->id1, $p->id2,
                        number_format(abs($p->amount), 2),
                        $p->acc1, $p->acc2
                    ));
                    $this->line(sprintf('          "%s"', mb_substr($p->desc1, 0, 70)));
                    $this->line(sprintf('          "%s"', mb_substr($p->desc2, 0, 70)));
                }
            }
        }

        // ── LAYER 2: KOD/REGEX ───────────────────────────────────────
        if (in_array($layer, ['code', 'all'])) {
            $this->line('');
            $this->info('▶ Layer 2 — Kod/Regex Kuralları');

            $result = $service->detectByCode($dryRun);

            $this->table(
                ['Metrik', 'Değer'],
                [['Bulunan işlem sayısı', $result['total_hits']]]
            );

            if ($dryRun && !empty($result['by_rule'])) {
                $this->line('Kurala göre dağılım:');
                $rows = [];
                foreach ($result['by_rule'] as $rule => $cnt) {
                    $rows[] = [$rule, $cnt];
                }
                $this->table(['Kural', 'Adet'], $rows);
            }
        }

        // ── ÖZET ─────────────────────────────────────────────────────
        $this->line('');
        $this->info('▶ Genel Özet');
        $summary = $service->summary();

        $this->table(
            ['Metrik', 'Değer'],
            [
                ['Toplam gerçek işlem',    $summary['total_real']],
                ['is_virman = 1',          $summary['is_virman_true']],
                ['Henüz işlemsiz (NONE)',  $summary['unprocessed']],
            ]
        );

        $methodRows = [];
        foreach ($summary['by_method'] as $row) {
            $methodRows[] = [$row->virman_method, $row->cnt, $row->avg_confidence ?? '-'];
        }
        $this->table(['Method', 'Adet', 'Ort. Confidence'], $methodRows);

        if ($dryRun) {
            $this->line('');
            $this->comment('Yazmak için: php artisan virman:detect --execute');
        }

        return Command::SUCCESS;
    }
}
