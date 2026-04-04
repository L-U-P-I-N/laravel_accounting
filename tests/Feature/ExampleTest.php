<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = app(AuthController::class)->showLanding();

        $this->assertInstanceOf(View::class, $response);
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('landing', $response->getName());
    }
}
