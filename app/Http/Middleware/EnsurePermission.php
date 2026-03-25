<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_if(!$user || !$user->hasPermission($permission), 403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة');

        return $next($request);
    }
}
