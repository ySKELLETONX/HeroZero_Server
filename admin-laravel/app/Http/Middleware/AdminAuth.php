<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Protege o painel: exige login (senha unica ADMIN_PASSWORD do .env). */
class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->get('is_admin')) {
            return redirect('/login');
        }
        return $next($request);
    }
}
