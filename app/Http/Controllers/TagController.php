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
}