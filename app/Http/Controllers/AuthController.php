<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // 1. Login Ekranını Göster
    public function showLogin()
    {
        return view('auth.login');
    }

    // 2. Giriş İşlemini Doğrula
    public function login(Request $request)
    {
        // Formdan gelen verileri kontrol et
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Bilgiler doğruysa sistemi aç
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // Başarılı girişte doğrudan ana sayfaya (veya dashboard'a) yönlendir
            return redirect()->intended('/')->with('success', 'Sisteme başarıyla giriş yaptınız.');
        }

        // Yanlışsa hata mesajıyla geri gönder
        return back()->withErrors([
            'email' => 'Girdiğiniz e-posta veya şifre hatalı.',
        ])->onlyInput('email');
    }

    // 3. Güvenli Çıkış İşlemi
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}