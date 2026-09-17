<?php

namespace App\Http\Controllers;

use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use Illuminate\Contracts\View\View;

class ResearchDebugController extends Controller
{
    public function index(): View
    {
        return view('research.debug', [
            'runs' => ExperimentRun::latest('id')->limit(20)->get(),
            'events' => GroundTruthEvent::with(['experimentRun', 'product', 'order'])
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
