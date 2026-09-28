<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedSubdomain;
use App\Models\ManagedDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlockedSubdomainController extends Controller
{
    public function index(): View
    {
        return view('admin.blocked-subdomains.index', [
            'blocks' => BlockedSubdomain::query()->with(['managedDomain', 'creator'])->latest()->get(),
            'domains' => ManagedDomain::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['label' => Str::lower((string) $request->input('label'))]);
        $validated = $request->validate([
            'managed_domain_id' => ['nullable', 'integer', 'exists:managed_domains,id'],
            'label' => [
                'required',
                'string',
                'max:63',
                'regex:/^(?!-)[a-z0-9-]+(?<!-)$/',
                Rule::unique('blocked_subdomains')->where(
                    fn ($query) => $query->where('managed_domain_id', $request->input('managed_domain_id')),
                ),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        BlockedSubdomain::create([
            ...$validated,
            'managed_domain_id' => $validated['managed_domain_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'The subdomain label was blocked.');
    }

    public function destroy(BlockedSubdomain $blockedSubdomain): RedirectResponse
    {
        $blockedSubdomain->delete();

        return back()->with('success', 'The subdomain label is available again.');
    }
}
