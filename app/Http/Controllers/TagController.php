<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use App\Models\Transaction; 
class TagController extends Controller
{
    
    public function store(Request $request)
    {
        // 1. Gelen veriyi doğrula
        $request->validate([
            'name'  => 'required|string|max:255|unique:tags,name', // Aynı isimde etiket olmasın
            'color' => 'required|string|max:7', 
        ]);

      
        Tag::create([
            'name'  => $request->name,
            'color' => $request->color,
        ]);

       
        return back()->with('mesaj', "Harika! '{$request->name}' etiketi başarıyla oluşturuldu.");
    }
 
    public function attachTag(Request $request, $transactionId)
    {
        $request->validate(['tag_id' => 'required|exists:tags,id']);
        
        // DİKKAT: Artık PosTransaction değil, Transaction modelinde arama yapıyoruz
        $transaction = Transaction::findOrFail($transactionId);
        
        $transaction->tags()->syncWithoutDetaching([$request->tag_id]);

        return back()->with('mesaj', '🏷️ Etiket başarıyla eklendi.');
    }

    public function detachTag($transactionId, $tagId)
    {
        // DİKKAT: Artık PosTransaction değil, Transaction modelinde arama yapıyoruz
        $transaction = Transaction::findOrFail($transactionId);
        
        // detach komutu aradaki köprüyü (pivot kaydını) siler
        $transaction->tags()->detach($tagId);

        return back()->with('mesaj', '🗑️ Etiket işlemden çıkarıldı.');
    }

}