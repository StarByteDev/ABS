import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'market_extra_screens.dart';

class ExploreAbsScreen extends StatelessWidget {
  const ExploreAbsScreen({super.key});

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Explore ABS',
        subtitle: 'Market intelligence, research and learning',
        child: ListView(
          children: [
            _ExploreTile(icon: Icons.show_chart_rounded, title: 'Market Overview', subtitle: 'Public ABS market pulse, core assets and movers', onTap: () => _push(context, const PublicMarketOverviewScreen())),
            _ExploreTile(icon: Icons.apps_rounded, title: 'ABS Services', subtitle: 'Explore Alpha Block Solutions products and services', onTap: () => _push(context, const ServicesScreen())),
            _ExploreTile(icon: Icons.newspaper_rounded, title: 'News & Live Market News', subtitle: 'Published ABS coverage and current verified headlines', onTap: () => _push(context, const NewsScreen())),
            _ExploreTile(icon: Icons.manage_search_rounded, title: 'Research', subtitle: 'Asset research, market context and risk views', onTap: () => _push(context, const ResearchScreen())),
            _ExploreTile(icon: Icons.school_rounded, title: 'Learning', subtitle: 'Structured trading and market education', onTap: () => _push(context, const LearningScreen())),
            _ExploreTile(icon: Icons.event_note_rounded, title: 'Economic Calendar', subtitle: 'Upcoming macro events, impact and actuals', onTap: () => _push(context, const EconomicCalendarScreen())),
            _ExploreTile(icon: Icons.search_rounded, title: 'Search ABS', subtitle: 'Find services and published ABS content', onTap: () => _push(context, const GlobalSearchScreen())),
            _ExploreTile(icon: Icons.support_agent_rounded, title: 'Contact ABS', subtitle: 'Send a message to Alpha Block Solutions', onTap: () => _push(context, const ContactScreen())),
            _ExploreTile(icon: Icons.mail_outline_rounded, title: 'Newsletter', subtitle: 'Daily market brief and ABS product updates', onTap: () => _push(context, const NewsletterScreen())),
            _ExploreTile(icon: Icons.gavel_rounded, title: 'Legal & Risk', subtitle: 'Privacy, terms, risk disclosure and market disclaimer', onTap: () => _push(context, const LegalHubScreen())),
          ],
        ),
      );

  void _push(BuildContext context, Widget page) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
}

class _ExploreTile extends StatelessWidget {
  const _ExploreTile({required this.icon, required this.title, required this.subtitle, required this.onTap});
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 9),
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: AbsCard(
            child: Row(
              children: [
                Container(width: 46, height: 46, decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(13)), child: Icon(icon, color: AbsColors.cyan)),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AbsColors.muted, fontSize: 11))])),
                const Icon(Icons.chevron_right, color: AbsColors.muted),
              ],
            ),
          ),
        ),
      );
}

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});
  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];

  @override
  void didChangeDependencies() { super.didChangeDependencies(); if (loading && items.isEmpty) _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try { items = JsonTools.mapList(JsonTools.at(await SessionScope.of(context).api.get('/products'), 'data', <dynamic>[])); }
    on ApiException catch (e) { error = e.message; }
    finally { if (mounted) setState(() => loading = false); }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'ABS Services',
        subtitle: 'Products and intelligence services',
        actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
        child: loading ? const LoadingBlock() : error != null ? ErrorBlock(message: error!, onRetry: _load) : ListView(children: [
          ...items.map((item) => Padding(padding: const EdgeInsets.only(bottom: 10), child: InkWell(borderRadius: BorderRadius.circular(18), onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ContentDetailScreen(type: 'product', slug: JsonTools.text(item['slug']), initial: item))), child: AbsCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [const Icon(Icons.hexagon_outlined, color: AbsColors.cyan), const SizedBox(width: 10), Expanded(child: Text(JsonTools.text(item['name']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17))), if (JsonTools.boolean(item['is_featured'])) const StatusChip('FEATURED', warning: true)]),
            const SizedBox(height: 8), Text(JsonTools.text(item['tagline'], JsonTools.plain(item['description'])), style: const TextStyle(color: AbsColors.muted)),
            if (JsonTools.list(item['features']).isNotEmpty) ...[const SizedBox(height: 10), Wrap(spacing: 6, runSpacing: 6, children: JsonTools.list(item['features']).take(4).map((f) => StatusChip(JsonTools.text(f))).toList())],
          ]))))),
          if (items.isEmpty) const EmptyState(title: 'No services published', message: 'ABS services will appear here when published.', icon: Icons.apps_outlined),
        ]),
      );
}

class NewsScreen extends StatefulWidget {
  const NewsScreen({super.key});
  @override
  State<NewsScreen> createState() => _NewsScreenState();
}

class _NewsScreenState extends State<NewsScreen> with SingleTickerProviderStateMixin {
  late final TabController tabs;
  @override void initState() { super.initState(); tabs = TabController(length: 2, vsync: this); }
  @override void dispose() { tabs.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Market News',
        subtitle: 'ABS published coverage and live headlines',
        child: Column(children: [
          TabBar(controller: tabs, tabs: const [Tab(text: 'ABS News'), Tab(text: 'Live News')]),
          const SizedBox(height: 10),
          Expanded(child: TabBarView(controller: tabs, children: const [_ContentList(kind: 'news'), _LiveNewsList()])),
        ]),
      );
}

class ResearchScreen extends StatelessWidget { const ResearchScreen({super.key}); @override Widget build(BuildContext context) => const AbsPage(title: 'Research', subtitle: 'Published ABS market and asset research', child: _ContentList(kind: 'research')); }
class LearningScreen extends StatelessWidget { const LearningScreen({super.key}); @override Widget build(BuildContext context) => const AbsPage(title: 'Learning', subtitle: 'Build a stronger trading process', child: _ContentList(kind: 'learning')); }

class _ContentList extends StatefulWidget {
  const _ContentList({required this.kind});
  final String kind;
  @override State<_ContentList> createState() => _ContentListState();
}

class _ContentListState extends State<_ContentList> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  String search = '';

  String get endpoint => '/${widget.kind}';

  @override void didChangeDependencies() { super.didChangeDependencies(); if (loading && items.isEmpty) _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try { items = JsonTools.pageItems(await SessionScope.of(context).api.get(endpoint, query: {'per_page': 50})); }
    on ApiException catch (e) { error = e.message; }
    finally { if (mounted) setState(() => loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const LoadingBlock();
    if (error != null) return ErrorBlock(message: error!, onRetry: _load);
    final filtered = items.where((item) {
      final haystack = '${JsonTools.text(item['title'])} ${JsonTools.text(item['category'], '')} ${JsonTools.text(item['asset_symbol'], '')}'.toLowerCase();
      return haystack.contains(search.toLowerCase());
    }).toList();
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          TextField(onChanged: (v) => setState(() => search = v), decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Search published content')),
          const SizedBox(height: 12),
          ...filtered.map((item) => _ContentCard(item: item, kind: widget.kind)),
          if (filtered.isEmpty) const EmptyState(title: 'Nothing found', message: 'Try a different search or check again later.', icon: Icons.article_outlined),
        ],
      ),
    );
  }
}

class _ContentCard extends StatelessWidget {
  const _ContentCard({required this.item, required this.kind});
  final Map<String, dynamic> item;
  final String kind;

  @override
  Widget build(BuildContext context) {
    final summary = kind == 'research' ? item['summary'] : item['excerpt'];
    final image = JsonTools.text(item['image_url'], '');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ContentDetailScreen(type: kind, slug: JsonTools.text(item['slug']), initial: item))),
        child: AbsCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (image.startsWith('http')) ...[
              ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(image, height: 150, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox.shrink())),
              const SizedBox(height: 12),
            ],
            Row(children: [
              if (JsonTools.text(item['category'], '').isNotEmpty) StatusChip(JsonTools.text(item['category']).toUpperCase()),
              if (JsonTools.text(item['asset_symbol'], '').isNotEmpty) ...[const SizedBox(width: 6), StatusChip(JsonTools.text(item['asset_symbol']).toUpperCase(), warning: true)],
              if (JsonTools.boolean(item['is_featured'])) ...[const Spacer(), const StatusChip('FEATURED', warning: true)],
            ]),
            if (JsonTools.text(item['category'], '').isNotEmpty || JsonTools.boolean(item['is_featured'])) const SizedBox(height: 10),
            Text(JsonTools.text(item['title']), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w900)),
            const SizedBox(height: 6),
            Text(JsonTools.plain(summary, 'Open to read the full ABS publication.'), maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted)),
            const SizedBox(height: 8),
            Text(compactDate(item['published_at']), style: const TextStyle(color: AbsColors.muted, fontSize: 10)),
          ]),
        ),
      ),
    );
  }
}

class _LiveNewsList extends StatefulWidget { const _LiveNewsList(); @override State<_LiveNewsList> createState() => _LiveNewsListState(); }
class _LiveNewsListState extends State<_LiveNewsList> {
  bool loading = true; String? error; List<Map<String, dynamic>> items = [];
  @override void didChangeDependencies() { super.didChangeDependencies(); if (loading && items.isEmpty) _load(); }
  Future<void> _load() async { setState(() { loading = true; error = null; }); try { items = JsonTools.mapList(JsonTools.at(await SessionScope.of(context).api.get('/news/live', query: {'limit': 40}), 'data', <dynamic>[])); } on ApiException catch(e) { error = e.message; } finally { if (mounted) setState(() => loading = false); } }
  @override Widget build(BuildContext context) => loading ? const LoadingBlock() : error != null ? ErrorBlock(message: error!, onRetry: _load) : RefreshIndicator(onRefresh: _load, child: ListView(physics: const AlwaysScrollableScrollPhysics(), children: [
    ...items.map((item) => Padding(padding: const EdgeInsets.only(bottom: 9), child: AbsCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [Expanded(child: Text(JsonTools.text(item['source'] ?? item['source_name'], 'Market source'), style: const TextStyle(color: AbsColors.cyan, fontSize: 11, fontWeight: FontWeight.w800))), Text(compactDate(item['published_at'] ?? item['publishedAt'] ?? item['timestamp']), style: const TextStyle(color: AbsColors.muted, fontSize: 10))]),
      const SizedBox(height: 7), Text(JsonTools.text(item['title'] ?? item['headline']), style: const TextStyle(fontWeight: FontWeight.w800)),
      if (JsonTools.text(item['url'] ?? item['source_url'], '').startsWith('http')) ...[const SizedBox(height: 8), TextButton.icon(onPressed: () => launchUrl(Uri.parse(JsonTools.text(item['url'] ?? item['source_url'])), mode: LaunchMode.externalApplication), icon: const Icon(Icons.open_in_new, size: 16), label: const Text('Open source'))],
    ])))),
    if (items.isEmpty) const EmptyState(title: 'No live headlines', message: 'Live news is temporarily unavailable.', icon: Icons.newspaper_outlined),
  ]));
}

class ContentDetailScreen extends StatefulWidget {
  const ContentDetailScreen({super.key, required this.type, required this.slug, this.initial});
  final String type;
  final String slug;
  final Map<String, dynamic>? initial;
  @override State<ContentDetailScreen> createState() => _ContentDetailScreenState();
}

class _ContentDetailScreenState extends State<ContentDetailScreen> {
  bool loading = true; String? error; Map<String, dynamic> item = {};
  @override void didChangeDependencies() { super.didChangeDependencies(); if (loading && item.isEmpty) _load(); }
  Future<void> _load() async {
    if (widget.slug.isEmpty) { setState(() { item = widget.initial ?? {}; loading = false; }); return; }
    try {
      final segment = widget.type == 'product' ? 'products' : widget.type;
      item = JsonTools.map(JsonTools.at(await SessionScope.of(context).api.get('/$segment/${widget.slug}'), 'data', widget.initial ?? <String,dynamic>{}));
    }
    on ApiException catch(e) { error = e.message; item = widget.initial ?? {}; }
    finally { if (mounted) setState(() => loading = false); }
  }
  @override Widget build(BuildContext context) => AbsPage(title: JsonTools.text(item['title'] ?? item['name'], 'ABS'), subtitle: JsonTools.text(item['category'], ''), child: loading ? const LoadingBlock() : error != null && item.isEmpty ? ErrorBlock(message: error!, onRetry: _load) : ListView(children: [
    if (JsonTools.text(item['image_url'], '').startsWith('http')) ...[ClipRRect(borderRadius: BorderRadius.circular(18), child: Image.network(JsonTools.text(item['image_url']), height: 210, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_,__,___)=>const SizedBox.shrink())), const SizedBox(height: 16)],
    Wrap(spacing: 7, runSpacing: 7, children: [if (JsonTools.text(item['category'], '').isNotEmpty) StatusChip(JsonTools.text(item['category']).toUpperCase()), if (JsonTools.text(item['asset_symbol'], '').isNotEmpty) StatusChip(JsonTools.text(item['asset_symbol']).toUpperCase(), warning: true), if (JsonTools.text(item['level'], '').isNotEmpty) StatusChip(JsonTools.text(item['level']).toUpperCase()), if (item['duration_minutes'] != null) StatusChip('${JsonTools.integer(item['duration_minutes'])} MIN')]),
    const SizedBox(height: 14), Text(JsonTools.text(item['title'] ?? item['name']), style: Theme.of(context).textTheme.headlineMedium),
    if (JsonTools.text(item['tagline'] ?? item['summary'] ?? item['excerpt'], '').isNotEmpty) ...[const SizedBox(height: 10), Text(JsonTools.plain(item['tagline'] ?? item['summary'] ?? item['excerpt']), style: const TextStyle(color: AbsColors.muted, fontSize: 15))],
    const SizedBox(height: 18), AbsCard(child: SelectableText(JsonTools.plain(item['body'] ?? item['description'], 'No additional content has been published for this item.'), style: const TextStyle(height: 1.55))),
    if (JsonTools.list(item['features']).isNotEmpty) ...[const SizedBox(height: 16), const AbsSectionTitle('Features'), const SizedBox(height: 8), ...JsonTools.list(item['features']).map((f) => Padding(padding: const EdgeInsets.only(bottom: 6), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [const Icon(Icons.check_circle, color: AbsColors.green, size: 18), const SizedBox(width: 8), Expanded(child: Text(JsonTools.text(f)))])))],
    if (JsonTools.text(item['source_url'], '').startsWith('http')) ...[const SizedBox(height: 16), OutlinedButton.icon(onPressed: () => launchUrl(Uri.parse(JsonTools.text(item['source_url'])), mode: LaunchMode.externalApplication), icon: const Icon(Icons.open_in_new), label: Text('Open ${JsonTools.text(item['source_name'], 'source')}'))],
    const SizedBox(height: 30),
  ]));
}

class EconomicCalendarScreen extends StatefulWidget { const EconomicCalendarScreen({super.key}); @override State<EconomicCalendarScreen> createState() => _EconomicCalendarScreenState(); }
class _EconomicCalendarScreenState extends State<EconomicCalendarScreen> {
  bool loading = true; String? error; List<Map<String,dynamic>> events = []; String impact = 'all';
  @override void didChangeDependencies() { super.didChangeDependencies(); if (loading && events.isEmpty) _load(); }
  Future<void> _load() async { setState(() { loading = true; error = null; }); try { final now=DateTime.now(); final to=now.add(const Duration(days:30)); events=JsonTools.mapList(JsonTools.at(await SessionScope.of(context).api.get('/economic-calendar', query: {'from': now.toIso8601String().substring(0,10), 'to': to.toIso8601String().substring(0,10), if (impact!='all') 'impact': impact}), 'data', <dynamic>[])); } on ApiException catch(e){error=e.message;} finally {if(mounted)setState(()=>loading=false);} }
  @override Widget build(BuildContext context) => AbsPage(title: 'Economic Calendar', subtitle: 'Upcoming macro events', actions:[IconButton(onPressed:_load,icon:const Icon(Icons.refresh))], child: loading?const LoadingBlock():error!=null?ErrorBlock(message:error!,onRetry:_load):ListView(children:[
    DropdownButtonFormField<String>(value:impact, decoration:const InputDecoration(labelText:'Impact filter'), items:const [DropdownMenuItem(value:'all',child:Text('All impact')),DropdownMenuItem(value:'high',child:Text('High')),DropdownMenuItem(value:'medium',child:Text('Medium')),DropdownMenuItem(value:'low',child:Text('Low'))], onChanged:(v){setState(()=>impact=v??'all');_load();}), const SizedBox(height:12),
    ...events.map((e){final i=JsonTools.text(e['impact']).toLowerCase(); return Padding(padding:const EdgeInsets.only(bottom:8),child:AbsCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Row(children:[StatusChip(JsonTools.text(e['currency'], JsonTools.text(e['country'])).toUpperCase(),warning:i=='high'),const SizedBox(width:7),StatusChip(i.toUpperCase(),good:i=='low',warning:i=='high'),const Spacer(),Text(compactDate(e['event_at']),style:const TextStyle(color:AbsColors.muted,fontSize:10))]),const SizedBox(height:9),Text(JsonTools.text(e['title']),style:const TextStyle(fontWeight:FontWeight.w900)),const SizedBox(height:8),Row(children:[Expanded(child:KeyValueRow('Previous',JsonTools.text(e['previous_value']))),const SizedBox(width:8),Expanded(child:KeyValueRow('Forecast',JsonTools.text(e['forecast_value'])))]),if(e['actual_value']!=null)KeyValueRow('Actual',JsonTools.text(e['actual_value']),valueColor:AbsColors.cyan)])));}),
    if(events.isEmpty)const EmptyState(title:'No upcoming events',message:'No events match the selected filter.',icon:Icons.event_busy_outlined),
  ]));
}

class GlobalSearchScreen extends StatefulWidget { const GlobalSearchScreen({super.key}); @override State<GlobalSearchScreen> createState()=>_GlobalSearchScreenState(); }
class _GlobalSearchScreenState extends State<GlobalSearchScreen> { final q=TextEditingController(); bool loading=false; List<Map<String,dynamic>> items=[]; String? error; @override void dispose(){q.dispose();super.dispose();} Future<void> _search() async { if(q.text.trim().length<2)return; setState((){loading=true;error=null;}); try{items=JsonTools.mapList(JsonTools.at(await SessionScope.of(context).api.get('/search',query:{'q':q.text.trim()}),'data',<dynamic>[]));}on ApiException catch(e){error=e.message;}finally{if(mounted)setState(()=>loading=false);} } @override Widget build(BuildContext context)=>AbsPage(title:'Search ABS',subtitle:'Services and published content',child:ListView(children:[TextField(controller:q,onSubmitted:(_)=>_search(),decoration:InputDecoration(prefixIcon:const Icon(Icons.search),hintText:'Search Alpha Block Solutions',suffixIcon:IconButton(onPressed:_search,icon:const Icon(Icons.arrow_forward)))),const SizedBox(height:12),if(loading)const LinearProgressIndicator(),if(error!=null)Padding(padding:const EdgeInsets.only(top:12),child:ErrorBlock(message:error!,onRetry:_search)),...items.map((item)=>Padding(padding:const EdgeInsets.only(top:8),child:AbsCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Row(children:[StatusChip(JsonTools.text(item['type']).toUpperCase()),const SizedBox(width:8),Expanded(child:Text(JsonTools.text(item['title']),style:const TextStyle(fontWeight:FontWeight.w900)))]),const SizedBox(height:6),Text(JsonTools.text(item['summary']),style:const TextStyle(color:AbsColors.muted))])))),if(!loading&&items.isEmpty&&q.text.trim().length>=2)const EmptyState(title:'No results',message:'Try another search term.',icon:Icons.search_off)])); }

class LegalHubScreen extends StatelessWidget { const LegalHubScreen({super.key}); @override Widget build(BuildContext context)=>AbsPage(title:'Legal & Risk',subtitle:'ABS policies and trading disclosures',child:ListView(children:[for(final item in const [('privacy','Privacy Policy'),('terms','Terms of Use'),('risk','Risk Disclosure'),('disclaimer','Market Disclaimer')])Padding(padding:const EdgeInsets.only(bottom:8),child:InkWell(onTap:()=>Navigator.of(context).push(MaterialPageRoute(builder:(_)=>LegalDocumentScreen(type:item.$1,title:item.$2))),child:AbsCard(child:Row(children:[const Icon(Icons.description_outlined,color:AbsColors.cyan),const SizedBox(width:10),Expanded(child:Text(item.$2,style:const TextStyle(fontWeight:FontWeight.w800))),const Icon(Icons.chevron_right,color:AbsColors.muted)]))))])); }

class LegalDocumentScreen extends StatefulWidget { const LegalDocumentScreen({super.key,required this.type,required this.title}); final String type; final String title; @override State<LegalDocumentScreen> createState()=>_LegalDocumentScreenState(); }
class _LegalDocumentScreenState extends State<LegalDocumentScreen>{bool loading=true;String?error;Map<String,dynamic>data={};@override void didChangeDependencies(){super.didChangeDependencies();if(loading&&data.isEmpty)_load();}Future<void>_load()async{try{data=JsonTools.map(JsonTools.at(await SessionScope.of(context).api.get('/legal/${widget.type}'),'data',<String,dynamic>{}));}on ApiException catch(e){error=e.message;}finally{if(mounted)setState(()=>loading=false);}}@override Widget build(BuildContext context)=>AbsPage(title:widget.title,child:loading?const LoadingBlock():error!=null?ErrorBlock(message:error!,onRetry:_load):ListView(children:[Text(JsonTools.text(data['title'],widget.title),style:Theme.of(context).textTheme.headlineMedium),const SizedBox(height:14),AbsCard(child:SelectableText(JsonTools.plain(data['body']??data['content']??data['text']),style:const TextStyle(height:1.55))),const SizedBox(height:24)]));}

class ContactScreen extends StatefulWidget { const ContactScreen({super.key}); @override State<ContactScreen> createState()=>_ContactScreenState(); }
class _ContactScreenState extends State<ContactScreen>{final name=TextEditingController(),email=TextEditingController(),subject=TextEditingController(),message=TextEditingController();bool busy=false;@override void dispose(){name.dispose();email.dispose();subject.dispose();message.dispose();super.dispose();}Future<void>_send()async{if(name.text.trim().isEmpty||!email.text.contains('@')||subject.text.trim().isEmpty||message.text.trim().length<10){showSnack(context,'Enter your name, email, subject and a message of at least 10 characters.',error:true);return;}setState(()=>busy=true);try{final r=JsonTools.map(await SessionScope.of(context).api.post('/contact',body:{'name':name.text.trim(),'email':email.text.trim(),'subject':subject.text.trim(),'message':message.text.trim()}));if(mounted){showSnack(context,JsonTools.text(r['message'],'Message sent to ABS.'));message.clear();subject.clear();}}on ApiException catch(e){if(mounted)showSnack(context,e.message,error:true);}finally{if(mounted)setState(()=>busy=false);}}@override Widget build(BuildContext context)=>AbsPage(title:'Contact ABS',subtitle:'Send a message to Alpha Block Solutions',child:ListView(children:[TextField(controller:name,decoration:const InputDecoration(labelText:'Name')),const SizedBox(height:10),TextField(controller:email,keyboardType:TextInputType.emailAddress,decoration:const InputDecoration(labelText:'Email')),const SizedBox(height:10),TextField(controller:subject,decoration:const InputDecoration(labelText:'Subject')),const SizedBox(height:10),TextField(controller:message,minLines:5,maxLines:9,decoration:const InputDecoration(labelText:'Message')),const SizedBox(height:14),ElevatedButton(onPressed:busy?null:_send,child:Text(busy?'Sending...':'Send message'))]));}

class NewsletterScreen extends StatefulWidget {const NewsletterScreen({super.key});@override State<NewsletterScreen> createState()=>_NewsletterScreenState();}
class _NewsletterScreenState extends State<NewsletterScreen>{final email=TextEditingController();bool daily=false,updates=true,busy=false;@override void didChangeDependencies(){super.didChangeDependencies();if(email.text.isEmpty){email.text=JsonTools.text(SessionScope.of(context).user?['email'],'');}}@override void dispose(){email.dispose();super.dispose();}Future<void>_save()async{if(!email.text.contains('@')){showSnack(context,'Enter a valid email.',error:true);return;}setState(()=>busy=true);try{await SessionScope.of(context).api.post('/newsletter',body:{'email':email.text.trim(),'preferences':{'daily_market_brief':daily,'product_updates':updates}});if(mounted)showSnack(context,'Newsletter preferences saved.');}on ApiException catch(e){if(mounted)showSnack(context,e.message,error:true);}finally{if(mounted)setState(()=>busy=false);}}@override Widget build(BuildContext context)=>AbsPage(title:'ABS Newsletter',subtitle:'Market briefs and product updates',child:ListView(children:[AbsCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Icon(Icons.mark_email_read_outlined,color:AbsColors.cyan,size:36),const SizedBox(height:12),const Text('Stay connected to ABS',style:TextStyle(fontSize:19,fontWeight:FontWeight.w900)),const SizedBox(height:6),const Text('Choose the updates you want delivered by email.',style:TextStyle(color:AbsColors.muted))])),const SizedBox(height:12),TextField(controller:email,keyboardType:TextInputType.emailAddress,decoration:const InputDecoration(labelText:'Email')),const SizedBox(height:10),SwitchListTile(contentPadding:EdgeInsets.zero,value:daily,onChanged:(v)=>setState(()=>daily=v),title:const Text('Daily market brief'),subtitle:const Text('Receive the ABS market brief when available.')),SwitchListTile(contentPadding:EdgeInsets.zero,value:updates,onChanged:(v)=>setState(()=>updates=v),title:const Text('Product updates'),subtitle:const Text('ABS service and platform announcements.')),const SizedBox(height:10),ElevatedButton(onPressed:busy?null:_save,child:Text(busy?'Saving...':'Save newsletter preferences'))]));}
