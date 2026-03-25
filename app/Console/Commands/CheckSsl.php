<?php

namespace App\Console\Commands;

use App\Services\SslService;
use Illuminate\Console\Command;

class CheckSsl extends Command
{
    protected $signature = 'pixelkraft:check-ssl';
    protected $description = 'Check SSL certificate expiry for all sites';

    public function handle(SslService $ssl): int
    {
        $alerts = $ssl->checkAllCertificates();

        $this->info("SSL check complete. {$alerts} alerts generated.");

        return self::SUCCESS;
    }
}
