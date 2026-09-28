import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../widgets/common.dart';

class TemplateSplashScreen extends StatefulWidget {
  const TemplateSplashScreen({super.key});

  @override
  State<TemplateSplashScreen> createState() => _TemplateSplashScreenState();
}

class _TemplateSplashScreenState extends State<TemplateSplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..forward();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final curve = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeOutCubic,
    );
    return Scaffold(
      body: Center(
        child: FadeTransition(
          opacity: curve,
          child: ScaleTransition(
            scale: Tween<double>(begin: .92, end: 1).animate(curve),
            child: const Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                AbsLogo(size: 76),
                SizedBox(height: 22),
                BrandWordmark(size: 16),
                SizedBox(height: 8),
                Text(
                  'Pulse intelligence for digital markets',
                  style: TextStyle(color: AppColors.muted, fontSize: 13),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
