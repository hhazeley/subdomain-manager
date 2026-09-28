<?php

namespace App\Http\Controllers;

use App\Models\ManagedDomain;
use App\Models\Subdomain;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'subdomains' => $request->user()->subdomains()
                ->where('status', '!=', Subdomain::STATUS_DELETION_HOLD)
                ->with('managedDomain')
                ->latest()
                ->get(),
            'domains' => ManagedDomain::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
