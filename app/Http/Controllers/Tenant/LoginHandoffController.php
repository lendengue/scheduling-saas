<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\LoginHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginHandoffController extends Controller
{
    public function __invoke(Request $request, string $token, LoginHandoff $handoff): RedirectResponse
    {
        $redeemed = $handoff->redeem($token);

        if ($redeemed === null) {
            return redirect()->route('login')->with('status', 'This sign-in link has expired. Please log in.');
        }

        Auth::guard('web')->login($redeemed['user']);
        $request->session()->regenerate();

        return redirect($redeemed['redirect_to']);
    }
}
