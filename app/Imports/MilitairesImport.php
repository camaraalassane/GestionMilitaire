<?php

namespace App\Imports;

use App\Models\Militaire;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MilitairesImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use SkipsFailures;

    protected string $duplicateAction;
    protected array $summary = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    protected array $duplicates = [];

    public function __construct(string $duplicateAction = 'ignore')
    {
        $this->duplicateAction = $duplicateAction;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            try {
                // Vérifier si le matricule existe déjà
                $existing = Militaire::where('matricule', $row['matricule'])->first();

                if ($existing) {
                    if ($this->duplicateAction === 'update') {
                        $this->updateMilitaire($existing, $row);
                        $this->summary['updated']++;
                    } else {
                        $this->summary['skipped']++;
                        $this->duplicates[] = [
                            'matricule' => $row['matricule'],
                            'nom' => $row['nom'] ?? '',
                            'prenom' => $row['prenom'] ?? ''
                        ];
                    }
                    continue;
                }

                $this->createMilitaire($row);
                $this->summary['created']++;

            } catch (\Exception $e) {
                $this->summary['errors']++;
                Log::error('Erreur import: ' . $e->getMessage(), ['row' => $row]);
            }
        }
    }

    protected function createMilitaire(array $row): void
    {
        $militaire = new Militaire();
        $militaire->matricule = $row['matricule'];
        $militaire->nom = $row['nom'] ?? '';
        $militaire->prenom = $row['prenom'] ?? '';
        $militaire->date_naissance = isset($row['date_naissance']) ? Carbon::parse($row['date_naissance']) : null;
        $militaire->date_entree_service = isset($row['date_entree_service']) ? Carbon::parse($row['date_entree_service']) : null;
        $militaire->grade_actuel = $row['grade_actuel'] ?? null;
        $militaire->date_derniere_promotion = isset($row['date_derniere_promotion']) ? Carbon::parse($row['date_derniere_promotion']) : null;
        $militaire->specialite = $row['specialite'] ?? null;
        $militaire->statut = $row['statut'] ?? 'actif';
        $militaire->telephone = $row['telephone'] ?? null;
        $militaire->sexe = $row['sexe'] ?? null;
        $militaire->groupe_sanguin = $row['groupe_sanguin'] ?? null;
        $militaire->personne_a_contacter = $row['personne_a_contacter'] ?? null;
        $militaire->telephone_personne_contacter = $row['telephone_personne_contacter'] ?? null;
        $militaire->position_actuelle = $row['position_actuelle'] ?? null;
        $militaire->fonction_passee = $row['fonction_passee'] ?? null;
        $militaire->fonction_actuelle = $row['fonction_actuelle'] ?? null;
        $militaire->a_permis_conduire = isset($row['a_permis_conduire']) ? (bool)$row['a_permis_conduire'] : false;
        $militaire->a_fait_justice = isset($row['a_fait_justice']) ? (bool)$row['a_fait_justice'] : false;
        $militaire->a_fait_discipline = isset($row['a_fait_discipline']) ? (bool)$row['a_fait_discipline'] : false;
        $militaire->save();
    }

    protected function updateMilitaire(Militaire $militaire, array $row): void
    {
        $militaire->nom = $row['nom'] ?? $militaire->nom;
        $militaire->prenom = $row['prenom'] ?? $militaire->prenom;
        $militaire->date_naissance = isset($row['date_naissance']) ? Carbon::parse($row['date_naissance']) : $militaire->date_naissance;
        $militaire->date_entree_service = isset($row['date_entree_service']) ? Carbon::parse($row['date_entree_service']) : $militaire->date_entree_service;
        $militaire->grade_actuel = $row['grade_actuel'] ?? $militaire->grade_actuel;
        $militaire->date_derniere_promotion = isset($row['date_derniere_promotion']) ? Carbon::parse($row['date_derniere_promotion']) : $militaire->date_derniere_promotion;
        $militaire->specialite = $row['specialite'] ?? $militaire->specialite;
        $militaire->statut = $row['statut'] ?? $militaire->statut;
        $militaire->telephone = $row['telephone'] ?? $militaire->telephone;
        $militaire->sexe = $row['sexe'] ?? $militaire->sexe;
        $militaire->groupe_sanguin = $row['groupe_sanguin'] ?? $militaire->groupe_sanguin;
        $militaire->personne_a_contacter = $row['personne_a_contacter'] ?? $militaire->personne_a_contacter;
        $militaire->telephone_personne_contacter = $row['telephone_personne_contacter'] ?? $militaire->telephone_personne_contacter;
        $militaire->position_actuelle = $row['position_actuelle'] ?? $militaire->position_actuelle;
        $militaire->fonction_passee = $row['fonction_passee'] ?? $militaire->fonction_passee;
        $militaire->fonction_actuelle = $row['fonction_actuelle'] ?? $militaire->fonction_actuelle;
        $militaire->a_permis_conduire = isset($row['a_permis_conduire']) ? (bool)$row['a_permis_conduire'] : $militaire->a_permis_conduire;
        $militaire->a_fait_justice = isset($row['a_fait_justice']) ? (bool)$row['a_fait_justice'] : $militaire->a_fait_justice;
        $militaire->a_fait_discipline = isset($row['a_fait_discipline']) ? (bool)$row['a_fait_discipline'] : $militaire->a_fait_discipline;
        $militaire->save();
    }

    public function rules(): array
    {
        return [
            'matricule' => 'required|string',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
        ];
    }

    public function getSummary(): array
    {
        return $this->summary;
    }

    public function getDuplicates(): array
    {
        return $this->duplicates;
    }

    public function getImportedCount(): int
    {
        return $this->summary['created'];
    }

    public function getSkippedCount(): int
    {
        return $this->summary['skipped'];
    }
}
