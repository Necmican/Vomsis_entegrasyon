<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    
   protected $fillable = ['vomsis_type_id', 'name', 'code'];
}
