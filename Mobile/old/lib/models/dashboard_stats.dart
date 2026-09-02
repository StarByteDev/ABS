class DashboardStats {
  final int signals;
  final int active;
  final int trades;
  final double pnl;

  DashboardStats({
    required this.signals,
    required this.active,
    required this.trades,
    required this.pnl,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    return DashboardStats(
      signals: int.tryParse(json['signals'].toString()) ?? 0,
      active: int.tryParse(json['active'].toString()) ?? 0,
      trades: int.tryParse(json['trades'].toString()) ?? 0,
      pnl: double.tryParse(json['pnl'].toString()) ?? 0,
    );
  }

  factory DashboardStats.empty() {
    return DashboardStats(signals: 0, active: 0, trades: 0, pnl: 0);
  }
}
