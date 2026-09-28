<?php

namespace App\Console\Commands;

use App\Models\Subdomain;
use App\Services\SubdomainService;
use Illuminate\Console\Command;
use Throwable;

class ExpireSubdomains extends Command
{
    protected $signature = 'dns:expire';

    protected $description = 'Remove DNS records and expire subdomains whose annual registration has ended';

    public function handle(SubdomainService $service): int
    {
        $failures = 0;

        Subdomain::query()
            ->where('status', Subdomain::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->with(['managedDomain', 'dnsRecords'])
            ->each(function (Subdomain $subdomain) use ($service, &$failures): void {
                try {
                    $service->expire($subdomain);
                    $this->info("Expired {$subdomain->fqdn}.");
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Could not expire {$subdomain->fqdn}: {$exception->getMessage()}");
                    $failures++;
                }
            });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
