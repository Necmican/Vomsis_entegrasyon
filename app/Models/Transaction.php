<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    protected $fillable = [
        'vomsis_transaction_id',
        'bank_account_id',
        'description',
        'amount',
        'type',
        'balance',
        'transaction_date',
        'transaction_type_code',
        'is_real'
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'balance' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * The "booted" method of the model.
     * Uygulama genelinde bu modeleyapılan tüm sorgular bu filtreden geçer.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('isolate_data', function (Builder $builder) {
            // Eğer konsol komutu veya giriş yapmamış bir istekse, filtreleme yapma
            if (app()->runningInConsole() || !auth()->check()) {
                return;
            }

            // Kullanıcı gerçek hesapsa (is_real_data == 1) sadece is_real == 1 olanları çek
            // Demo hesap ise (is_real_data == 0) sadece is_real == 0 olanları çek
            $isRealContext = auth()->user()->is_real_data;
            
            $builder->where('is_real', $isRealContext ? 1 : 0);
        });
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_code', 'vomsis_type_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
    
    public function tags()
    {
        // Pivot tablonun adını ve sütunlarını Laravel'e açıkça belirtiyoruz
        return $this->belongsToMany(Tag::class, 'transaction_tag', 'transaction_id', 'tag_id');
    }
}
