<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // Toon het admin dashboard
    public function index()
    {
        // Haal alle openstaande (pending) reports op, nieuwste eerst
        $reports = Report::with('reporter')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('admin.reports', compact('reports'));
    }

    // Markeer een report als afgehandeld (zonder iets te verwijderen)
    public function resolveReport($id)
    {
        $report = Report::findOrFail($id);
        $report->update(['status' => 'resolved']);

        return back()->with('success', 'Report gemarkeerd als afgehandeld.');
    }

    // Verwijder een Pop en sluit gerelateerde reports
    public function deletePop($id)
    {
        $pop = Pop::findOrFail($id);

        // Markeer reports over deze pop als resolved
        Report::where('target_type', 'pop')->where('target_id', $id)->update(['status' => 'resolved']);

        $pop->delete();

        return back()->with('success', 'Pop-up succesvol verwijderd. 🗑️');
    }

    // Verwijder een Gebruiker en sluit gerelateerde reports
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // Markeer reports over deze user als resolved
        Report::where('target_type', 'user')->where('target_id', $id)->update(['status' => 'resolved']);

        $user->delete();

        return back()->with('success', 'Gebruiker succesvol verwijderd. 🚫');
    }
}
