import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../core/ads/ad_policy.dart';
import '../../core/ads/banner_ad_slot.dart';
import '../../investor/investor_ui.dart';
import '../../investor/premium_ui.dart';
import '../../screens/alerts_screen.dart';
import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../widgets/common.dart' show snack;

/// V1.7.1 signal/trade plan uses current API signal data, not design-only prices.
class SignalDetailScreen extends StatelessWidget {
  const SignalDetailScreen({super.key,required this.signal});
  final Signal signal;
  @override Widget build(BuildContext context) {
    final s=signal;
    final long=s.side==Side.long;
    final sideColor=long?invGreen:const Color(0xFFFF6575);
    final coins=MockData.coins.where((c)=>s.symbol.toUpperCase().startsWith(c.symbol.toUpperCase())).toList();
    final coin=coins.isNotEmpty?coins.first:null;
    final hasPrice=coin!=null && coin.price>0;
    final tp=s.targets.isEmpty?null:s.targets.first;
    final levels=<({String label,double price,Color color,String detail})>[
      for(var i=s.targets.length-1;i>=0;i--) (label:'Take profit ${i+1}',price:s.targets[i],color:invGreen,detail:'Target price'),
      if(hasPrice) (label:'Market price',price:coin.price,color:invBlue,detail:'Latest available feed'),
      (label:'Entry',price:s.entry,color:invGold,detail:'Signal entry level'),
      (label:'Stop loss',price:s.stop,color:const Color(0xFFFF6575),detail:'Invalidation level'),
    ];
    Future<void> copy() async {
      await Clipboard.setData(ClipboardData(text:[s.pair,long?'LONG':'SHORT',
        'Entry: ${s.entry}',for(var i=0;i<s.targets.length;i++) 'TP${i+1}: ${s.targets[i]}',
        'SL: ${s.stop}','Confidence: ${s.confidence}%','ABS market analysis only, not financial advice.'].join('\n')));
      if(context.mounted) snack(context,'Signal levels copied.');
    }
    return Scaffold(backgroundColor:invBg,body:PremiumBackdrop(child:SafeArea(child:ListView(
      padding:const EdgeInsets.fromLTRB(16,9,16,30),children:[
        Row(children:[IconButton(onPressed:()=>Navigator.maybePop(context),icon:const Icon(Icons.arrow_back_ios_new_rounded,color:Colors.white)),
          Expanded(child:Text(s.pair,textAlign:TextAlign.center,style:const TextStyle(color:Colors.white,fontSize:23,fontWeight:FontWeight.w900))),
          IconButton(onPressed:copy,icon:const Icon(Icons.share_rounded,color:invGold)),
        ]),
        const SizedBox(height:8),
        Wrap(spacing:7,runSpacing:7,children:[
          PremiumChip(long?'▲ LONG':'▼ SHORT',color:sideColor),
          PremiumChip(s.status.name.toUpperCase(),color:invBlue),
          PremiumChip(s.timeframe,color:invBlue),
          PremiumChip('Confidence ${s.confidence}%',color:invGold),
        ]),
        const SizedBox(height:17),
        PremiumCard(child:Row(children:[
          Expanded(child:PremiumKpi('Last price',hasPrice?coin.price.toStringAsFixed(4):'Not available',color:Colors.white)),
          const SizedBox(width:10),
          Expanded(child:PremiumKpi('Since entry',hasPrice && s.entry>0
            ? '${((coin.price-s.entry)/s.entry*(long?1:-1)*100).toStringAsFixed(2)}%':'—',
            subtitle:'Indicative price move only',color:invGreen)),
        ])),
        const SizedBox(height:13),
        PremiumCard(highlight:true,child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
          const Text('Market price history',style:TextStyle(color:Colors.white,fontSize:16,fontWeight:FontWeight.w800)),
          const SizedBox(height:5),
          const Text('Available feed · signal levels shown below',style:TextStyle(color:invMuted,fontSize:11)),
          const SizedBox(height:11),
          PremiumPerformanceChart(values:coin?.spark??const <double>[],color:invBlue,height:185,
            emptyLabel:'No historical chart is available from the current price feed.'),
          const SizedBox(height:8),
          Row(children:[const Icon(Icons.circle,color:invGold,size:10),const SizedBox(width:7),
            Expanded(child:Text('Entry ${s.entry}  ·  Stop ${s.stop}',style:const TextStyle(color:invMuted,fontSize:11))),
          ]),
        ])),
        const PremiumSection('Trade levels'),
        PremiumCard(child:Column(children:[
          for(final l in levels)Padding(padding:const EdgeInsets.symmetric(vertical:7),child:Row(children:[
            Container(width:4,height:38,decoration:BoxDecoration(color:l.color,borderRadius:BorderRadius.circular(4))),
            const SizedBox(width:11),
            Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
              Text(l.label,style:TextStyle(color:l.color,fontWeight:FontWeight.w800,fontSize:13)),
              const SizedBox(height:3),Text(l.detail,style:const TextStyle(color:invMuted,fontSize:11)),
            ])),
            Text(l.price>0?l.price.toString():'—',style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w800,fontSize:14)),
          ])),
          const Divider(color:invBorder),
          Row(children:[const Expanded(child:Text('Risk : reward to TP1',style:TextStyle(color:invMuted,fontSize:12))),
            Text(tp==null?'—':'1 : ${s.riskReward.toStringAsFixed(2)}',
              style:const TextStyle(color:invGold,fontWeight:FontWeight.w800))]),
        ])),
        const PremiumSection('Position size · planning tool'),
        _PremiumRiskCalculator(signal:s),
        const PremiumSection('Why this signal'),
        PremiumCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
          Text(s.note.trim().isNotEmpty?s.note:'This signal has no additional analyst notes.',
            style:const TextStyle(color:Colors.white,fontSize:13,height:1.55)),
          const SizedBox(height:13),
          Text('${s.agree} strategy confirmations · ${s.timeframe}',
            style:const TextStyle(color:invMuted,fontSize:11)),
          const Divider(color:invBorder),
          const Text('Strategy diagnostics',style:TextStyle(color:invGold,fontSize:12,fontWeight:FontWeight.w800)),
          const SizedBox(height:10),
          for(var i=0;i<MockData.strategies.length;i++) Padding(
            padding:const EdgeInsets.symmetric(vertical:5),child:Row(children:[
              Expanded(child:Text(MockData.strategies[i],style:const TextStyle(color:invMuted,fontSize:12))),
              _vote(s.votes('15M')[i]),const SizedBox(width:15),_vote(s.votes('4H')[i]),
            ])),
          const SizedBox(height:9),
          const Text('Columns: 15M / 4H. A dash means no per-strategy vote was supplied by the API.',
            style:TextStyle(color:invMuted,fontSize:10.5)),
        ])),
        const InlineBannerAdSlot(placement:BannerPlacement.signalDetail,margin:EdgeInsets.only(top:16)),
        const SizedBox(height:15),
        PremiumButton(label:'Alert me at levels',icon:Icons.notifications_active_outlined,onTap:(){
          if(AppScope.read(context).emailVerified){Navigator.push(context,MaterialPageRoute<void>(builder:(_)=>const AlertsScreen()));}
          else snack(context,'Verify your email to create alerts.');
        }),
        const SizedBox(height:9),PremiumButton(label:'Copy levels',gold:false,icon:Icons.copy_rounded,onTap:copy),
        const SizedBox(height:16),
        const Text('Planning only; no order is placed. Position calculations exclude fees, slippage and leverage. Digital asset trading carries substantial risk.',
          textAlign:TextAlign.center,style:TextStyle(color:invMuted,fontSize:10.5,height:1.5)),
      ],
    ))));
  }
  static Widget _vote(int n)=>Icon(n>0?Icons.check_circle_rounded:n<0?Icons.cancel_rounded:Icons.remove_circle_outline_rounded,
    color:n>0?invGreen:n<0?const Color(0xFFFF6575):invMuted,size:16);
}
class _PremiumRiskCalculator extends StatefulWidget {
  const _PremiumRiskCalculator({required this.signal});
  final Signal signal;
  @override State<_PremiumRiskCalculator> createState()=>_PremiumRiskCalculatorState();
}
class _PremiumRiskCalculatorState extends State<_PremiumRiskCalculator> {
  final balance=TextEditingController(text:'5000');
  final risk=TextEditingController(text:'1');
  @override void dispose(){balance.dispose();risk.dispose();super.dispose();}
  @override Widget build(BuildContext context){
    final b=double.tryParse(balance.text)??0;
    final r=double.tryParse(risk.text)??0;
    final s=widget.signal;
    final distance=(s.entry-s.stop).abs();
    final valid=b>0 && r>0 && r<=100 && s.entry>0 && distance>0;
    final maxLoss=valid?b*r/100:0.0;
    final units=valid?maxLoss/distance:0.0;
    final target=s.targets.isEmpty?s.entry:s.targets.first;
    final reward=valid?units*(target-s.entry).abs():0.0;
    return PremiumCard(child:Column(children:[
      Row(children:[Expanded(child:TextField(controller:balance,onChanged:(_)=>setState((){}),
        keyboardType:const TextInputType.numberWithOptions(decimal:true),
        decoration:const InputDecoration(labelText:'Account USD'))),
        const SizedBox(width:9),
        Expanded(child:TextField(controller:risk,onChanged:(_)=>setState((){}),
          keyboardType:const TextInputType.numberWithOptions(decimal:true),
          decoration:const InputDecoration(labelText:'Risk %'))),
      ]),
      const SizedBox(height:16),
      if(!valid)const Text('Set balance and risk percentage (0–100); entry must differ from stop.',
        style:TextStyle(color:invGold,fontSize:12))
      else Row(children:[
        Expanded(child:PremiumKpi('Max loss','\$${maxLoss.toStringAsFixed(2)}',color:const Color(0xFFFF6479))),
        Expanded(child:PremiumKpi('Units',units.toStringAsFixed(6))),
        Expanded(child:PremiumKpi('Target TP1','\$${reward.toStringAsFixed(2)}',color:invGreen)),
      ]),
      const SizedBox(height:11),
      const Text('Indicative sizing only; no exchange trade is executed.',
        style:TextStyle(color:invMuted,fontSize:10.5)),
    ]));
  }
}
