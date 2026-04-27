<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    
    protected $fillable = [
        'vomsis_bank_id',
        'bank_name'
    ];

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }
    
    public function transactions()
    {
        return $this->hasManyThrough(Transaction::class, BankAccount::class);
    }
}