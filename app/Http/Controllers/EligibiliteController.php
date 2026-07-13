<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Alerte;
use App\Models\Contrat;
use App\Exports\EligibilitesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Collection;

class EligibiliteController extends Controller
{
    // 🔥 Durée du cache : 10 minutes
    const CACHE_DURATION = 600;
    const CACHE_TAG = 'eligibilites';

    /**
     * GRADES ÉLIGIBLES POUR LES CONTRATS (identique à AlerteController)
     * Sous-officiers jusqu'à 1ère classe uniquement
     */
    private function getGradesEligiblesContrat(): array
    {
        return [
            'Soldat 2',
            'Soldat 1',
            'Caporal',
            'Caporal-chef',
            'Sergent',
            'Sergent-Chef',
            'Adjudant',
            'Adjudant-Chef',
            'Major'
        ];
    }

    /**
     * Affiche la page des éligibilités avec les listes de filtres.
     */
    public function index(): \Inertia\Response
    {
        $formationsListe = $this->getFormationsListe();
        $gradesListe = $this->getGradesListe();

        return Inertia::render('eligibilites/index', [
            'formationsListe' => $formationsListe,
            'gradesListe' => $gradesListe
        ]);
    }

    /**
     * API: Récupère les éligibilités filtrées avec pagination.
     */
    public function getFiltered(Request $request): \Illuminate\Http\JsonResponse
    {
        $type = $request->input('type', 'promotions');
        $formation = $request->input('formation', '');
        $grade = $request->input('grade', '');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 30);

        $eligibilites = $this->getEligibilites($type, $formation, $grade, $page, $perPage);

        $response = [
            'data' => [],
            'total' => 0,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => 1,
            'statistiques' => [
                'total_promotions' => 0,
                'total_formations' => 0,
                'total_retraites' => 0,
                'total_contrats' => 0,
            ],
            'all_data' => []
        ];

        if (!empty($type)) {
            $response['data'] = $eligibilites[$type] ?? [];
            $response['total'] = $eligibilites['total'] ?? 0;
            $response['last_page'] = $eligibilites['last_page'] ?? 1;
            $response['statistiques'] = $eligibilites['statistiques'] ?? $response['statistiques'];
            $response['all_data'] = $eligibilites['all_data'] ?? [];
        } else {
            $response['data'] = $eligibilites['promotions'] ?? [];
            $response['total'] = $eligibilites['total'] ?? 0;
            $response['last_page'] = $eligibilites['last_page'] ?? 1;
            $response['statistiques'] = $eligibilites['statistiques'] ?? $response['statistiques'];
            $response['all_data'] = $eligibilites['all_data'] ?? [];
        }

        return response()->json($response);
    }

    /**
     * Récupère la liste unique de toutes les formations (avec cache).
     */
    private function getFormationsListe(): array
    {
        return cache()->remember('formations_liste', self::CACHE_DURATION, function () {
            return [
                ['id' => 'CAT1', 'nom' => 'CAT1 (Certificat Technique Niveau 1)'],
                ['id' => 'CAT2', 'nom' => 'CAT2 (Certificat Technique Niveau 2)'],
                ['id' => 'CIA', 'nom' => 'CIA (Certificat Instruction d\'Armes)'],
                ['id' => 'BA1', 'nom' => 'BA1 (Brevet Aptitude Niveau 1)'],
                ['id' => 'BA2', 'nom' => 'BA2 (Brevet Aptitude Niveau 2)'],
                ['id' => 'APLI', 'nom' => 'APLI (Cour d\'Application)'],
                ['id' => 'CFCU', 'nom' => 'CFCU (Cour des Futurs Commandants)'],
                ['id' => 'CEM', 'nom' => 'CEM (Cour d\'État-Major)'],
                ['id' => 'CERT_EM', 'nom' => 'Certificat d\'État-Major'],
                ['id' => 'ECOLE_GUERRE', 'nom' => 'École de Guerre'],
            ];
        });
    }

    /**
     * Récupère la liste unique de tous les grades (avec cache).
     */
    private function getGradesListe(): Collection
    {
        return cache()->remember('grades_liste', self::CACHE_DURATION, function () {
            $grades = \App\Models\Grade::orderBy('ordre')->get();
            return $grades->map(fn($g) => [
                'id' => $g->nom_grade,
                'nom' => $g->nom_grade
            ]);
        });
    }

    /**
     * Calcule les éligibilités avec filtres et pagination.
     */
    private function getEligibilites(string $type = '', string $formation = '', string $grade = '', int $page = 1, int $perPage = 30): array
    {
        $cacheKey = self::CACHE_TAG . ':' . md5($type . '|' . $formation . '|' . $grade);

        $cached = cache()->remember($cacheKey, self::CACHE_DURATION, function () use ($type, $formation, $grade) {
            return $this->computeEligibilites($type, $formation, $grade);
        });

        $allPromotions = $cached['promotions'];
        $allFormations = $cached['formations'];
        $allRetraites = $cached['retraites'];
        $allContrats = $cached['contrats'];

        // Statistiques
        $statistiques = [
            'total_promotions' => count($allPromotions),
            'total_formations' => count($allFormations),
            'total_retraites' => count($allRetraites),
            'total_contrats' => count($allContrats),
        ];

        // Structure de pagination
        $response = [
            'statistiques' => $statistiques,
            'total' => 0,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => 0,
            'all_data' => [
                'promotions' => $allPromotions,
                'formations' => $allFormations,
                'retraites' => $allRetraites,
                'contrats' => $allContrats,
            ]
        ];

        // Ajouter les données paginées pour chaque type
        if ($type === 'promotions' || empty($type)) {
            $response['promotions'] = $this->paginateArray($allPromotions, $page, $perPage);
            $response['total'] = max($response['total'], count($allPromotions));
            $response['last_page'] = max($response['last_page'], ceil(count($allPromotions) / $perPage));
            if ($type === 'promotions') {
                $response['data'] = $response['promotions'];
            }
        }

        if ($type === 'formations' || empty($type)) {
            $response['formations'] = $this->paginateArray($allFormations, $page, $perPage);
            $response['total'] = max($response['total'], count($allFormations));
            $response['last_page'] = max($response['last_page'], ceil(count($allFormations) / $perPage));
            if ($type === 'formations') {
                $response['data'] = $response['formations'];
            }
        }

        if ($type === 'retraites' || empty($type)) {
            $response['retraites'] = $this->paginateArray($allRetraites, $page, $perPage);
            $response['total'] = max($response['total'], count($allRetraites));
            $response['last_page'] = max($response['last_page'], ceil(count($allRetraites) / $perPage));
            if ($type === 'retraites') {
                $response['data'] = $response['retraites'];
            }
        }

        if ($type === 'contrats' || empty($type)) {
            $response['contrats'] = $this->paginateArray($allContrats, $page, $perPage);
            $response['total'] = max($response['total'], count($allContrats));
            $response['last_page'] = max($response['last_page'], ceil(count($allContrats) / $perPage));
            if ($type === 'contrats') {
                $response['data'] = $response['contrats'];
            }
        }

        if (empty($type)) {
            $response['data'] = $response['promotions'] ?? [];
            $response['total'] = count($allPromotions);
            $response['last_page'] = ceil(count($allPromotions) / $perPage);
        }

        return $response;
    }

    /**
     * Effectue le calcul complet (non paginé) des éligibilités.
     */
    private function computeEligibilites(string $type, string $formation, string $grade): array
    {
        $query = Militaire::where('statut', 'actif')
            ->with([
                'certificats' => function ($q) {
                    $q->select('certificats.id', 'niveau_certificat', 'nom_certificat');
                },
                'contratActif',
            ])
            ->select([
                'id', 'matricule', 'nom', 'prenom', 'grade_actuel',
                'date_entree_service', 'date_derniere_promotion',
                'statut', 'a_permis_conduire', 'a_fait_justice',
                'a_fait_discipline', 'date_naissance'
            ]);

        if (!empty($grade)) {
            $query->where('grade_actuel', $grade);
        }

        $militaires = $query->get();

        $militaireIds = $militaires->pluck('id')->toArray();
        $alertesContrat = Alerte::whereIn('militaire_id', $militaireIds)
            ->where('type_alerte', 'contrat')
            ->where('est_vue', false)
            ->get()
            ->keyBy('militaire_id');

        $dateProposition = $this->getDateProposition();

        $allPromotions = [];
        $allFormations = [];
        $allRetraites = [];
        $allContrats = [];

        // 🔥 Récupérer la liste des grades éligibles pour les contrats
        $gradesEligibles = $this->getGradesEligiblesContrat();

        foreach ($militaires as $militaire) {
            // Promotions
            if (empty($type) || $type === 'promotions') {
                $promos = $this->checkPromotions($militaire);
                $allPromotions = array_merge($allPromotions, $promos);
            }

            // Formations
            if (empty($type) || $type === 'formations') {
                $forms = $this->checkFormations($militaire, $formation);
                $allFormations = array_merge($allFormations, $forms);
            }

            // Retraites
            if (empty($type) || $type === 'retraites') {
                $retraites = $this->checkRetraite($militaire);
                $allRetraites = array_merge($allRetraites, $retraites);
            }

            // 🔥 CONTRATS - Mêmes conditions que AlerteController
            if (empty($type) || $type === 'contrats') {
                $contrats = $this->checkContratsARenouveler($militaire, $gradesEligibles);
                $allContrats = array_merge($allContrats, $contrats);
            }
        }

        // Tri des résultats
        usort($allPromotions, fn($a, $b) => strcmp($a['date_estimation'] ?? '', $b['date_estimation'] ?? ''));
        usort($allFormations, fn($a, $b) => strcmp($a['date_estimation'] ?? '', $b['date_estimation'] ?? ''));
        usort($allRetraites, fn($a, $b) => $a['date_retraite'] <=> $b['date_retraite']);
        usort($allContrats, fn($a, $b) => $b['annees_service'] <=> $a['annees_service']);

        return [
            'promotions' => $allPromotions,
            'formations' => $allFormations,
            'retraites' => $allRetraites,
            'contrats' => $allContrats,
        ];
    }

    /**
     * Invalider le cache des éligibilités
     */
    public function invalidateCache(Request $request): \Illuminate\Http\JsonResponse
    {
        $type = $request->input('type', '');
        $formation = $request->input('formation', '');
        $grade = $request->input('grade', '');

        $cacheKey = self::CACHE_TAG . ':' . md5($type . '|' . $formation . '|' . $grade);
        cache()->forget($cacheKey);

        return response()->json(['message' => 'Cache invalidé avec succès']);
    }

    /**
     * Pagine un tableau.
     */
    private function paginateArray(array $array, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return array_slice($array, $offset, $perPage);
    }

    /**
     * 🔥 METHODE CORRIGEE : Récupère uniquement les militaires qui DOIVENT renouveler
     * Conditions IDENTIQUES à AlerteController :
     * - Grade éligible (sous-officiers)
     * - 5 ans de service ou plus
     * - N'a PAS encore renouvelé (pas de contrat avec statut 'renouvele')
     */
    private function checkContratsARenouveler(Militaire $militaire, array $gradesEligibles): array
    {
        $result = [];

        // 🔥 1. Vérifier si le grade est éligible (comme dans AlerteController)
        if (!in_array($militaire->grade_actuel, $gradesEligibles)) {
            return $result;
        }

        // 🔥 2. Vérifier si le militaire a un contrat actif
        $contratActif = $militaire->contratActif;

        if (!$contratActif) {
            return $result;
        }

        // 🔥 3. Calculer les années de service (comme dans AlerteController)
        $anneesService = 0;
        if ($militaire->date_entree_service) {
            $anneesService = floor($militaire->date_entree_service->diffInYears(now()));
        }

        // 🔥 4. Ne garder que ceux qui ont 5 ans ou plus
        if ($anneesService < 5) {
            return $result;
        }

        // 🔥 5. EXCLURE ceux qui ont déjà renouvelé (contrat avec statut 'renouvele')
        $contratRenouvele = $militaire->contrats()
            ->where('statut', 'renouvele')
            ->exists();

        if ($contratRenouvele) {
            return $result; // Déjà renouvelé, on ne l'affiche pas
        }

        $statutContrat = $contratActif ? 'actif' : 'sans contrat';
        $dateEcheance = $contratActif?->date_fin?->format('Y-m-d');

        // Message personnalisé
        $message = "Renouvellement requis - {$anneesService} ans de service";

        $result[] = [
            'militaire' => [
                'id' => $militaire->id,
                'matricule' => $militaire->matricule,
                'nom' => $militaire->nom,
                'prenom' => $militaire->prenom,
                'grade_actuel' => $militaire->grade_actuel,
            ],
            'annees_service' => $anneesService,
            'statut_contrat' => $statutContrat,
            'date_echeance' => $dateEcheance,
            'message' => $message,
        ];

        return $result;
    }

    /**
     * Vérification des contrats optimisée (avec alerte) - pour l'affichage dans la page
     */
    private function checkContratsOptimized(Militaire $militaire, Collection $alertesContrat): array
    {
        $result = [];

        $alerte = $alertesContrat->get($militaire->id);

        if (!$alerte) {
            return $result;
        }

        $contratActif = $militaire->contratActif;

        $anneesService = 0;
        if ($militaire->date_entree_service) {
            $anneesService = floor($militaire->date_entree_service->diffInYears(now()));
        }

        $statutContrat = $contratActif ? 'actif' : 'sans contrat';

        $result[] = [
            'militaire' => [
                'id' => $militaire->id,
                'matricule' => $militaire->matricule,
                'nom' => $militaire->nom,
                'prenom' => $militaire->prenom,
                'grade_actuel' => $militaire->grade_actuel,
            ],
            'annees_service' => $anneesService,
            'statut_contrat' => $statutContrat,
            'date_echeance' => $contratActif?->date_fin?->format('Y-m-d'),
            'message' => $alerte->message,
        ];

        return $result;
    }

    /**
     * Vérifie les éligibilités aux promotions.
     */
    private function checkPromotions(Militaire $militaire): array
    {
        $result = [];

        $grade = $militaire->grade_actuel;
        $anciennete = $militaire->anciennete;
        $age = $militaire->age;
        $ancienneteGrade = $militaire->ancienneteGrade;
        $conditionsBase = !$militaire->a_fait_justice && !$militaire->a_fait_discipline;
        $certificatsObtenus = $militaire->certificats->pluck('niveau_certificat')->toArray();

        $dateProposition = $this->getDateProposition();

        // Soldat 1 → Caporal
        if ($grade == 'Soldat 1' && in_array('CAT1', $certificatsObtenus) && $conditionsBase && $anciennete >= 5) {
            $dateAnciennete = Carbon::parse($militaire->date_entree_service)->addYears(5);
            $result[] = $this->formatPromotion($militaire, 'Caporal', $dateProposition, $dateAnciennete);
        }

        // Caporal → Sergent
        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && in_array('CAT2', $certificatsObtenus) && $conditionsBase) {
            $certifCAT1 = $militaire->certificats->where('niveau_certificat', 'CAT1')->first();
            $dateAnciennete = null;
            if ($certifCAT1 && $certifCAT1->pivot->date_obtention) {
                $dateAnciennete = Carbon::parse($certifCAT1->pivot->date_obtention)->addYears(3);
            }
            $result[] = $this->formatPromotion($militaire, 'Sergent', $dateProposition, $dateAnciennete);
        }

        // Caporal → Caporal-chef
        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && !in_array('CAT2', $certificatsObtenus) && $age >= 47 && $ancienneteGrade >= 3 && $conditionsBase) {
            $dateAnciennete = $militaire->date_derniere_promotion ? Carbon::parse($militaire->date_derniere_promotion)->addYears(3) : null;
            $result[] = $this->formatPromotion($militaire, 'Caporal-chef', $dateProposition, $dateAnciennete, "âge ≥ 47 ans");
        }

        // Sergent → Sergent-Chef
        if ($grade == 'Sergent' && $conditionsBase && $ancienneteGrade >= 2 && $anciennete >= 5) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(2);
            $result[] = $this->formatPromotion($militaire, 'Sergent-Chef', $dateProposition, $dateAnciennete);
        }

        // Sergent-Chef → Adjudant
        if ($grade == 'Sergent-Chef' && $conditionsBase && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $result[] = $this->formatPromotion($militaire, 'Adjudant', $dateProposition, $dateAnciennete);
        }

        // Adjudant → Adjudant-Chef
        if ($grade == 'Adjudant' && $conditionsBase && $ancienneteGrade >= 2) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(2);
            $result[] = $this->formatPromotion($militaire, 'Adjudant-Chef', $dateProposition, $dateAnciennete);
        }

        // Sous-lieutenant → Lieutenant
        if ($grade == 'Sous-lieutenant' && $ancienneteGrade >= 2) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(2);
            $result[] = $this->formatPromotion($militaire, 'Lieutenant', $dateProposition, $dateAnciennete);
        }

        // Lieutenant → Capitaine
        if ($grade == 'Lieutenant' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $result[] = $this->formatPromotion($militaire, 'Capitaine', $dateProposition, $dateAnciennete);
        }

        // Capitaine → Commandant
        if ($grade == 'Capitaine' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $result[] = $this->formatPromotion($militaire, 'Commandant', $dateProposition, $dateAnciennete);
        }

        // Commandant → Lieutenant-colonel
        if ($grade == 'Commandant' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $result[] = $this->formatPromotion($militaire, 'Lieutenant-colonel', $dateProposition, $dateAnciennete);
        }

        // Lieutenant-colonel → Colonel
        if ($grade == 'Lieutenant-colonel' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $result[] = $this->formatPromotion($militaire, 'Colonel', $dateProposition, $dateAnciennete);
        }

        // Colonel → Colonel-Major
        if ($grade == 'Colonel' && $ancienneteGrade >= 6) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(6);
            $result[] = $this->formatPromotion($militaire, 'Colonel-Major', $dateProposition, $dateAnciennete);
        }

        return $result;
    }

    /**
     * Vérifie les éligibilités aux formations.
     */
    private function checkFormations(Militaire $militaire, string $formationFiltre = ''): array
    {
        $result = [];

        $grade = $militaire->grade_actuel;
        $age = $militaire->age;
        $anciennete = $militaire->anciennete;
        $ancienneteGrade = $militaire->ancienneteGrade;
        $certificatsObtenus = $militaire->certificats->pluck('niveau_certificat')->toArray();
        $conditionsBase = !$militaire->a_fait_justice && !$militaire->a_fait_discipline;

        $dateProposition = $this->getDateProposition();

        // CAT1
        if (in_array($grade, ['Soldat 2', 'Soldat 1']) && !in_array('CAT1', $certificatsObtenus) && $ancienneteGrade >= 5 && $conditionsBase) {
            if (empty($formationFiltre) || $formationFiltre === 'CAT1') {
                $dateConditions = Carbon::parse($militaire->date_entree_service)->addYears(5);
                $result[] = $this->formatFormation($militaire, 'CAT1', 'Certificat d\'Aptitude Technique Niveau 1', $dateProposition, $dateConditions);
            }
        }

        // CAT2
        if ($grade == 'Caporal' && $age < 47 && !in_array('CAT2', $certificatsObtenus) && $ancienneteGrade >= 3 && $conditionsBase && in_array('CAT1', $certificatsObtenus)) {
            if (empty($formationFiltre) || $formationFiltre === 'CAT2') {
                $certifCAT1 = $militaire->certificats->where('niveau_certificat', 'CAT1')->first();
                $dateConditions = null;
                if ($certifCAT1 && $certifCAT1->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifCAT1->pivot->date_obtention)->addYears(3);
                }
                $result[] = $this->formatFormation($militaire, 'CAT2', 'Certificat d\'Aptitude Technique Niveau 2', $dateProposition, $dateConditions);
            }
        }

        // CIA
        if (in_array($grade, ['Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && !in_array('CIA', $certificatsObtenus) && $conditionsBase && $militaire->a_permis_conduire) {
            if (empty($formationFiltre) || $formationFiltre === 'CIA') {
                $result[] = $this->formatFormation($militaire, 'CIA', 'Certificat d\'Instruction d\'Armes', $dateProposition, null, "permis de conduire requis");
            }
        }

        // BA1
        if (in_array($grade, ['Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && !in_array('BA1', $certificatsObtenus) && in_array('CIA', $certificatsObtenus) && $conditionsBase && $anciennete >= 8) {
            if (empty($formationFiltre) || $formationFiltre === 'BA1') {
                $certifCIA = $militaire->certificats->where('niveau_certificat', 'CIA')->first();
                $dateConditions = null;
                if ($certifCIA && $certifCIA->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifCIA->pivot->date_obtention)->addYears(3);
                }
                $result[] = $this->formatFormation($militaire, 'BA1', 'Brevet d\'Aptitude Niveau 1', $dateProposition, $dateConditions);
            }
        }

        // BA2
        if (in_array($grade, ['Adjudant', 'Adjudant-Chef']) && !in_array('BA2', $certificatsObtenus) && in_array('BA1', $certificatsObtenus) && $conditionsBase) {
            if (empty($formationFiltre) || $formationFiltre === 'BA2') {
                $certifBA1 = $militaire->certificats->where('niveau_certificat', 'BA1')->first();
                $dateConditions = null;
                if ($certifBA1 && $certifBA1->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifBA1->pivot->date_obtention)->addYears(3);
                }
                $result[] = $this->formatFormation($militaire, 'BA2', 'Brevet d\'Aptitude Niveau 2', $dateProposition, $dateConditions);
            }
        }

        // APLI
        if (in_array($grade, ['Sous-lieutenant', 'Lieutenant', 'Capitaine']) && !in_array('APLI', $certificatsObtenus) && !in_array('CFCU', $certificatsObtenus) && $age <= 50) {
            if (empty($formationFiltre) || $formationFiltre === 'APLI') {
                $result[] = $this->formatFormation($militaire, 'APLI', 'Cour d\'Application', $dateProposition, null, "âge ≤ 50 ans");
            }
        }

        // CFCU
        if (in_array($grade, ['Lieutenant', 'Capitaine']) && !in_array('CFCU', $certificatsObtenus)) {
            if (empty($formationFiltre) || $formationFiltre === 'CFCU') {
                if ($grade == 'Capitaine' || in_array('APLI', $certificatsObtenus)) {
                    $condition = $grade == 'Capitaine' ? "grade Capitaine" : "APLI validé";
                    $result[] = $this->formatFormation($militaire, 'CFCU', 'Cour des Futurs Commandants d\'Unité', $dateProposition, null, $condition);
                }
            }
        }

        // CEM
        if (in_array($grade, ['Capitaine', 'Commandant']) && !in_array('CEM', $certificatsObtenus)) {
            if (empty($formationFiltre) || $formationFiltre === 'CEM') {
                if (($grade == 'Capitaine' && $ancienneteGrade >= 3) || $grade == 'Commandant') {
                    if ($age <= 45) {
                        $result[] = $this->formatFormation($militaire, 'CEM', 'Cour d\'État-Major', $dateProposition, null, "âge ≤ 45 ans");
                    }
                }
            }
        }

        // Certificat d'État-Major
        if ($grade == 'Commandant' && $age > 45 && !in_array('CERT_EM', $certificatsObtenus)) {
            if (empty($formationFiltre) || $formationFiltre === 'CERT_EM') {
                $result[] = $this->formatFormation($militaire, 'CERT_EM', 'Certificat d\'État-Major', $dateProposition, null, "âge > 45 ans");
            }
        }

        // École de Guerre
        if (in_array($grade, ['Lieutenant-colonel', 'Colonel', 'Colonel-Major']) && !in_array('ECOLE_GUERRE', $certificatsObtenus) && $ancienneteGrade >= 2 && $age <= 53) {
            if (empty($formationFiltre) || $formationFiltre === 'ECOLE_GUERRE') {
                $result[] = $this->formatFormation($militaire, 'ECOLE_GUERRE', 'École de Guerre', $dateProposition, null, "âge ≤ 53 ans");
            }
        }

        return $result;
    }

    /**
     * Vérifie les retraites proches.
     */
    private function checkRetraite(Militaire $militaire): array
    {
        $result = [];

        $dateRetraite = $militaire->calculerDateRetraite();

        if ($dateRetraite) {
            $diffJours = Carbon::now()->startOfDay()->diffInDays($dateRetraite);
            $moisRestants = floor($diffJours / 30);

            if ($moisRestants <= 12 && $diffJours >= 0) {
                $result[] = [
                    'militaire' => [
                        'id' => $militaire->id,
                        'matricule' => $militaire->matricule,
                        'nom' => $militaire->nom,
                        'prenom' => $militaire->prenom,
                        'grade_actuel' => $militaire->grade_actuel,
                    ],
                    'date_retraite' => $dateRetraite->format('Y-m-d'),
                    'date_retraite_formatted' => $dateRetraite->format('d/m/Y'),
                    'mois_restants' => $moisRestants,
                ];
            }
        }

        return $result;
    }

    /**
     * Formate une promotion pour l'affichage.
     */
    private function formatPromotion(Militaire $militaire, string $gradeCible, Carbon $dateProposition, ?Carbon $dateAnciennete = null, string $detail = ''): array
    {
        $anneeProposition = $dateProposition->format('Y');

        if ($dateAnciennete && $dateAnciennete->year <= $anneeProposition) {
            $dateAncienneteTexte = $dateAnciennete->format('d/m/Y');
            $message = "Proposable pour {$anneeProposition} (ancienneté atteinte le {$dateAncienneteTexte})";
        } elseif ($dateAnciennete) {
            $dateAncienneteTexte = $dateAnciennete->format('d/m/Y');
            $message = "Proposable pour {$anneeProposition} (ancienneté atteinte le {$dateAncienneteTexte} - dans l'année)";
        } elseif ($detail) {
            $message = "Proposable pour {$anneeProposition} ({$detail})";
        } else {
            $message = "Proposable pour {$anneeProposition}";
        }

        return [
            'militaire' => [
                'id' => $militaire->id,
                'matricule' => $militaire->matricule,
                'nom' => $militaire->nom,
                'prenom' => $militaire->prenom,
                'grade_actuel' => $militaire->grade_actuel,
            ],
            'type' => 'Promotion',
            'grade_cible' => $gradeCible,
            'message' => $message,
            'date_estimation' => $dateProposition->format('Y-m-d'),
            'annee_proposition' => $anneeProposition,
            'date_anciennete' => $dateAnciennete ? $dateAnciennete->format('Y-m-d') : null,
        ];
    }

    /**
     * Formate une formation pour l'affichage.
     */
    private function formatFormation(Militaire $militaire, string $formation, string $nomFormation, Carbon $dateProposition, ?Carbon $dateConditions = null, string $conditionTexte = ''): array
    {
        $anneeProposition = $dateProposition->format('Y');

        if ($dateConditions) {
            $dateConditionsTexte = $dateConditions->format('d/m/Y');
            $message = "Proposable pour {$anneeProposition} (conditions remplies le {$dateConditionsTexte})";
        } elseif ($conditionTexte) {
            $message = "Proposable pour {$anneeProposition} ({$conditionTexte})";
        } else {
            $message = "Proposable pour {$anneeProposition}";
        }

        return [
            'militaire' => [
                'id' => $militaire->id,
                'matricule' => $militaire->matricule,
                'nom' => $militaire->nom,
                'prenom' => $militaire->prenom,
                'grade_actuel' => $militaire->grade_actuel,
            ],
            'formation' => $formation,
            'nom_formation' => $nomFormation,
            'message' => $message,
            'date_estimation' => $dateProposition->format('Y-m-d'),
            'annee_proposition' => $anneeProposition,
            'date_conditions' => $dateConditions ? $dateConditions->format('Y-m-d') : null,
        ];
    }

    /**
     * Retourne l'année de proposition (31 décembre de l'année en cours)
     */
    private function getDateProposition(): Carbon
    {
        $annee = Carbon::now()->year;
        return Carbon::create($annee, 12, 31, 23, 59, 59);
    }

    /**
     * 🔥 EXPORT - Version corrigée pour récupérer les contrats à renouveler
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        $type = $request->input('type', 'all');
        $formation = $request->input('formation', '');
        $grade = $request->input('grade', '');
        $exportAll = $request->input('export_all', false);

        // 🔥 Récupérer les données sans cache pour l'export
        $eligibilites = $this->computeEligibilites($type, $formation, $grade);

        $allData = $eligibilites;

        if (empty($allData)) {
            return redirect()->back()->with('error', 'Aucune donnée à exporter.');
        }

        if (!$exportAll && !empty($type)) {
            $filteredData = [];
            if ($type === 'promotions' && isset($allData['promotions'])) {
                $filteredData['promotions'] = $allData['promotions'];
            } elseif ($type === 'formations' && isset($allData['formations'])) {
                $filteredData['formations'] = $allData['formations'];
            } elseif ($type === 'retraites' && isset($allData['retraites'])) {
                $filteredData['retraites'] = $allData['retraites'];
            } elseif ($type === 'contrats' && isset($allData['contrats'])) {
                $filteredData['contrats'] = $allData['contrats'];
            }
            $allData = $filteredData;
        }

        $totalItems = 0;
        foreach ($allData as $items) {
            $totalItems += count($items);
        }

        if ($totalItems === 0) {
            return redirect()->back()->with('error', "Aucune donnée à exporter pour le type '{$type}'.");
        }

        $typeName = $type ?: 'all';
        $fileName = "eligibilites_{$typeName}_" . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new EligibilitesExport($allData, $type), $fileName);
    }
}
