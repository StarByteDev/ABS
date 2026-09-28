import 'package:flutter/material.dart';

import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import '../widgets/tiles.dart';
import 'news_detail_screen.dart';

class NewsScreen extends StatefulWidget {
  const NewsScreen({super.key});

  @override
  State<NewsScreen> createState() => _NewsScreenState();
}

class _NewsScreenState extends State<NewsScreen> {
  int _view = 0; // Calendar is intentionally first/default.
  String _cat = 'All';
  String _period = 'Upcoming';
  bool _requested = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_requested) {
      _requested = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) AppScope.read(context).ensureNews();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: const Row(
          children: [
            AbsLogo(size: 34),
            SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Pulse Intelligence', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                  Text('Calendar, ABS News & live market wire', style: AppText.muted),
                ],
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Saved articles',
            onPressed: () => push(context, const SavedNewsScreen()),
            icon: const Icon(Icons.bookmark_border_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: AppColors.accent,
        backgroundColor: AppColors.surface,
        onRefresh: app.refreshNews,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
          children: [
            SegmentToggle(
              options: const ['Calendar', 'ABS News', 'Live'],
              index: _view,
              onChanged: (i) => setState(() => _view = i),
            ),
            if (app.newsLoading) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(minHeight: 2),
            ],
            if (app.newsError != null) ...[
              const SizedBox(height: 12),
              AbsCard(
                borderColor: fade(AppColors.amber, .4),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline_rounded, color: AppColors.amber),
                    const SizedBox(width: 10),
                    Expanded(child: Text(app.newsError!, style: AppText.muted)),
                    IconButton(onPressed: app.refreshNews, icon: const Icon(Icons.refresh_rounded)),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 14),
            if (_view == 0) _calendarView() else if (_view == 1) _newsView() else _liveView(),
            const RiskNotice(),
          ],
        ),
      ),
    );
  }

  Widget _calendarView() {
    const periods = <String>['Today', 'Upcoming', 'Previous', 'All'];
    final now = DateTime.now();
    final start = DateTime(now.year, now.month, now.day);
    final end = start.add(const Duration(days: 1));
    final events = MockData.calendar.where((e) {
      final at = e.at?.toLocal();
      if (_period == 'All') return true;
      if (at == null) return _period == 'Upcoming';
      if (_period == 'Today') return !at.isBefore(start) && at.isBefore(end);
      if (_period == 'Previous') return at.isBefore(now);
      return !at.isBefore(now);
    }).toList();
    if (_period == 'Previous') events.sort((a, b) => (b.at ?? DateTime(1970)).compareTo(a.at ?? DateTime(1970)));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionTitle('Market-moving events'),
        const Text(
          'Economic releases that may influence liquidity, the US dollar, rates and crypto risk sentiment.',
          style: AppText.muted,
        ),
        const SizedBox(height: 12),
        SizedBox(
          height: 38,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: periods.length,
            separatorBuilder: (_, __) => const SizedBox(width: 8),
            itemBuilder: (_, i) {
              final value = periods[i];
              final selected = value == _period;
              final count = _countForPeriod(value, now, start, end);
              return ChoiceChip(
                label: Text('$value  $count'),
                selected: selected,
                showCheckmark: false,
                onSelected: (_) => setState(() => _period = value),
                selectedColor: fade(AppColors.accent, .2),
                backgroundColor: AppColors.surface,
                side: BorderSide(color: selected ? fade(AppColors.accent, .55) : AppColors.line),
                labelStyle: TextStyle(
                  color: selected ? AppColors.text : AppColors.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                ),
              );
            },
          ),
        ),
        const SizedBox(height: 14),
        if (events.isEmpty)
          const EmptyState(
            icon: Icons.event_available_outlined,
            title: 'No events in this view',
            message: 'Pull to refresh. ABS shows calendar data supplied by the live backend.',
          )
        else
          for (final e in events.take(80))
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _CalendarCard(event: e),
            ),
      ],
    );
  }

  int _countForPeriod(String value, DateTime now, DateTime start, DateTime end) {
    return MockData.calendar.where((e) {
      final at = e.at?.toLocal();
      if (value == 'All') return true;
      if (at == null) return value == 'Upcoming';
      if (value == 'Today') return !at.isBefore(start) && at.isBefore(end);
      if (value == 'Previous') return at.isBefore(now);
      return !at.isBefore(now);
    }).length;
  }

  Widget _newsView() {
    final categories = MockData.newsCategories;
    final items = _cat == 'All' ? MockData.news : MockData.news.where((n) => n.category == _cat).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: 38,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: categories.length,
            separatorBuilder: (_, __) => const SizedBox(width: 8),
            itemBuilder: (_, i) {
              final c = categories[i];
              final selected = c == _cat;
              return ChoiceChip(
                label: Text(c),
                selected: selected,
                showCheckmark: false,
                onSelected: (_) => setState(() => _cat = c),
                selectedColor: fade(AppColors.accent, .2),
                backgroundColor: AppColors.surface,
                side: BorderSide(color: selected ? fade(AppColors.accent, .5) : AppColors.line),
                labelStyle: TextStyle(color: selected ? AppColors.accent : AppColors.muted, fontWeight: FontWeight.w600),
              );
            },
          ),
        ),
        const SizedBox(height: 14),
        if (items.isEmpty)
          const EmptyState(
            icon: Icons.article_outlined,
            title: 'No ABS News articles found',
            message: 'Pull to refresh or choose another category.',
          )
        else ...[
          _FeaturedCard(item: items.first),
          const SizedBox(height: 12),
          for (final n in items.skip(1))
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: NewsTile(item: n, onTap: () => push(context, NewsDetailScreen(item: n))),
            ),
        ],
      ],
    );
  }

  Widget _liveView() {
    final items = MockData.liveNews;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Pill('LIVE MARKET WIRE', color: AppColors.up),
            const Spacer(),
            Text('${items.length} headlines', style: AppText.muted),
          ],
        ),
        const SizedBox(height: 12),
        if (items.isEmpty)
          const EmptyState(
            icon: Icons.sensors_outlined,
            title: 'No live headlines available',
            message: 'Pull to refresh. The app does not substitute demo stories.',
          )
        else
          for (final n in items)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: NewsTile(item: n, onTap: () => push(context, NewsDetailScreen(item: n))),
            ),
      ],
    );
  }
}

class _CalendarCard extends StatelessWidget {
  const _CalendarCard({required this.event});
  final CalendarEvent event;

  Color get _impactColor {
    final impact = event.impact.toLowerCase();
    if (impact.contains('high') || impact == '3') return AppColors.down;
    if (impact.contains('low') || impact == '1') return AppColors.up;
    return AppColors.gold;
  }

  @override
  Widget build(BuildContext context) {
    final at = event.at?.toLocal();
    final date = at == null ? 'Date pending' : '${at.day.toString().padLeft(2, '0')} ${_month(at.month)}';
    final time = at == null ? '—' : '${at.hour.toString().padLeft(2, '0')}:${at.minute.toString().padLeft(2, '0')}';
    final rawActual = event.actual.trim();
    final missingActual = rawActual.isEmpty || rawActual.toLowerCase() == 'pending' || rawActual == '—';
    final isPast = at != null && at.isBefore(DateTime.now());
    // Never label a historical release as Pending. If the provider has not
    // published a value yet, say so explicitly instead of displaying a blank.
    final actualDisplay = missingActual ? (isPast ? 'Not reported' : 'Pending') : rawActual;
    final previousDisplay = event.previous.trim().isEmpty ? 'Not provided' : event.previous.trim();
    final forecastDisplay = event.forecast.trim().isEmpty ? 'Not provided' : event.forecast.trim();
    final actualColor = missingActual
        ? (isPast ? AppColors.muted : AppColors.gold)
        : AppColors.text;
    return AbsCard(
      borderColor: fade(_impactColor, .45),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 58,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(time, style: AppText.figure.copyWith(fontSize: 15)),
                    const SizedBox(height: 2),
                    Text(date, style: AppText.muted.copyWith(fontSize: 10.5)),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Container(width: 1, height: 46, color: AppColors.line),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 6,
                      runSpacing: 5,
                      children: [
                        Pill(event.impact.toUpperCase(), color: _impactColor),
                        Pill(event.currency, color: AppColors.muted),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(event.title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, height: 1.25)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: MiniStat(label: 'Previous', value: previousDisplay)),
              Expanded(child: MiniStat(label: 'Forecast', value: forecastDisplay)),
              Expanded(child: MiniStat(label: 'Actual', value: actualDisplay, valueColor: actualColor)),
            ],
          ),
          if (event.context.isNotEmpty) ...[
            const Divider(height: 22),
            Text(event.context, style: AppText.muted.copyWith(height: 1.45)),
          ],
        ],
      ),
    );
  }

  String _month(int month) => const <String>['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][month - 1];
}

class _FeaturedCard extends StatelessWidget {
  const _FeaturedCard({required this.item});
  final NewsItem item;

  @override
  Widget build(BuildContext context) {
    final (_, color) = MockData.categoryStyle(item.category);
    return AbsCard(
      padding: EdgeInsets.zero,
      onTap: () => push(context, NewsDetailScreen(item: item)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Stack(
            children: [
              NewsThumb(category: item.category, width: double.infinity, height: 150),
              Positioned(left: 12, top: 12, child: Pill(item.category, color: color)),
            ],
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, height: 1.3)),
                if (item.summary.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(item.summary, maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.muted, height: 1.4)),
                ],
                const SizedBox(height: 10),
                Text('${item.source} · ${timeAgo(item.minutesAgo)}', style: AppText.muted.copyWith(fontSize: 11.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class SavedNewsScreen extends StatelessWidget {
  const SavedNewsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final all = <NewsItem>[...MockData.news, ...MockData.liveNews];
    final saved = all.where((n) => app.savedNews.contains(n.id)).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Saved articles')),
      body: saved.isEmpty
          ? const Center(
              child: EmptyState(
                icon: Icons.bookmark_border_rounded,
                title: 'Nothing saved yet',
                message: 'Tap the bookmark on an article to keep it in this app session.',
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: saved.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (_, i) => NewsTile(item: saved[i], onTap: () => push(context, NewsDetailScreen(item: saved[i]))),
            ),
    );
  }
}
