<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class TransactionSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    private $transactions;
    private $title;

    public function __construct($transactions, $title)
    {
        $this->transactions = $transactions;
        // Sekme isminde Excel'in kızacağı geçersiz karakterleri temizliyoruz
        $this->title = str_replace(['*', ':', '?', '[', ']'], '', $title);
    }

    // 1. Verileri veriyoruz
    public function collection()
    {
        return $this->transactions;
    }

    // 2. Excel'in en üstündeki başlık satırını (A1, B1, C1...) yazıyoruz
    public function headings(): array
    {
        return [
            'Tarih',
            'Banka',
            'Hesap Adı',
            'Kur',
            'İşlem Tipi',
            'Açıklama',
            'Tutar',
            'Bakiye',
        ];
    }

    // 3. Veritabanındaki karmaşık veriyi Excel satırlarına (map) eşliyoruz
    public function map($transaction): array
    {
        return [
            Carbon::parse($transaction->transaction_date)->format('d.m.Y H:i'),
            $transaction->bankAccount->bank->bank_name ?? '-',
            $transaction->bankAccount->account_name ?? '-',
            $transaction->bankAccount->currency ?? '-',
            $transaction->transactionType->name ?? 'Diğer',
            $transaction->description,
            // Excel'de sayıların düzgün toplanabilmesi için formatlama yapıyoruz
            number_format($transaction->amount, 2, ',', ''), 
            number_format($transaction->balance, 2, ',', ''),
        ];
    }

    // 4. Alttaki sekmenin (Sheet) adını belirliyoruz
    public function title(): string
    {
        // Excel sekme isimleri maksimum 31 karakter olabilir, fazlasını kesiyoruz
        return substr($this->title, 0, 31);
    }
}