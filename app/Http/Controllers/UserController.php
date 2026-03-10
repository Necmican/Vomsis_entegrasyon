<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        
        return view('users.create', compact('bankalar'));
    }

    // 2. VERİYİ KAYDETME (Sadece Admin)
    public function store(Request $request)
    {
        // GÜVENLİK DUVARI: Biri postman veya başka bir yolla buraya veri yollamaya çalışırsa diye kaydetmeden önce de soruyoruz.
        if (auth()->user()->role !== 'admin') {
            return redirect()->route('dashboard')->with('error', 'Personel ekleme yetkiniz bulunmamaktadır.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $yetkiliBankalar = $request->input('allowed_banks', []);

        $sanalPosYetkisi = $request->has('can_view_pos');

        User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'role'          => 'user', 
            'can_view_pos'  => $sanalPosYetkisi,
            'allowed_banks' => empty($yetkiliBankalar) ? null : array_values($yetkiliBankalar),
        ]);

        return redirect()->route('dashboard')->with('success', 'Personel başarıyla eklendi ve yetkileri tanımlandı.');
    }
}