<?php

namespace App\Services;

use App\Models\Document;
use App\Models\MetadonneeDocument;
use App\Models\TypeMetadonnee;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class MetadonneeExtracteur
{
    /** Mapping des clés PDF vers les codes TypeMetadonnee */
    private const PDF_KEY_MAP = [
        'Author' => 'auteur',
        'Title' => 'titre',
        'Creator' => 'createur',
        'Producer' => 'producteur',
        'CreationDate' => 'date_creation',
        'ModDate' => 'date_modification',
        'Keywords' => 'mots_cles',
    ];

    public function extrairePourDocument(Document $document): int
    {
        $ext = strtolower($document->extension ?? pathinfo($document->nom_original ?? '', PATHINFO_EXTENSION));
        $path = Storage::disk('public')->path($document->chemin);

        if (! file_exists($path)) {
            return 0;
        }

        if ($ext === 'pdf') {
            return $this->extrairePdf($document, $path);
        }

        return 0;
    }

    private function extrairePdf(Document $document, string $path): int
    {
        $count = 0;

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($path);
            $details = $pdf->getDetails();

            if (empty($details)) {
                return 0;
            }

            $nbPages = $pdf->getPages() ? count($pdf->getPages()) : null;
            if ($nbPages !== null) {
                $this->sauvegarderMetadonnee($document, 'nb_pages', valeurNumerique: (float) $nbPages);
                $count++;
            }

            foreach (self::PDF_KEY_MAP as $pdfKey => $code) {
                if (! isset($details[$pdfKey])) {
                    continue;
                }
                $valeur = $details[$pdfKey];
                if ($valeur === '' || $valeur === null) {
                    continue;
                }

                $type = TypeMetadonnee::where('code', $code)->where('actif', true)->first();
                if (! $type) {
                    continue;
                }

                if (in_array($code, ['date_creation', 'date_modification'])) {
                    $date = $this->parsePdfDate((string) $valeur);
                    if ($date) {
                        $this->sauvegarderMetadonnee($document, $code, valeurDate: $date);
                        $count++;
                    }
                } else {
                    $this->sauvegarderMetadonnee($document, $code, valeurTexte: (string) $valeur);
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $count;
    }

    /** Parse une date au format PDF (D:YYYYMMDDHHmmSS+HH'mm') */
    private function parsePdfDate(string $str): ?\Carbon\Carbon
    {
        if (preg_match('/D:(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?/', $str, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            $h = isset($m[4]) ? (int) $m[4] : 0;
            $i = isset($m[5]) ? (int) $m[5] : 0;
            $s = isset($m[6]) ? (int) $m[6] : 0;
            try {
                return \Carbon\Carbon::create($y, $mo, $d, $h, $i, $s);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }

    private function sauvegarderMetadonnee(
        Document $document,
        string $code,
        ?string $valeurTexte = null,
        ?\DateTimeInterface $valeurDate = null,
        ?float $valeurNumerique = null
    ): void {
        $type = TypeMetadonnee::where('code', $code)->where('actif', true)->first();
        if (! $type) {
            return;
        }

        MetadonneeDocument::updateOrCreate(
            [
                'document_id' => $document->id,
                'cle' => $code,
            ],
            [
                'type_metadonnee_id' => $type->id,
                'valeur' => $valeurTexte,
                'valeur_numerique' => $valeurNumerique,
                'valeur_date' => $valeurDate,
                'ordre_affichage' => $type->id,
            ]
        );
    }
}
