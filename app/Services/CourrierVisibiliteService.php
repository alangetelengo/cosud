<?php

namespace App\Services;

use App\Models\CircuitCourrierEtape;
use App\Models\Courrier;
use App\Models\TypeCourrier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visibilité listes / fiches courriers selon permissions de périmètre
 * (factures vs dépenses) et secrétariat DG vs autres structures.
 */
class CourrierVisibiliteService
{
    /** @var list<string> */
    public const CODES_FACTURES = ['facture'];

    /** @var list<string> */
    public const CODES_DEPENSES = ['mad'];

    public const PERM_VOIR_FACTURES = 'courriers.voir-factures';

    public const PERM_VOIR_DEPENSES = 'courriers.voir-depenses';

    /**
     * Codes type pour un filtre « périmètre » exclusif (un seul scope).
     * null = pas de restriction exclusive (admin, ou les deux scopes / aucun).
     *
     * @return list<string>|null
     */
    public function codesTypesAutorises(User $user): ?array
    {
        if ($user->aAccesTotal()) {
            return null;
        }

        $factures = $user->can(self::PERM_VOIR_FACTURES);
        $depenses = $user->can(self::PERM_VOIR_DEPENSES);

        if ($factures && ! $depenses) {
            return self::CODES_FACTURES;
        }

        if ($depenses && ! $factures) {
            return self::CODES_DEPENSES;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function codesFluxFacturesDepenses(User $user): array
    {
        $codes = [];
        if ($user->can(self::PERM_VOIR_FACTURES)) {
            $codes = array_merge($codes, self::CODES_FACTURES);
        }
        if ($user->can(self::PERM_VOIR_DEPENSES)) {
            $codes = array_merge($codes, self::CODES_DEPENSES);
        }

        return array_values(array_unique($codes));
    }

    public function voitSansFiltreStructure(User $user): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }

        if ($user->hasRole('agent_comptable') || $user->hasRole('caissier')) {
            return true;
        }

        return $this->estActeurSecretariatDg($user);
    }

    public function estActeurSecretariatDg(User $user): bool
    {
        if ($user->hasRole('particulier_dg')) {
            return true;
        }

        $code = $user->structure?->code
            ?? $user->structurePourValidationHierarchique()?->code;

        return in_array($code, ['SEC-DIR', 'DG'], true);
    }

    public function typeEstAutorise(User $user, ?TypeCourrier $type): bool
    {
        $codes = $this->codesTypesAutorises($user);
        if ($codes === null) {
            return true;
        }

        if ($type === null) {
            return false;
        }

        return in_array($type->code, $codes, true);
    }

    public function appliquerFiltreListe(Builder $query, User $user): Builder
    {
        if ($user->aAccesTotal()) {
            return $query;
        }

        $factures = $user->can(self::PERM_VOIR_FACTURES);
        $depenses = $user->can(self::PERM_VOIR_DEPENSES);
        $codesFlux = $this->codesFluxFacturesDepenses($user);
        $sansStructureFlux = $this->voitSansFiltreStructure($user);
        $structureId = (int) $user->structure_id;

        return $query->where(function (Builder $q) use ($user, $factures, $depenses, $codesFlux, $sansStructureFlux, $structureId): void {
            $q->where(function (Builder $main) use ($factures, $depenses, $codesFlux, $sansStructureFlux, $structureId): void {
                if ($codesFlux !== []) {
                    $main->where(function (Builder $flux) use ($codesFlux, $sansStructureFlux, $structureId, $factures, $depenses): void {
                        $flux->where(function (Builder $typed) use ($codesFlux, $sansStructureFlux, $structureId): void {
                            $typed->whereHas('typeCourrier', fn (Builder $t) => $t->whereIn('code', $codesFlux));
                            if (! $sansStructureFlux) {
                                if ($structureId > 0) {
                                    $typed->where('structure_id', $structureId);
                                } else {
                                    $typed->whereRaw('0 = 1');
                                }
                            }
                        });

                        // Les deux scopes : conserver le registre local (demandes, etc.).
                        if ($factures && $depenses && $structureId > 0) {
                            $flux->orWhere('structure_id', $structureId);
                        }
                    });
                } elseif ($structureId > 0) {
                    $main->where('structure_id', $structureId);
                } else {
                    $main->whereRaw('0 = 1');
                }
            });

            $q->orWhere('createur_id', $user->id)
                ->orWhere('directeur_en_attente_id', $user->id)
                ->orWhere('destinataire_agent_id', $user->id)
                ->orWhereHas('ventilationDestinataires', fn (Builder $v) => $v->where('user_id', $user->id));

            if ($user->hasRole('responsable_suivi_depenses')) {
                $q->orWhereHas('suiviPaiement');
            }

            $roleNames = $user->getRoleNames()->all();
            if ($roleNames !== []) {
                $q->orWhere(function (Builder $circuit) use ($roleNames): void {
                    $circuit->whereNotNull('circuit_courrier_id')
                        ->where(function (Builder $implique) use ($roleNames): void {
                            $implique->whereHas('circuit.etapes', function (Builder $e) use ($roleNames): void {
                                $e->where('actif', true)
                                    ->where('acteur_type', CircuitCourrierEtape::ACTEUR_ROLE)
                                    ->whereIn('acteur_valeur', $roleNames);
                            });

                            foreach ($roleNames as $role) {
                                $implique->orWhereHas(
                                    'circuitEtapeActuelle',
                                    fn (Builder $e) => $e->whereJsonContains('notifie_roles', $role)
                                );
                            }

                            if (in_array('responsable_suivi_depenses', $roleNames, true)) {
                                foreach ($roleNames as $role) {
                                    $implique->orWhereHas(
                                        'circuit.etapes',
                                        fn (Builder $e) => $e->where('actif', true)
                                            ->whereJsonContains('notifie_roles', $role)
                                    );
                                }
                            }
                        });
                });
            }
        });
    }

    /**
     * Acteur prévu sur une étape du circuit, ou rôle notifié sur l’étape en cours
     * (ex. Eleni suit une facture avant sa propre étape de contrôle).
     */
    public function estImpliqueDansCircuit(Courrier $courrier, User $user): bool
    {
        if (! $courrier->circuit_courrier_id || ! $user->can('courriers.view')) {
            return false;
        }

        $roleNames = $user->getRoleNames()->all();
        if ($roleNames === []) {
            return false;
        }

        $estActeurCircuit = CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $courrier->circuit_courrier_id)
            ->where('actif', true)
            ->where('acteur_type', CircuitCourrierEtape::ACTEUR_ROLE)
            ->whereIn('acteur_valeur', $roleNames)
            ->exists();

        if ($estActeurCircuit) {
            return true;
        }

        $courrier->loadMissing('circuitEtapeActuelle');
        $notifie = $courrier->circuitEtapeActuelle?->notifie_roles ?? [];

        if (is_array($notifie) && array_intersect($roleNames, $notifie) !== []) {
            return true;
        }

        // Eleni est notifiée à l’étape « preuve_paiement » : elle peut suivre la facture en amont.
        if (! $user->hasRole('responsable_suivi_depenses')) {
            return false;
        }

        return CircuitCourrierEtape::query()
            ->where('circuit_courrier_id', $courrier->circuit_courrier_id)
            ->where('actif', true)
            ->where(function (Builder $q) use ($roleNames): void {
                foreach ($roleNames as $role) {
                    $q->orWhereJsonContains('notifie_roles', $role);
                }
            })
            ->exists();
    }

    public function estVisible(Courrier $courrier, User $user): bool
    {
        if ($user->aAccesTotal()) {
            return true;
        }

        if ((int) $courrier->createur_id === (int) $user->id) {
            return true;
        }

        if ($courrier->circuit_etape_actuelle_id
            && app(CircuitCourrierMoteurService::class)->peutAgir($courrier, $user)) {
            return true;
        }

        if ($this->estImpliqueDansCircuit($courrier, $user)) {
            return true;
        }

        // Contrôle hors circuit : accès aux dossiers ayant une fiche de suivi (après chèque / décharge).
        if ($user->hasRole('responsable_suivi_depenses')
            && ($courrier->relationLoaded('suiviPaiement')
                ? $courrier->suiviPaiement !== null
                : $courrier->suiviPaiement()->exists())) {
            return true;
        }

        if ($user->peutSignerCourrierDepart()
            && $courrier->estDepart()
            && (int) $courrier->directeur_en_attente_id === (int) $user->id) {
            return true;
        }

        if ($courrier->estDepart()
            && $courrier->destinataire_agent_id
            && (int) $courrier->destinataire_agent_id === (int) $user->id) {
            return true;
        }

        if ($courrier->ventilationDestinataires()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($courrier->enAttenteReceptionInterne()
            && (int) $courrier->structure_destinataire_id === (int) $user->structure_id) {
            return $user->gereCourrierSecretariat();
        }

        $factures = $user->can(self::PERM_VOIR_FACTURES);
        $depenses = $user->can(self::PERM_VOIR_DEPENSES);
        $courrier->loadMissing('typeCourrier');
        $codeType = $courrier->typeCourrier?->code;
        $estFacture = $codeType !== null && in_array($codeType, self::CODES_FACTURES, true);
        $estDepense = $codeType !== null && in_array($codeType, self::CODES_DEPENSES, true);
        $dansFlux = ($factures && $estFacture) || ($depenses && $estDepense);

        if ($dansFlux) {
            if ($this->voitSansFiltreStructure($user)) {
                return $user->can('courriers.view');
            }

            return (int) $courrier->structure_id === (int) $user->structure_id;
        }

        // Hors flux facture/MAD : registre local si les deux scopes, ou secrétariat sans scope.
        if (($factures && $depenses) || (! $factures && ! $depenses)) {
            if ($user->gereCourrierSecretariat() || $user->hasRole('particulier_dg')) {
                return (int) $courrier->structure_id === (int) $user->structure_id
                    || $courrier->appartientAuPerimetreSecretariat($user);
            }
        }

        return false;
    }
}
