<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyStatement;
use App\Models\PortfolioAccount;
use App\Models\PortfolioTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPortfolioController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.portfolios', [
            'accounts' => PortfolioAccount::with('user')->latest()->paginate(20)->withQueryString(),
            'members' => User::where('role','private_member')->orderBy('name')->get(),
            'selectedMemberId' => $request->integer('user') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['user_id'=>['required', Rule::exists('users','id')->where(fn ($query) => $query->where('role','private_member'))],'account_name'=>['required','string'],'currency'=>['required','string','max:10'],'opening_value'=>['required','numeric'],'current_value'=>['required','numeric'],'net_contributions'=>['required','numeric'],'total_profit'=>['required','numeric'],'monthly_profit'=>['required','numeric'],'valuation_date'=>['required','date'],'notes'=>['nullable','string']]);
        PortfolioAccount::updateOrCreate(['user_id'=>$data['user_id']], $data + ['is_active'=>true]);
        return back()->with('success','Private member account saved.');
    }

    public function transaction(Request $request, PortfolioAccount $account)
    {
        $data=$request->validate(['type'=>['required','in:deposit,withdrawal,profit,loss,fee,adjustment'],'amount'=>['required','numeric'],'transaction_date'=>['required','date'],'reference'=>['nullable','string'],'description'=>['nullable','string']]);
        $account->transactions()->create($data);
        return back()->with('success','Transaction added.');
    }

    public function statement(Request $request, PortfolioAccount $account)
    {
        $data=$request->validate(['statement_month'=>['required','date'],'opening_balance'=>['required','numeric'],'contributions'=>['required','numeric'],'withdrawals'=>['required','numeric'],'profit_loss'=>['required','numeric'],'closing_balance'=>['required','numeric'],'notes'=>['nullable','string']]);
        $account->statements()->updateOrCreate(['statement_month'=>$data['statement_month']],$data+['published_at'=>now()]);
        return back()->with('success','Monthly statement published.');
    }
}
