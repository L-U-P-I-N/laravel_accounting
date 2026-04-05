<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountingService;
use App\Support\ChartOfAccountsSynchronizer;
use App\Support\InventoryMovementService;
use App\Support\PaymentSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:seed-demo-if-empty', function () {
    $hasOperationalData = Customer::query()->exists()
        || Supplier::query()->exists()
        || Invoice::query()->exists()
        || Purchase::query()->exists()
        || JournalEntry::query()->exists();

    if ($hasOperationalData) {
        $this->info('Skipping demo seed because the database already contains operational data.');

        return self::SUCCESS;
    }

    $this->info('Database is empty. Seeding demo data...');

    $this->call('db:seed', ['--force' => true]);

    /** @var \App\Support\ChartOfAccountsSynchronizer $chart */
    $chart = app(ChartOfAccountsSynchronizer::class);
    /** @var \App\Support\AccountingService $accounting */
    $accounting = app(AccountingService::class);
    /** @var \App\Support\PaymentSyncService $paymentSync */
    $paymentSync = app(PaymentSyncService::class);
    /** @var \App\Support\InventoryMovementService $inventorySync */
    $inventorySync = app(InventoryMovementService::class);

    Company::query()->orderBy('id')->get()->each(function (Company $company) use ($chart, $accounting, $paymentSync, $inventorySync) {
        $chart->synchronizeCompany($company);

        $user = User::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->first();

        if (! $user) {
            return;
        }

        $accounting->resyncCompanyTransactions($company, $user);

        Invoice::query()
            ->with(['items.product', 'customer', 'paymentAccount'])
            ->where('company_id', $company->id)
            ->get()
            ->each(function (Invoice $invoice) use ($paymentSync, $inventorySync) {
                $entry = JournalEntry::query()
                    ->where('source_type', Invoice::class)
                    ->where('source_id', $invoice->id)
                    ->first();

                $paymentSync->syncInvoicePayment($invoice, $entry);

                if ((string) $invoice->status !== 'draft') {
                    $inventorySync->syncInvoice($invoice);
                }
            });

        Purchase::query()
            ->with(['items.product', 'supplier', 'paymentAccount'])
            ->where('company_id', $company->id)
            ->get()
            ->each(function (Purchase $purchase) use ($paymentSync, $inventorySync) {
                $entry = JournalEntry::query()
                    ->where('source_type', Purchase::class)
                    ->where('source_id', $purchase->id)
                    ->first();

                $paymentSync->syncPurchasePayment($purchase, $entry);

                if (in_array((string) $purchase->status, ['approved', 'paid'], true)) {
                    $inventorySync->syncPurchase($purchase);
                }
            });
    });

    $this->info('Demo data, accounting entries, payments, and inventory movements were prepared successfully.');

    return self::SUCCESS;
})->purpose('Seed demo data once when the database is empty and rebuild accounting links.');
