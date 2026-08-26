<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CosudVersionCommand extends Command
{
    protected $signature = 'cosud:version';

    protected $description = 'Affiche la version applicative COSUD et la date de release';

    public function handle(): int
    {
        $name = config('app.name', 'COSUD');
        $version = config('cosud.version', '?.?.?');
        $releasedAt = config('cosud.released_at');
        $env = config('app.env');

        $this->line("{$name} {$version} ({$env})");

        if ($releasedAt) {
            $this->line("Release : {$releasedAt}");
        }

        return self::SUCCESS;
    }
}
