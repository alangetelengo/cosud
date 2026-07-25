@php
    $filService = app(\App\Services\CourrierFilService::class);
@endphp
<div class="space-y-4">
    <div>
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Racine :
            <a href="{{ route('courriers.show', $filRacine) }}" class="text-emerald-600 font-medium no-underline">
                n° {{ $filRacine->numeroRegistreComplet() }}
            </a>
            <span class="text-slate-400">·</span>
            {{ $filCourriers->count() }} courrier(s) lié(s)
        </p>
    </div>

    @if($filCourriers->count() > 1)
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200 dark:border-slate-600">
                    <th class="py-2 pr-3 font-semibold">Courrier</th>
                    <th class="py-2 pr-3 font-semibold">Sens / origine</th>
                    <th class="py-2 pr-3 font-semibold">Statut</th>
                    <th class="py-2 pr-3 font-semibold">Documents</th>
                    <th class="py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($filCourriers as $fc)
                <tr @class(['bg-emerald-50/50 dark:bg-emerald-900/10' => $fc->id === $courrier->id])>
                    <td class="py-3 pr-3">
                        <span class="font-medium text-slate-800 dark:text-slate-100">n° {{ $fc->numeroRegistreComplet() }}</span>
                        <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ $fc->objet }}</p>
                    </td>
                    <td class="py-3 pr-3">
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $fc->estArrivee() ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' }}">
                            {{ $fc->sensCourrier->libelle }}
                        </span>
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ml-1">
                            {{ ucfirst($fc->origine) }}
                        </span>
                    </td>
                    <td class="py-3 pr-3 text-slate-600 dark:text-slate-300">{{ $fc->statutCourrier->libelle }}</td>
                    <td class="py-3 pr-3">
                        @forelse($fc->documents as $doc)
                        <div class="flex items-center gap-1.5 text-xs mb-1">
                            <span @class([
                                'px-1.5 py-0.5 rounded font-semibold',
                                $filService->estDocumentEntrant($fc) ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700',
                            ])>{{ $filService->sensDocumentLabel($fc) }}</span>
                            @can('view', $doc)
                            <a href="{{ route('documents.fiche', $doc) }}" class="text-emerald-600 no-underline truncate max-w-[180px]">{{ $doc->nom_original }}</a>
                            @else
                            <span class="text-slate-400 truncate max-w-[180px]">{{ $doc->nom_original }}</span>
                            @endcan
                        </div>
                        @empty
                        <span @class([
                            'px-1.5 py-0.5 rounded text-xs font-semibold',
                            $filService->estDocumentEntrant($fc) ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700',
                        ])>{{ $filService->sensDocumentLabel($fc) }}</span>
                        <span class="text-slate-400 text-xs">(aucune pièce)</span>
                        @endforelse
                    </td>
                    <td class="py-3 text-right">
                        @if($fc->id !== $courrier->id)
                        <a href="{{ route('courriers.show', $fc) }}" class="text-emerald-600 text-xs font-semibold no-underline">Ouvrir</a>
                        @else
                        <span class="text-xs text-emerald-600 font-semibold">Courant</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($filHistorique->isNotEmpty())
    <div>
        <h4 class="font-semibold text-slate-800 dark:text-slate-100 mb-3">Chronologie</h4>
        <ol class="relative border-l border-slate-200 dark:border-slate-600 ml-2 space-y-4">
            @foreach($filHistorique as $evt)
            <li class="relative ml-5">
                <span @class([
                    'absolute -left-[1.625rem] top-1 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800',
                    match($evt['type']) {
                        'courrier' => 'bg-emerald-500',
                        'orientation' => 'bg-amber-500',
                        'ventilation' => 'bg-sky-500',
                        'transmission' => 'bg-slate-500',
                        'audit' => 'bg-indigo-400',
                        default => 'bg-slate-400',
                    },
                ])></span>
                <time class="text-xs text-slate-500">{{ $evt['date']?->format('d/m/Y H:i') }}</time>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $evt['libelle'] }}</p>
                @if($evt['details'])
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ $evt['details'] }}</p>
                @endif
                @if($evt['courrier'] && $evt['courrier']->id !== $courrier->id)
                <a href="{{ route('courriers.show', $evt['courrier']) }}" class="text-xs text-emerald-600 no-underline">Voir le courrier</a>
                @endif
                @foreach($evt['documents'] as $doc)
                <div class="text-xs mt-1">
                    @can('view', $doc)
                    <a href="{{ route('documents.fiche', $doc) }}" class="text-emerald-600 no-underline">{{ $doc->nom_original }}</a>
                    @else
                    <span class="text-slate-400">{{ $doc->nom_original }}</span>
                    @endcan
                </div>
                @endforeach
            </li>
            @endforeach
        </ol>
    </div>
    @endif
</div>
