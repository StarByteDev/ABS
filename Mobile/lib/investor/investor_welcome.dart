import 'package:flutter/material.dart';
import 'investor_ui.dart';
import 'premium_ui.dart';
import 'pulse_experience.dart';

class InvestorWelcome extends StatefulWidget {
  const InvestorWelcome({super.key});
  @override State<InvestorWelcome> createState() => _InvestorWelcomeState();
}
class _InvestorWelcomeState extends State<InvestorWelcome> {
  String mode = 'investor';
  bool busy = false;
  Future<void> _save(String value) async {
    if (busy) return;
    setState(() => busy = true);
    try { await InvestorScope.read(context).selectMode(value); }
    finally { if (mounted) setState(() => busy = false); }
  }
  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor:invBg,
    body:PremiumBackdrop(child:SafeArea(child:ListView(
      padding:const EdgeInsets.fromLTRB(20,24,20,32),children: [
        const PremiumHeader(),
        const SizedBox(height:27),
        const Center(child:Text('STEP 1 OF 2',style:TextStyle(color:invBlue,fontSize:11,fontWeight:FontWeight.w800,letterSpacing:2))),
        const SizedBox(height:11),
        Row(mainAxisAlignment:MainAxisAlignment.center,children:[
          Container(height:5,width:50,decoration:BoxDecoration(color:invGold,borderRadius:BorderRadius.circular(5))),
          const SizedBox(width:5),Container(height:5,width:50,decoration:BoxDecoration(color:invBorder,borderRadius:BorderRadius.circular(5))),
        ]),
        const SizedBox(height:27),
        const Text('How will you use Pulse?',textAlign:TextAlign.center,
          style:TextStyle(color:Colors.white,fontSize:30,fontWeight:FontWeight.w900,letterSpacing:-.7)),
        const SizedBox(height:8),
        const Text('Your home screen will focus on what matters to you. Switch anytime from Account.',
          textAlign:TextAlign.center,style:TextStyle(color:invMuted,height:1.5,fontSize:13)),
        const SizedBox(height:24),
        _choice(id:'investor',icon:Icons.trending_up_rounded,title:"I'm an investor",
          subtitle:'My capital is managed by ABS', color:invGold,
          points:const ['Account value, returns & growth', 'Profit payouts and withdrawal requests', 'Statements and investment agreement']),
        const SizedBox(height:14),
        _choice(id:'trader',icon:Icons.candlestick_chart_rounded,title:"I'm a trader",
          subtitle:'I trade my own account',color:invBlue,
          points:const ['Live signals with entry, TP & SL', 'Market Pulse and your watchlist', 'Signal statistics and trade plans']),
        const SizedBox(height:24),
        PremiumButton(label:busy?'Saving…':'Continue as $mode',onTap:busy?null:()=>_save(mode)),
        const SizedBox(height:7),
        TextButton(onPressed:busy?null:()=>_save('both'),
          child:const Text('I do both — show me the switcher',style:TextStyle(color:invBlue,fontSize:13))),
      ],
    ))),
  );
  Widget _choice({required String id,required IconData icon,required String title,
    required String subtitle,required Color color,required List<String> points}) {
    final selected=mode==id;
    return InkWell(onTap:()=>setState(()=>mode=id),borderRadius:BorderRadius.circular(20),
      child:PremiumCard(highlight:selected,accent:selected?color:invBlue.withAlpha(85),
      padding:const EdgeInsets.all(17),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children: [
        Row(children: [
          Container(width:53,height:53,decoration:BoxDecoration(color:color.withAlpha(29),borderRadius:BorderRadius.circular(13)),
            child:Icon(icon,color:color,size:30)),
          const SizedBox(width:11),
          Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
            Text(title,style:const TextStyle(color:Colors.white,fontSize:19,fontWeight:FontWeight.w900)),
            const SizedBox(height:4),Text(subtitle,style:const TextStyle(color:invMuted,fontSize:12)),
          ])),
          Icon(selected?Icons.check_circle_rounded:Icons.radio_button_unchecked_rounded,
            color:selected?color:invMuted,size:24),
        ]),
        const SizedBox(height:15),
        for(final p in points)Padding(padding:const EdgeInsets.only(bottom:9),child:Row(children:[
          Icon(Icons.circle,color:color,size:7),const SizedBox(width:10),
          Expanded(child:Text(p,style:const TextStyle(color:invMuted,fontSize:13))),
        ])),
      ])));
  }
}
