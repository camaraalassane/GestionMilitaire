<?php

namespace App\Http\Controllers;

use App\Imports\MilitairesImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;

class MilitairesImportController extends Controller
{
    public function showForm()
    {
        return Inertia::render('militaires/import');
    }

    public function process(Request $request)
    {
        $request->validate([
            'fichier' => 'required|file|mimes:xlsx,xls,csv|max:2048',
            'duplicate_action' => 'sometimes|in:ignore,update',
        ]);

        $file = $request->file('fichier');
        $duplicateAction = $request->input('duplicate_action', 'ignore');

        try {
            $import = new MilitairesImport($duplicateAction);
            Excel::import($import, $file);

            $summary = $import->getSummary();
            $duplicates = $import->getDuplicates();

            if (!empty($duplicates) && $duplicateAction === 'ignore') {
                return back()->with([
                    'duplicates' => $duplicates,
                    'flash' => [
                        'warning' => 'Des doublons ont été détectés et ignorés'
                    ]
                ]);
            }

            $message = "Importation terminée : ";
            if ($summary['created'] > 0) $message .= "{$summary['created']} créé(s) ";
            if ($summary['updated'] > 0) $message .= "{$summary['updated']} mis à jour ";
            if ($summary['skipped'] > 0) $message .= "{$summary['skipped']} ignoré(s) ";
            if ($summary['errors'] > 0) $message .= "⚠️ {$summary['errors']} erreur(s) ";

            return redirect()->route('militaires.index')->with([
                'success' => trim($message),
                'import_summary' => $summary
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'fichier' => 'Erreur lors de l\'importation : ' . $e->getMessage()
            ]);
        }
    }
}
