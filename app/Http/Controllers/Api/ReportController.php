<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_type' => 'required|in:pop,user',
            'target_id' => 'required|integer',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Bedankt voor je melding. We gaan dit zo snel mogelijk onderzoeken! 🛡️',
            'report' => $report
        ], 201);
    }
}
