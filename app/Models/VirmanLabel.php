<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirmanLabel extends Model
{
    protected $fillable = ['transaction_id', 'user_id', 'label', 'source'];

    protected $casts = ['label' => 'boolean'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
