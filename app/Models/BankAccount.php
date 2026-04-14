<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    
    protected $fillable = [
        'vomsis_account_id',
        'bank_id',
        'iban',
        'account_name',
        'currency',
        'balance'
    ];
    
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}