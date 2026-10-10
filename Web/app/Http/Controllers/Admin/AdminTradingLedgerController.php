<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminTradingLedgerEntry;
use App\Models\AdminTradingMonthTarget;
use App\Services\AdminTradingLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class AdminTradingLedgerController extends Controller
{
    public function index(Request $request, AdminTradingLedgerService $service)
    {
        $month=Carbon::parse($request->input('month',now()->format('Y-m').'-01'))->startOfMonth();
        $summary=$service->month($month);
        $history=$service->history(12);
        return view('admin.private-investors.trading-ledger',compact('summary','month','history'));
    }
    public function store(Request $request)
    {
        $data=$request->validate(['entry_date'=>['required','date'],'type'=>['required',Rule::in(['opening','deposit','withdrawal','pnl','fee','adjustment'])],'amount'=>['required','numeric'],'currency'=>['required','string','max:10'],'exchange'=>['nullable','string','max:80'],'reference'=>['nullable','string','max:190'],'notes'=>['nullable','string','max:2000']]);
        $amount=(float)$data['amount'];
        if (in_array($data['type'],['opening','deposit','withdrawal','fee'],true) && $amount <= 0) return back()->withErrors(['amount'=>'Enter a positive amount for this ledger type.'])->withInput();
        if (in_array($data['type'],['pnl','adjustment'],true) && abs($amount) < 0.01) return back()->withErrors(['amount'=>'P/L or adjustment cannot be zero. Use a negative amount for a loss/decrease.'])->withInput();
        $data['created_by']=$request->user()->id; AdminTradingLedgerEntry::create($data);
        return back()->with('success','Exchange ledger entry added.');
    }
    public function target(Request $request)
    {
        $data=$request->validate(['target_month'=>['required','date'],'target_amount'=>['required','numeric','min:0'],'currency'=>['required','string','max:10'],'notes'=>['nullable','string','max:2000']]);
        AdminTradingMonthTarget::query()->updateOrCreate(['target_month'=>Carbon::parse($data['target_month'])->startOfMonth()->toDateString()],$data+['created_by'=>$request->user()->id]);
        return back()->with('success','Monthly trading target saved.');
    }
    public function destroy(Request $request, AdminTradingLedgerEntry $entry) { $data=$request->validate(['void_reason'=>['required','string','min:5','max:1000']]); $entry->update(['voided_at'=>now(),'voided_by'=>$request->user()->id,'void_reason'=>$data['void_reason']]); return back()->with('success','Ledger entry voided. The audit record was retained.'); }
}
