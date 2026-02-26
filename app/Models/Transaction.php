<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
    'vomsis_transaction_id',
    'bank_account_id',
    'description',
    'amount',
    'type',
    'balance',
    'transaction_date',
    'transaction_type_code'
];

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_code', 'vomsis_type_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
