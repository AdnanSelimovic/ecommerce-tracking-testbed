<?php

namespace App\Http\Controllers;

use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Services\ExperimentRunContext;
use App\Services\ExperimentRunManager;
use App\Tracking\ClientTrackingEligibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResearchDebugController extends Controller
{
    public function index(Request $request, ExperimentRunContext $context, ClientTrackingEligibility $tracking): View
    {
        $selectedRun = $request->filled('run')
            ? ExperimentRun::where('run_id', $request->string('run'))->firstOrFail()
            : null;

        $currentRun = $context->current();

        return view('research.debug', [
            'currentRun' => $currentRun,
            'clientTrackingEligible' => $tracking->allowsRun($currentRun),
            'ga4Configured' => (bool) config('tracking.ga4.enabled'),
            'metaConfigured' => (bool) config('tracking.meta.enabled'),
            'selectedRun' => $selectedRun,
            'runs' => ExperimentRun::withCount('groundTruthEvents')->latest('id')->limit(20)->get(),
            'events' => GroundTruthEvent::with(['experimentRun', 'product', 'order'])
                ->when($selectedRun, fn ($query) => $query->where('experiment_run_id', $selectedRun->id))
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function start(Request $request, ExperimentRunManager $runs, ExperimentRunContext $context): RedirectResponse
    {
        $run = $runs->start($runs->create($request->all()));
        $context->bind($run);

        return redirect()->route('research.debug')->with('status', "Started and bound experiment run {$run->run_id}.");
    }

    public function finish(ExperimentRun $run, ExperimentRunManager $runs): RedirectResponse
    {
        $runs->complete($run);

        return redirect()->route('research.debug')->with('status', "Completed experiment run {$run->run_id}.");
    }

    public function clear(ExperimentRunContext $context): RedirectResponse
    {
        $context->clear();

        return redirect()->route('research.debug')->with('status', 'Cleared the active experiment run from this browser session.');
    }
}
