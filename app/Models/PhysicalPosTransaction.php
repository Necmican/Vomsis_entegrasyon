<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalPosTransaction extends Model
{
    // Veritabanına toplu kayıt (mass assignment) izni
    protected $guarded = [];

    // Veritabanından tarihleri düzgün formatta çekebilmek için
    protected function casts(): array
    {
        return [
            'system_date' => 'datetime',
        ];
    }

    // İLİŞKİ: Bu işlemin bağlı olduğu Ana Fiziksel POS Cihazı
    public function physicalPos()
    {
        return $this->belongsTo(PhysicalPos::class, 'physical_pos_id');
    }
}