@php
    $historiqueCircuit = app(\App\Services\CircuitCourrierMoteurService::class)->historiquePourAffichage($courrier);
@endphp

@if(count($historiqueCircuit) > 0)
<div class="max-h-72 overflow-y-auto space-y-0">
    @foreach($historiqueCircuit as $h)
    <div class="flex gap-3 py-2.5 border-b border-slate-100 dark:border-slate-700 last:border-0">
        <div class="flex-shrink-0 w-2 h-2 mt-1.5 rounded-full
            {{ in_array($h['evenement'], ['cloture_circuit', 'avancement'], true) ? 'bg-emerald-400' : ($h['evenement'] === 'alerte_retard' || $h['evenement'] === 'relance' ? 'bg-amber-500' : 'bg-sky-400') }}"></div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $h['libelle'] }}</p>
            @if($h['etape'])
            <p class="text-xs text-slate-500">Étape : {{ $h['etape'] }}</p>
            @endif
            @if($h['commentaire'])
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">{{ $h['commentaire'] }}</p>
            @endif
            <p class="text-[11px] text-slate-500 mt-1">
                {{ $h['date']?->format('d/m/Y à H:i') ?? '—' }}
                @if($h['user']) · {{ $h['user'] }} @endif
            </p>
        </div>
    </div>
    @endforeach
</div>
@else
<p class="text-sm text-slate-500 py-4">Aucun mouvement de circuit enregistré.</p>
@endif
