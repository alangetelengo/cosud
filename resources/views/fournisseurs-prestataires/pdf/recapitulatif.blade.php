<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Tableau récapitulatif contrats et dossiers fiscaux</title>
<style>
@page { size: A4 landscape; margin: 12mm 12mm 14mm 14mm; }
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10px;
    color: #000;
    margin: 0;
    padding: 0;
}
/*
 * En-tête Charte uniquement en 1re page (bloc dans le flux).
 * Un background sur body se répéterait et chevaucherait le tableau.
 */
.entete-officiel {
    height: 42mm;
    margin: -8mm -8mm 6px -10mm;
    background-image: url("{{ public_path('images/Charte.png') }}");
    background-size: cover;
    background-repeat: no-repeat;
    background-position: top center;
}
main {
    margin-left: 48px;
    margin-right: 4px;
}
.zone0 {
    text-align: center;
    font-size: 12px;
    font-weight: bold;
    margin: 0 0 4px;
    line-height: 1.35;
    text-transform: uppercase;
}
.sous-titre {
    text-align: center;
    font-size: 9px;
    margin: 0 0 10px;
    color: #333;
    line-height: 1.3;
}
table.recap {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.5px;
    table-layout: fixed;
}
table.recap th,
table.recap td {
    border: 1px solid #333;
    padding: 3px 4px;
    vertical-align: top;
    word-wrap: break-word;
    overflow-wrap: break-word;
}
table.recap th {
    background-color: #f2f2f2;
    font-weight: bold;
    text-transform: uppercase;
}
.text-center { text-align: center; }
.signature {
    margin-top: 28px;
    margin-right: 24px;
    text-align: right;
    font-size: 10px;
    line-height: 1.4;
}
</style>
</head>
<body>
<div class="entete-officiel" aria-hidden="true"></div>
<main>
<div class="zone0">Tableau récapitulatif des contrats et dossiers fiscaux<br>des partenaires et fournisseurs de l’ACSI</div>
<p class="sous-titre">Édité le {{ $genereLe->format('d/m/Y à H:i') }} — {{ $lignes->count() }} ligne(s)</p>

<table class="recap">
<thead>
<tr>
    <th style="width:5%;">N°</th>
    <th style="width:22%;">Nom du partenaire / fournisseurs</th>
    <th style="width:28%;">Type de contrats</th>
    <th style="width:8%;">Contrat</th>
    <th style="width:10%;">Dossier fiscal</th>
    <th style="width:27%;">Observation</th>
</tr>
</thead>
<tbody>
@forelse($lignes as $i => $ligne)
<tr>
    <td class="text-center">{{ $i + 1 }}</td>
    <td>{{ $ligne->nom }}</td>
    <td>{{ $ligne->type_contrat ?: '—' }}</td>
    <td class="text-center">{{ $ligne->libelleContratCourt() }}</td>
    <td class="text-center">{{ $ligne->libelleDossierFiscalCourt() }}</td>
    <td>{{ $ligne->observation ?: '' }}</td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center">Aucun enregistrement.</td>
</tr>
@endforelse
</tbody>
</table>

<div class="signature">
    Fait à Brazzaville, le {{ $genereLe->format('d/m/Y') }} <br><br>
    <strong>Responsable dossiers fournisseurs et prestataires</strong><br><br><br><br>
    <strong><u style="font-size: 12px;">{{ $signataire ?? '—' }}</u></strong>
</div>
</main>
{{-- Numérotation uniquement si le document fait plus d’une page --}}
<script type="text/php">
if (isset($pdf)) {
    $pdf->page_script('
        if ($PAGE_COUNT > 1) {
            $font = $fontMetrics->getFont("DejaVu Sans");
            $size = 8;
            $text = "Page " . $PAGE_NUM . " / " . $PAGE_COUNT;
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $pdf->text($pdf->get_width() - $width - 36, $pdf->get_height() - 28, $text, $font, $size);
        }
    ');
}
</script>
</body>
</html>
