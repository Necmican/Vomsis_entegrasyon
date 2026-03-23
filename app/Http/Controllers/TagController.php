<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use App\Models\Transaction; 

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
            'name'  => 'required|string|max:255|unique:tags,name', // Aynı isimde etiket olmasın
            'color' => 'required|string|max:7', 
        ]);

        // 3. Etiketi Kaydet
        Tag::create([
            'name'  => $request->name,
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
        $transaction = Transaction::findOrFail($transactionId);
        $transaction->tags()->detach($tagId);

        return back()->with('mesaj', '🗑️ Etiket işlemden çıkarıldı.');
    }

    public function bulkAttachTags(Request $request)
    {
        // 1. Gelen Verileri Doğrula (Validation)
        $request->validate([
            'transaction_ids'   => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id', 
            'tag_ids'           => 'required|array',
            'tag_ids.*'         => 'exists:tags,id',    
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
            
            $transaction->tags()->syncWithoutDetaching($request->tag_ids);
        }

        return back()->with('mesaj', count($request->transaction_ids) . ' adet işleme başarıyla etiketler eklendi.');
    }
    public function bulkDetachTags(Request $request)
    {
        // 1. Doğrulama
        $request->validate([
            'transaction_ids'   => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'tag_ids'           => 'required|array',
            'tag_ids.*'         => 'exists:tags,id',
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
            // Eğer işlemde o etiket yoksa hata vermez, sessizce geçer. Varsa siler.
            $transaction->tags()->detach($request->tag_ids);
        }

        return back()->with('mesaj', count($request->transaction_ids) . ' adet işlemden seçtiğiniz etiketler başarıyla kaldırıldı.');
    }
}