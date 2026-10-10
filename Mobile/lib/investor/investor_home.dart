import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/json_tools.dart';
import '../template_rebase/screens/free_signal_screen.dart';
import 'pulse_experience.dart';
import 'investor_ui.dart';
import 'premium_ui.dart';

/// V1.7.1 investor-first dashboard based on the approved design.
/// All figures are the authenticated ABS response; the PDF's examples are not used.
class InvestorHome extends StatelessWidget {
  const InvestorHome({super.key, required this.onTab});
  final ValueChanged<int> onTab;

  @override Widget build(BuildContext context) {
    final exp = InvestorScope.of(context);
    final data = exp.account;
    final c = JsonTools.text(data['currency'], 'USD');
    final agreement = JsonTools.map(data['investment_agreement']);
    final plan = JsonTools.map(data['performance_plan']);
    final history = JsonTools.mapList(data['performance_history']);
    final activity = JsonTools.mapList(data['recent_transactions']);
    final principal = data['net_contributions'] ?? data['investor_principal'];
    final value = agreement['indicative_value'] ?? plan['indicative_value'] ?? data['current_value'];
    final totalProfit = data['total_profit'];
    final paid = data['profit_paid'];
    final rate = JsonTools.number(data['overall_return_percent']);
    final name = JsonTools.text(exp.session.user?['name'], 'Investor').split(' ').first;
    final month = DateFormat.MMMM().format(DateTime.now());
    final rows = history.reversed.take(8).toList().reversed.map((row) {
      final parsed = DateTime.tryParse('${JsonTools.text(row['month'])}-01');
      return (label: parsed == null ? JsonTools.text(row['month'],'—') : DateFormat.MMM().format(parsed),
        amount: JsonTools.number(row['posted']));
    }).toList();
    void freeSignal() => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const FreeSignalScreen()));

    return PremiumBackdrop(child: SafeArea(bottom: false, child: RefreshIndicator(
      color: invGold, onRefresh: exp.refresh,
      child: ListView(padding: const EdgeInsets.fromLTRB(16, 15, 16, 28), children: [
        PremiumHeader(onFreeSignal: freeSignal),
        const SizedBox(height: 18),
        Row(children: [
          CircleAvatar(radius: 22, backgroundColor: invGold.withAlpha(33),
            child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'I', style: const TextStyle(color: invGold, fontWeight: FontWeight.w900))),
          const SizedBox(width: 9),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Good ${DateTime.now().hour < 12 ? 'morning' : DateTime.now().hour < 18 ? 'afternoon' : 'evening'}, $name',
              overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)),
            const SizedBox(height: 3), const PremiumChip('PRIVATE INVESTOR', icon: Icons.show_chart_rounded),
          ])),
        ]),
        const SizedBox(height: 17),
        InvestorModeSwitch(investorSelected: true, onSelect: exp.switchMode),
        const SizedBox(height: 17),
        if (exp.loading && data.isEmpty) const Center(child: Padding(
          padding: EdgeInsets.all(60), child: CircularProgressIndicator(color: invGold)))
        else if (data.isEmpty) PremiumCard(child: Column(children: [
          Text(exp.error ?? 'No investment agreement is currently available.',
            style: const TextStyle(color: invMuted)),
          TextButton(onPressed: exp.refresh, child: const Text('Refresh account')),
        ]))
        else ...[
          PremiumCard(highlight: true, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [const Expanded(child: Text('Total account value',
              style: TextStyle(color: invMuted, fontSize: 13))),
              PremiumChip(JsonTools.text(agreement['status'], 'Reported').toUpperCase(), color: invGreen, icon: Icons.check_circle_outline),
            ]),
            const SizedBox(height: 9),
            Text(value != null ? money(value,c) : 'Not yet reported',
              style: const TextStyle(fontSize: 30, color: Colors.white, fontWeight: FontWeight.w900, letterSpacing: -.8)),
            const SizedBox(height: 4),
            Text('Reported return ${pct(rate, signed:true)}  ·  ${money(totalProfit,c)} lifetime profit',
              style: TextStyle(color: rate < 0 ? const Color(0xFFFF6676) : invGreen, fontSize: 12, fontWeight: FontWeight.w700)),
            const SizedBox(height: 13),
            const Text('Posted monthly profit trend', style: TextStyle(color: invMuted, fontSize: 11)),
            const SizedBox(height: 8),
            PremiumPerformanceChart(values: history.map((r) => JsonTools.number(r['posted'])).toList(), height: 118),
            const SizedBox(height: 13), const Divider(color: invBorder), const SizedBox(height: 9),
            Row(children: [
              Expanded(child: PremiumKpi('Principal',money(principal,c))),
              Expanded(child: PremiumKpi('Profit paid',money(paid,c),color:invGreen)),
              Expanded(child: PremiumKpi('Net return',pct(rate,signed:true),color:rate < 0 ? const Color(0xFFFF6575) : invGreen)),
            ]),
          ])),
          const SizedBox(height: 15),
          Row(children: [
            PremiumAction(icon:Icons.add_card_rounded,label:'Top up',gold:true,onTap:()=>showInvestorRequestSheet(context,'add_investment')),
            PremiumAction(icon:Icons.north_east_rounded,label:'Withdraw',onTap:()=>showInvestorRequestSheet(context,'withdrawal')),
            PremiumAction(icon:Icons.description_outlined,label:'Statements',onTap:()=>onTab(2)),
            PremiumAction(icon:Icons.support_agent_rounded,label:'Support',onTap:()=>showInvestorRequestSheet(context,'portfolio_review')),
          ]),
          const SizedBox(height: 17),
          PremiumCard(accent: invGold.withAlpha(95), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [const Icon(Icons.calendar_month_rounded, color: invGold), const SizedBox(width: 10),
              Expanded(child: Text('$month · monthly progress', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15))),
              Text(money(plan['mtd_provisional'],c), style: const TextStyle(color: invGreen, fontWeight: FontWeight.w800, fontSize: 15)),
            ]),
            const SizedBox(height: 13),
            LinearProgressIndicator(value: (JsonTools.number(plan['progress_percent']) / 100).clamp(0.0,1.0),
              minHeight: 6, color: invGold, backgroundColor: invBorder, borderRadius: BorderRadius.circular(9)),
            const SizedBox(height: 8),
            Text('Provisional accrual · ${JsonTools.integer(plan['days_posted'])} of ${JsonTools.integer(plan['days_total'])} days posted · Target ${money(plan['target_amount'],c)}',
              style: const TextStyle(color: invMuted, fontSize: 11, height:1.45)),
            const SizedBox(height: 5),
            Text(agreement['payout_day'] != null
              ? 'Scheduled settlement: day ${agreement['payout_day']} of the next month'
              : 'Scheduled settlement: month end (subject to ABS confirmation)',
              style: const TextStyle(color: invGold, fontSize: 11)),
          ])),
          PremiumSection('Monthly returns',action:'Performance',onAction:()=>onTab(1)),
          PremiumCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Monthly posted results',style:TextStyle(color:invMuted,fontSize:11)),
            const SizedBox(height:10), PremiumReturnsBar(rows:rows),
            const SizedBox(height:11),
            Text('Results are posted amounts in $c, not monthly percentage returns.',
              style: const TextStyle(color: invMuted, fontSize: 10.5)),
          ])),
          const PremiumSection('Where your capital works'),
          const PremiumCard(child: Row(children: [
            Icon(Icons.pie_chart_outline_rounded,color:invGold,size:30),SizedBox(width:12),
            Expanded(child: Column(crossAxisAlignment:CrossAxisAlignment.start,children: [
              Text('Allocation details not published',style:TextStyle(color:Colors.white,fontWeight:FontWeight.w700,fontSize:13)),
              SizedBox(height:4),Text('Your ABS account does not yet supply verified asset allocation percentages.',
                style:TextStyle(color:invMuted,fontSize:11,height:1.45)),
            ])),
          ])),
          PremiumSection('Your agreement',action:'View portfolio',onAction:()=>onTab(1)),
          PremiumCard(child: Column(children: [
            _dataLine('Account status',JsonTools.text(agreement['status'],'Not configured')),
            _dataLine('Agreed monthly rate',pct(agreement['monthly_target_rate'])),
            _dataLine('Investment started',JsonTools.text(agreement['effective_from'],'Not provided')),
            _dataLine('Profit reinvested',money(data['profit_reinvested'],c)),
          ])),
          PremiumSection('Recent activity',action:'Statements',onAction:()=>onTab(2)),
          PremiumCard(child: activity.isEmpty
            ? const Text('No recent posted transactions.',style:TextStyle(color:invMuted,fontSize:12))
            : Column(children: activity.take(4).map((r) => PremiumTinyRow(
                icon: Icons.receipt_long_rounded,
                iconColor: JsonTools.number(r['amount']) < 0 ? const Color(0xFFFF6479) : invGreen,
                title: JsonTools.text(r['type'],'Transaction').replaceAll('_',' '),
                subtitle: '${JsonTools.text(r['transaction_date'],'')} · ${money(r['amount'],c)}',
                onTap: ()=>onTab(1),
              )).toList())),
        ],
        const SizedBox(height:17),
        PremiumCard(accent:invGold.withAlpha(80), child: Column(children: [
          const Text('Free Pulse Signal', style:TextStyle(color:Colors.white,fontSize:17,fontWeight:FontWeight.w800)),
          const SizedBox(height:6),
          const Text('Watch one rewarded ad to reveal a qualified setup or the BTC market outlook.',
            textAlign:TextAlign.center,style:TextStyle(color:invMuted,fontSize:12)),
          const SizedBox(height:12),
          PremiumButton(label:'Explore Free Signal',icon:Icons.play_circle_fill_rounded,onTap:freeSignal),
        ])),
        const SizedBox(height:18),
        const Text('Illustrative charts only where historical information is available. ABS account values remain server-authoritative; past results are not a guarantee.',
          textAlign: TextAlign.center, style:TextStyle(color:invMuted,fontSize:10.5,height:1.5)),
      ]),
    )));
  }

  static Widget _dataLine(String a,String b) => Padding(padding:const EdgeInsets.symmetric(vertical:9),
    child:Row(children:[Expanded(child:Text(a,style:const TextStyle(color:invMuted,fontSize:12))),
      Expanded(child:Text(b,textAlign:TextAlign.right,
        style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w700,fontSize:12))),
    ]));
}
Future<void> showInvestorRequestSheet(BuildContext context,String type) async {
  final amountController=TextEditingController();
  final noteController=TextEditingController();
  final investor=InvestorScope.read(context);
  final currency=JsonTools.text(investor.account['currency'],'USD');
  final title=type=='add_investment'?'Top up investment':type=='withdrawal'?'Withdraw principal':'Contact investor desk';
  var processing=false;
  try {
    await showModalBottomSheet<void>(context:context,isScrollControlled:true,
      backgroundColor:invCard,showDragHandle:true,
      builder:(sheetContext)=>StatefulBuilder(builder:(ctx,setSheet)=>
      Padding(padding:EdgeInsets.fromLTRB(19,9,19,MediaQuery.of(ctx).viewInsets.bottom+24),
        child:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,crossAxisAlignment:CrossAxisAlignment.start,children:[
          Text(title,style:const TextStyle(color:Colors.white,fontSize:21,fontWeight:FontWeight.w800)),
          const SizedBox(height:8),
          const InvestorNotice('A request is submitted to ABS for review. No funds are moved by this form.'),
          const SizedBox(height:17),
          if(type!='portfolio_review')TextField(controller:amountController,keyboardType:const TextInputType.numberWithOptions(decimal:true),
            decoration:InputDecoration(labelText:'Amount ($currency)')),
          const SizedBox(height:12),
          TextField(controller:noteController,minLines:2,maxLines:4,
            decoration:const InputDecoration(labelText:'Message (optional)')),
          const SizedBox(height:18),
          SizedBox(width:double.infinity,child:FilledButton(onPressed:processing?null:()async{
            final parsed=double.tryParse(amountController.text.trim());
            if(type!='portfolio_review'&&(parsed==null||parsed<=0)){
              ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content:Text('Enter a valid amount.')));return;
            }
            setSheet(()=>processing=true);
            try{
              await investor.submitRequest(type:type,amount:parsed,message:noteController.text);
              if(ctx.mounted){Navigator.of(ctx).pop();ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Request submitted for ABS review.')));}
            }catch(e){if(ctx.mounted)ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content:Text(e.toString())));}
            finally{if(ctx.mounted)setSheet(()=>processing=false);}
          },style:FilledButton.styleFrom(backgroundColor:invGold,foregroundColor:invBg),
          child:Text(processing?'Submitting…':'Submit request'))),
        ]))),
      ),
    );
  } finally {
    amountController.dispose(); noteController.dispose();
  }
}
