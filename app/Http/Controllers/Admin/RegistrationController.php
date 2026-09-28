<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subdomain;
use App\Services\SubdomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RegistrationController extends Controller
{
    public function index(): View
    {
        return view('admin.registrations.index', [
            'registrations' => Subdomain::query()
                ->with(['user', 'managedDomain', 'approver'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(50),
        ]);
    }

    public function approve(Request $request, Subdomain $subdomain, SubdomainService $service): RedirectResponse
    {
        $service->approve($subdomain, $request->user());

        return back()->with('success', "{$subdomain->fqdn} was approved for one year.");
    }

    public function reject(Request $request, Subdomain $subdomain, SubdomainService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $service->reject($subdomain, $request->user(), $validated['reason']);

        return back()->with('success', "{$subdomain->fqdn} was rejected.");
    }

    public function restore(Subdomain $subdomain, SubdomainService $service): RedirectResponse
    {
        $status = $service->restore($subdomain);

        $message = $status === Subdomain::STATUS_EXPIRED
            ? "{$subdomain->fqdn} was released from its hold, but it had expired and its DNS records were removed."
            : "{$subdomain->fqdn} was restored.";

        return back()->with('success', $message);
    }

    public function destroy(Request $request, Subdomain $subdomain, SubdomainService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $fqdn = $subdomain->fqdn;
            $service->deletePermanently($subdomain, $validated['reason'] ?? 'Deleted by an administrator.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'cloudflare' => 'The subdomain was not deleted because Cloudflare cleanup failed: '.$exception->getMessage(),
            ]);
        }

        return back()->with('success', "{$fqdn} and all of its DNS records were deleted.");
    }
}
