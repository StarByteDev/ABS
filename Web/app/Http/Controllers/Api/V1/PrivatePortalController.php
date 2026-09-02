<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MonthlyStatement;
use Illuminate\Http\Request;

class PrivatePortalController extends Controller
{
    public function account(Request $request){ return response()->json(['data'=>$request->user()->portfolioAccount()->with(['transactions','statements'])->first()]); }
    public function statement(Request $request, MonthlyStatement $statement){ abort_unless($statement->account?->user_id===$request->user()->id,403); return response()->json(['data'=>$statement]); }
}
