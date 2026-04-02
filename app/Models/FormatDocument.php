<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormatDocument extends Model
{
    protected $table = 'formats_documents';

    protected $fillable = ['code', 'libelle', 'type_mime', 'extension_defaut', 'est_archivable', 'actif'];

    protected function casts(): array
    {
        return [
            'est_archivable' => 'boolean',
            'actif' => 'boolean',
        ];
    }
}
