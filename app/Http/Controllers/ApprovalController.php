<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($request->user()->isApproved()) {
            return redirect()->route('dashboard');
        }

        return view('auth.pending-approval');
    }
}
