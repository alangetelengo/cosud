<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\SensCourrier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CourrierDoublonService
{
    /**
     * Recherche un doublon d’arrivée selon, dans l’ordre :
     * 1) n° fulgurant (identifiant de correspondance)
     * 2) référence
     * 3) empreinte expéditeur + date du courrier + objet
     *
     * @param  array{numero_fulgurant?: ?string, reference?: ?string, expediteur_libelle?: ?string, date_courrier?: ?string, objet?: ?string}  $attributs
     * @return array{courrier: Courrier, critere: string}|null
     */
    public function trouverDoublonArrivee(array $attributs, ?int $ignorerCourrierId = null): ?array
    {
        $fulgurant = $this->normaliserIdentifiant($attributs['numero_fulgurant'] ?? null);
        if ($fulgurant !== null) {
            $existant = $this->requeteArrivees($ignorerCourrierId)
                ->whereRaw('LOWER(TRIM(numero_fulgurant)) = ?', [$fulgurant])
                ->first();

            if ($existant) {
                return ['courrier' => $existant, 'critere' => 'numero_fulgurant'];
            }
        }

        $reference = $this->normaliserIdentifiant($attributs['reference'] ?? null);
        if ($reference !== null) {
            $existant = $this->requeteArrivees($ignorerCourrierId)
                ->whereRaw('LOWER(TRIM(reference)) = ?', [$reference])
                ->first();

            if ($existant) {
                return ['courrier' => $existant, 'critere' => 'reference'];
            }
        }

        $expediteur = $this->normaliserTexte($attributs['expediteur_libelle'] ?? null);
        $objet = $this->normaliserTexte($attributs['objet'] ?? null);
        $dateCourrier = $attributs['date_courrier'] ?? null;

        if ($expediteur !== null && $objet !== null && filled($dateCourrier)) {
            $existant = $this->requeteArrivees($ignorerCourrierId)
                ->whereDate('date_courrier', $dateCourrier)
                ->whereNotNull('expediteur_libelle')
                ->whereNotNull('objet')
                ->get()
                ->first(function (Courrier $c) use ($expediteur, $objet) {
                    return $this->normaliserTexte($c->expediteur_libelle) === $expediteur
                        && $this->normaliserTexte($c->objet) === $objet;
                });

            if ($existant) {
                return ['courrier' => $existant, 'critere' => 'empreinte'];
            }
        }

        return null;
    }

    public function messagePour(Courrier $existant, string $critere): string
    {
        $numero = $existant->numeroRegistreComplet();
        $lien = 'n° '.$numero;

        return match ($critere) {
            'numero_fulgurant' => "Ce n° fulgurant est déjà enregistré sur le courrier {$lien}.",
            'reference' => "Cette référence est déjà enregistrée sur le courrier {$lien}.",
            'empreinte' => "Un courrier arrivée similaire existe déjà ({$lien}) : même expéditeur, date et objet.",
            default => "Ce courrier semble déjà enregistré ({$lien}).",
        };
    }

    protected function requeteArrivees(?int $ignorerCourrierId = null): Builder
    {
        $sensId = SensCourrier::query()->where('code', SensCourrier::ARRIVEE)->value('id');

        return Courrier::query()
            ->where('sens_courrier_id', $sensId)
            ->when($ignorerCourrierId, fn (Builder $q) => $q->whereKeyNot($ignorerCourrierId));
    }

    protected function normaliserIdentifiant(?string $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $valeur = Str::lower(trim($valeur));

        return $valeur === '' ? null : $valeur;
    }

    protected function normaliserTexte(?string $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $valeur = Str::lower(trim(preg_replace('/\s+/u', ' ', $valeur) ?? ''));

        return $valeur === '' ? null : $valeur;
    }
}
