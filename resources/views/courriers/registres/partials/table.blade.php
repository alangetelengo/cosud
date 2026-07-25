{{-- Table registre papier : colonnes Arrivée ou Départ — $side = full|left|right --}}
@php
    $isDepart = $sensCode === 'depart';
    $side = $side ?? 'full';
@endphp

<table class="registre-table w-full border-collapse text-[11px] sm:text-xs leading-snug">
    <thead>
        @if($isDepart)
            @if($side === 'left')
            <tr>
                <th class="registre-th w-[18%]">N° D'ORDRE</th>
                <th class="registre-th w-[18%]">Nbre de PIÈCES</th>
                <th class="registre-th w-[24%]">DATE DU DÉPART</th>
                <th class="registre-th w-[40%]">DESTINATAIRE</th>
            </tr>
            @elseif($side === 'right')
            <tr>
                <th class="registre-th w-[50%]">OBJET</th>
                <th class="registre-th w-[22%]">N° ARCHIVES</th>
                <th class="registre-th w-[28%]">OBSERVATIONS</th>
            </tr>
            @else
            <tr>
                <th class="registre-th w-[7%]">N° D'ORDRE</th>
                <th class="registre-th w-[7%]">Nbre de PIÈCES</th>
                <th class="registre-th w-[12%]">DATE DU DÉPART</th>
                <th class="registre-th w-[20%]">DESTINATAIRE</th>
                <th class="registre-th w-[28%]">OBJET</th>
                <th class="registre-th w-[12%]">N° ARCHIVES</th>
                <th class="registre-th w-[14%]">OBSERVATIONS</th>
            </tr>
            @endif
        @else
            @if($side === 'left')
            <tr>
                <th class="registre-th w-[30%]">DATE D'ARRIVÉE</th>
                <th class="registre-th w-[30%]">DATE ET N° DE LA CORRESPONDANCE</th>
                <th class="registre-th w-[40%]">EXPÉDITEUR</th>
            </tr>
            @elseif($side === 'right')
            <tr>
                <th class="registre-th w-[55%]">OBJET</th>
                <th class="registre-th w-[45%]">DATE ET N° DE LA RÉPONSE</th>
            </tr>
            @else
            <tr>
                <th class="registre-th w-[16%]">DATE D'ARRIVÉE</th>
                <th class="registre-th w-[16%]">DATE ET N° DE LA CORRESPONDANCE</th>
                <th class="registre-th w-[20%]">EXPÉDITEUR</th>
                <th class="registre-th w-[28%]">OBJET</th>
                <th class="registre-th w-[20%]">DATE ET N° DE LA RÉPONSE</th>
            </tr>
            @endif
        @endif
    </thead>
    <tbody>
        @forelse($courriers as $c)
            @if($isDepart)
                @if($side === 'left')
                <tr class="registre-row">
                    <td class="registre-td text-center font-semibold">{{ $c->numero_registre }}</td>
                    <td class="registre-td text-center">{{ $c->nombre_pieces ?? '—' }}</td>
                    <td class="registre-td text-center">{{ $c->date_expedition?->format('d/m/Y') ?? $c->date_courrier?->format('d/m/Y') ?? '—' }}</td>
                    <td class="registre-td">{{ $c->destinataire_libelle ?? '—' }}</td>
                </tr>
                @elseif($side === 'right')
                <tr class="registre-row">
                    <td class="registre-td">{{ str_replace('[DÉMO] ', '', $c->objet) }}</td>
                    <td class="registre-td text-center">{{ $c->numero_archives ?? '—' }}</td>
                    <td class="registre-td">{{ $c->observations ?? '—' }}</td>
                </tr>
                @else
                <tr class="registre-row">
                    <td class="registre-td text-center font-semibold">{{ $c->numero_registre }}</td>
                    <td class="registre-td text-center">{{ $c->nombre_pieces ?? '—' }}</td>
                    <td class="registre-td text-center">{{ $c->date_expedition?->format('d/m/Y') ?? $c->date_courrier?->format('d/m/Y') ?? '—' }}</td>
                    <td class="registre-td">{{ $c->destinataire_libelle ?? '—' }}</td>
                    <td class="registre-td">{{ str_replace('[DÉMO] ', '', $c->objet) }}</td>
                    <td class="registre-td text-center">{{ $c->numero_archives ?? '—' }}</td>
                    <td class="registre-td">{{ $c->observations ?? '—' }}</td>
                </tr>
                @endif
            @else
                @if($side === 'left')
                <tr class="registre-row">
                    <td class="registre-td">
                        <div>le {{ $c->date_reception?->format('d/m/Y') ?? '—' }}</div>
                        <div class="font-semibold">n° {{ $c->numero_registre }}</div>
                    </td>
                    <td class="registre-td">
                        <div>du {{ $c->date_courrier?->format('d/m/Y') ?? '—' }}</div>
                        <div>n° {{ $c->reference ?? $c->numero_fulgurant ?? '—' }}</div>
                    </td>
                    <td class="registre-td">{{ $c->expediteur_libelle ?? '—' }}</td>
                </tr>
                @elseif($side === 'right')
                <tr class="registre-row">
                    <td class="registre-td">{{ str_replace('[DÉMO] ', '', $c->objet) }}</td>
                    <td class="registre-td whitespace-pre-line">{{ $c->libelleReponseRegistre() ?? '—' }}</td>
                </tr>
                @else
                <tr class="registre-row">
                    <td class="registre-td">
                        <div>le {{ $c->date_reception?->format('d/m/Y') ?? '—' }}</div>
                        <div class="font-semibold">n° {{ $c->numero_registre }}</div>
                    </td>
                    <td class="registre-td">
                        <div>du {{ $c->date_courrier?->format('d/m/Y') ?? '—' }}</div>
                        <div>n° {{ $c->reference ?? $c->numero_fulgurant ?? '—' }}</div>
                    </td>
                    <td class="registre-td">{{ $c->expediteur_libelle ?? '—' }}</td>
                    <td class="registre-td">{{ str_replace('[DÉMO] ', '', $c->objet) }}</td>
                    <td class="registre-td whitespace-pre-line">{{ $c->libelleReponseRegistre() ?? '—' }}</td>
                </tr>
                @endif
            @endif
        @empty
            <tr>
                <td colspan="{{ $isDepart ? ($side === 'left' ? 4 : ($side === 'right' ? 3 : 7)) : ($side === 'left' ? 3 : ($side === 'right' ? 2 : 5)) }}"
                    class="registre-td text-center py-10 text-slate-500">
                    Aucune entrée pour l'année {{ $annee }}.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
