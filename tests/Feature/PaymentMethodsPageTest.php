<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PaymentMethodsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_methods_page_supports_index_create_update_and_delete(): void
    {
        [$company, $user] = $this->paymentMethodContext();

        $indexRequest = Request::create('/payment-methods', 'GET');
        $indexRequest->setUserResolver(fn () => $user);
        view()->share('errors', new ViewErrorBag());

        $indexView = app(AccountingPageController::class)->paymentMethods($indexRequest);

        $this->assertSame('payment_methods', $indexView->name());
        $this->assertStringContainsString('طرق الدفع', $indexView->render());

        $storeCashRequest = Request::create('/payment-methods', 'POST', [
            'payment_method_modal' => 'createPaymentMethodModal',
            'name' => 'نقدي',
            'code' => 'CASH',
            'type' => 'cash',
            'is_default' => '1',
        ]);
        $storeCashRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storePaymentMethod($storeCashRequest);

        $cashMethod = PaymentMethod::query()
            ->where('company_id', $company->id)
            ->where('code', 'CASH')
            ->firstOrFail();

        $this->assertSame('نقدي', $cashMethod->name);
        $this->assertTrue($cashMethod->is_default);

        $storeCardRequest = Request::create('/payment-methods', 'POST', [
            'payment_method_modal' => 'createPaymentMethodModal',
            'name' => 'بطاقة',
            'code' => 'CARD',
            'type' => 'card',
        ]);
        $storeCardRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storePaymentMethod($storeCardRequest);

        $cardMethod = PaymentMethod::query()
            ->where('company_id', $company->id)
            ->where('code', 'CARD')
            ->firstOrFail();

        $updateRequest = Request::create('/payment-methods/' . $cardMethod->id, 'PUT', [
            'payment_method_modal' => 'editPaymentMethodModal' . $cardMethod->id,
            'name' => 'بطاقة ائتمان',
            'code' => 'CARD',
            'type' => 'card',
        ]);
        $updateRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->updatePaymentMethod($updateRequest, $cardMethod);

        $cardMethod->refresh();
        $this->assertSame('بطاقة ائتمان', $cardMethod->name);

        $destroyRequest = Request::create('/payment-methods/' . $cardMethod->id, 'DELETE');
        $destroyRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->destroyPaymentMethod($destroyRequest, $cardMethod);

        $this->assertDatabaseMissing('payment_methods', [
            'id' => $cardMethod->id,
        ]);
    }

    private function paymentMethodContext(): array
    {
        $company = Company::create([
            'name' => 'Payment Methods Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
            'city' => 'الرياض',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Payment',
            'last_name' => 'Owner',
            'name' => 'Payment Owner',
            'email' => 'payment-methods@example.com',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        return [$company, $user];
    }
}
