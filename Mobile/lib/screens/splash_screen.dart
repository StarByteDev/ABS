import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';

import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;
  late final Animation<double> _fade;
  final AudioPlayer _player = AudioPlayer();
  bool _played = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1600))..repeat(reverse: true);
    _scale = Tween<double>(begin: .94, end: 1.03).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));
    _fade = Tween<double>(begin: .55, end: 1).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));
    _playIntro();
  }

  Future<void> _playIntro() async {
    if (_played) return;
    _played = true;
    try {
      await _player.setReleaseMode(ReleaseMode.stop);
      await _player.play(AssetSource('audio/abs_pulse_intro.wav'), volume: 0.6);
    } catch (_) {}
  }

  @override
  void dispose() {
    _controller.dispose();
    _player.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.transparent,
        body: AbsBackground(
          child: Center(
            child: FadeTransition(
              opacity: _fade,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  ScaleTransition(
                    scale: _scale,
                    child: Container(
                      width: 128,
                      height: 128,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(colors: [Color(0xFF11172B), Color(0xFF0D1326)]),
                        borderRadius: BorderRadius.circular(36),
                        border: Border.all(color: AbsColors.gold.withValues(alpha: .28)),
                        boxShadow: const [
                          BoxShadow(color: Color(0x3300E0FF), blurRadius: 24, spreadRadius: 2),
                          BoxShadow(color: Color(0x44000000), blurRadius: 40, offset: Offset(0, 18)),
                        ],
                      ),
                      child: Image.asset('assets/brand/abs-logo-master.png'),
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text('ABS PULSE', style: TextStyle(fontWeight: FontWeight.w900, letterSpacing: 1.8, fontSize: 18)),
                  const SizedBox(height: 8),
                  const Text('Professional market intelligence', style: TextStyle(color: AbsColors.muted, fontSize: 12.5, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 24),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                    decoration: BoxDecoration(
                      color: AbsColors.panel2.withValues(alpha: .9),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: AbsColors.lineSoft),
                    ),
                    child: const Text('Preparing live market access', style: TextStyle(color: AbsColors.muted2, fontSize: 11, fontWeight: FontWeight.w700)),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
}

class MaintenanceScreen extends StatelessWidget {
  const MaintenanceScreen({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/brand/abs-logo-master.png', width: 96, height: 96),
                const SizedBox(height: 24),
                const Icon(Icons.construction_rounded, color: AbsColors.gold, size: 42),
                const SizedBox(height: 14),
                const Text('Mobile maintenance', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900)),
                const SizedBox(height: 10),
                Text(message, textAlign: TextAlign.center, style: const TextStyle(color: AbsColors.muted)),
                const SizedBox(height: 22),
                ElevatedButton.icon(
                  onPressed: () async {
                    try { await session.refreshBootstrap(); } catch (_) {}
                  },
                  icon: const Icon(Icons.refresh),
                  label: const Text('Check again'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class UpdateRequiredScreen extends StatelessWidget {
  const UpdateRequiredScreen({super.key, required this.requiredVersion});
  final String requiredVersion;

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/brand/abs-logo-master.png', width: 96, height: 96),
                const SizedBox(height: 22),
                const Icon(Icons.system_update_alt_rounded, color: AbsColors.gold, size: 44),
                const SizedBox(height: 14),
                const Text('ABS Pulse update required', textAlign: TextAlign.center, style: TextStyle(fontSize: 23, fontWeight: FontWeight.w900)),
                const SizedBox(height: 10),
                Text('This backend requires ABS Pulse $requiredVersion or later. Update the application before continuing.', textAlign: TextAlign.center, style: const TextStyle(color: AbsColors.muted)),
                const SizedBox(height: 20),
                OutlinedButton.icon(
                  onPressed: () async { try { await session.refreshBootstrap(); } catch (_) {} },
                  icon: const Icon(Icons.refresh),
                  label: const Text('Check again'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class BackendCompatibilityScreen extends StatelessWidget {
  const BackendCompatibilityScreen({super.key, required this.backendBuild});

  final String backendBuild;

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/brand/abs-logo-master.png', width: 96, height: 96),
                const SizedBox(height: 22),
                const Icon(Icons.hub_outlined, color: AbsColors.gold, size: 44),
                const SizedBox(height: 14),
                const Text(
                  'ABS backend update required',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 23, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 10),
                Text(
                  'This mobile release requires ABS backend 14.9.2 or later. The connected backend reports $backendBuild.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AbsColors.muted),
                ),
                const SizedBox(height: 20),
                OutlinedButton.icon(
                  onPressed: () async {
                    try {
                      await session.refreshBootstrap();
                    } catch (_) {}
                  },
                  icon: const Icon(Icons.refresh),
                  label: const Text('Check again'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
