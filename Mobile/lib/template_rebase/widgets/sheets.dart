import 'package:flutter/material.dart';

import '../data/mock_data.dart';
import '../../screens/alerts_screen.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import 'charts.dart';
import 'common.dart';

void showCoinSheet(BuildContext context, Coin coin) {
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (_) => _CoinSheet(coin: coin),
  );
}

class _CoinSheet extends StatelessWidget {
  const _CoinSheet({required this.coin});
  final Coin coin;

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final watched = app.watchlist.contains(coin.symbol);
    final color = coin.change >= 0 ? AppColors.up : AppColors.down;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              CoinAvatar(symbol: coin.symbol, color: coin.color, size: 44),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(coin.pair,
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                  Text(coin.name, style: AppText.muted),
                ]),
              ),
              ChangeBadge(coin.change),
            ]),
            const SizedBox(height: 16),
            Text(fmtPrice(coin.price), style: AppText.figure.copyWith(fontSize: 30)),
            const SizedBox(height: 12),
            SizedBox(height: 130, child: InteractiveLineChart(values: coin.chart, color: color)),
            const SizedBox(height: 14),
            Row(children: [
              Expanded(child: MiniStat(label: '24h high', value: fmtPrice(coin.high))),
              Expanded(child: MiniStat(label: '24h low', value: fmtPrice(coin.low))),
              Expanded(child: MiniStat(label: '24h volume', value: fmtCompact(coin.volume))),
            ]),
            const SizedBox(height: 20),
            Row(children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => app.toggleWatch(coin.symbol),
                  icon: Icon(watched ? Icons.star_rounded : Icons.star_outline_rounded,
                      color: watched ? AppColors.gold : null),
                  label: Text(watched ? 'Watching' : 'Watch'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton.icon(
                  onPressed: () {
                    final nav = Navigator.of(context);
                    nav.pop();
                    nav.push(MaterialPageRoute(builder: (_) => const AlertsScreen()));
                  },
                  icon: const Icon(Icons.add_alert_outlined, size: 20),
                  label: const Text('Alert'),
                ),
              ),
            ]),
          ],
        ),
      ),
    );
  }
}
