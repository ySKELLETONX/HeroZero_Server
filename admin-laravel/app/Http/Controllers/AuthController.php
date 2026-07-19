<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $pass = (string)$request->input('password', '');
        if ($pass !== '' && hash_equals((string)env('ADMIN_PASSWORD', 'admin123'), $pass)) {
            $request->session()->put('is_admin', true);
            $request->session()->regenerate();
            return redirect('/');
        }
        return back()->with('error', 'Senha incorreta.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('is_admin');
        return redirect('/login');
    }
}
