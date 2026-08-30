<?php

namespace Database\Seeders;

use App\Models\FournisseurPrestataire;
use Illuminate\Database\Seeder;

class FournisseurPrestataireSeeder extends Seeder
{
    /**
     * Fiches enrichies (conservées : type / contrat).
     *
     * @return list<array{nom: string, type: string, type_contrat: string, a_contrat: bool, a_dossier_fiscal: bool}>
     */
    private function fichesEnrichies(): array
    {
        return [
            [
                'nom' => 'ACS - Approvisionnement Congo Services',
                'type' => FournisseurPrestataire::TYPE_PRESTATAIRE,
                'type_contrat' => 'Entretien des groupes électrogènes',
                'a_contrat' => true,
                'a_dossier_fiscal' => false,
            ],
            [
                'nom' => 'AIRTEL',
                'type' => FournisseurPrestataire::TYPE_PARTENAIRE,
                'type_contrat' => 'Protocole de partenariat d’intégration plateforme à travers un réseau mobile (API)',
                'a_contrat' => true,
                'a_dossier_fiscal' => false,
            ],
            [
                'nom' => 'BILLY SERVICES',
                'type' => FournisseurPrestataire::TYPE_PRESTATAIRE,
                'type_contrat' => 'Location véhicule',
                'a_contrat' => true,
                'a_dossier_fiscal' => false,
            ],
        ];
    }

    /**
     * Noms issus des feuilles du fichier
     * docs/referentiel/LISTE DES FOURNISSEURS ET PRESTATAIRES FACTURES IMPAYES.xlsx
     * (générés une fois — type fournisseur par défaut).
     *
     * @return list<string>
     */
    private function nomsDepuisReferentielImpayes(): array
    {
        return [
            'TELE CONGO',
            'AFCOM',
            'INC',
            'SOMAC',
            'SPORAFRIC',
            'DALPIVOT',
            'GPE-DLT',
            'ARDIA',
            'CONGO TELECOM',
            'NSIA',
            'HOP. TALANGAI',
            'HOP. REG. ARMEE',
            'HOP. MILITAIRE',
            'FCIE MAVRE',
            'FCIE CROIX DU SUD',
            'FCIE DAFFE',
            'DS',
            'OLALA',
            'ETS ND SCE',
            'BUROTOP IRIS',
            'Mr KOUKA',
            'LABO INJECTION',
            'ETS SYMPA',
            'MEGASTORE',
            'ORCA',
            'METRO DE LUXE',
            'MICROPLUS',
            'DRTV PN',
            'DRTV',
            'MCRTV',
            'BONNE NOUVELLE',
            'LIBTECH.EXE',
            'MANAGER HOR.',
            'LES DEPECHES DE BZV',
            'ED. LES SOZO',
            'BUROTEC',
            'SUPER SONIC',
            'CARREFOUR',
            'EC',
            'Chanel MONGONGO',
            'SCHEKINAH',
            'STECH SERVICES',
            'ROSERAIE SCE',
            'POINT SYS CG',
            'UNITED CONGO',
            'CONGO INFORMATIQUE',
            'LABO NATIONALE',
            'MBOTE SHOP',
            'MASSANZAMBI',
            'MPC',
            'COMPU-STORE',
            'GAR-AUTOLOGIC',
            'AUTO MOBILE',
            'ETS HANA FRED',
            'ETS BRISCO',
            'GAR. AUTO MONDIAL',
            'GAR. LABO INJECTION',
            'CARROSSERIE BAZAS',
            'BILLY SERVICES',
            'SOFT-RENOVATION',
            'CHRIST FROID',
            'STATION TOTAL',
            'PB SOLUTION',
            'E2C',
            'LCDE',
            'BL TECHNOLOGY',
            'BANAB TECH & SCES',
            'IT-TECH',
            'CAPEL SCES',
            'SAFE-TECH',
            'SILICONE',
            'KUDIA',
            'KINGM',
            'BIANTOS AGENCY',
            'VISION2MZ',
            'ETS-DB',
            'GENYNE SCE',
            'GARDE REP.',
            'GLENN SERVICES',
            'LAVAGE AUTO',
            "L'ASSOCIE",
            'CONSO PLUS',
            'MALONGA SCES',
            'AFRIQUE AUTOMOBILE',
            'ETS DIALLA AUTO',
            'PUMA',
        ];
    }

    /**
     * Compléments issus de docs/Checklist-reprise-registre-aout.xlsx
     * (absents du référentiel impayés ou libellés distincts).
     *
     * @return list<string>
     */
    private function nomsDepuisChecklistRepriseAout(): array
    {
        return [
            'GROUPE DREAMS LINK TECHNOLOGIES',
            'ETS DALPIVOT AIR FROID',
            'DS POWER',
            'ETS ND SERVICES',
            "AUTO MOBILE TOYOTA D'ORIGINE PIECES",
            'SILICON CONNECT',
            'PHARMACIE MAVRE',
        ];
    }

    public function run(): void
    {
        $enrichies = $this->fichesEnrichies();
        $nomsProteges = [];

        foreach ($enrichies as $ligne) {
            $normalise = FournisseurPrestataire::normaliserNom($ligne['nom']);
            $nomsProteges[$normalise] = true;

            FournisseurPrestataire::query()->updateOrCreate(
                ['nom_normalise' => $normalise],
                array_merge($ligne, [
                    'nom_normalise' => $normalise,
                    'actif' => true,
                ])
            );
        }

        foreach (array_merge(
            $this->nomsDepuisReferentielImpayes(),
            $this->nomsDepuisChecklistRepriseAout(),
        ) as $nom) {
            $normalise = FournisseurPrestataire::normaliserNom($nom);

            if (isset($nomsProteges[$normalise])) {
                continue;
            }

            FournisseurPrestataire::query()->updateOrCreate(
                ['nom_normalise' => $normalise],
                [
                    'nom' => $nom,
                    'nom_normalise' => $normalise,
                    'type' => FournisseurPrestataire::TYPE_FOURNISSEUR,
                    'a_contrat' => false,
                    'a_dossier_fiscal' => false,
                    'actif' => true,
                ]
            );
        }
    }
}
