<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualPos extends Model
{
    use HasFactory;

    protected $table = 'virtual_poses';

    protected $fillable = [
        'bank_id', 'name', 'merchant_id', 'terminal_id', 
        'api_key', 'api_secret', 'currency', 'is_active'
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function transactions()
    {
        return $this->hasMany(PosTransaction::class);
    }
}