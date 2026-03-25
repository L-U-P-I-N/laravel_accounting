<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxSetting extends Model
{
    protected $fillable = [
        'tax_name',
        'tax_name_ar',
        'tax_type',
        'rate',
        'is_default',
        'account_id',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function getNameAttribute(): string
    {
        return $this->tax_name_ar ?: ($this->tax_name ?: 'إعداد ضريبي');
    }
}
