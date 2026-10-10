import 'package:flutter/material.dart';

import '../../investor/investor_ui.dart';

/// Full-width consent target for the rewarded Free Signal flow.
///
/// CheckboxListTile wires both the checkbox AND the caption to the same
/// callback. No navigation/reward request is made by changing this setting.
class RewardedAdConsentTile extends StatelessWidget {
  const RewardedAdConsentTile({
    super.key,
    required this.value,
    required this.onChanged,
    this.enabled = true,
  });

  final bool value;
  final ValueChanged<bool> onChanged;
  final bool enabled;

  @override
  Widget build(BuildContext context) => Material(
        color: const Color(0xFF142539),
        borderRadius: BorderRadius.circular(12),
        child: CheckboxListTile(
          key: const Key('free-signal-consent'),
          value: value,
          onChanged: enabled ? (next) => onChanged(next ?? false) : null,
          controlAffinity: ListTileControlAffinity.leading,
          contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          checkboxShape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
          activeColor: invGold,
          checkColor: const Color(0xFF07111F),
          side: BorderSide(color: value ? invGold : const Color(0xFFCCD6E7), width: 1.8),
          title: const Text(
            'I agree to watch a rewarded ad for one free reveal.',
            style: TextStyle(color: Color(0xFFEBF1F9), fontSize: 13, height: 1.4),
          ),
        ),
      );
}
