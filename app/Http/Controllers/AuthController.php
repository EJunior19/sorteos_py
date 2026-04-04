<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // 🔹 Mostrar login
    public function login()
    {
        return view('admin.login');
    }

    // 🔹 Procesar login (SEGURO CON HASH)
    public function doLogin(Request $request)
    {
        $request->validate([
            'password' => 'required'
        ]);

        if (Hash::check($request->password, env('ADMIN_PASSWORD_HASH'))) {
            session(['admin' => true]);
            return redirect('/admin');
        }

        return back()->with('error', 'Contraseña incorrecta');
    }

    // 🔹 Logout
    public function logout()
    {
        session()->forget('admin');
        return redirect('/');
    }
}