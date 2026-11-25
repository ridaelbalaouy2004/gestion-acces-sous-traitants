<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'module', 'label'])]
class Permission extends Model
{
    public const CATALOG = [
        'prestataires.view' => ['module' => 'prestataires', 'label' => 'Consulter les Prestataires'],
        'prestataires.create' => ['module' => 'prestataires', 'label' => 'Créer un Prestataire'],
        'prestataires.update' => ['module' => 'prestataires', 'label' => 'Modifier un Prestataire'],
        'prestataires.toggle_status' => ['module' => 'prestataires', 'label' => 'Activer ou désactiver un Prestataire'],
        'documents.download' => ['module' => 'documents', 'label' => 'Télécharger les documents'],
        'entreprises.view' => ['module' => 'entreprises', 'label' => 'Consulter les entreprises'],
        'entreprises.edit' => ['module' => 'entreprises', 'label' => 'Compléter ou modifier les informations des entreprises'],
        'entreprises.validate' => ['module' => 'entreprises', 'label' => 'Valider les entreprises et leurs documents'],
        'entreprises.reject' => ['module' => 'entreprises', 'label' => 'Rejeter les entreprises et leurs documents'],
        'entreprises.download' => ['module' => 'entreprises', 'label' => 'Télécharger et prévisualiser les documents des entreprises'],
        'employees.view' => ['module' => 'employees', 'label' => 'Consulter les employés'],
        'employees.edit' => ['module' => 'employees', 'label' => 'Compléter ou modifier les informations des employés'],
        'employees.validate' => ['module' => 'employees', 'label' => 'Valider les employés et leurs documents'],
        'employees.reject' => ['module' => 'employees', 'label' => 'Rejeter les employés et leurs documents'],
        'employees.download' => ['module' => 'employees', 'label' => 'Télécharger et prévisualiser les documents des employés'],
        'machinery.view' => ['module' => 'machinery', 'label' => 'Consulter les engins'],
        'machinery.update' => ['module' => 'machinery', 'label' => 'Compléter les informations des engins'],
        'machinery.approve' => ['module' => 'machinery', 'label' => 'Valider les engins et documents'],
        'machinery.reject' => ['module' => 'machinery', 'label' => 'Rejeter les engins et documents'],
        'machinery.download' => ['module' => 'machinery', 'label' => 'Télécharger les documents des engins'],
        'toolings.view' => ['module' => 'toolings', 'label' => 'Consulter les outillages'],
        'toolings.update' => ['module' => 'toolings', 'label' => 'Compléter les informations des outillages'],
        'toolings.approve' => ['module' => 'toolings', 'label' => 'Valider les outillages et documents'],
        'toolings.reject' => ['module' => 'toolings', 'label' => 'Rejeter les outillages et documents'],
        'toolings.download' => ['module' => 'toolings', 'label' => 'Télécharger les documents des outillages'],
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
