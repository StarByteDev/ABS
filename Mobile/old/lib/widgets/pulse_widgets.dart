import 'package:flutter/material.dart';
import '../core/brand.dart';

class PulseShell extends StatelessWidget {
  final Widget child;
  const PulseShell({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Brand.bg,
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF03131C), Brand.bg],
          ),
        ),
        child: SafeArea(child: child),
      ),
    );
  }
}

class PulseCard extends StatelessWidget {
  final Widget child;
  final EdgeInsets padding;
  const PulseCard({super.key, required this.child, this.padding = const EdgeInsets.all(18)});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Brand.panel.withOpacity(.95),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: Brand.border),
        boxShadow: const [BoxShadow(color: Colors.black45, blurRadius: 22, offset: Offset(0, 10))],
      ),
      child: child,
    );
  }
}

class PulseButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool secondary;
  const PulseButton({super.key, required this.text, this.onPressed, this.icon, this.secondary = false});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 54,
      child: ElevatedButton.icon(
        onPressed: onPressed,
        icon: icon == null ? const SizedBox.shrink() : Icon(icon, size: 19),
        label: Text(text, style: const TextStyle(fontWeight: FontWeight.w900, letterSpacing: .2)),
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: secondary ? Brand.panel2 : Brand.green,
          foregroundColor: secondary ? Brand.text : Colors.black,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: BorderSide(color: secondary ? Brand.border : Brand.green),
          ),
        ),
      ),
    );
  }
}

class PulseInput extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final bool obscure;
  final TextInputType keyboardType;
  const PulseInput({super.key, required this.controller, required this.label, this.obscure = false, this.keyboardType = TextInputType.text});

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      obscureText: obscure,
      keyboardType: keyboardType,
      style: const TextStyle(color: Brand.text, fontSize: 16),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: Brand.muted),
        filled: true,
        fillColor: const Color(0xFF04071A),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Brand.border)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Brand.green, width: 1.4)),
      ),
    );
  }
}

class StatTile extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  const StatTile({super.key, required this.label, required this.value, required this.icon});

  @override
  Widget build(BuildContext context) {
    return PulseCard(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(icon, color: Brand.green, size: 27),
        const SizedBox(height: 10),
        Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Brand.muted, fontSize: 13)),
        const SizedBox(height: 5),
        Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Brand.text, fontSize: 24, fontWeight: FontWeight.w900)),
      ]),
    );
  }
}

class AppTopBar extends StatelessWidget {
  final String title;
  final List<Widget> actions;
  final bool back;
  final bool logoOnly;
  const AppTopBar({super.key, required this.title, this.actions = const [], this.back = false, this.logoOnly = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 10, 10),
      decoration: const BoxDecoration(
        color: Color(0xFF03151B),
        border: Border(bottom: BorderSide(color: Brand.border)),
      ),
      child: Row(children: [
        if (back) IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.arrow_back, color: Brand.text)),
        Expanded(
          child: logoOnly
              ? Align(alignment: Alignment.centerLeft, child: Image.asset(Brand.logo, height: 48, fit: BoxFit.contain))
              : Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Brand.text, fontSize: 20, fontWeight: FontWeight.w800)),
        ),
        ...actions,
      ]),
    );
  }
}

class SectionTitle extends StatelessWidget {
  final String text;
  const SectionTitle(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Text(text, style: const TextStyle(color: Brand.text, fontSize: 19, fontWeight: FontWeight.w900));
}
