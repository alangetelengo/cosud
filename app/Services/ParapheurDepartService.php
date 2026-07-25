<?php

namespace App\Services;

use App\Models\Document;
use App\Models\SensCourrier;
use App\Models\StatutDocument;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ParapheurDepartService
{
    public function __construct(
        private MesDossiersRacineService $mesDossiersRacine
    ) {}

    /**
     * @return list<string>
     */
    public function codesTypesDocument(): array
    {
        return config('ged.parapheur_depart.types_document', []);
    }

    /**
     * @return list<string>
     */
    public function codesStatutsDocument(): array
    {
        return config('ged.parapheur_depart.statuts_document', []);
    }

    /**
     * @return list<string>
     */
    public function codesStatutsCourrierDepartActifs(): array
    {
        return config('ged.parapheur_depart.statuts_courrier_depart_actifs', []);
    }

    public function queryEligiblePour(User $user): Builder
    {
        $typeIds = TypeDocument::query()
            ->whereIn('code', $this->codesTypesDocument())
            ->where('actif', true)
            ->pluck('id');

        return Document::query()
            ->visibleBy($user)
            ->horsCorbeille()
            ->whereIn('type_document_id', $typeIds)
            ->whereIn('statut', $this->codesStatutsDocument())
            ->where(function ($q) use ($user) {
                $q->where('createur_id', $user->id)
                    ->orWhere('proprietaire_id', $user->id);
            })
            ->whereDoesntHave('courriers', function ($cq) {
                $cq->whereHas('sensCourrier', fn ($sq) => $sq->where('code', SensCourrier::DEPART))
                    ->whereHas('statutCourrier', fn ($sq) => $sq->whereIn('code', $this->codesStatutsCourrierDepartActifs()));
            })
            ->with(['typeDocument', 'statutDocument'])
            ->orderByDesc('updated_at');
    }

    public function estEligible(Document $document, User $user): bool
    {
        if (! $document->visiblePar($user)) {
            return false;
        }

        if ($document->en_corbeille) {
            return false;
        }

        $typeCode = $document->typeDocument?->code;
        if (! $typeCode || ! in_array($typeCode, $this->codesTypesDocument(), true)) {
            return false;
        }

        if (! in_array($document->statut, $this->codesStatutsDocument(), true)) {
            return false;
        }

        if ((int) $document->createur_id !== (int) $user->id && (int) $document->proprietaire_id !== (int) $user->id) {
            return false;
        }

        $lieCourrierDepartActif = $document->courriers()
            ->whereHas('sensCourrier', fn ($sq) => $sq->where('code', SensCourrier::DEPART))
            ->whereHas('statutCourrier', fn ($sq) => $sq->whereIn('code', $this->codesStatutsCourrierDepartActifs()))
            ->exists();

        return ! $lieCourrierDepartActif;
    }

    /**
     * @return Collection<int, TypeDocument>
     */
    public function typesDocumentPourDepot(): Collection
    {
        return TypeDocument::query()
            ->whereIn('code', $this->codesTypesDocument())
            ->where('actif', true)
            ->orderBy('libelle')
            ->get();
    }

    public function deposerPiece(User $user, UploadedFile $file, int $typeDocumentId): Document
    {
        $type = TypeDocument::query()
            ->whereKey($typeDocumentId)
            ->whereIn('code', $this->codesTypesDocument())
            ->where('actif', true)
            ->firstOrFail();

        $statut = StatutDocument::query()->where('code', 'brouillon')->first();
        $chemin = $file->store('documents/parapheur-depart', 'public');
        $dossier = $this->mesDossiersRacine->ensureSousDossierParapheurDepart($user);

        return Document::create([
            'type_document_id' => $type->id,
            'user_id' => $user->id,
            'createur_id' => $user->id,
            'proprietaire_id' => $user->id,
            'dossier_id' => $dossier->id,
            'nom_original' => $file->getClientOriginalName(),
            'titre' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'chemin' => $chemin,
            'extension' => $file->getClientOriginalExtension(),
            'taille_octets' => $file->getSize(),
            'statut' => 'brouillon',
            'statut_document_id' => $statut?->id,
            'en_corbeille' => false,
        ]);
    }
}
