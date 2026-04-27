<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalPos extends Model
{
    protected $table = 'physical_poses';
    protected $guarded = [];

    
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }
   
    public function transactions()
    {
        return $this->hasMany(PhysicalPosTransaction::class, 'physical_pos_id');
    }
}