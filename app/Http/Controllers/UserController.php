<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tag;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // 1. EKRANI GÖSTERME (Sadece Admin)
    public function create()
    {
        // GÜVENLİK DUVARI: İçeri giren kişinin rolü 'admin' değilse geri fırlat!
        if (auth()->user()->role !== 'admin') {
            return redirect()->route('dashboard')->with('error', 'Personel ekleme sayfasına sadece Yöneticiler erişebilir.');
        }

        $bankalar = Bank::all();
        $tags = Tag::all(); // YENİ: Sistemdeki tüm etiketleri çek
        
        // YENİ: $tags değişkenini de sayfaya gönderiyoruz
        return view('users.create', compact('bankalar', 'tags'));
    }

    // 2. VERİYİ KAYDETME (Sadece Admin)
    public function store(Request $request)
    {
        // 1. Gelen verileri doğrula (Validation)
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        // 2. Yetkileri Yakala (Checkbox'tan geliyorsa true, gelmiyorsa false yap)
        $yetkiliBankalar = $request->input('allowed_banks', []);
        
        // DİKKAT: Laravel'de checkbox seçilmemişse request'te hiç gelmez.
        // Bu yüzden has() metodu ile "Bu kutucuk işaretlenmiş mi?" diye soruyoruz.
        $sanalPosYetkisi = $request->has('can_view_pos');
        $fizikselPosYetkisi = $request->has('can_view_physical_pos');
        
        // YENİ: Etiket yetkilerini yakala
        $etiketUretmeYetkisi = $request->has('can_create_tags');
        $izinVerilenEtiketler = $request->input('allowed_tags', []); 

        // 3. Kullanıcıyı Veritabanına Kaydet
        User::create([
            'name'                  => $request->name,
            'email'                 => $request->email,
            'password'              => bcrypt($request->password), // Hash::make yerine bcrypt daha pratik
            'role'                  => 'user', 
            'can_view_pos'          => $sanalPosYetkisi,
            'can_view_physical_pos' => $fizikselPosYetkisi, 
            'can_create_tags'       => $etiketUretmeYetkisi, // TİK İŞARETLENDİYSE TRUE (1) YAZACAK
            'allowed_banks'         => empty($yetkiliBankalar) ? null : array_values($yetkiliBankalar),
            'allowed_tags'          => empty($izinVerilenEtiketler) ? null : array_values($izinVerilenEtiketler), // Seçilen etiketleri JSON (Array) olarak kaydet
            'is_real_data'          => $request->input('is_real_data', 0), // Gerçek mi Demo mu verisi
        ]);

        return redirect()->route('dashboard')->with('mesaj', 'Yeni personel başarıyla eklendi ve yetkilendirildi.');
    }
}