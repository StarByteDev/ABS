import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import '../widgets/tiles.dart';

class NewsDetailScreen extends StatelessWidget {
  const NewsDetailScreen({super.key, required this.item});
  final NewsItem item;

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final saved = app.savedNews.contains(item.id);
    final (_, color) = MockData.categoryStyle(item.category);
    return Scaffold(
      appBar: AppBar(
        actions: [
          IconButton(
            tooltip: saved ? 'Remove from saved' : 'Save article',
            onPressed: () => app.toggleSaved(item.id),
            icon: Icon(saved ? Icons.bookmark_rounded : Icons.bookmark_border_rounded,
                color: saved ? AppColors.gold : null),
          ),
          IconButton(
            tooltip: 'Share',
            onPressed: () => Share.share('${item.title}\n${item.sourceUrl.isNotEmpty ? item.sourceUrl : item.summary}'),
            icon: const Icon(Icons.ios_share_rounded),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
        children: [
          Align(alignment: Alignment.centerLeft, child: Pill(item.category, color: color)),
          const SizedBox(height: 12),
          Text(item.title,
              style: const TextStyle(
                  fontSize: 24, fontWeight: FontWeight.w800, height: 1.25, letterSpacing: -0.4)),
          const SizedBox(height: 10),
          Text('${item.source}, ${timeAgo(item.minutesAgo)}', style: AppText.muted),
          const SizedBox(height: 18),
          NewsThumb(category: item.category, width: double.infinity, height: 170),
          const SizedBox(height: 18),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: fade(AppColors.accent, .08),
              border: const Border(left: BorderSide(color: AppColors.accent, width: 3)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Summary',
                    style: TextStyle(
                        color: AppColors.accent, fontWeight: FontWeight.w700, fontSize: 12.5)),
                const SizedBox(height: 6),
                Text(item.summary, style: const TextStyle(height: 1.5)),
              ],
            ),
          ),
          const SizedBox(height: 20),
          for (final p in (item.body.isEmpty && item.summary.isNotEmpty ? <String>[item.summary] : item.body))
            Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Text(p, style: const TextStyle(fontSize: 15.5, height: 1.65)),
            ),
          const SizedBox(height: 6),
          const Text('Key points', style: AppText.h2),
          const SizedBox(height: 10),
          for (final k in item.keyPoints)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Container(
                      width: 6,
                      height: 6,
                      decoration: const BoxDecoration(
                          color: AppColors.accent, shape: BoxShape.circle),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(child: Text(k, style: const TextStyle(height: 1.5))),
                ],
              ),
            ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: item.sourceUrl.startsWith('http') ? () => launchUrl(Uri.parse(item.sourceUrl), mode: LaunchMode.externalApplication) : null,
              icon: const Icon(Icons.open_in_new_rounded, size: 18),
              label: Text('Read on ${item.source}'),
            ),
          ),
          const RiskNotice(),
        ],
      ),
    );
  }
}
