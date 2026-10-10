import 'package:app_settings/app_settings.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/theme.dart';
import '../notifications/notification_config.dart';
import '../notifications/notification_preferences.dart';
import '../notifications/notification_service.dart';
import '../widgets/abs_ui.dart';

/// ABS notification settings: the branded explainer, the OS permission state
/// and every category switch.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  bool _busy = false;

  NotificationService get _service => NotificationService.instance;

  Future<void> _enable() async {
    if (_busy) return;
    setState(() => _busy = true);
    final granted = await _service.requestPermission();
    if (!mounted) return;
    setState(() => _busy = false);
    if (!granted) {
      showSnack(
        context,
        'Notifications are off. You can turn them on in system settings.',
        error: true,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _service,
      builder: (context, _) {
        final prefs = _service.preferences;
        final config = _service.config;
        final granted = _service.permissionGranted;
        final groups = <String, List<NotificationCategory>>{};
        for (final category in NotificationCategory.values) {
          groups.putIfAbsent(category.group, () => []).add(category);
        }

        return AbsPage(
          title: 'Notifications',
          subtitle: 'Choose what Pulse tells you about',
          child: ListView(
            children: [
              if (!granted) ...[
                _ExplainerCard(busy: _busy, onEnable: _enable),
                const SizedBox(height: 18),
              ] else ...[
                AbsCard(
                  accent: AbsColors.green,
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.notifications_active_outlined,
                        color: AbsColors.green,
                        size: 19,
                      ),
                      const SizedBox(width: 11),
                      const Expanded(
                        child: Text(
                          'Notifications are enabled for this device.',
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
              ],
              if (!_service.available)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: AbsCard(
                    accent: AbsColors.gold,
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.cloud_off_rounded,
                          color: AbsColors.gold,
                          size: 18,
                        ),
                        const SizedBox(width: 11),
                        const Expanded(
                          child: Text(
                            'The notification service is unavailable right '
                            'now. Everything else in Pulse works normally.',
                            style: TextStyle(fontSize: 11, height: 1.4),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              if (prefs != null) ...[
                AbsCard(
                  padding: const EdgeInsets.fromLTRB(14, 6, 8, 6),
                  child: SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    value: prefs.master,
                    onChanged: granted
                        ? (value) => _service.setMaster(value)
                        : null,
                    title: const Text(
                      'Notifications',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: const Text(
                      'Master switch for every Pulse notification.',
                      style: TextStyle(color: AbsColors.muted, fontSize: 11),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                for (final entry in groups.entries) ...[
                  _GroupLabel(entry.key),
                  AbsCard(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 4,
                    ),
                    child: Column(
                      children: [
                        for (final category in entry.value)
                          _CategoryTile(
                            category: category,
                            preferences: prefs,
                            config: config,
                            enabled: granted && prefs.master,
                            onChanged: (value) =>
                                _service.setCategory(category, value),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              ],
              OutlinedButton.icon(
                onPressed: () => AppSettings.openAppSettings(
                  type: AppSettingsType.notification,
                ),
                icon: const Icon(Icons.settings_outlined, size: 18),
                label: const Text('Open system notification settings'),
              ),
              if (kDebugMode && _service.debugToken != null) ...[
                const SizedBox(height: 18),
                _DebugTokenCard(token: _service.debugToken!),
              ],
              const SizedBox(height: 26),
            ],
          ),
        );
      },
    );
  }
}

class _ExplainerCard extends StatelessWidget {
  const _ExplainerCard({required this.busy, required this.onEnable});

  final bool busy;
  final VoidCallback onEnable;

  @override
  Widget build(BuildContext context) => AbsCard(
    accent: AbsColors.cyan,
    gradient: const LinearGradient(
      colors: [Color(0xFF101A25), Color(0xFF0C121C)],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.all(9),
              decoration: BoxDecoration(
                color: AbsColors.cyan.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: AbsColors.cyan.withValues(alpha: .26),
                ),
              ),
              child: const Icon(
                Icons.notifications_none_rounded,
                color: AbsColors.cyanSoft,
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            const Expanded(
              child: Text(
                'Stay ahead of important market moves',
                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        const _ExplainerLine(
          icon: Icons.trending_up_rounded,
          text: 'Significant market movement alerts',
        ),
        const _ExplainerLine(
          icon: Icons.bolt_outlined,
          text: 'Qualified Pulse alerts',
        ),
        const _ExplainerLine(
          icon: Icons.event_note_outlined,
          text: 'Important economic events',
        ),
        const _ExplainerLine(
          icon: Icons.shield_outlined,
          text: 'Account and security updates',
        ),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: busy ? null : onEnable,
            icon: busy
                ? const SizedBox(
                    width: 15,
                    height: 15,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.notifications_active_outlined, size: 18),
            label: Text(busy ? 'Requesting...' : 'Enable Notifications'),
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'You can change any of this later. Pulse works normally without '
          'notifications.',
          style: TextStyle(
            color: AbsColors.muted2,
            fontSize: 10.5,
            height: 1.4,
          ),
        ),
      ],
    ),
  );
}

class _ExplainerLine extends StatelessWidget {
  const _ExplainerLine({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 7),
    child: Row(
      children: [
        Icon(icon, size: 15, color: AbsColors.cyanSoft),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(fontSize: 11.5, color: AbsColors.muted),
          ),
        ),
      ],
    ),
  );
}

class _GroupLabel extends StatelessWidget {
  const _GroupLabel(this.label);
  final String label;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(2, 0, 2, 9),
    child: Row(
      children: [
        Text(
          label,
          style: const TextStyle(
            color: AbsColors.muted2,
            fontSize: 9,
            fontWeight: FontWeight.w900,
            letterSpacing: .9,
          ),
        ),
        const SizedBox(width: 9),
        const Expanded(child: Divider(height: 1, color: AbsColors.lineSoft)),
      ],
    ),
  );
}

class _CategoryTile extends StatelessWidget {
  const _CategoryTile({
    required this.category,
    required this.preferences,
    required this.config,
    required this.enabled,
    required this.onChanged,
  });

  final NotificationCategory category;
  final NotificationPreferences preferences;
  final NotificationConfig config;
  final bool enabled;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    // A category switched off server-side is shown as unavailable rather than
    // silently ignoring the user's switch.
    final gatedOff = !category.allowedBy(config);
    final interactive = enabled && !category.mandatory && !gatedOff;
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      value: category.mandatory
          ? true
          : preferences.isEnabled(category, config),
      onChanged: interactive ? onChanged : null,
      title: Row(
        children: [
          Flexible(
            child: Text(
              category.label,
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 12.5,
              ),
            ),
          ),
          if (category.mandatory) ...[
            const SizedBox(width: 8),
            const Flexible(child: StatusChip('ALWAYS ON', good: true)),
          ],
        ],
      ),
      subtitle: Text(
        gatedOff && !category.mandatory
            ? '${category.description} Currently unavailable.'
            : category.description,
        style: const TextStyle(
          color: AbsColors.muted,
          fontSize: 10.5,
          height: 1.35,
        ),
      ),
    );
  }
}

/// Development-only helper so the FCM token can be pasted into the Firebase
/// console's "Send test message" dialog. Never built in release.
class _DebugTokenCard extends StatelessWidget {
  const _DebugTokenCard({required this.token});
  final String token;

  @override
  Widget build(BuildContext context) => AbsCard(
    padding: const EdgeInsets.all(14),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'DEBUG · FCM REGISTRATION TOKEN',
          style: TextStyle(
            color: AbsColors.muted2,
            fontSize: 9,
            fontWeight: FontWeight.w900,
            letterSpacing: .8,
          ),
        ),
        const SizedBox(height: 8),
        SelectableText(
          token,
          style: const TextStyle(fontSize: 10, height: 1.4),
        ),
        const SizedBox(height: 10),
        OutlinedButton.icon(
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: token));
            if (context.mounted) showSnack(context, 'FCM token copied.');
          },
          icon: const Icon(Icons.copy_rounded, size: 16),
          label: const Text('Copy token'),
        ),
      ],
    ),
  );
}
