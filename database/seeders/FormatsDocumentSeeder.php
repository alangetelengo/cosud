<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormatsDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            ['code' => 'PDF', 'libelle' => 'PDF', 'type_mime' => 'application/pdf', 'extension_defaut' => 'pdf', 'est_archivable' => true],
            ['code' => 'DOCX', 'libelle' => 'Word', 'type_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension_defaut' => 'docx', 'est_archivable' => true],
            ['code' => 'XLSX', 'libelle' => 'Excel', 'type_mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extension_defaut' => 'xlsx', 'est_archivable' => true],
            ['code' => 'JPEG', 'libelle' => 'Image JPEG', 'type_mime' => 'image/jpeg', 'extension_defaut' => 'jpg', 'est_archivable' => true],
            ['code' => 'PNG', 'libelle' => 'Image PNG', 'type_mime' => 'image/png', 'extension_defaut' => 'png', 'est_archivable' => true],
        ];

        $now = now();
        foreach ($formats as $format) {
            $row = array_merge($format, ['actif' => true, 'updated_at' => $now]);
            if (! DB::table('formats_documents')->where('code', $format['code'])->exists()) {
                $row['created_at'] = $now;
            }
            DB::table('formats_documents')->updateOrInsert(['code' => $format['code']], $row);
        }
    }
}
