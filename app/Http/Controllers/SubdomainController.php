<?php

namespace App\Http\Controllers;

use App\Models\BlockedSubdomain;
use App\Models\ManagedDomain;
use App\Models\Subdomain;
use App\Services\SubdomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubdomainController extends Controller
{
    public function store(Request $request, SubdomainService $service): RedirectResponse
    {
        $request->merge(['label' => Str::lower((string) $request->input('label'))]);
        $validated = $request->validate([
            'managed_domain_id' => ['required', 'integer', 'exists:managed_domains,id'],
            'label' => [
                'required',
                'string',
                'min:1',
                'max:63',
                'regex:/^(?!-)[a-z0-9-]+(?<!-)$/i',
                Rule::unique('subdomains')->where(
                    fn ($query) => $query->where('managed_domain_id', $request->input('managed_domain_id')),
                ),
            ],
        ]);

        $domain = ManagedDomain::query()
            ->whereKey($validated['managed_domain_id'])
            ->where('is_active', true)
            ->firstOrFail();
        $label = Str::lower($validated['label']);

        $blocked = BlockedSubdomain::query()
            ->where('label', $label)
            ->where(fn ($query) => $query
                ->whereNull('managed_domain_id')
                ->orWhere('managed_domain_id', $domain->id))
            ->exists();

        if ($blocked) {
            return back()->withErrors(['label' => 'This subdomain name is reserved and cannot be registered.'])->withInput();
        }

        $subdomain = $request->user()->subdomains()->create([
            'managed_domain_id' => $domain->id,
            'label' => $label,
            'fqdn' => "{$label}.{$domain->name}",
            'status' => Subdomain::STATUS_PENDING,
        ]);
        $service->registrationRequested($subdomain->load('user'));

        return back()->with('success', 'Registration submitted for administrator approval.');
    }

    public function show(Request $request, Subdomain $subdomain): View
    {
        $this->authorizeOwner($request, $subdomain);
        abort_unless($subdomain->status === Subdomain::STATUS_ACTIVE, 404);

        return view('subdomains.show', [
            'subdomain' => $subdomain->load(['managedDomain', 'dnsRecords']),
            'recordTypes' => config('dns.record_types'),
        ]);
    }

    public function renew(Request $request, Subdomain $subdomain, SubdomainService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $subdomain);
        $service->renew($subdomain);

        return back()->with('success', 'The subdomain was renewed for one year and auto-approved.');
    }

    public function destroy(Request $request, Subdomain $subdomain, SubdomainService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $subdomain);
        $service->requestDeletion($subdomain);

        return redirect()->route('dashboard')
            ->with('success', 'The subdomain is hidden and scheduled for permanent deletion in 14 days.');
    }

    private function authorizeOwner(Request $request, Subdomain $subdomain): void
    {
        abort_unless($subdomain->user_id === $request->user()->id, 403);
    }
}
