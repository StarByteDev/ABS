import 'package:flutter/material.dart';

import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import 'charts.dart';
import 'common.dart';

class SignalCard extends StatelessWidget {
  const SignalCard({super.key, required this.signal, this.onTap, this.preview = false});
  final Signal signal;
  final VoidCallback? onTap;
  final bool preview;

  @override
  Widget build(BuildContext context) {
    final s = signal;
    final isLong = s.side == Side.long;
    final sideColor = isLong ? AppColors.up : AppColors.down;
    return AbsCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CoinAvatar(symbol: s.symbol, size: 38),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Text(s.pair,
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                      const SizedBox(width: 8),
                      Pill(isLong ? 'Long' : 'Short', color: sideColor),
                    ]),
                    const SizedBox(height: 4),
                    Text('${s.timeframe} chart, ${timeAgo(s.minutesAgo)}', style: AppText.muted),
                  ],
                ),
              ),
              ConfidenceRing(value: s.confidence),
            ],
          ),
          const SizedBox(height: 14),
          Row(children: [
            Expanded(child: MiniStat(label: 'Entry', value: s.entry > 0 ? fmtPrice(s.entry) : '—')),
            Expanded(
                child: MiniStat(
                    label: 'Stop', value: s.stop > 0 ? fmtPrice(s.stop) : '—', valueColor: AppColors.down)),
            Expanded(
                child: MiniStat(
                    label: 'Target 1',
                    value: s.targets.isNotEmpty ? fmtPrice(s.targets.first) : '—',
                    valueColor: AppColors.up)),
          ]),
          const SizedBox(height: 14),
          Row(
            children: [
              VoteDots(agree: s.agree),
              const SizedBox(width: 8),
              Expanded(
                child: Text('${s.agree}/15 agree',
                    style: AppText.muted, overflow: TextOverflow.ellipsis),
              ),
              StatusLabel(signal: s),
            ],
          ),
          if (preview) ...[
            const SizedBox(height: 10),
            const Text('Preview signal. Get Pulse to see the full list.',
                style: TextStyle(color: AppColors.gold, fontSize: 12)),
          ],
        ],
      ),
    );
  }
}

class VoteDots extends StatelessWidget {
  const VoteDots({super.key, required this.agree});
  final int agree;

  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (int i = 0; i < 15; i++)
            Container(
              width: 5,
              height: 5,
              margin: const EdgeInsets.only(right: 2),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: i < agree ? AppColors.accent : AppColors.line,
              ),
            ),
        ],
      );
}

class StatusLabel extends StatelessWidget {
  const StatusLabel({super.key, required this.signal});
  final Signal signal;

  @override
  Widget build(BuildContext context) {
    final (String text, Color color) = switch (signal.status) {
      SignalStatus.active => ('Active', AppColors.up),
      SignalStatus.watching => ('Watching', AppColors.amber),
      SignalStatus.closed => ('Closed ${signal.result ?? ''}'.trim(), AppColors.muted),
    };
    return LegendDot(color: color, label: text);
  }
}

class VoteIcon extends StatelessWidget {
  const VoteIcon(this.vote, {super.key});
  final int vote;

  @override
  Widget build(BuildContext context) => switch (vote) {
        1 => const Icon(Icons.check_circle_rounded, color: AppColors.up, size: 18),
        0 => const Icon(Icons.remove_circle_outline_rounded, color: AppColors.faint, size: 18),
        _ => Icon(Icons.cancel_rounded, color: fade(AppColors.down, .85), size: 18),
      };
}

class NewsThumb extends StatelessWidget {
  const NewsThumb({super.key, required this.category, this.width = 68, this.height = 68});
  final String category;
  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    final (icon, color) = MockData.categoryStyle(category);
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [fade(color, .35), fade(color, .06)],
        ),
      ),
      child: Icon(icon, color: color, size: (height * 0.4).clamp(20.0, 56.0).toDouble()),
    );
  }
}

class NewsTile extends StatelessWidget {
  const NewsTile({super.key, required this.item, required this.onTap});
  final NewsItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final (_, color) = MockData.categoryStyle(item.category);
    return AbsCard(
      padding: const EdgeInsets.all(12),
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          NewsThumb(category: item.category),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${item.source}, ${timeAgo(item.minutesAgo)}',
                    style: AppText.muted.copyWith(fontSize: 11.5)),
                const SizedBox(height: 4),
                Text(item.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 14.5, height: 1.3)),
                const SizedBox(height: 8),
                Pill(item.category, color: color),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class CoinRow extends StatelessWidget {
  const CoinRow({super.key, required this.coin, required this.onTap});
  final Coin coin;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final watched = app.watchlist.contains(coin.symbol);
    final c = coin.change >= 0 ? AppColors.up : AppColors.down;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            CoinAvatar(symbol: coin.symbol, color: coin.color),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(coin.symbol, style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text(coin.name, style: AppText.muted),
                ],
              ),
            ),
            SizedBox(width: 60, height: 28, child: Sparkline(values: coin.spark, color: c)),
            const SizedBox(width: 12),
            SizedBox(
              width: 86,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(fmtPrice(coin.price), style: AppText.figure.copyWith(fontSize: 14)),
                  ChangeText(coin.change, size: 12),
                ],
              ),
            ),
            IconButton(
              visualDensity: VisualDensity.compact,
              onPressed: () => app.toggleWatch(coin.symbol),
              icon: Icon(watched ? Icons.star_rounded : Icons.star_outline_rounded,
                  color: watched ? AppColors.gold : AppColors.faint),
            ),
          ],
        ),
      ),
    );
  }
}
