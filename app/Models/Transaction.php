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
    
    public function tags()
    {
        // Pivot tablonun adını ve sütunlarını Laravel'e açıkça belirtiyoruz
        return $this->belongsToMany(Tag::class, 'pos_transaction_tag', 'pos_transaction_id', 'tag_id');
    }
}
