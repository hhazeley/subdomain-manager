<?php

namespace App\Http\Controllers;

use App\Http\Requests\DnsRecordRequest;
use App\Models\DnsRecord;
use App\Models\Subdomain;
use App\Services\DnsRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class DnsRecordController extends Controller
{
    public function store(DnsRecordRequest $request, Subdomain $subdomain, DnsRecordService $service): RedirectResponse
    {
        try {
            $service->create($subdomain, $request->attributesForRecord());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['cloudflare' => 'Cloudflare rejected the record: '.$exception->getMessage()])->withInput();
        }

        return back()->with('success', 'DNS record created in Cloudflare.');
    }

    public function update(
        DnsRecordRequest $request,
        Subdomain $subdomain,
        DnsRecord $record,
        DnsRecordService $service,
    ): RedirectResponse {
        $this->authorizeRecord($request, $subdomain, $record);

        try {
            $service->update($record, $request->attributesForRecord());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['cloudflare' => 'Cloudflare rejected the update: '.$exception->getMessage()]);
        }

        return back()->with('success', 'DNS record updated in Cloudflare.');
    }

    public function destroy(
        Request $request,
        Subdomain $subdomain,
        DnsRecord $record,
        DnsRecordService $service,
    ): RedirectResponse {
        $this->authorizeRecord($request, $subdomain, $record);
        abort_unless($subdomain->status === Subdomain::STATUS_ACTIVE, 403);

        try {
            $service->delete($record);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['cloudflare' => 'Cloudflare could not delete the record: '.$exception->getMessage()]);
        }

        return back()->with('success', 'DNS record deleted from Cloudflare.');
    }

    private function authorizeRecord(Request $request, Subdomain $subdomain, DnsRecord $record): void
    {
        abort_unless(
            $subdomain->user_id === $request->user()->id && $record->subdomain_id === $subdomain->id,
            403,
        );
    }
}
