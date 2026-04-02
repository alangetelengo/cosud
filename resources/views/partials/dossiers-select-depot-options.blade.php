{{-- Options <select> dépôt document : personnel d’abord, puis plan de classement. --}}
@php
    $selected = (string) ($selectedDossierId ?? '');
@endphp
<option value="">— Aucun dossier —</option>
@if($dossiersPersoDepot->isNotEmpty())
<optgroup label="Mon espace personnel">
    @foreach($dossiersPersoDepot as $d)
    <option value="{{ $d->id }}" @selected((string) $d->id === $selected)>{{ $d->chemin_complet }}</option>
    @endforeach
</optgroup>
@endif
@if($dossiersPlanDepot->isNotEmpty())
@if($dossiersPersoDepot->isNotEmpty())
<optgroup label="Plan de classement">
@endif
@foreach($dossiersPlanDepot as $d)
    <option value="{{ $d->id }}" @selected((string) $d->id === $selected)>{{ $d->chemin_complet }}</option>
@endforeach
@if($dossiersPersoDepot->isNotEmpty())
</optgroup>
@endif
@endif
