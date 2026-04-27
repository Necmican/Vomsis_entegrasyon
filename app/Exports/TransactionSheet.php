<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class TransactionSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    private $query;
    private $title;
    private $task;
    private $totalRows;
    private $counter = 0;

    public function __construct($query, $title, $task = null, $totalRows = 1)
    {
        $this->query = $query;
        $this->title = str_replace(['*', ':', '?', '[', ']'], '', $title);
        $this->task = $task;
        $this->totalRows = $totalRows > 0 ? $totalRows : 1;
    }

    public function query()
    {
        return $this->query;
    }

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

    public function map($transaction): array
    {
        $this->counter++;

        // Her 500 satırda bir veritabanı yormadan "percentage" değerini güncelle
        if ($this->task && $this->counter % 500 === 0) {
            $percentage = min(99, (int)round(($this->counter / $this->totalRows) * 100));
            $this->task->update(['percentage' => $percentage]);
        }

        return [
            Carbon::parse($transaction->transaction_date)->format('d.m.Y H:i'),
            $transaction->bankAccount->bank->bank_name ?? '-',
            $transaction->bankAccount->account_name ?? '-',
            $transaction->bankAccount->currency ?? '-',
            $transaction->transactionType->name ?? 'Diğer',
            $transaction->description,
            number_format($transaction->amount, 2, ',', ''), 
            number_format($transaction->balance, 2, ',', ''),
        ];
    }

    public function title(): string
    {
        return substr($this->title, 0, 31);
    }
}