<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\AutoTagExclusion;
use App\Models\BankAccount;
use App\Models\AutoTagRule;
use Illuminate\Support\Facades\Validator;
use App\Models\Bank;

class TagController extends Controller
{
    public function store(Request $request)
    {
        // 1. GÜVENLİK DUVARI: Admin DEĞİLSE ve Etiket Üretme Yetkisi YOKSA engelle!
        if (auth()->user()->role !== 'admin' && !auth()->user()->can_create_tags) {
            return back()->with('error', 'Sistemde yeni etiket oluşturma yetkiniz bulunmamaktadır.');
        }

        // 2. Gelen veriyi doğrula
        $request->validate([
            'name' => 'required|string|max:255|unique:tags,name', // Aynı isimde etiket olmasın
            'color' => 'required|string|max:7',
        ]);

        // 3. Etiketi Kaydet
        Tag::create([
            'name' => $request->name,
            'color' => $request->color,
        ]);

        return back()->with('mesaj', "Harika! '{$request->name}' etiketi başarıyla oluşturuldu.");
    }

    public function attachTag(Request $request, $transactionId)
    {
        $request->validate(['tag_id' => 'required|exists:tags,id']);
        $transaction = Transaction::findOrFail($transactionId);
        $transaction->tags()->syncWithoutDetaching([$request->tag_id]);

        return back()->with('mesaj', '🏷️ Etiket başarıyla eklendi.');
    }

    public function detachTag($transactionId, $tagId)
    {
        /** @var Transaction $transaction */
        $transaction = Transaction::findOrFail($transactionId);
        $transaction->tags()->detach($tagId);

        return back()->with('mesaj', '🗑️ Etiket işlemden çıkarıldı.');
    }

    public function bulkAttachTags(Request $request)
    {
        // 1. Gelen Verileri Doğrula (Validation)
        $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $user = auth()->user();
        $isAdmin = $user->role === 'admin';
        $izinliBankalar = $user->allowed_banks ?? [];
        $izinliEtiketler = $user->allowed_tags ?? [];

        // 2. GÜVENLİK: Personel yetkisi olmayan bir etiketi eklemeye çalışıyor mu?
        if (!$isAdmin) {
            // Gönderilen etiketler ile izinli etiketler arasındaki farkı bul. Fark varsa hile yapılıyordur!
            $yetkisizEtiketler = array_diff($request->tag_ids, $izinliEtiketler);
            if (!empty($yetkisizEtiketler)) {
                return back()->with('error', 'Seçtiğiniz bazı etiketleri kullanma yetkiniz bulunmuyor.');
            }
        }

        // 3. Veritabanından seçilen işlemleri topluca çek
        $transactions = Transaction::with('bankAccount')->whereIn('id', $request->transaction_ids)->get();

        // 4. GÜVENLİK: Personel yetkisi olmayan bir bankanın işlemine etiket atmaya çalışıyor mu?
        if (!$isAdmin) {
            foreach ($transactions as $transaction) {
                if (!in_array($transaction->bankAccount->bank_id, $izinliBankalar)) {
                    return back()->with('error', 'Seçilen işlemlerden bazılarına müdahale etme yetkiniz yok (Yetkisiz Banka).');
                }
            }
        }

        // 5. ETİKETLERİ ZIMBALA (SyncWithoutDetaching)
        foreach ($transactions as $transaction) {
            /** @var Transaction $transaction */
            $transaction->tags()->syncWithoutDetaching($request->tag_ids);
        }

        return back()->with('mesaj', count($request->transaction_ids) . ' adet işleme başarıyla etiketler eklendi.');
    }
    public function bulkDetachTags(Request $request)
    {
        // 1. Doğrulama
        $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $user = auth()->user();
        $isAdmin = $user->role === 'admin';
        $izinliBankalar = $user->allowed_banks ?? [];
        $izinliEtiketler = $user->allowed_tags ?? [];

        // 2. GÜVENLİK: Personel yetkisi olmayan bir etiketi çıkarmaya çalışıyor mu?
        if (!$isAdmin) {
            $yetkisizEtiketler = array_diff($request->tag_ids, $izinliEtiketler);
            if (!empty($yetkisizEtiketler)) {
                return back()->with('error', 'Seçtiğiniz bazı etiketleri çıkarma yetkiniz bulunmuyor.');
            }
        }

        $transactions = Transaction::with('bankAccount')->whereIn('id', $request->transaction_ids)->get();

        // 3. GÜVENLİK: Yetkisiz banka işlemi kontrolü
        if (!$isAdmin) {
            foreach ($transactions as $transaction) {
                if (!in_array($transaction->bankAccount->bank_id, $izinliBankalar)) {
                    return back()->with('error', 'Seçilen işlemlerden bazılarına müdahale etme yetkiniz yok.');
                }
            }
        }

        // 4. ETİKETLERİ SÖK AT (Detach)
        foreach ($transactions as $transaction) {
            /** @var Transaction $transaction */
            // Eğer işlemde o etiket yoksa hata vermez, sessizce geçer. Varsa siler.
            $transaction->tags()->detach($request->tag_ids);
        }

        return back()->with('mesaj', count($request->transaction_ids) . ' adet işlemden seçtiğiniz etiketler başarıyla kaldırıldı.');
    }

    // =========================================================================
    // OTO-ETİKET (AUTO-TAG) FONKSİYONLARI
    // =========================================================================

    // ==========================================================================
    // HIZLI ETİKETLEME — Anahtar Kelime Arama, Etiket Oluştur+Uygula, İşlem Sil
    // ==========================================================================
    public function keywordPreview(Request $request)
    {
        $keyword = strtoupper(trim($request->input('keyword', '')));

        if (strlen($keyword) < 2) {
            return response()->json(['status' => 'error', 'message' => 'Kelime en az 2 karakter olmalı.'], 422);
        }

        // Çoklu kelime desteği: "SHELL FATURA" → hem SHELL hem FATURA içerenleri bul
        // Her kelime için ayrı REGEXP \b ile tam kelime eşleşmesi (ÇEK ≠ ÇEKİM)
        $words = preg_split('/\s+/', $keyword);
        $query = Transaction::with('bankAccount');
        foreach ($words as $word) {
            if (strlen($word) >= 2) {
                $query->where('description', 'REGEXP', '\\b' . $word . '\\b');
            }
        }

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->input('bank_account_id'));
        } elseif ($request->filled('bank_id')) {
            $query->whereHas('bankAccount', fn($q) => $q->where('bank_id', $request->input('bank_id')));
        }

        $totalCount = $query->count();
        $limit = $request->input('limit') === 'all' ? 10000 : 100;

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->limit($limit)
            ->get(['id', 'description', 'amount', 'transaction_date', 'bank_account_id'])
            ->map(fn($t) => [
                'id' => $t->id,
                'description' => $t->description,
                'amount' => number_format(abs($t->amount), 2, ',', '.'),
                'direction' => $t->amount >= 0 ? 'in' : 'out',
                'date' => $t->transaction_date ? substr($t->transaction_date, 0, 10) : '',
                'type' => $t->bankAccount ? $t->bankAccount->currency : 'TRY',
            ]);

        return response()->json([
            'status' => 'success',
            'keyword' => $keyword,
            'count' => $totalCount,
            'transactions' => $transactions->values(),
        ]);
    }

    /**
     * AJAX: Etiket oluştur (yoksa) → kural kaydet → eşleşen işlemleri arka planda etiketle.
     * POST /oto-etiket/hizli-etiketle
     */
    public function quickTagByKeyword(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
            'tag_name' => 'required|string|max:255',
            'tag_color' => 'nullable|string|max:7',
            'ignored_ids' => 'nullable|array',
            'bank_id' => 'nullable|exists:banks,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id'
        ]);

        $keyword = strtoupper(trim($request->keyword));
        $tagName = trim($request->tag_name);
        $tagColor = $request->tag_color ?: '#' . substr(md5($tagName), 0, 6);
        $ignoredIds = $request->ignored_ids ?? [];
        $bankId = $request->input('bank_id');
        $accountId = $request->input('bank_account_id');

        // Etiket yoksa oluştur
        $tag = Tag::firstOrCreate(
            ['name' => $tagName],
            ['color' => $tagColor]
        );

        // Kuralı kaydet (Aynı banka/hesap kısıtıyla yeni kural olarak bağlanabilir)
        $rule = AutoTagRule::updateOrCreate(
            [
                'keyword' => $keyword,
                'bank_id' => $bankId,
                'bank_account_id' => $accountId,
            ],
            ['tag_id' => $tag->id]
        );

        // Eşleşen işlemleri arka planda etiketle (ignored_ids hariç)
        \App\Jobs\ApplyAutoTagRulesJob::dispatch($rule->id, null, [], $ignoredIds);

        return response()->json([
            'status' => 'success',
            'message' => "✅ Kural kaydedildi. \"{$tagName}\" etiketi arka planda uygulanıyor.",
            'tag' => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color],
            'rule_id' => $rule->id,
        ]);
    }

    /**
     * AJAX: Anahtar kelimeyle eşleşen işlemleri soft-delete ile kaldır.
     * DELETE /oto-etiket/islemleri-sil   body: { keyword: "BLOKE" }
     */
    public function deleteMatchingTransactions(Request $request)
    {
        $keyword = strtoupper(trim($request->input('keyword', '')));

        if (strlen($keyword) < 2) {
            return response()->json(['status' => 'error', 'message' => 'Kelime en az 2 karakter olmalı.'], 422);
        }

        $query = Transaction::where('description', 'REGEXP', '\\b' . $keyword . '\\b');

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->input('bank_account_id'));
        } elseif ($request->filled('bank_id')) {
            $query->whereHas('bankAccount', fn($q) => $q->where('bank_id', $request->input('bank_id')));
        }

        $deleted = $query->delete(); // SoftDelete veya hard delete (Transaction modeline göre)

        return response()->json([
            'status' => 'success',
            'message' => "🗑️ {$deleted} işlem kaldırıldı.",
            'count' => $deleted,
        ]);
    }

    /**
     * Auto-Tag Yönetim Sayfasını Göster.
     *
     * ─────────────────────────────────────────────────────────────────────
     */
    public function autoTagIndex(Request $request)
    {
        $tags = Tag::orderBy('name')->get();
        // Kuralları listelerken hem genel hem özel olanları çekiyoruz
        $rules = AutoTagRule::with(['tag', 'bank', 'bankAccount'])->orderByDesc('created_at')->get();
        $exclusions = AutoTagExclusion::orderBy('keyword')->get();

        $banks = Bank::with('bankAccounts')->orderBy('bank_name')->get();
        $bankAccounts = BankAccount::orderBy('account_name')->get();

        $filterParams = [
            'min_amount' => $request->input('min_amount'),
            'max_amount' => $request->input('max_amount'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'bank_id' => $request->input('bank_id'),
            'bank_account_id' => $request->input('bank_account_id'),
            'n_clusters' => (int) $request->input('n_clusters', 20),
        ];

        [$clusters, $clusterError] = $this->runClustering($exclusions, $filterParams);

        return view('auto_tag.index', compact(
            'tags',
            'rules',
            'exclusions',
            'banks',
            'bankAccounts',
            'clusters',
            'clusterError',
            'filterParams'
        ));
    }

    /**
     * AJAX: Mevcut DB exclusions ile kümelemeyi yeniden çalıştır → JSON döner.
     * Exclusion eklendiğinde/silindiğinde frontend bu endpoint'i çağırarak
     * küme kartlarını sayfayı yenilemeden günceller.
     */
    public function clustersJson(Request $request)
    {
        $exclusions = AutoTagExclusion::orderBy('keyword')->get();

        $filterParams = [
            'min_amount' => $request->input('min_amount'),
            'max_amount' => $request->input('max_amount'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'bank_id' => $request->input('bank_id'),
            'bank_account_id' => $request->input('bank_account_id'),
            'n_clusters' => (int) $request->input('n_clusters', 20),
        ];

        [$clusters, $clusterError] = $this->runClustering($exclusions, $filterParams);

        if ($clusterError) {
            return response()->json(['status' => 'error', 'message' => $clusterError], 500);
        }

        // Tags da lazım (auto-suggest için her kümeyle birlikte dön)
        $tags = Tag::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'status' => 'success',
            'clusters' => $clusters,
            'tags' => $tags,
        ]);
    }

    /**
     * Kümeleme mantığı: DB sorgusu + Python ML çağrısı + etiketlenme sayıları.
     * Return: [clusters[], clusterError|null]
     */
    private function runClustering($exclusions, array $filterParams): array
    {
        $clusters = [];
        $clusterError = null;

        $query = \App\Models\Transaction::query()
            ->where('is_real', 1)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->whereNotIn('transaction_type_code', ['VRM', 'TRF']);

        if (!empty($filterParams['min_amount']))
            $query->where('amount', '<=', -(float) $filterParams['min_amount']);
        if (!empty($filterParams['max_amount']))
            $query->where('amount', '>=', -(float) $filterParams['max_amount']);
        if (!empty($filterParams['start_date']))
            $query->where('transaction_date', '>=', $filterParams['start_date'] . ' 00:00:00');
        if (!empty($filterParams['end_date']))
            $query->where('transaction_date', '<=', $filterParams['end_date'] . ' 23:59:59');

        if (!empty($filterParams['bank_account_id'])) {
            $query->where('bank_account_id', $filterParams['bank_account_id']);
        } elseif (!empty($filterParams['bank_id'])) {
            $query->whereHas('bankAccount', fn($q) => $q->where('bank_id', $filterParams['bank_id']));
        }

        try {
            $exclusionKeywords = $exclusions->pluck('keyword')->toArray();
            $txnData = $query->limit(5000)->get(['id', 'description']);

            if ($txnData->count() > 0) {
                $response = \Illuminate\Support\Facades\Http::timeout(60)
                    ->post('http://python_ml:8000/api/cluster_transactions', [
                        'descriptions' => $txnData->pluck('description')->toArray(),
                        'transaction_ids' => $txnData->pluck('id')->toArray(),
                        'n_clusters' => $filterParams['n_clusters'] ?? 20,
                        'exclusions' => $exclusionKeywords,
                    ]);

                if ($response->successful()) {
                    $rawClusters = $response->json('clusters', []);

                    // Her kümedeki işlemlerin kaçının zaten etiketli olduğunu hesapla
                    $allIds = collect($rawClusters)->flatMap(fn($c) => $c['transaction_ids'] ?? [])->unique()->toArray();
                    $taggedSet = \DB::table('transaction_tag')
                        ->whereIn('transaction_id', $allIds)
                        ->distinct('transaction_id')
                        ->pluck('transaction_id')
                        ->flip()->toArray();

                    foreach ($rawClusters as &$cluster) {
                        $ids = $cluster['transaction_ids'] ?? [];
                        $taggedCount = count(array_filter($ids, fn($id) => isset($taggedSet[$id])));
                        $cluster['tagged_count'] = $taggedCount;
                        $cluster['untagged_count'] = count($ids) - $taggedCount;
                        $cluster['tagged_pct'] = count($ids) > 0
                            ? round($taggedCount / count($ids) * 100) : 0;
                    }
                    unset($cluster);
                    $clusters = $rawClusters;
                } else {
                    $clusterError = 'Python servisi hata döndürdü: HTTP ' . $response->status();
                }
            }
        } catch (\Exception $e) {
            $clusterError = $e->getMessage();
            \Log::error('Auto-Tag NLP Servis Hatası: ' . $e->getMessage());
        }

        return [$clusters, $clusterError];
    }

    /**
     * Bir kümedeki TÜM transaction_id'leri doğrudan etiketle (Kural kaydetmeden).
     */
    public function applyCluster(Request $request)
    {
        $request->validate([
            'tag_id' => 'required|exists:tags,id',
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'integer',
            'keyword' => 'nullable|string|max:255',
            'bank_id' => 'nullable|exists:banks,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id'
        ]);

        $txnIds = $request->transaction_ids;
        $tagId = $request->tag_id;

        $transactions = Transaction::whereIn('id', $txnIds)->get();
        $count = 0;
        foreach ($transactions as $txn) {
            /** @var \App\Models\Transaction $txn */
            $txn->tags()->syncWithoutDetaching([$tagId]);
            $count++;
        }

        // Eğer keyword de geldiyse kural olarak da kaydet (gelecek için)
        if ($request->filled('keyword')) {
            AutoTagRule::updateOrCreate(
                [
                    'keyword' => strtoupper(trim($request->keyword)),
                    'bank_id' => $request->bank_id,
                    'bank_account_id' => $request->bank_account_id
                ],
                ['tag_id' => $tagId]
            );
        }

        return back()->with('mesaj', "✅ {$count} işleme etiket basıldı.");
    }

    /**
     * Bir anahtar kelimeyi belirli bir etiketle eşleştiren kural kaydet.
     */
    public function saveAutoTagRule(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
            'tag_id' => 'required|exists:tags,id',
            'bank_id' => 'nullable|exists:banks,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        $keyword = strtoupper(trim($request->keyword));

        AutoTagRule::updateOrCreate(
            [
                'keyword' => $keyword,
                'bank_id' => $request->bank_id,
                'bank_account_id' => $request->bank_account_id
            ],
            ['tag_id' => $request->tag_id]
        );

        $matching = Transaction::where('description', 'REGEXP', '\\b' . $keyword . '\\b')
            ->get();
        foreach ($matching as $txn) {
            /** @var \App\Models\Transaction $txn */
            $txn->tags()->syncWithoutDetaching([$request->tag_id]);
            $count++;
        }

        return back()->with('mesaj', "✅ Kural kaydedildi ve {$count} işleme hemen uygulandı.");
    }

    /**
     * Tüm kayıtlı kuralları geçmişe dönük etiketlenmemiş işlemlere uygula.
     */
    public function applyAutoTagRules()
    {
        $rules = AutoTagRule::with('tag')->get();
        $totalTagged = 0;

        foreach ($rules as $rule) {
            $keyword = $rule->keyword;

            $query = Transaction::where('description', 'REGEXP', '\\b' . $keyword . '\\b');

            if ($rule->bank_account_id) {
                $query->where('bank_account_id', $rule->bank_account_id);
            } elseif ($rule->bank_id) {
                $query->whereHas('bankAccount', fn($q) => $q->where('bank_id', $rule->bank_id));
            }

            $matchingTxns = $query->get();

            foreach ($matchingTxns as $txn) {
                /** @var \App\Models\Transaction $txn */
                $txn->tags()->syncWithoutDetaching([$rule->tag_id]);
                $totalTagged++;
            }
        }

        return back()->with('mesaj', "Tamamlandı! Toplam {$totalTagged} işlem otomatik etiketlendi.");
    }

    /**
     * Bir auto-tag kuralını sil.
     */
    public function deleteAutoTagRule(Request $request, $id)
    {
        $rule = AutoTagRule::findOrFail($id);
        $keyword = $rule->keyword;
        $rule->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => '🗑️ Kural silindi.',
                'id' => $id,
                'keyword' => $keyword,
            ]);
        }

        return back()->with('mesaj', '🗑️ Kural silindi.');
    }

    /**
     * Analizden hariç tutulacak kelime ekle.
     */
    public function addExclusion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string|max:255|unique:auto_tag_exclusions,keyword',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bu kelime zaten listede.',
                ], 422);
            }
            return back()->withErrors($validator);
        }

        $exclusion = AutoTagExclusion::create(['keyword' => strtoupper(trim($request->keyword))]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => ' Kelime hariç tutuldu.',
                'exclusion' => $exclusion
            ]);
        }

        return back()->with('mesaj', ' Kelime analizden hariç tutuldu.');
    }

    /**
     * Analizden hariç tutulan kelimeyi sil.
     */
    public function deleteExclusion($id)
    {
        $exclusion = AutoTagExclusion::findOrFail($id);
        $keyword = $exclusion->keyword;
        $exclusion->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => '🗑️ Hariç tutulan kelime silindi.',
                'keyword' => $keyword
            ]);
        }

        return back()->with('mesaj', '🗑️ Hariç tutulan kelime silindi.');
    }

    // =========================================================================
    // YAPAY ZEKA (AI) VERGİ/SGK SINIFLAMASI BÖLÜMÜ
    // =========================================================================

    /**
     * Python tarafındaki ML modelini veritabanındaki verilerle eğitir.
     */
    public function trainTaxClassifier(Request $request)
    {
        ini_set('memory_limit', '512M'); // Çok fazla veri dönme ihtimaline karşı

        // Tüm gerçek verileri çekiyoruz (Limit koymuyoruz, 28 bin civarı verinin tamamı çekilecek)
        $transactions = \DB::table('transactions')
            ->where('is_real', 1)
            ->whereNotNull('description')
            ->orderBy('id', 'desc')
            ->get(['description', 'amount', 'transaction_date']);

        $descriptions = [];
        $amounts = [];
        $dates = [];
        $labels = [];
        $taxCount = 0;

        foreach ($transactions as $t) {
            $descriptions[] = $t->description;
            $amounts[] = (float) $t->amount;
            $dates[] = $t->transaction_date;
            
            // Açıklamasında İngilizce/Türkçe karakter fark etmeksizin "VERGİ" geçenler = 1
            $descLower = mb_strtolower($t->description, 'UTF-8');
            $isTax = (str_contains($descLower, 'vergi') || str_contains($descLower, 'vergı')) ? 1 : 0;
            
            $labels[] = $isTax;
            if ($isTax) {
                $taxCount++;
            }
        }

        if ($taxCount < 10) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Sistemde açıklamasında "vergi" kelimesi geçen en az 10 işlem bulunamadı. Yapay zeka henüz karakteristiği öğrenemez.'
            ]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)
                ->post('http://python_ml:8000/api/train_tax_classifier', [
                    'descriptions' => $descriptions,
                    'amounts' => $amounts,
                    'dates' => $dates,
                    'is_tax' => $labels
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['status' => 'error', 'message' => 'Python servisi hata döndürdü.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Bağlantı hatası: ' . $e->getMessage()]);
        }
    }

    /**
     * Hiç etiketi olmayan işlemleri Python'a sorup "Vergi" olma ihtimali %80 üzeri olanları döndürür
     */
    public function predictTaxClassifier(Request $request)
    {
        // Hiç etiketi olmayan, ayrıca açıklamasında 'vergi' kelimesi GEÇMEYEN işlemleri al
        $untaggedIds = \DB::table('transactions')
            ->leftJoin('transaction_tag', 'transactions.id', '=', 'transaction_tag.transaction_id')
            ->whereNull('transaction_tag.tag_id')
            ->where('is_real', 1)
            ->where(function($q) {
                $q->where('transactions.description', 'not like', '%vergi%')
                  ->where('transactions.description', 'not like', '%vergİ%')
                  ->where('transactions.description', 'not like', '%VERGI%')
                  ->where('transactions.description', 'not like', '%VERGİ%')
                  ->where('transactions.description', 'not like', '%vergı%');
            })
            ->whereNotIn('transaction_type_code', ['VRM', 'TRF', 'EFT', 'HAV']) // Temel net transfer kodlarını pas geç
            ->whereNotNull('description')
            ->orderBy('transactions.id', 'desc')
            ->limit(2000) // Performans için en son 2000 belirsiz işlemi tarayalım
            ->pluck('transactions.id')
            ->toArray();

        if (empty($untaggedIds)) {
            return response()->json(['status' => 'success', 'predictions' => [], 'found_count' => 0]);
        }

        $transactions = Transaction::whereIn('id', $untaggedIds)
            ->get(['id', 'description', 'amount', 'transaction_date']);

        $payload = [
            'transaction_ids' => $transactions->pluck('id')->toArray(),
            'descriptions' => $transactions->pluck('description')->toArray(),
            'amounts' => $transactions->pluck('amount')->map(fn($a) => (float)$a)->toArray(),
            'dates' => $transactions->pluck('transaction_date')->toArray(),
        ];

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)
                ->post('http://python_ml:8000/api/predict_tax', $payload);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['status' => 'error', 'message' => 'Python servisi hata döndürdü.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Bağlantı hatası: ' . $e->getMessage()]);
        }
    }

    /**
     * Kullanıcının arayüzden onayladığı yapay zeka tahminlerine etiket atar.
     */
    public function applyTaxPredictions(Request $request)
    {
        $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'integer'
        ]);

        // Özel "🤖 AI: Vergi/SGK" etiketini bul veya oluştur
        $aiTag = Tag::firstOrCreate(
            ['name' => '🤖 AI: Vergi/SGK'],
            ['color' => '#8b5cf6'] // Mor renk (AI hissi)
        );

        $transactions = Transaction::whereIn('id', $request->transaction_ids)->get();
        $count = 0;
        foreach ($transactions as $txn) {
            $txn->tags()->syncWithoutDetaching([$aiTag->id]);
            $count++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "$count adet işleme AI etiketi başarıyla eklendi."
        ]);
    }

    // ========================================================================
    // 🤖 MULTI-CLASS YAPISAL SINIFLANDIRICI (STRUCTURAL AI)
    // ========================================================================

    public function trainStructuralClassifier(Request $request)
    {
        ini_set('memory_limit', '512M');

        // Sınıflandırma yapabileceğimiz ana verileri çekelim
        $transactions = \DB::table('transactions')
            ->where('is_real', 1)
            ->whereNotNull('description')
            ->orderBy('id', 'desc')
            ->get(['description', 'amount', 'transaction_date', 'transaction_type_code']);

        $descriptions = [];
        $amounts = [];
        $dates = [];
        $codes = [];
        $labels = [];

        foreach ($transactions as $t) {
            $descLower = mb_strtolower($t->description, 'UTF-8');
            $amt = (float) $t->amount;
            $code = strtoupper($t->transaction_type_code ?? '');
            
            // Varsayılan: 0 (Diğer)
            $label = 0; 
            
            // Sınıf 1: Vergi / SGK / Yasal
            if (str_contains($descLower, 'vergi') || str_contains($descLower, 'vergı') || in_array($code, ['VERGİ', 'TAX', 'SGK'])) {
                $label = 1;
            }
            // Sınıf 2: Masraf / Komisyon / Ücret (genellikle küçük eksi tutarlar veya net komisyon kodları)
            elseif ($amt < 0 && (in_array($code, ['CHG']) || str_contains($descLower, 'masraf') || str_contains($descLower, 'komisyon') || str_contains($descLower, 'ücret') || str_contains($descLower, 'ucret'))) {
                $label = 2;
            }
            // Sınıf 3: İthalat / Dış Ticaret / Yüksek Hacimli Gider (COL, 4283 ve büyük eksi tutarlar)
            elseif ($amt < 0 && (in_array($code, ['COL', '4283', 'FEX']) || ($amt < -50000 && str_contains($descLower, 'ithalat')))) {
                $label = 3;
            }

            $descriptions[] = $t->description;
            $amounts[] = $amt;
            $dates[] = $t->transaction_date;
            $codes[] = $code;
            $labels[] = $label;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(120)->post('http://python_ml:8000/api/train_structural_classifier', [
                'descriptions' => $descriptions,
                'amounts' => $amounts,
                'dates' => $dates,
                'codes' => $codes,
                'labels' => $labels
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Python ML servisine bağlanılamadı: ' . $e->getMessage()
            ], 500);
        }
    }

    public function predictStructuralClassifier(Request $request)
    {
        // Tür kodu belli olmayan ve etiketi olmayan işlemleri al (son 2000)
        $untaggedIds = \DB::table('transactions')
            ->leftJoin('transaction_tag', 'transactions.id', '=', 'transaction_tag.transaction_id')
            ->whereNull('transaction_tag.tag_id')
            ->where('is_real', 1)
            ->whereNotIn('transaction_type_code', ['VRM', 'TRF', 'EFT', 'HAV']) // Temel transferleri dışla
            ->whereNotNull('description')
            ->orderBy('transactions.id', 'desc')
            ->limit(2000)
            ->pluck('transactions.id')
            ->toArray();

        if (empty($untaggedIds)) {
            return response()->json(['status' => 'success', 'found_count' => 0, 'predictions' => []]);
        }

        // Seçilen işlemlerin detayları
        $transactions = \DB::table('transactions')
            ->whereIn('id', $untaggedIds)
            ->get(['id', 'description', 'amount', 'transaction_date', 'transaction_type_code']);

        $reqIds = [];
        $descriptions = [];
        $amounts = [];
        $dates = [];
        $codes = [];

        foreach ($transactions as $t) {
            $reqIds[] = $t->id;
            $descriptions[] = $t->description;
            $amounts[] = (float) $t->amount;
            $dates[] = $t->transaction_date;
            $codes[] = strtoupper($t->transaction_type_code ?? '');
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)->post('http://python_ml:8000/api/predict_structural_classifier', [
                'transaction_ids' => $reqIds,
                'descriptions' => $descriptions,
                'amounts' => $amounts,
                'dates' => $dates,
                'codes' => $codes
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Python ML servisine bağlanılamadı: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================================================
    // 🔍 KÜME İŞLEM LİSTESİ (Cluster Transaction Details)
    // ========================================================================
    public function getClusterTransactions(Request $request)
    {
        $ids = $request->input('transaction_ids', []);
        
        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'ID listesi boş.']);
        }

        $transactions = \DB::table('transactions')
            ->whereIn('id', $ids)
            ->orderBy('transaction_date', 'desc')
            ->get(['id', 'description', 'amount', 'transaction_date', 'transaction_type_code']);

        return response()->json([
            'status' => 'success',
            'count'  => count($transactions),
            'transactions' => $transactions
        ]);
    }
}