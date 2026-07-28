<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registre {{ $sensCode === 'depart' ? 'Départ' : 'Arrivée' }} {{ $annee }} — GED ACSI</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Times New Roman", Times, Georgia, serif;
            color: #0f172a;
            background: #e8e4d9;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #0f172a;
            color: #fff;
            font-family: system-ui, sans-serif;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 0;
            border-radius: 0.5rem;
            padding: 0.55rem 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
            background: #f8fafc;
            font-size: 0.875rem;
        }
        .toolbar .btn-print { background: #06a269; color: #fff; }
        .sheet {
            max-width: 297mm;
            margin: 1rem auto 2rem;
            background: #fffef9;
            border: 1px solid #b8a67a;
            padding: 14mm 10mm 12mm;
            box-shadow: 0 12px 28px rgba(0,0,0,0.18);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 2px solid #334155;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .eyebrow {
            font-family: system-ui, sans-serif;
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 4px;
        }
        .title {
            margin: 0;
            font-size: 34px;
            letter-spacing: 0.1em;
            color: {{ $sensCode === 'depart' ? '#1d4ed8' : '#0f172a' }};
        }
        .meta {
            text-align: right;
            font-family: system-ui, sans-serif;
            font-size: 12px;
            color: #334155;
        }
        .registre-table { width: 100%; border-collapse: collapse; }
        .registre-th {
            border: 1px solid #1e293b;
            background: {{ $sensCode === 'depart' ? '#dbeafe' : '#e2e8f0' }};
            padding: 6px 4px;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            line-height: 1.2;
            vertical-align: middle;
        }
        .registre-td {
            border: 1px solid #475569;
            padding: 6px 5px;
            vertical-align: top;
            font-size: 11px;
            line-height: 1.35;
        }
        .registre-row:nth-child(even) .registre-td { background: #faf7f0; }
        .footer {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            font-family: system-ui, sans-serif;
            font-size: 10px;
            color: #64748b;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                margin: 0;
                max-width: none;
                border: 0;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div>
            <strong>Impression registre {{ $sensCode === 'depart' ? 'Départ' : 'Arrivée' }}</strong>
            — utilisez « Enregistrer au format PDF » dans la boîte d’impression
        </div>
        <div style="display:flex; gap:0.5rem;">
            <a href="{{ $sensCode === 'depart' ? route('courriers.registres.depart', ['annee' => $annee]) : route('courriers.registres.arrivee', ['annee' => $annee]) }}">Retour</a>
            <button type="button" class="btn-print" onclick="window.print()">Imprimer / PDF</button>
        </div>
    </div>

    <div class="sheet">
        <div class="header">
            <div>
                <p class="eyebrow">{{ $libelleStructureRegistre }}</p>
                <h1 class="title">{{ $sensCode === 'depart' ? 'DÉPART' : 'ARRIVÉE' }}</h1>
            </div>
            <div class="meta">
                <div>Année {{ $annee }}</div>
                <div>{{ $courriers->count() }} entrée(s)</div>
                <div>Édité le {{ now()->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        @include('courriers.registres.partials.table', [
            'courriers' => $courriers,
            'sensCode' => $sensCode,
            'annee' => $annee,
        ])

        <div class="footer">
            <span>GED ACSI — Registre du courrier {{ $sensCode === 'depart' ? 'départ' : 'arrivée' }}</span>
            <span>Page imprimable A4 paysage</span>
        </div>
    </div>

    <script>
        // Ouverture directe : prêt pour Ctrl+P / PDF
        window.addEventListener('load', function () {
            if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
