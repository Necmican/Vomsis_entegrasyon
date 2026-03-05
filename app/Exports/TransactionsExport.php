<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class TransactionsExport implements WithMultipleSheets
{
    use Exportable;

    protected $transactions;
    protected $ayriBankalar;
    protected $ayriHesaplar;

    // Controller'dan gelen verileri (işlemler ve işaretlenen kutucuklar) burada içeri alıyoruz
    public function __construct($transactions, $ayriBankalar, $ayriHesaplar)
    {
        $this->transactions = $transactions;
        $this->ayriBankalar = $ayriBankalar;
        $this->ayriHesaplar = $ayriHesaplar;
    }

    // Bu zorunlu fonksiyon, Excel'in altındaki "Sekmeleri (Tabları)" oluşturur
    public function sheets(): array
    {
        $sheets = [];

        // 1. SENARYO: "Hesaplara Göre Ayır" seçildiyse
        if ($this->ayriHesaplar) {
            // İşlemleri hesap ID'sine göre grupla (Örn: 33 nolu hesabın işlemleri bir torbaya)
            $groupedTransactions = $this->transactions->groupBy('bank_account_id');
            
            foreach ($groupedTransactions as $accountId => $trans) {
                // Hesap adını bul (Örn: "Hesap No: 33")
                $accountName = $trans->first()->bankAccount->account_name ?? 'Hesap ' . $accountId;
                // Her bir hesap için yeni bir Excel Sekmesi (Sheet) oluştur
                $sheets[] = new TransactionSheet($trans, $accountName);
            }
        } 
        // 2. SENARYO: "Bankalara Göre Ayır" seçildiyse
        elseif ($this->ayriBankalar) {
            // İşlemleri banka ID'sine göre grupla (Büyükbabanın ID'si)
            $groupedTransactions = $this->transactions->groupBy(function($item) {
                return $item->bankAccount->bank_id;
            });

            foreach ($groupedTransactions as $bankId => $trans) {
                // Banka adını bul (Örn: "Akbank")
                $bankName = $trans->first()->bankAccount->bank->bank_name ?? 'Banka ' . $bankId;
                // Her bir banka için yeni bir sekme oluştur
                $sheets[] = new TransactionSheet($trans, $bankName);
            }
        } 
        // 3. SENARYO: Hiçbiri seçilmediyse (Hepsi tek sayfada)
        else {
            // Bütün işlemleri "Tüm İşlemler" adında tek bir sekmeye koy
            $sheets[] = new TransactionSheet($this->transactions, 'Tüm İşlemler');
        }

        // Hazırlanan sekmeleri Excel motoruna teslim et
        return $sheets;
    }
}