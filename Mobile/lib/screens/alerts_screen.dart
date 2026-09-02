import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});
  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> alerts = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && alerts.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      alerts = JsonTools.pageItems(await SessionScope.of(context).api.get('/pulse/alerts', query: {'per_page': 50}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _readAll() async {
    try {
      await SessionScope.of(context).api.patch('/pulse/alerts/read-all');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Pulse Alerts',
        subtitle: 'Trading, risk and system notices',
        actions: [TextButton(onPressed: _readAll, child: const Text('Read all'))],
        child: loading
            ? const LoadingBlock()
            : error != null
                ? ErrorBlock(message: error!, onRetry: _load)
                : ListView(
                    children: alerts.isEmpty
                        ? [const EmptyState(title: 'No alerts', message: 'Important Pulse notices will appear here.', icon: Icons.notifications_none)]
                        : alerts.map((a) {
                            final read = JsonTools.boolean(a['is_read']);
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(18),
                                onTap: read ? null : () => _read(a),
                                child: AbsCard(
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Icon(read ? Icons.notifications_none : Icons.notifications_active, color: read ? AbsColors.muted : AbsColors.gold),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(JsonTools.text(a['title'], JsonTools.text(a['type'], 'Pulse Alert')), style: const TextStyle(fontWeight: FontWeight.w800)),
                                            const SizedBox(height: 5),
                                            Text(JsonTools.text(a['message'] ?? a['body']), style: const TextStyle(color: AbsColors.muted)),
                                            const SizedBox(height: 5),
                                            Text(compactDate(a['created_at']), style: const TextStyle(color: AbsColors.muted, fontSize: 10)),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            );
                          }).toList(),
                  ),
      );

  Future<void> _read(Map<String, dynamic> alert) async {
    try {
      await SessionScope.of(context).api.patch('/pulse/alerts/${JsonTools.integer(alert['id'])}/read');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }
}
