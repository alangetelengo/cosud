<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\JournalAudit;
use App\Models\SensCourrier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CourrierFilService
{
    /**
     * @return list<int>
     */
    public function idsDuFil(Courrier $courrier): array
    {
        $visited = [];
        $queue = [$courrier->id];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;

            foreach ($this->idsVoisins($id) as $voisinId) {
                if (! isset($visited[$voisinId])) {
                    $queue[] = $voisinId;
                }
            }
        }

        return array_keys($visited);
    }

    /**
     * @return Collection<int, Courrier>
     */
    public function courriersDuFil(Courrier $courrier): Collection
    {
        $ids = $this->idsDuFil($courrier);

        return Courrier::query()
            ->whereIn('id', $ids)
            ->with([
                'sensCourrier', 'statutCourrier', 'typeCourrier', 'createur',
                'structureExpediteur', 'structureDestinataire', 'documents.typeDocument',
                'orientations.structure', 'orientations.orientePar',
                'transmissions.versStructure', 'transmissions.versUser',
                'ventilationDestinataires.user', 'ventilationDestinataires.document',
            ])
            ->get()
            ->sortBy(fn (Courrier $c) => $this->dateReference($c)->timestamp)
            ->values();
    }

    public function racine(Courrier $courrier): Courrier
    {
        $fil = $this->courriersDuFil($courrier);

        $arriveeExterne = $fil->first(
            fn (Courrier $c) => $c->estArrivee() && $c->estOrigineExterne()
        );
        if ($arriveeExterne) {
            return $arriveeExterne;
        }

        $premiereArrivee = $fil->first(fn (Courrier $c) => $c->estArrivee());
        if ($premiereArrivee) {
            return $premiereArrivee;
        }

        return $fil->first() ?? $courrier;
    }

    /**
     * @return Collection<int, array{
     *     date: Carbon,
     *     type: string,
     *     libelle: string,
     *     courrier: Courrier|null,
     *     documents: Collection,
     *     details: string|null
     * }>
     */
    public function construireHistorique(Courrier $courrier): Collection
    {
        $fil = $this->courriersDuFil($courrier);
        $ids = $fil->pluck('id')->all();

        $evenements = collect();

        foreach ($fil as $c) {
            $sens = $c->estArrivee() ? 'Arrivée' : 'Départ';
            $origine = ucfirst($c->origine ?? '');
            $evenements->push([
                'date' => $this->dateReference($c),
                'type' => 'courrier',
                'libelle' => "{$sens} {$origine} n° {$c->numeroRegistreComplet()}",
                'courrier' => $c,
                'documents' => $c->documents,
                'details' => $c->objet,
            ]);

            foreach ($c->orientations as $orientation) {
                $evenements->push([
                    'date' => $orientation->created_at,
                    'type' => 'orientation',
                    'libelle' => 'Orientation — '.$orientation->structure?->nom,
                    'courrier' => $c,
                    'documents' => collect(),
                    'details' => $orientation->instructions,
                ]);
            }

            foreach ($c->transmissions as $transmission) {
                $vers = $transmission->versStructure?->nom ?? $transmission->versUser?->name ?? '—';
                $evenements->push([
                    'date' => $transmission->date_transmission,
                    'type' => 'transmission',
                    'libelle' => 'Transmission vers '.$vers,
                    'courrier' => $c,
                    'documents' => collect(),
                    'details' => $transmission->commentaire,
                ]);
            }

            foreach ($c->ventilationDestinataires as $ventilation) {
                $evenements->push([
                    'date' => $ventilation->created_at,
                    'type' => 'ventilation',
                    'libelle' => 'Ventilation — '.$ventilation->user?->name,
                    'courrier' => $c,
                    'documents' => $ventilation->document ? collect([$ventilation->document]) : collect(),
                    'details' => $ventilation->document?->nom_original,
                ]);
            }
        }

        $audits = JournalAudit::query()
            ->where('module', 'courriers')
            ->whereNotNull('commentaire')
            ->where(function ($query) use ($ids) {
                foreach ($ids as $id) {
                    $needle = '"courrier_id":'.(int) $id;
                    $query->orWhere('commentaire', 'like', '%'.$needle.'%');
                }
            })
            ->with('user')
            ->latest()
            ->limit(200)
            ->get();

        foreach ($audits as $audit) {
            $evenements->push([
                'date' => $audit->created_at,
                'type' => 'audit',
                'libelle' => $this->libelleAudit($audit->action),
                'courrier' => null,
                'documents' => collect(),
                'details' => $audit->user?->name,
            ]);
        }

        return $evenements
            ->sortByDesc(fn (array $e) => $e['date']?->timestamp ?? 0)
            ->values();
    }

    /**
     * @return list<int>
     */
    private function idsVoisins(int $courrierId): array
    {
        $c = Courrier::query()->find($courrierId);
        if (! $c) {
            return [];
        }

        $ids = array_filter([
            $c->courrier_parent_id,
            $c->courrier_depart_source_id,
            $c->courrier_arrivee_lie_id,
        ]);

        $ids = array_merge($ids, Courrier::query()
            ->where('courrier_parent_id', $courrierId)
            ->pluck('id')
            ->all());

        $ids = array_merge($ids, Courrier::query()
            ->where('courrier_depart_source_id', $courrierId)
            ->pluck('id')
            ->all());

        $ids = array_merge($ids, Courrier::query()
            ->where('courrier_arrivee_lie_id', $courrierId)
            ->pluck('id')
            ->all());

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function dateReference(Courrier $courrier): Carbon
    {
        if ($courrier->estArrivee() && $courrier->date_reception) {
            return $courrier->date_reception->startOfDay();
        }

        if ($courrier->date_courrier) {
            return $courrier->date_courrier->startOfDay();
        }

        if ($courrier->date_expedition) {
            return $courrier->date_expedition;
        }

        return $courrier->created_at ?? now();
    }

    private function libelleAudit(string $action): string
    {
        return match ($action) {
            'courrier.create' => 'Création du courrier',
            'courrier.update' => 'Modification du courrier',
            'courrier.parapheur' => 'Mise en parapheur',
            'courrier.orienter' => 'Orientation',
            'courrier.ventiler' => 'Ventilation',
            'courrier.transmettre' => 'Transmission registre',
            'courrier.creer_reponse' => 'Création d\'une réponse',
            'courrier.reception_interne' => 'Réception interne',
            'courrier.transition' => 'Changement de statut',
            'courrier.annule' => 'Annulation',
            default => $action,
        };
    }

    public function sensDocumentLabel(Courrier $courrier): string
    {
        if ($courrier->estArrivee()) {
            return 'Document entrant';
        }

        if ($courrier->courrier_parent_id) {
            return 'Document sortant (réponse)';
        }

        return 'Document sortant';
    }

    public function estDocumentEntrant(Courrier $courrier): bool
    {
        return $courrier->sensCourrier?->code === SensCourrier::ARRIVEE;
    }
}
