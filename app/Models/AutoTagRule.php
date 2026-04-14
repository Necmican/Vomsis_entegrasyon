<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoTagRule extends Model
{
    protected $fillable = ['keyword', 'tag_id', 'bank_id', 'bank_account_id'];

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
