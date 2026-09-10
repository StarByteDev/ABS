import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
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
  const NewsScreen({super.key, this.embedded = false});

  final bool embedded;
  @override
  State<NewsScreen> createState() => _NewsScreenState();
}

class _NewsScreenState extends State<NewsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController tabs;

  @override
  void initState() {
    super.initState();
    tabs = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'ABS Intelligence',
        subtitle: 'News, live headlines & economic calendar',
        padding: EdgeInsets.fromLTRB(16, 8, 16, widget.embedded ? 104 : 28),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: AbsColors.panel.withValues(alpha: .88),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AbsColors.lineSoft),
              ),
              child: TabBar(
                controller: tabs,
                indicatorSize: TabBarIndicatorSize.tab,
                dividerColor: Colors.transparent,
                labelPadding: const EdgeInsets.symmetric(horizontal: 4),
                indicator: BoxDecoration(
                  color: AbsColors.panel3,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: AbsColors.purple.withValues(alpha: .24),
                  ),
                ),
                labelStyle: const TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w900,
                ),
                unselectedLabelStyle: const TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
                tabs: const [
                  Tab(text: 'ABS NEWS'),
                  Tab(text: 'LIVE'),
                  Tab(text: 'CALENDAR'),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Expanded(
              child: TabBarView(
                controller: tabs,
                children: const [
                  _ContentList(kind: 'news'),
                  _LiveNewsList(),
                  EconomicCalendarBody(),
                ],
              ),
            ),
          ],
        ),
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
    if (kind == 'news') return _newsCard(context);
    final summary = kind == 'research' ? item['summary'] : item['excerpt'];
    final image = JsonTools.text(item['image_url'], '');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ContentDetailScreen(
              type: kind,
              slug: JsonTools.text(item['slug']),
              initial: item,
            ),
          ),
        ),
        child: AbsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (image.startsWith('http')) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    image,
                    height: 150,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                  ),
                ),
                const SizedBox(height: 12),
              ],
              Row(
                children: [
                  if (JsonTools.text(item['category'], '').isNotEmpty)
                    StatusChip(JsonTools.text(item['category']).toUpperCase()),
                  if (JsonTools.text(item['asset_symbol'], '').isNotEmpty) ...[
                    const SizedBox(width: 6),
                    StatusChip(
                      JsonTools.text(item['asset_symbol']).toUpperCase(),
                      warning: true,
                    ),
                  ],
                  if (JsonTools.boolean(item['is_featured'])) ...[
                    const Spacer(),
                    const StatusChip('FEATURED', warning: true),
                  ],
                ],
              ),
              if (JsonTools.text(item['category'], '').isNotEmpty ||
                  JsonTools.boolean(item['is_featured']))
                const SizedBox(height: 10),
              Text(
                JsonTools.text(item['title']),
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                JsonTools.plain(
                  summary,
                  'Open to read the full ABS publication.',
                ),
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AbsColors.muted),
              ),
              const SizedBox(height: 8),
              Text(
                compactDate(item['published_at']),
                style: const TextStyle(color: AbsColors.muted, fontSize: 10),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _newsCard(BuildContext context) {
    final image = JsonTools.text(item['image_url'], '');
    final category = JsonTools.text(item['category'], 'ABS');
    final excerpt = JsonTools.plain(
      item['excerpt'] ?? item['summary'],
      'Open for the full ABS market update.',
    );
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ContentDetailScreen(
              type: kind,
              slug: JsonTools.text(item['slug']),
              initial: item,
            ),
          ),
        ),
        child: AbsCard(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            category.toUpperCase(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: AbsColors.cyanSoft,
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                              letterSpacing: .75,
                            ),
                          ),
                        ),
                        if (JsonTools.boolean(item['is_featured'])) ...[
                          const SizedBox(width: 7),
                          const StatusChip('FEATURED', warning: true),
                        ],
                      ],
                    ),
                    const SizedBox(height: 7),
                    Text(
                      JsonTools.text(item['title']),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 14.5,
                        height: 1.25,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      excerpt,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AbsColors.muted,
                        fontSize: 10.5,
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      compactDate(item['published_at']),
                      style: const TextStyle(
                        color: AbsColors.muted2,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              if (image.startsWith('http')) ...[
                const SizedBox(width: 11),
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    image,
                    width: 92,
                    height: 92,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                  ),
                ),
              ] else ...[
                const SizedBox(width: 10),
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: AbsColors.cyan.withValues(alpha: .08),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(
                    Icons.article_outlined,
                    color: AbsColors.cyanSoft,
                    size: 21,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _LiveNewsList extends StatefulWidget {
  const _LiveNewsList();
  @override
  State<_LiveNewsList> createState() => _LiveNewsListState();
}

class _LiveNewsListState extends State<_LiveNewsList> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && items.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      items = JsonTools.mapList(
        JsonTools.at(
          await SessionScope.of(context).api.get(
            '/news/live',
            query: {'limit': 40},
          ),
          'data',
          <dynamic>[],
        ),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const LoadingBlock(label: 'Loading live headlines...');
    if (error != null) return ErrorBlock(message: error!, onRetry: _load);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: AbsColors.green.withValues(alpha: .055),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AbsColors.green.withValues(alpha: .16)),
            ),
            child: const Row(
              children: [
                Icon(Icons.sensors_rounded, size: 16, color: AbsColors.green),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'LIVE MARKET WIRE · external headlines supplied through ABS',
                    style: TextStyle(
                      color: AbsColors.muted,
                      fontSize: 9.5,
                      fontWeight: FontWeight.w800,
                      letterSpacing: .15,
                    ),
                  ),
                ),
              ],
            ),
          ),
          ...items.map((item) {
            final source = JsonTools.text(
              item['source'] ?? item['source_name'],
              'Market source',
            );
            final url = JsonTools.text(
              item['url'] ?? item['source_url'],
              '',
            );
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: InkWell(
                onTap: url.startsWith('http')
                    ? () => launchUrl(
                          Uri.parse(url),
                          mode: LaunchMode.externalApplication,
                        )
                    : null,
                borderRadius: BorderRadius.circular(18),
                child: AbsCard(
                  padding: const EdgeInsets.fromLTRB(13, 12, 11, 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          color: AbsColors.cyan.withValues(alpha: .08),
                          borderRadius: BorderRadius.circular(11),
                        ),
                        child: const Icon(
                          Icons.bolt_rounded,
                          color: AbsColors.cyanSoft,
                          size: 17,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    source.toUpperCase(),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: AbsColors.cyanSoft,
                                      fontSize: 8.75,
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: .6,
                                    ),
                                  ),
                                ),
                                Text(
                                  compactDate(
                                    item['published_at'] ??
                                        item['publishedAt'] ??
                                        item['timestamp'],
                                  ),
                                  style: const TextStyle(
                                    color: AbsColors.muted2,
                                    fontSize: 9,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 5),
                            Text(
                              JsonTools.text(item['title'] ?? item['headline']),
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 12.5,
                                height: 1.3,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (url.startsWith('http')) ...[
                        const SizedBox(width: 6),
                        const Padding(
                          padding: EdgeInsets.only(top: 11),
                          child: Icon(
                            Icons.open_in_new_rounded,
                            size: 15,
                            color: AbsColors.muted2,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            );
          }),
          if (items.isEmpty)
            const EmptyState(
              title: 'No live headlines',
              message: 'Live news is temporarily unavailable.',
              icon: Icons.newspaper_outlined,
            ),
          const SizedBox(height: 26),
        ],
      ),
    );
  }
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

class EconomicCalendarScreen extends StatelessWidget {
  const EconomicCalendarScreen({super.key});

  @override
  Widget build(BuildContext context) => const AbsPage(
        title: 'Economic Calendar',
        subtitle: 'Past, current & upcoming macro events',
        child: EconomicCalendarBody(),
      );
}

class EconomicCalendarBody extends StatefulWidget {
  const EconomicCalendarBody({super.key});

  @override
  State<EconomicCalendarBody> createState() => _EconomicCalendarBodyState();
}

class _EconomicCalendarBodyState extends State<EconomicCalendarBody> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> events = [];
  String impact = 'all';
  String currency = 'all';
  String period = 'today';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && events.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      final api = SessionScope.of(context).api;
      final responses = await Future.wait([
        api.get(
          '/economic-calendar',
          query: {
            'from': _apiDate(today.subtract(const Duration(days: 30))),
            'to': _apiDate(today.subtract(const Duration(days: 1))),
          },
        ),
        api.get(
          '/economic-calendar',
          query: {
            'from': _apiDate(today),
            'to': _apiDate(today.add(const Duration(days: 30))),
          },
        ),
      ]);
      final combined = <Map<String, dynamic>>[
        ...JsonTools.mapList(
          JsonTools.at(responses[0], 'data', <dynamic>[]),
        ),
        ...JsonTools.mapList(
          JsonTools.at(responses[1], 'data', <dynamic>[]),
        ),
      ];
      final seen = <String>{};
      events = combined.where((event) {
        final id = JsonTools.text(event['id'], '');
        final at = _eventAt(event)?.toIso8601String() ?? '';
        final title = JsonTools.text(
          event['title'] ?? event['event'] ?? event['name'],
          '',
        );
        final key = id.isNotEmpty ? 'id:$id' : '$at|${_currencyOf(event)}|$title';
        return seen.add(key);
      }).toList();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const LoadingBlock(label: 'Loading economic calendar...');
    }
    if (error != null) return ErrorBlock(message: error!, onRetry: _load);

    final currencies = events
        .map(_currencyOf)
        .where((value) => value.isNotEmpty && value != '—')
        .toSet()
        .toList()
      ..sort();
    if (currency != 'all' && !currencies.contains(currency)) {
      currency = 'all';
    }

    final filtered = events.where(_matchesFilters).toList()
      ..sort((a, b) {
        final aa = _eventAt(a);
        final bb = _eventAt(b);
        if (aa == null && bb == null) return 0;
        if (aa == null) return 1;
        if (bb == null) return -1;
        return aa.compareTo(bb);
      });

    final now = DateTime.now();
    final todayCount = events.where((e) => _isToday(_eventAt(e), now)).length;
    final highToday = events
        .where((e) => _isToday(_eventAt(e), now) && _impactOf(e) == 'high')
        .length;
    final next = events
        .where((e) {
          final at = _eventAt(e);
          return at != null && at.isAfter(now);
        })
        .toList()
      ..sort((a, b) => _eventAt(a)!.compareTo(_eventAt(b)!));

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          _CalendarPulseHeader(
            todayCount: todayCount,
            highToday: highToday,
            nextEvent: next.isEmpty ? null : next.first,
          ),
          const SizedBox(height: 11),
          _CalendarPeriodSwitch(
            value: period,
            onChanged: (value) => setState(() => period = value),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<String>(
                  value: impact,
                  isDense: true,
                  decoration: const InputDecoration(
                    labelText: 'Impact',
                    prefixIcon: Icon(Icons.tune_rounded, size: 18),
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 11,
                    ),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'all', child: Text('All impact')),
                    DropdownMenuItem(value: 'high', child: Text('High')),
                    DropdownMenuItem(value: 'medium', child: Text('Medium')),
                    DropdownMenuItem(value: 'low', child: Text('Low')),
                  ],
                  onChanged: (value) =>
                      setState(() => impact = value ?? 'all'),
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: DropdownButtonFormField<String>(
                  value: currency,
                  isDense: true,
                  decoration: const InputDecoration(
                    labelText: 'Currency',
                    prefixIcon: Icon(Icons.public_rounded, size: 18),
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 11,
                    ),
                  ),
                  items: [
                    const DropdownMenuItem(
                      value: 'all',
                      child: Text('All currencies'),
                    ),
                    ...currencies.map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(value),
                      ),
                    ),
                  ],
                  onChanged: (value) =>
                      setState(() => currency = value ?? 'all'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const _CalendarColumnHeader(),
          const SizedBox(height: 6),
          if (filtered.isEmpty)
            EmptyState(
              title: period == 'yesterday'
                  ? 'No events yesterday'
                  : period == 'tomorrow'
                      ? 'No events tomorrow'
                      : period == 'week'
                          ? 'No events this week'
                          : 'No events today',
              message: 'No events match the selected impact and currency filters.',
              icon: Icons.event_busy_outlined,
            )
          else
            ..._calendarRows(filtered),
          const SizedBox(height: 30),
        ],
      ),
    );
  }

  bool _matchesFilters(Map<String, dynamic> event) {
    if (impact != 'all' && _impactOf(event) != impact) return false;
    if (currency != 'all' && _currencyOf(event) != currency) return false;
    final at = _eventAt(event);
    if (at == null) return false;
    final now = DateTime.now();
    final day = DateTime(at.year, at.month, at.day);
    final today = DateTime(now.year, now.month, now.day);
    if (period == 'yesterday') {
      return day == today.subtract(const Duration(days: 1));
    }
    if (period == 'tomorrow') {
      return day == today.add(const Duration(days: 1));
    }
    if (period == 'week') {
      final monday = today.subtract(Duration(days: today.weekday - 1));
      final nextMonday = monday.add(const Duration(days: 7));
      return !day.isBefore(monday) && day.isBefore(nextMonday);
    }
    return day == today;
  }

  List<Widget> _calendarRows(List<Map<String, dynamic>> rows) {
    final widgets = <Widget>[];
    String? previousDay;
    for (final event in rows) {
      final at = _eventAt(event);
      final dayLabel = at == null
          ? 'DATE NOT AVAILABLE'
          : DateFormat('EEEE · d MMMM').format(at).toUpperCase();
      if (dayLabel != previousDay) {
        if (widgets.isNotEmpty) widgets.add(const SizedBox(height: 5));
        widgets.add(
          Padding(
            padding: const EdgeInsets.fromLTRB(2, 2, 2, 8),
            child: Row(
              children: [
                Text(
                  dayLabel,
                  style: const TextStyle(
                    color: AbsColors.muted2,
                    fontSize: 9,
                    fontWeight: FontWeight.w900,
                    letterSpacing: .8,
                  ),
                ),
                const SizedBox(width: 8),
                const Expanded(
                  child: Divider(height: 1, color: AbsColors.lineSoft),
                ),
              ],
            ),
          ),
        );
        previousDay = dayLabel;
      }
      widgets.add(
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: _EconomicEventCard(event: event),
        ),
      );
    }
    return widgets;
  }

  static String _apiDate(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
}

class _CalendarPulseHeader extends StatelessWidget {
  const _CalendarPulseHeader({
    required this.todayCount,
    required this.highToday,
    required this.nextEvent,
  });

  final int todayCount;
  final int highToday;
  final Map<String, dynamic>? nextEvent;

  @override
  Widget build(BuildContext context) {
    final nextAt = nextEvent == null ? null : _eventAt(nextEvent!);
    final nextCurrency = nextEvent == null ? '—' : _currencyOf(nextEvent!);
    final nextTitle = nextEvent == null
        ? 'No upcoming event in range'
        : JsonTools.text(
            nextEvent!['title'] ?? nextEvent!['event'] ?? nextEvent!['name'],
            'Macro event',
          );
    return AbsCard(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF101A25), Color(0xFF0C121C), Color(0xFF090D15)],
      ),
      padding: const EdgeInsets.fromLTRB(15, 14, 15, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.language_rounded, color: AbsColors.cyanSoft, size: 18),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'TODAY’S MACRO PULSE',
                  style: TextStyle(
                    color: AbsColors.cyanSoft,
                    fontSize: 9.5,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.05,
                  ),
                ),
              ),
              StatusChip('30D PAST · 30D AHEAD'),
            ],
          ),
          const SizedBox(height: 13),
          Row(
            children: [
              Expanded(
                child: _CalendarMiniMetric(
                  label: 'EVENTS TODAY',
                  value: '$todayCount',
                  color: AbsColors.text,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _CalendarMiniMetric(
                  label: 'HIGH IMPACT',
                  value: '$highToday',
                  color: highToday > 0 ? AbsColors.gold : AbsColors.muted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
            decoration: BoxDecoration(
              color: AbsColors.panel2.withValues(alpha: .68),
              borderRadius: BorderRadius.circular(13),
              border: Border.all(color: AbsColors.lineSoft),
            ),
            child: Row(
              children: [
                Container(
                  width: 33,
                  height: 33,
                  decoration: BoxDecoration(
                    color: AbsColors.purple.withValues(alpha: .10),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(
                    Icons.schedule_rounded,
                    color: AbsColors.purpleSoft,
                    size: 17,
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        nextAt == null
                            ? 'NEXT EVENT'
                            : 'NEXT · $nextCurrency · ${DateFormat('HH:mm').format(nextAt)}',
                        style: const TextStyle(
                          color: AbsColors.muted2,
                          fontSize: 8.75,
                          fontWeight: FontWeight.w900,
                          letterSpacing: .65,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        nextTitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 11.25,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CalendarMiniMetric extends StatelessWidget {
  const _CalendarMiniMetric({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
        decoration: BoxDecoration(
          color: AbsColors.panel2.withValues(alpha: .60),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AbsColors.lineSoft),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                color: AbsColors.muted2,
                fontSize: 8.25,
                fontWeight: FontWeight.w900,
                letterSpacing: .7,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              value,
              style: TextStyle(
                color: color,
                fontSize: 19,
                height: 1,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      );
}

class _CalendarPeriodSwitch extends StatelessWidget {
  const _CalendarPeriodSwitch({
    required this.value,
    required this.onChanged,
  });

  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: AbsColors.panel,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AbsColors.lineSoft),
          ),
          child: Row(
            children: [
              _PeriodButton(
                label: 'Yesterday',
                selected: value == 'yesterday',
                onTap: () => onChanged('yesterday'),
              ),
              _PeriodButton(
                label: 'Today',
                selected: value == 'today',
                onTap: () => onChanged('today'),
              ),
              _PeriodButton(
                label: 'Tomorrow',
                selected: value == 'tomorrow',
                onTap: () => onChanged('tomorrow'),
              ),
              _PeriodButton(
                label: 'This Week',
                selected: value == 'week',
                onTap: () => onChanged('week'),
              ),
            ],
          ),
        ),
      );
}

class _PeriodButton extends StatelessWidget {
  const _PeriodButton({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
            decoration: BoxDecoration(
              color: selected ? AbsColors.panel3 : Colors.transparent,
              borderRadius: BorderRadius.circular(10),
              border: selected
                  ? Border.all(color: AbsColors.purple.withValues(alpha: .24))
                  : null,
            ),
            child: Text(
              label,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: selected ? AbsColors.text : AbsColors.muted,
                fontSize: 10.5,
                fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ),
        );
}

class _CalendarColumnHeader extends StatelessWidget {
  const _CalendarColumnHeader();

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: AbsColors.panel2.withValues(alpha: .72),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AbsColors.lineSoft),
        ),
        child: const Row(
          children: [
            SizedBox(width: 46, child: Text('TIME', style: _calendarHeaderStyle)),
            SizedBox(width: 46, child: Text('CUR.', style: _calendarHeaderStyle)),
            SizedBox(width: 48, child: Text('IMP.', style: _calendarHeaderStyle)),
            Expanded(child: Text('EVENT', style: _calendarHeaderStyle)),
          ],
        ),
      );

  static const _calendarHeaderStyle = TextStyle(
    color: AbsColors.muted2,
    fontSize: 8,
    fontWeight: FontWeight.w900,
    letterSpacing: .7,
  );
}

class _EconomicEventCard extends StatelessWidget {
  const _EconomicEventCard({required this.event});

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    final eventImpact = _impactOf(event);
    final impactColor = eventImpact == 'high'
        ? AbsColors.gold
        : eventImpact == 'medium'
            ? AbsColors.cyan
            : AbsColors.muted;
    final at = _eventAt(event);
    final title = JsonTools.text(
      event['title'] ?? event['event'] ?? event['name'] ?? event['event_name'],
      'Economic event',
    );
    final actual = _eventValue(event, ['actual_value', 'actual', 'actualValue']);
    final forecast = _eventValue(event, ['forecast_value', 'forecast', 'consensus', 'forecastValue']);
    final previous = _eventValue(event, ['previous_value', 'previous', 'prev', 'previousValue']);
    final surprise = _surpriseLabel(actual, forecast);

    return Container(
      padding: const EdgeInsets.fromLTRB(10, 11, 10, 11),
      decoration: BoxDecoration(
        color: AbsColors.panel.withValues(alpha: .88),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AbsColors.lineSoft),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 46,
            child: Text(
              at == null ? '—' : DateFormat('HH:mm').format(at),
              style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w900),
            ),
          ),
          SizedBox(
            width: 46,
            child: Align(
              alignment: Alignment.topLeft,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                decoration: BoxDecoration(
                  color: AbsColors.panel2,
                  borderRadius: BorderRadius.circular(7),
                  border: Border.all(color: AbsColors.lineSoft),
                ),
                child: Text(
                  _currencyOf(event),
                  style: const TextStyle(fontSize: 8.5, fontWeight: FontWeight.w900),
                ),
              ),
            ),
          ),
          SizedBox(
            width: 48,
            child: _ImpactBars(level: eventImpact, color: impactColor),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontSize: 11.5, height: 1.25, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 7),
                Wrap(
                  spacing: 10,
                  runSpacing: 4,
                  children: [
                    _InlineMacroValue(label: 'Actual', value: actual, emphasize: actual != '—'),
                    _InlineMacroValue(label: 'Forecast', value: forecast),
                    _InlineMacroValue(label: 'Previous', value: previous),
                  ],
                ),
                if (surprise != null) ...[
                  const SizedBox(height: 5),
                  Text(
                    surprise,
                    style: const TextStyle(color: AbsColors.goldSoft, fontSize: 8.5, fontWeight: FontWeight.w800),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ImpactBars extends StatelessWidget {
  const _ImpactBars({required this.level, required this.color});
  final String level;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final active = level == 'high' ? 3 : level == 'medium' ? 2 : 1;
    return Row(
      children: List.generate(3, (index) {
        final on = index < active;
        return Container(
          width: 5,
          height: 10 + (index * 4),
          margin: const EdgeInsets.only(right: 3),
          decoration: BoxDecoration(
            color: on ? color : AbsColors.lineSoft,
            borderRadius: BorderRadius.circular(3),
          ),
        );
      }),
    );
  }
}

class _InlineMacroValue extends StatelessWidget {
  const _InlineMacroValue({required this.label, required this.value, this.emphasize = false});
  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) => RichText(
        text: TextSpan(
          style: const TextStyle(fontSize: 8.8, color: AbsColors.muted2, fontWeight: FontWeight.w700),
          children: [
            TextSpan(text: '$label '),
            TextSpan(
              text: value,
              style: TextStyle(
                color: emphasize ? AbsColors.cyanSoft : AbsColors.text,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      );
}

class _EventMetric extends StatelessWidget {
  const _EventMetric({
    required this.label,
    required this.value,
    this.emphasize = false,
  });

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 9),
        decoration: BoxDecoration(
          color: AbsColors.panel2.withValues(alpha: .58),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: emphasize
                ? AbsColors.cyan.withValues(alpha: .20)
                : AbsColors.lineSoft,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                color: AbsColors.muted2,
                fontSize: 7.75,
                fontWeight: FontWeight.w900,
                letterSpacing: .55,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: emphasize ? AbsColors.cyanSoft : AbsColors.text,
                fontSize: 11.5,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      );
}

DateTime? _eventAt(Map<String, dynamic> event) {
  final raw = event['event_at'] ??
      event['scheduled_at'] ??
      event['release_at'] ??
      event['scheduled_for'] ??
      event['datetime'] ??
      event['date_time'] ??
      event['dateTime'] ??
      event['event_datetime'] ??
      event['event_date_time'] ??
      event['timestamp'];
  if (raw is num) {
    final millis = raw > 100000000000 ? raw.toInt() : raw.toInt() * 1000;
    return DateTime.fromMillisecondsSinceEpoch(millis, isUtc: true).toLocal();
  }
  if (raw != null && raw.toString().trim().isNotEmpty) {
    final parsed = DateTime.tryParse(raw.toString().trim());
    if (parsed != null) return parsed.toLocal();
  }
  final date = JsonTools.text(event['date'] ?? event['event_date'] ?? event['release_date'], '');
  final time = JsonTools.text(event['time'] ?? event['event_time'] ?? event['release_time'], '');
  if (date.isNotEmpty) {
    final combined = time.isEmpty ? date : '$date $time';
    final parsed = DateTime.tryParse(combined);
    if (parsed != null) return parsed.toLocal();
  }
  return null;
}

String _impactOf(Map<String, dynamic> event) {
  final raw = JsonTools.text(
    event['impact'] ?? event['importance'] ?? event['priority'],
    'low',
  ).toLowerCase();
  if (raw.contains('high') || raw == '3') return 'high';
  if (raw.contains('med') || raw.contains('moderate') || raw == '2') {
    return 'medium';
  }
  return 'low';
}

String _currencyOf(Map<String, dynamic> event) {
  final raw = JsonTools.text(
    event['currency'] ?? event['currency_code'] ?? event['country_code'] ?? event['country'] ?? event['region'],
    '—',
  ).toUpperCase();
  const aliases = {
    'US': 'USD', 'USA': 'USD',
    'GB': 'GBP', 'UK': 'GBP',
    'EU': 'EUR', 'EMU': 'EUR',
    'JP': 'JPY', 'JAPAN': 'JPY',
    'CA': 'CAD', 'CANADA': 'CAD',
    'AU': 'AUD', 'AUSTRALIA': 'AUD',
    'NZ': 'NZD', 'NEW ZEALAND': 'NZD',
    'CH': 'CHF', 'SWITZERLAND': 'CHF',
  };
  return aliases[raw] ?? raw;
}

String _eventValue(Map<String, dynamic> event, List<String> keys) {
  for (final key in keys) {
    if (event.containsKey(key) && event[key] != null) {
      final value = JsonTools.text(event[key], '—');
      if (value.isNotEmpty) return value;
    }
  }
  return '—';
}

bool _isToday(DateTime? at, DateTime now) => at != null &&
    at.year == now.year &&
    at.month == now.month &&
    at.day == now.day;

String? _surpriseLabel(String actual, String forecast) {
  final a = _numericMacroValue(actual);
  final f = _numericMacroValue(forecast);
  if (a == null || f == null) return null;
  if ((a - f).abs() < 0.0000001) return 'Actual is in line with forecast';
  return a > f ? 'Actual is above forecast' : 'Actual is below forecast';
}

double? _numericMacroValue(String value) {
  if (value == '—') return null;
  var cleaned = value
      .replaceAll(',', '')
      .replaceAll('%', '')
      .replaceAll(RegExp(r'[^0-9.\-]'), '');
  if (cleaned.isEmpty || cleaned == '-' || cleaned == '.') return null;
  return double.tryParse(cleaned);
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
