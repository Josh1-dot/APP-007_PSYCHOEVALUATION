<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true])) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects ou compte inactif.']);
        }
        $r->session()->regenerate();
        $r->session()->put('auth_version', Auth::user()->auth_version);

        return redirect()->intended('/');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/connexion');
    }

    public function password(Request $r)
    {
        $d = $r->validate(['current_password' => 'required|current_password', 'password' => 'required|confirmed|min:12|max:128']);
        $r->user()->update(['password' => $d['password'], 'auth_version' => $r->user()->auth_version + 1]);
        DB::table('sessions')->where('user_id', $r->user()->id)->where('id', '!=', $r->session()->getId())->delete();
        $r->session()->put('auth_version', $r->user()->auth_version);
        $r->session()->regenerate();

        return back()->with('success', 'Mot de passe modifié.');
    }
}
