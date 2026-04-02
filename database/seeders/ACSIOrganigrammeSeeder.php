<?php

namespace Database\Seeders;

use App\Models\Structure;
use Illuminate\Database\Seeder;

class ACSIOrganigrammeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = $this->loadRows();
        if ($rows === []) {
            return;
        }

        // Racine
        $dg = Structure::updateOrCreate(
            ['code' => 'DG'],
            [
                'parent_id' => null,
                'nom' => 'DIRECTION GENERALE',
                'type' => 'direction',
                'actif' => true,
            ]
        );

        $dirIds = [];

        // Directions (1 niveau sous DG)
        foreach ($this->uniqueDirections($rows) as $dir) {
            $code = $this->codeDirection($dir['codeDirection'] ?? null, $dir['libDirection'] ?? null);
            $nom = (string) ($dir['libDirection'] ?? 'DIRECTION');

            $d = Structure::updateOrCreate(
                ['code' => $code],
                [
                    'parent_id' => $dg->id,
                    'nom' => $nom,
                    'type' => 'direction',
                    'actif' => true,
                ]
            );
            $dirIds[$code] = $d->id;
        }

        // Services (2e niveau sous direction) — ignorer CENTRE*
        foreach ($this->uniqueServices($rows) as $svc) {
            $libService = (string) ($svc['libService'] ?? '');
            $libDirection = (string) ($svc['libDirection'] ?? '');
            if ($this->isCentre($libService)) {
                continue;
            }
            // Évite de créer une "fausse" structure service quand la valeur service
            // est en réalité le libellé de la direction elle-même (cas des directeurs).
            if ($this->norm($libService) === $this->norm($libDirection)) {
                continue;
            }

            $canonicalSvcCode = $this->canonicalDdsaitServiceCode($libService);
            $dirCode = $canonicalSvcCode !== null
                ? 'DDSAIT'
                : $this->codeDirection($svc['codeDirection'] ?? null, $svc['libDirection'] ?? null);
            $parentId = $dirIds[$dirCode] ?? $dg->id;

            $svcCode = $canonicalSvcCode ?? $this->codeService($svc['codeService'] ?? null, $libService, $dirCode);
            if ($svcCode === null) {
                continue;
            }

            Structure::updateOrCreate(
                ['code' => $svcCode],
                [
                    'parent_id' => $parentId,
                    'nom' => $libService,
                    'type' => 'service',
                    'actif' => true,
                ]
            );
        }

        $this->assurerServicesDdsait($dg->id);
        $this->desactiverAliasesServicesDdsait();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRows(): array
    {
        $path = database_path('seeders/data/acsi_agents_full.json');
        if (! file_exists($path)) {
            $this->command?->warn("Fichier introuvable : {$path}");
            return [];
        }

        $rows = json_decode(file_get_contents($path) ?: '[]', true);
        return is_array($rows) ? $rows : [];
    }

    private function isCentre(string $libService): bool
    {
        $s = trim(mb_strtoupper($libService, 'UTF-8'));
        return str_starts_with($s, 'CENTRE');
    }

    private function codeDirection($codeDirection, $libDirection = null): string
    {
        $lib = $this->norm((string) ($libDirection ?? ''));
        if (
            str_contains($lib, 'DIRECTION DEV')
            && str_contains($lib, 'SYS')
            && str_contains($lib, 'APPL')
            && str_contains($lib, 'INNOV')
        ) {
            return 'DDSAIT';
        }

        $raw = trim((string) ($codeDirection ?? ''));
        $raw = $raw !== '' ? $raw : '0000';
        return 'DIR-'.$raw;
    }

    private function codeService($codeService, string $libService = '', string $directionCode = ''): ?string
    {
        $lib = $this->norm($libService);
        $canonicalCode = $this->canonicalDdsaitServiceCode($libService);
        if ($canonicalCode !== null) {
            return $canonicalCode;
        }
        if ($directionCode === 'DDSAIT') {
            if (str_contains($lib, 'SYSTEME D') || str_contains($lib, 'EXPL PROD') || str_contains($lib, 'INTEGRATION')) {
                return 'SVC-DDI-DEVINT';
            }
            if (str_contains($lib, 'BASE DE DONNEE') || str_contains($lib, 'BDD') || str_contains($lib, 'FORMATION')) {
                return 'SVC-DDI-BDD';
            }
            if (str_contains($lib, 'MAINT') || str_contains($lib, 'RESEAU')) {
                return 'SVC-DDI-MAINT';
            }
            if (str_contains($lib, 'VEILLE') || str_contains($lib, 'INNOVATION')) {
                return 'SVC-DDI-VEILLE';
            }
        }

        $raw = trim((string) ($codeService ?? ''));
        if ($raw === '') {
            return null;
        }
        return 'SVC-'.$raw;
    }

    private function canonicalDdsaitServiceCode(string $libService): ?string
    {
        $lib = $this->norm($libService);
        if (str_contains($lib, 'DEVELOPPEMENT') && str_contains($lib, 'INTEGRATION')) {
            return 'SVC-DDI-DEVINT';
        }
        if (str_contains($lib, 'BASE DE DONNEE') || str_contains($lib, 'BASES DE DONNEES') || str_contains($lib, 'BDD')) {
            return 'SVC-DDI-BDD';
        }
        if (str_contains($lib, 'MAINTENANCE') || str_contains($lib, 'RESEAU')) {
            return 'SVC-DDI-MAINT';
        }
        if (str_contains($lib, 'VEILLE') || str_contains($lib, 'INNOVATION')) {
            return 'SVC-DDI-VEILLE';
        }

        return null;
    }

    private function norm(string $s): string
    {
        $s = trim(mb_strtoupper($s, 'UTF-8'));
        return preg_replace('/\s+/', ' ', $s) ?: $s;
    }

    private function assurerServicesDdsait(int $dgId): void
    {
        $ddsait = Structure::updateOrCreate(
            ['code' => 'DDSAIT'],
            [
                'parent_id' => $dgId,
                'nom' => 'DIRECTION DEV. DES SYS. APPL. ET INNOV. TECH.',
                'type' => 'direction',
                'actif' => true,
            ]
        );

        $services = [
            ['SVC-DDI-DEVINT', 'SERVICE DU DEVELOPPEMENT ET DE L\'INTEGRATION DES APPLICATIONS'],
            ['SVC-DDI-BDD', 'SERVICE DE LA CONCEPTION ET DE L\'ADMINISTRATION DES BASES DE DONNEES'],
            ['SVC-DDI-MAINT', 'SERVICE DE LA MAINTENANCE DES SYSTEMES APPLICATIFS'],
            ['SVC-DDI-VEILLE', 'SERVICE DE LA VEILLE ET DE L\'INNOVATION TECHNOLOGIQUE'],
        ];

        foreach ($services as [$code, $nom]) {
            Structure::updateOrCreate(
                ['code' => $code],
                [
                    'parent_id' => $ddsait->id,
                    'nom' => $nom,
                    'type' => 'service',
                    'actif' => true,
                ]
            );
        }
    }

    private function desactiverAliasesServicesDdsait(): void
    {
        $codesCanoniques = ['SVC-DDI-DEVINT', 'SVC-DDI-BDD', 'SVC-DDI-MAINT', 'SVC-DDI-VEILLE'];

        $services = Structure::query()
            ->where('type', 'service')
            ->whereNotIn('code', $codesCanoniques)
            ->get(['id', 'code', 'nom', 'actif']);

        foreach ($services as $service) {
            if ($this->canonicalDdsaitServiceCode((string) $service->nom) === null) {
                continue;
            }
            if (! $service->actif) {
                continue;
            }

            $service->update(['actif' => false]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{codeDirection?:mixed, libDirection?:mixed}>
     */
    private function uniqueDirections(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $cd = trim((string) ($r['codeDirection'] ?? ''));
            $ld = trim((string) ($r['libDirection'] ?? ''));
            if ($cd === '' || $ld === '') {
                continue;
            }
            $key = $cd.'|'.$ld;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['codeDirection' => $cd, 'libDirection' => $ld];
        }
        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{codeDirection?:mixed, codeService?:mixed, libService?:mixed}>
     */
    private function uniqueServices(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $cd = trim((string) ($r['codeDirection'] ?? ''));
            $cs = trim((string) ($r['codeService'] ?? ''));
            $ls = trim((string) ($r['libService'] ?? ''));
            if ($cd === '' || $cs === '' || $ls === '') {
                continue;
            }
            $key = $cd.'|'.$cs.'|'.$ls;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['codeDirection' => $cd, 'codeService' => $cs, 'libService' => $ls];
        }
        return $out;
    }
}

