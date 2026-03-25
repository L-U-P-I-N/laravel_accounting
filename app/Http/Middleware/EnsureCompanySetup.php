<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Redirect;

class EnsureCompanySetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || !$user->company_id) {
            return Redirect::route('setup.company')
                ->with('warning', 'يرجى إنشاء شركة أولاً');
        }

        return $next($request);
    }
}
