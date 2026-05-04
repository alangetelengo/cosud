{{--
    Options « Type de dossier » : libellé seul, infobulle (description + aide), regroupement optgroup.
    @var \Illuminate\Support\Collection<int, \App\Models\TypeDossier> $typesDossier
    @var string|int|null $selectedId
    @var bool $afficherCodeDansTitle (optionnel) afficher le code technique dans l’infobulle (écran admin plan de classement)
--}}
@php
    $afficherCodeDansTitle = $afficherCodeDansTitle ?? false;
    $selectedId = $selectedId ?? null;

    $sensibilite = $typesDossier->filter(fn (\App\Models\TypeDossier $t) => strcasecmp((string) $t->code, 'confidentiel') === 0);
    $projets = $typesDossier->filter(fn (\App\Models\TypeDossier $t) => $t->estProjet());
    $autres = $typesDossier->filter(fn (\App\Models\TypeDossier $t) => strcasecmp((string) $t->code, 'confidentiel') !== 0 && ! $t->estProjet());

    $hintPourCode = function (string $code): ?string {
        $c = mb_strtolower($code);

        return match ($c) {
            'confidentiel' => 'Dossier sensible : visibilité limitée aux profils autorisés, au créateur et au propriétaire.',
            'projet', 'project' => 'Dossier projet : après création, partage d’équipe recommandé. Réservé aux chefs de service ou administrateurs.',
            default => null,
        };
    };
@endphp
@foreach([
    'Sensibilité' => $sensibilite,
    'Projet (équipe)' => $projets,
    'Classification' => $autres,
] as $groupeLabel => $groupe)
    @if($groupe->isEmpty())
        @continue
    @endif
    <optgroup label="{{ $groupeLabel }}">
        @foreach($groupe as $td)
            @php
                $parts = [];
                $desc = trim((string) ($td->description ?? ''));
                if ($desc !== '') {
                    $parts[] = $desc;
                }
                $hint = $hintPourCode((string) $td->code);
                if ($hint !== null && ! in_array($hint, $parts, true)) {
                    $parts[] = $hint;
                }
                if ($afficherCodeDansTitle) {
                    $parts[] = 'Code : '.$td->code;
                }
                $title = $parts !== [] ? implode(' — ', $parts) : $td->libelle;
            @endphp
            <option
                value="{{ $td->id }}"
                title="{{ e($title) }}"
                @selected((string) $selectedId === (string) $td->id)
            >{{ $td->libelle }}</option>
        @endforeach
    </optgroup>
@endforeach
