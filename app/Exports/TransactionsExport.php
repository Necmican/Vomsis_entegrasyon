<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


use App\Models\BankAccount;

class TransactionsExport implements WithMultipleSheets
{
    use Exportable;

    protected $baseQuery;
    protected $params;
    protected $task;
    protected $totalRows;

    public function __construct($baseQuery, $params, $task, $totalRows)
    {
        $this->baseQuery = $baseQuery;
        $this->params = $params;
        $this->task = $task;
        $this->totalRows = $totalRows > 0 ? $totalRows : 1;
    }

    public function sheets(): array
    {
        $sheets = [];
        $ayriBankalar = !empty($this->params['separate_banks']);
        $ayriHesaplar = !empty($this->params['separate_accounts']);

        if ($ayriHesaplar) {
            $accountIds = (clone $this->baseQuery)->select('bank_account_id')->distinct()->pluck('bank_account_id');
            
            foreach ($accountIds as $accountId) {
                $q = (clone $this->baseQuery)->where('bank_account_id', $accountId);
                $accountName = BankAccount::find($accountId)->account_name ?? 'Hesap ' . $accountId;
                $sheets[] = new TransactionSheet($q, $accountName, $this->task, $this->totalRows);
            }
        } 
        elseif ($ayriBankalar) {
            $accountIds = (clone $this->baseQuery)->select('bank_account_id')->distinct()->pluck('bank_account_id');
            $bankAccounts = BankAccount::with('bank')->whereIn('id', $accountIds)->get();
            $bankIds = $bankAccounts->pluck('bank_id')->unique();

            foreach ($bankIds as $bankId) {
                $q = (clone $this->baseQuery)->whereHas('bankAccount', function($sq) use ($bankId) {
                    $sq->where('bank_id', $bankId);
                });
                $bankName = $bankAccounts->where('bank_id', $bankId)->first()->bank->bank_name ?? 'Banka ' . $bankId;
                $sheets[] = new TransactionSheet($q, $bankName, $this->task, $this->totalRows);
            }
        } 
        else {
            $sheets[] = new TransactionSheet(clone $this->baseQuery, 'Tüm İşlemler', $this->task, $this->totalRows);
        }

        return $sheets;
    }
}