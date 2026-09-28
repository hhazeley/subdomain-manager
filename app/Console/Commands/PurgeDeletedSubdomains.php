<?php

namespace App\Console\Commands;

use App\Models\Subdomain;
use App\Services\SubdomainService;
use Illuminate\Console\Command;
use Throwable;

class PurgeDeletedSubdomains extends Command
{
    protected $signature = 'dns:purge-deletion-holds';

    protected $description = 'Permanently delete subdomains whose 14-day deletion hold has ended';

    public function handle(SubdomainService $service): int
    {
        $failures = 0;

        Subdomain::query()
            ->where('status', Subdomain::STATUS_DELETION_HOLD)
            ->where('deletion_requested_at', '<=', now()->subDays(14))
            ->with(['managedDomain', 'dnsRecords', 'user'])
            ->each(function (Subdomain $subdomain) use ($service, &$failures): void {
                try {
                    $fqdn = $subdomain->fqdn;
                    $service->deletePermanently($subdomain, 'The 14-day deletion hold ended.');
                    $this->info("Deleted {$fqdn}.");
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Could not delete {$subdomain->fqdn}: {$exception->getMessage()}");
                    $failures++;
                }
            });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
