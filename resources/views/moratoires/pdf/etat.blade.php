<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Paiements progressifs — {{ $moratoire->fournisseur_libelle }}</title>
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
main {
    margin-left: 32px;
    margin-right: 4px;
}
.zone0 {
    text-align: center;
    font-size: 13px;
    font-weight: bold;
    margin: 165px 0 8px;
    color: #000;
    text-transform: uppercase;
}
.sous-titre { text-align: center; font-size: 11px; margin: 0 0 4px; }
table.paiement {
    width: 100%;
    margin: 12px 0 0;
    border-collapse: collapse;
    font-size: 9px;
}
table.paiement th,
table.paiement td {
    border: 1px solid #333;
    padding: 3px 4px;
}
table.paiement th {
    background-color: #f2f2f2;
    font-weight: bold;
    text-transform: uppercase;
}
.text-right { text-align: right; }
.text-center { text-align: center; }
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
<div class="zone0">État récapitulatif des paiements progressifs de {{ $moratoire->fournisseur_libelle }}</div>
<p class="sous-titre">Dette initiale : <strong>{{ number_format((float) $moratoire->montant_dette_initial, 0, ',', ' ') }} FCFA</strong>
({{ $montantEnLettres }} francs CFA)</p>

<table class="paiement">
<thead>
<tr>
<th style="width:5%;">N°</th>
<th style="width:13%;">Montant dette</th>
<th style="width:12%;">Échéancier</th>
<th style="width:11%;">Solde</th>
<th style="width:11%;">Date paiement</th>
<th style="width:12%;">Période</th>
<th style="width:9%;">Mode</th>
<th style="width:12%;">N° chèque</th>
<th style="width:15%;">OBS</th>
</tr>
</thead>
<tbody>
@foreach ($moratoire->echeances as $echeance)
<tr>
<td class="text-center">{{ $echeance->numero }}</td>
<td class="text-right">{{ number_format((float) $echeance->montant_dette, 0, ',', ' ') }}</td>
<td class="text-right">{{ number_format((float) $echeance->montant_echeance, 0, ',', ' ') }}</td>
<td class="text-right">{{ number_format((float) $echeance->solde, 0, ',', ' ') }}</td>
<td class="text-center">{{ $echeance->date_paiement?->format('d/m/Y') ?: '' }}</td>
<td class="text-center">{{ $echeance->libellePeriode() ?: '' }}</td>
<td class="text-center">{{ $echeance->mode_paiement ? $echeance->libelleModePaiement() : '' }}</td>
<td>{{ $echeance->mode_paiement === 'espece' ? '' : ($echeance->numero_cheque ?: '') }}</td>
<td>{{ $echeance->observation ?: '' }}</td>
</tr>
@endforeach
</tbody>
</table>

<div class="signature">
    {{ $moratoire->lieu ?: 'Brazzaville' }}, le
    {{ $moratoire->date_document?->format('d/m/Y') ?? '………………' }}
    <br><br>
    <strong>{{ $titreSignataire }}</strong><br><br><br><br>
    <strong><u style="font-size: 12px;">{{ $moratoire->signataire_libelle ?: '—' }}</u></strong>
</div>
</main>
</body>
</html>
