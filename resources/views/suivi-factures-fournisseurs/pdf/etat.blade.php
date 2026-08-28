<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Suivi factures fournisseurs et Prestataires — {{ $annee }}</title>
<style>
@page { size: A4 portrait; margin: 18mm 12mm 16mm 18mm; }

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

/* Décalage pour ne pas chevaucher la bande rouge/jaune de la Charte */
main {
    margin-left: 32px;
    margin-right: 4px;
}

.zone0 {
    text-align: center;
    font-size: 14px;
    font-weight: bold;
    margin: 165px 0 8px;
    color: #000;
    text-transform: uppercase;
}

.zone1 {
    font-size: 10px;
    font-weight: bold;
    margin: 10px 10px 10px 0;
    color: #000;
}

.sous-titre {
    text-align: center;
    font-size: 12px;
    margin: 0 0 4px;
}

table.paiement {
    width: 100%;
    margin: 12px 0 0;
    border-collapse: collapse;
    font-size: 9.5px;
}

table.paiement th,
table.paiement td {
    border: 1px solid #ccc;
    padding: 4px;
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
    width: 100%;
    margin-left: 0;
    margin-right: 0;
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
<div class="zone0">État récapitulatif — factures fournisseurs et Prestataires</div>
<p class="sous-titre"><strong>Relatif à : Suivi factures fournisseurs et Prestataires</strong></p>
<p class="sous-titre">{{ $periodeLabel }}</p>

<div class="zone1">Ref: SFFP/{{ $annee }}/{{ now()->format('Ymd') }}</div>

<table class="paiement">
<thead>
<tr>
<th style="width:4%;">N°</th>
<th style="width:9%;">Date BPA</th>
<th style="width:11%;">N° registre</th>
<th style="width:14%;">Fournisseur</th>
<th style="width:18%;">Objet</th>
<th style="width:11%;">Statut</th>
<th style="width:11%;">Montant facture</th>
<th style="width:11%;">Payé</th>
<th style="width:11%;">Reliquat</th>
</tr>
</thead>
<tbody>
@foreach ($lignes as $ligne)
@php $c = $ligne['courrier']; @endphp
<tr>
<td class="text-center">{{ $loop->iteration }}</td>
<td class="text-center">{{ $c->date_orientation?->format('d/m/Y') ?? '—' }}</td>
<td>{{ $c->numeroRegistreComplet() }}</td>
<td>{{ $c->expediteur_libelle ?? '—' }}</td>
<td>{{ $c->objet }}</td>
<td>{{ $ligne['libelle_statut'] }}</td>
<td class="text-right">{{ $service->formaterMontant($ligne['montant_facture']) }}</td>
<td class="text-right">{{ $ligne['montant_paye'] > 0 ? $service->formaterMontant($ligne['montant_paye']) : '—' }}</td>
<td class="text-right">{{ $service->formaterMontant($ligne['reliquat']) }}</td>
</tr>
@endforeach

<tr class="total-row">
<td colspan="6" class="text-right">TOTAL FACTURES</td>
<td class="text-right">{{ number_format((float) $totalMontant, 0, ',', ' ') }}</td>
<td class="text-right">{{ number_format((float) $totalPaye, 0, ',', ' ') }}</td>
<td class="text-right">{{ number_format((float) $totalReliquat, 0, ',', ' ') }}</td>
</tr>
</tbody>
</table>

<p class="arrete">
Arrêté le présent état à la somme de :
<strong>{{ $montantEnLettres }} francs CFA</strong>
({{ number_format((float) $totalMontant, 0, ',', ' ') }} FCFA de factures —
{{ number_format((float) $totalPaye, 0, ',', ' ') }} FCFA payés —
{{ number_format((float) $totalReliquat, 0, ',', ' ') }} FCFA de reliquats —
{{ $lignes->count() }} facture(s)).
</p>

<div class="signature">
    Fait à Brazzaville, le {{ now()->format('d/m/Y') }} <br><br>
    <strong>Responsable dossiers fournisseurs et prestataires</strong><br><br><br><br>
    <strong><u style="font-size: 12px;">{{ $signataire ?? '—' }}</u></strong>
</div>
</main>
</body>
</html>
