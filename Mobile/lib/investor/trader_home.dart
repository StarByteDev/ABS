import 'package:flutter/material.dart';
import '../core/ads/ad_policy.dart';
import '../core/ads/banner_ad_slot.dart';
import '../template_rebase/data/mock_data.dart';
import '../template_rebase/state/app_state.dart';
import '../template_rebase/screens/signal_detail_screen.dart';
import '../template_rebase/screens/free_signal_screen.dart';
import 'pulse_experience.dart';
import 'investor_ui.dart';
import 'premium_ui.dart';

/// Premium trading home; live results only, no fictitious signals or win rates.
class TraderHome extends StatefulWidget {
  const TraderHome({super.key, required this.onTab});
  final ValueChanged<int> onTab;
  @override State<TraderHome> createState() => _TraderHomeState();
}
class _TraderHomeState extends State<TraderHome> {
  bool _loaded = false;
  @override void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) { final a = AppScope.read(context); a.ensureHome(); a.ensurePulse(); a.ensureNews(); }
      });
    }
  }
  @override Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final exp = InvestorScope.of(context);
    final active = MockData.signals.where((s) => s.status == SignalStatus.active).toList()
      ..sort((a,b) => b.confidence.compareTo(a.confidence));
    final best = active.isEmpty ? null : active.first;
    final btc = MockData.coins.where((x) => x.symbol == 'BTC').toList();
    final coin = btc.isEmpty ? null : btc.first;
    final events = MockData.calendar.where((e) => e.impact.toLowerCase() == 'high' &&
      (e.at?.isAfter(DateTime.now()) ?? false)).take(3).toList();
    void freeSignal() => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const FreeSignalScreen()));
    return PremiumBackdrop(child: SafeArea(bottom:false, child: RefreshIndicator(
      color:invBlue,onRefresh:() async { await Future.wait([app.refreshHome(),app.refreshPulse(),app.refreshNews()]); },
      child: ListView(padding: const EdgeInsets.fromLTRB(16,15,16,28), children: [
        PremiumHeader(onFreeSignal: freeSignal),
        const SizedBox(height:18),
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Welcome to Pulse',style:TextStyle(color:invMuted,fontSize:11.5)),
            Text(app.userName, maxLines:1, overflow:TextOverflow.ellipsis,
              style:const TextStyle(color:Colors.white,fontSize:22,fontWeight:FontWeight.w900)),
          ])),
          PremiumChip(app.hasPulse ? 'PULSE PRO' : 'PULSE',color:invBlue,icon:Icons.bolt_rounded),
        ]),
        if (exp.eligible) ...[const SizedBox(height:16),
          InvestorModeSwitch(investorSelected:false,onSelect:exp.switchMode)],
        const SizedBox(height:17),
        PremiumCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Row(children: [Icon(Icons.monitor_heart_rounded,color:invGold,size:21),SizedBox(width:8),
            Expanded(child:Text('Market Pulse · now',style:TextStyle(color:Colors.white,fontSize:17,fontWeight:FontWeight.w800))),
            Text('Market context',style:TextStyle(color:invMuted,fontSize:10)),
          ]),const SizedBox(height:14),
          Row(children: [
            SizedBox(width:100,height:100,child: Stack(alignment:Alignment.center,children: [
              SizedBox(width:98,height:98,child:CircularProgressIndicator(
                value:(MockData.pulseScore/100).clamp(0.0,1.0),strokeWidth:9,strokeCap:StrokeCap.round,
                color:invBlue,backgroundColor:invBorder)),
              Column(mainAxisAlignment:MainAxisAlignment.center,children: [
                Text(MockData.pulseScore > 0 ? '${MockData.pulseScore}' : '—',
                  style:const TextStyle(color:Colors.white,fontSize:29,fontWeight:FontWeight.w900)),
                const Text('/ 100',style:TextStyle(color:invMuted,fontSize:10)),
              ])])),
            const SizedBox(width:13),
            Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children: [
              Text(MockData.pulseLabel,style:const TextStyle(color:invGreen,fontSize:19,fontWeight:FontWeight.w800)),
              const SizedBox(height:7),
              Text(MockData.dailyInsight, maxLines:5,overflow:TextOverflow.ellipsis,
                style:const TextStyle(color:invMuted,fontSize:11.5,height:1.45)),
            ])),
          ]),const SizedBox(height:14),const Divider(color:invBorder),const SizedBox(height:9),
          Row(children: [
            Expanded(child:PremiumKpi('BTC / USDT',coin != null && coin.price > 0 ? coin.price.toStringAsFixed(2) : '—')),
            Expanded(child:PremiumKpi('Funding (8H)',MockData.fundingRate.isNaN ? '—' : '${MockData.fundingRate.toStringAsFixed(4)}%')),
            Expanded(child:PremiumKpi('Live signals','${active.length}',color:invBlue)),
          ]),
        ])),
        const InlineBannerAdSlot(placement:BannerPlacement.home),
        PremiumSection('Best signal right now',action:'View all',onAction:()=>widget.onTab(2)),
        if (best == null) const PremiumCard(child:Column(children: [
          Icon(Icons.radar_rounded,color:invGold,size:34),SizedBox(height:8),
          Text('Waiting for a qualified setup',style:TextStyle(color:Colors.white,fontWeight:FontWeight.w800)),
          SizedBox(height:5),Text('No live qualified signal is available. Pulse will show one here when it meets the strategy rules.',
            textAlign:TextAlign.center,style:TextStyle(color:invMuted,fontSize:12,height:1.45)),
        ])) else PremiumCard(highlight:true,child:Column(crossAxisAlignment:CrossAxisAlignment.start,children: [
          Row(children: [
            Expanded(child:Text(best.pair,style:const TextStyle(color:Colors.white,fontSize:23,fontWeight:FontWeight.w900))),
            PremiumChip(best.side == Side.long ? '▲ LONG':'▼ SHORT',
              color:best.side == Side.long ? invGreen:const Color(0xFFFF6479)),
          ]),
          const SizedBox(height:4),
          Text('${best.timeframe} · ${best.agree} strategy confirmations',style:const TextStyle(color:invMuted,fontSize:12)),
          const SizedBox(height:12),
          Row(children: [const Text('Signal confidence',style:TextStyle(color:invMuted,fontSize:12)),
            const Spacer(),Text('${best.confidence}%',style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w900,fontSize:17))]),
          const SizedBox(height:7),LinearProgressIndicator(value:(best.confidence/100).clamp(0.0,1.0),
            minHeight:6,color:invGreen,backgroundColor:invBorder,borderRadius:BorderRadius.circular(9)),
          const SizedBox(height:16),Row(children: [
            Expanded(child:PremiumKpi('Entry zone',best.entry>0?best.entry.toString():'—')),
            Expanded(child:PremiumKpi('Take profit 1',best.targets.isEmpty?'—':best.targets.first.toString(),color:invGreen)),
            Expanded(child:PremiumKpi('Stop loss',best.stop>0?best.stop.toString():'—',color:const Color(0xFFFF6575))),
          ]),const SizedBox(height:10),
          Text('Risk : reward   1 : ${best.riskReward.toStringAsFixed(2)}',style:const TextStyle(color:invGold,fontSize:12,fontWeight:FontWeight.w700)),
          const SizedBox(height:13),PremiumButton(label:'View trade plan',onTap:()=>Navigator.push(context,
            MaterialPageRoute<void>(builder:(_)=>SignalDetailScreen(signal:best)))),
        ])),
        PremiumSection('Active signals · ${active.length}',action:'All signals',onAction:()=>widget.onTab(2)),
        PremiumCard(child:active.isEmpty
          ?const Text('No live signals currently qualified.',style:TextStyle(color:invMuted,fontSize:12))
          :Column(children:active.take(6).map((s)=>PremiumTinyRow(icon:Icons.currency_bitcoin_rounded,
              iconColor:s.side==Side.long?invGreen:const Color(0xFFFF6479),title:s.pair,
              subtitle:'${s.timeframe} · Confidence ${s.confidence}%',
              trailing:Text(s.side==Side.long?'LONG':'SHORT',style:TextStyle(color:s.side==Side.long?invGreen:const Color(0xFFFF6479),fontSize:11,fontWeight:FontWeight.w800)),
              onTap:()=>Navigator.push(context,MaterialPageRoute<void>(builder:(_)=>SignalDetailScreen(signal:s))),
            )).toList())),
        PremiumSection('Your watchlist',action:'Account',onAction:()=>widget.onTab(4)),
        PremiumCard(child:app.watchlist.isEmpty
          ?const Text('Your watchlist is empty. Add markets under Account.',style:TextStyle(color:invMuted,fontSize:12))
          :Wrap(spacing:9,runSpacing:9,children:app.watchlist.map((x)=>PremiumChip(x,color:invBlue)).toList())),
        PremiumSection('High-impact events',action:'News',onAction:()=>widget.onTab(3)),
        PremiumCard(child:events.isEmpty
          ?const Text('No upcoming high-impact events from the current feed.',style:TextStyle(color:invMuted,fontSize:12))
          :Column(children:events.map((e)=>PremiumTinyRow(
            icon:Icons.event_rounded,iconColor:invGold,title:e.title,
            subtitle:'${e.at?.toLocal() ?? ''} · Forecast: ${e.forecast}',
            trailing:const PremiumChip('HIGH',color:Color(0xFFFF6575)),
            onTap:()=>widget.onTab(3))).toList())),
        const SizedBox(height:18),
        PremiumCard(highlight:true,child:Column(children: [
          const Text('Free Pulse Signal',style:TextStyle(color:Colors.white,fontSize:18,fontWeight:FontWeight.w800)),
          const SizedBox(height:7),
          const Text('Watch a rewarded ad to reveal a qualified signal or the BTC market outlook.',
            textAlign:TextAlign.center,style:TextStyle(color:invMuted,fontSize:12)),
          const SizedBox(height:13),PremiumButton(label:'Watch Ad & Reveal Free Signal',
            icon:Icons.play_circle_fill_rounded,onTap:freeSignal),
        ])),
        const SizedBox(height:18),
        const Text('Signal confidence is analytical context, not a guarantee. Trading involves risk of loss.',
          textAlign:TextAlign.center,style:TextStyle(color:invMuted,fontSize:10.5,height:1.45)),
      ]),
    )));
  }
}
