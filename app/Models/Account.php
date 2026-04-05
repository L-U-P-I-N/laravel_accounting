<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'account_type',
        'display_account_type',
        'parent_id',
        'allows_direct_transactions',
        'is_active',
        'is_system',
        'description',
        'balance',
        'company_id',
    ];

    protected $casts = [
        'allows_direct_transactions' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'balance' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function getFullNameAttribute(): string
    {
        $name = $this->name;
        if ($this->parent) {
            $name = $this->parent->getFullNameAttribute() . ' > ' . $name;
        }
        return $name;
    }

    public function updateBalance(float $debit, float $credit): void
    {
        if (in_array($this->account_type, ['asset', 'expense', 'cogs'])) {
            $this->balance += $debit - $credit;
        } else {
            $this->balance += $credit - $debit;
        }
        $this->save();
    }

    public function isDebitAccount(): bool
    {
        return in_array($this->account_type, ['asset', 'expense', 'cogs']);
    }

    public function isCreditAccount(): bool
    {
        return in_array($this->account_type, ['liability', 'equity', 'revenue']);
    }
}
