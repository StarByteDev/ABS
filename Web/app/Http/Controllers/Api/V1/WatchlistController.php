<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request){ return response()->json(['data'=>$request->user()->watchlists()->orderBy('sort_order')->get()]); }
    public function store(Request $request){ $data=$request->validate(['symbol'=>['required','string','max:30'],'display_name'=>['nullable','string','max:100']]); $item=$request->user()->watchlists()->updateOrCreate(['symbol'=>strtoupper($data['symbol'])],['display_name'=>$data['display_name']??null]); return response()->json(['data'=>$item],201); }
    public function destroy(Request $request,string $symbol){ $request->user()->watchlists()->where('symbol',strtoupper($symbol))->delete(); return response()->json(['message'=>'Removed.']); }
}
