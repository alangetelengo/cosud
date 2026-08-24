<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>État récapitulatif de suivi des dépenses — {{ $annee }}</title>
<style>
@page { size: A4 portrait; margin: 18mm 12mm 16mm 12mm; }

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 11px;
    color: #000;
    margin: 0;
    padding: 0;
    background-image: url("{{ public_path('images/Charte.png') }}");
    background-size: cover;
    background-repeat: no-repeat;
    background-position: top center;
}

/* Espace sous le bandeau Charte (République / ministère / ACSI) */
.zone0 {
    text-align: center;
    font-size: 15px;
    font-weight: bold;
    margin: 165px 0 8px;
    color: #000;
    text-transform: uppercase;
}

.zone1 {
    font-size: 10px;
    font-weight: bold;
    margin: 10px 10px 10px 45px;
    color: #000;
}

.sous-titre {
    text-align: center;
    font-size: 12px;
    margin: 0 0 4px;
}

table.paiement {
    width: 90%;
    margin: 12px auto 0;
    border-collapse: collapse;
    font-size: 10.5px;
}

table.paiement th,
table.paiement td {
    border: 1px solid #ccc;
    padding: 5px;
}

table.paiement th {
    background-color: #f2f2f2;
    font-weight: bold;
    text-transform: uppercase;
}

table.paiement tr:nth-child(even) { background-color: #fafafa; }
table.paiement tr:nth-child(odd) { background-color: #fff; }

.total-row {
    background-color: #e0e0e0;
    font-weight: bold;
}

.text-right { text-align: right; }
.text-center { text-align: center; }

.arrete {
    margin-top: 10px;
    width: 90%;
    margin-left: auto;
    margin-right: auto;
    font-size: 11px;
}

.signature {
    margin-top: 28px;
    margin-right: 40px;
    text-align: right;
    font-size: 11px;
}
</style>
</head>
<body>
<main>
<div class="zone0">État récapitulatif de suivi des dépenses</div>
<p class="sous-titre">
    <strong>Relatif à : Suivi des dépenses</strong>
    @if($categorieLibelle)
        — {{ $categorieLibelle }}
    @endif
</p>

@if($periodeDebut || $periodeFin)
<p class="sous-titre">
    Période
    @if($periodeDebut)<b>du {{ \Illuminate\Support\Carbon::parse($periodeDebut)->format('d/m/Y') }}</b>@endif
    @if($periodeFin)<b> au {{ \Illuminate\Support\Carbon::parse($periodeFin)->format('d/m/Y') }}</b>@endif
</p>
@else
<p class="sous-titre">Année <b>{{ $annee }}</b></p>
@endif

<div class="zone1">Ref: SDEP/{{ $annee }}/{{ now()->format('Ymd') }}</div>

<table class="paiement">
<thead>
<tr>
<th style="width:5%;">N°</th>
<th style="width:12%;">Date</th>
<th style="width:14%;">Réf. pièce</th>
<th style="width:18%;">Catégorie</th>
<th style="width:26%;">Intitulé / Bénéficiaire</th>
<th style="width:15%;">Montant (FCFA)</th>
</tr>
</thead>
<tbody>
@foreach ($lignes as $ligne)
<tr>
<td class="text-center">{{ $loop->iteration }}</td>
<td class="text-center">{{ $ligne->date_suivi?->format('d/m/Y') }}</td>
<td>{{ $ligne->numero_piece ?: '—' }}</td>
<td>{{ $ligne->categorieDepense?->libelle ?? '—' }}</td>
<td>
    {{ $ligne->intitule }}
    <div style="font-size:9px;color:#555;">{{ $ligne->beneficiaire_libelle ?: ($ligne->fournisseur_libelle ?? '') }}</div>
</td>
<td class="text-right">{{ number_format((float) $ligne->montant, 0, ',', ' ') }}</td>
</tr>
@endforeach

<tr class="total-row">
<td colspan="5" class="text-right">TOTAL GÉNÉRAL</td>
<td class="text-right">{{ number_format((float) $totalMontant, 0, ',', ' ') }} FCFA</td>
</tr>
</tbody>
</table>

<p class="arrete">
Arrêté le présent état à la somme de :
<strong>{{ $montantEnLettres }} francs CFA</strong>
({{ number_format((float) $totalMontant, 0, ',', ' ') }} FCFA — {{ $lignes->count() }} ligne(s)).
</p>

<div class="signature">
    Fait à Brazzaville, le {{ now()->format('d/m/Y') }} <br><br>
    <strong>Responsable suivi des dépenses</strong><br><br><br><br>
    <strong><u style="font-size: 12px;">{{ $signataire ?? '—' }}</u></strong>
</div>
</main>
</body>
</html>
