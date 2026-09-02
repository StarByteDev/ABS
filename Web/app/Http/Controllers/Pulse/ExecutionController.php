<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExecutionController extends Controller
{
    public function __invoke(Request $request)
    {
        $signalId = $request->integer('signal') ?: null;
        $query = $signalId ? ['selected' => $signalId, 'open_trade' => $signalId] : [];

        return redirect()->route('pulse.signals.index', $query);
    }
}
