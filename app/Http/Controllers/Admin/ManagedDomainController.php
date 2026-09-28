<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManagedDomain;
use App\Services\CloudflareClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ManagedDomainController extends Controller
{
    public function index(): View
    {
        return view('admin.domains.index', [
            'domains' => ManagedDomain::query()->withCount('subdomains')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CloudflareClient $cloudflare): RedirectResponse
    {
        $validated = $this->validateDomain($request);
        $validated['name'] = Str::lower($validated['name']);

        try {
            $cloudflare->verifyZone($validated['cloudflare_zone_id'], $validated['name']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['cloudflare_zone_id' => 'Cloudflare zone verification failed: '.$exception->getMessage()])->withInput();
        }

        ManagedDomain::create([...$validated, 'is_active' => true]);

        return back()->with('success', 'Domain added and verified with Cloudflare.');
    }

    public function update(Request $request, ManagedDomain $domain, CloudflareClient $cloudflare): RedirectResponse
    {
        $validated = $this->validateDomain($request, $domain);
        $validated['name'] = Str::lower($validated['name']);

        if ($domain->subdomains()->exists()
            && ($validated['name'] !== $domain->name
                || $validated['cloudflare_zone_id'] !== $domain->cloudflare_zone_id)) {
            return back()->withErrors([
                'name' => 'The domain name and Cloudflare zone cannot be changed after registrations exist.',
            ]);
        }

        try {
            $cloudflare->verifyZone($validated['cloudflare_zone_id'], $validated['name']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['cloudflare_zone_id' => 'Cloudflare zone verification failed: '.$exception->getMessage()]);
        }

        $domain->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Domain settings updated.');
    }

    private function validateDomain(Request $request, ?ManagedDomain $domain = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
                'unique:managed_domains,name'.($domain ? ",{$domain->id}" : ''),
            ],
            'cloudflare_zone_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-f0-9]{32}$/i',
                'unique:managed_domains,cloudflare_zone_id'.($domain ? ",{$domain->id}" : ''),
            ],
        ]);
    }
}
