<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplyAutoTagRulesJob implements ShouldQueue
{
    use Queueable;

    protected $ruleId;
    protected $transactionIds;
    protected $ignoredIds;
    protected $tagId;

    /**
     * Create a new job instance.
     * $ruleId: Boşsa tüm kuralları uygular.
     * $transactionIds: Boşsa tüm işlemleri tarar.
     */
    public function __construct($ruleId = null, $tagId = null, $transactionIds = [], $ignoredIds = [])
    {
        $this->ruleId = $ruleId;
        $this->tagId = $tagId;
        $this->transactionIds = $transactionIds;
        $this->ignoredIds = $ignoredIds ?? [];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->tagId && !empty($this->transactionIds)) {
            // Belirli bir küme için etiketleme
            $transactions = \App\Models\Transaction::whereIn('id', $this->transactionIds)->get();
            foreach ($transactions as $txn) {
                $txn->tags()->syncWithoutDetaching([$this->tagId]);
            }
        } else if ($this->ruleId) {
            // Tek bir kuralı uygula
            $rule = \App\Models\AutoTagRule::find($this->ruleId);
            if ($rule) {
                $this->applyRule($rule);
            }
        } else {
            // Tüm kuralları uygula
            $rules = \App\Models\AutoTagRule::all();
            foreach ($rules as $rule) {
                $this->applyRule($rule);
            }
        }
    }

    protected function applyRule($rule)
    {
        $keyword = $rule->keyword;
        $tagId = $rule->tag_id;

        // Word Boundary (MySQL 8+ and Legacy support)
        $query = \App\Models\Transaction::where('is_real', 1)
            ->where('description', 'REGEXP', '\\b' . $keyword . '\\b');

        if ($rule->bank_account_id) {
            $query->where('bank_account_id', $rule->bank_account_id);
        } elseif ($rule->bank_id) {
            $query->whereHas('bankAccount', fn($q) => $q->where('bank_id', $rule->bank_id));
        }

        if (!empty($this->ignoredIds)) {
            $query->whereNotIn('id', $this->ignoredIds);
        }

        $matchingTxns = $query->get();

        foreach ($matchingTxns as $txn) {
            $txn->tags()->syncWithoutDetaching([$tagId]);
        }
    }
}
