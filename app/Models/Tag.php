<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color'];

    // DÜZELTİLDİ: PosTransaction silindi, asıl model olan Transaction eklendi.
    // Ayrıca pivot tablo ve kolon isimleri ters sırayla Laravel'e tanıtıldı.
    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'transaction_tag', 'tag_id', 'transaction_id');
    }
}