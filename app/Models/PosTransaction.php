<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'virtual_pos_id', 'order_id', 'amount', 'currency', 'installments',
        'card_mask', 'status', 'response_code', 'error_message', 'transaction_date'
    ];

    // İLİŞKİ: Bu işlem HANGİ Sanal POS'a ait? (Tekil - BelongsTo)
    public function virtualPos()
    {
        return $this->belongsTo(VirtualPos::class);
    }
}