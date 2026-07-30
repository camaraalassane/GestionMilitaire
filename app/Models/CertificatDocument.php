<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificatDocument extends Model
{
    protected $fillable = [
        'militaire_certificat_id',
        'nom_fichier',
        'chemin_fichier',
        'type_fichier',
        'taille'
    ];

    /**
     * Relation vers la table pivot militaire_certificat
     */
    public function militaireCertificat(): BelongsTo
    {
        return $this->belongsTo(Militaire::class, 'militaire_certificat_id', 'id');
    }
}
