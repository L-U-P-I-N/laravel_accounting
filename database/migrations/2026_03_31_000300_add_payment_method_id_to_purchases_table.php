<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchases', 'payment_method_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained('payment_methods')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('payment_methods')) {
            return;
        }

        DB::table('purchases')
            ->select(['id', 'company_id', 'payment_status', 'payment_method'])
            ->whereNull('payment_method_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $purchase): void {
                $paymentMethodId = $this->resolvePurchasePaymentMethodId($purchase);

                DB::table('purchases')
                    ->where('id', $purchase->id)
                    ->update(['payment_method_id' => $paymentMethodId]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('purchases', 'payment_method_id')) {
            return;
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }

    private function resolvePurchasePaymentMethodId(object $purchase): ?int
    {
        $status = strtolower(trim((string) $purchase->payment_status));
        $legacyMethod = strtolower(trim((string) $purchase->payment_method));

        if ($status === 'pending' || $legacyMethod === '' || $legacyMethod === 'payables') {
            return null;
        }

        $config = match ($legacyMethod) {
            'cash' => ['name' => 'نقدي', 'code' => 'CASH', 'type' => 'cash'],
            'bank_transfer' => ['name' => 'تحويل بنكي', 'code' => 'BANK_TRANSFER', 'type' => 'bank'],
            'cheque' => ['name' => 'شيك', 'code' => 'CHEQUE', 'type' => 'bank'],
            'credit_card' => ['name' => 'بطاقة ائتمان', 'code' => 'CREDIT_CARD', 'type' => 'card'],
            'other' => ['name' => 'أخرى', 'code' => 'OTHER', 'type' => 'other'],
            default => [
                'name' => (string) $purchase->payment_method,
                'code' => Str::upper(Str::snake((string) $purchase->payment_method, '_')),
                'type' => 'other',
            ],
        };

        return $this->firstOrCreatePaymentMethodId((int) $purchase->company_id, $config);
    }

    private function firstOrCreatePaymentMethodId(int $companyId, array $config): int
    {
        $existingId = DB::table('payment_methods')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($config) {
                $query->whereRaw('UPPER(code) = ?', [Str::upper($config['code'])])
                    ->orWhere('name', $config['name']);
            })
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        $matchingTypeId = DB::table('payment_methods')
            ->where('company_id', $companyId)
            ->where('type', $config['type'])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        if ($matchingTypeId) {
            return (int) $matchingTypeId;
        }

        return (int) DB::table('payment_methods')->insertGetId([
            'company_id' => $companyId,
            'name' => $config['name'],
            'code' => Str::upper($config['code']),
            'type' => $config['type'],
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
