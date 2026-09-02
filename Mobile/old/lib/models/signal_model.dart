class SignalModel {
  final int id;
  final String createdAt;
  final String symbol;
  final String strategy;
  final String reason;
  final String side;
  final String entryPrice;
  final String stopLoss;
  final String takeProfit;
  final int confidence;
  final String status;

  SignalModel({
    required this.id,
    required this.createdAt,
    required this.symbol,
    required this.strategy,
    required this.reason,
    required this.side,
    required this.entryPrice,
    required this.stopLoss,
    required this.takeProfit,
    required this.confidence,
    required this.status,
  });

  factory SignalModel.fromJson(Map<String, dynamic> json) {
    return SignalModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      createdAt: json['created_at']?.toString() ?? '',
      symbol: json['symbol']?.toString() ?? '',
      strategy: json['strategy']?.toString() ?? '',
      reason: json['reason']?.toString() ?? '',
      side: json['side']?.toString() ?? '',
      entryPrice: json['entry_price']?.toString() ?? '',
      stopLoss: json['stop_loss']?.toString() ?? '',
      takeProfit: json['take_profit']?.toString() ?? '',
      confidence: int.tryParse(json['confidence'].toString()) ?? 0,
      status: json['status']?.toString() ?? '',
    );
  }
}
