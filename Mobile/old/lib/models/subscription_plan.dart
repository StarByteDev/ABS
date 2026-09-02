class SubscriptionPlan {
  final int id;
  final String name;
  final String code;
  final String description;
  final double price;
  final int maxPairs;
  final int maxBatchSize;
  final int scanFrequencyMinutes;
  final int minConfidence;
  final List<String> features;

  SubscriptionPlan({
    required this.id,
    required this.name,
    required this.code,
    required this.description,
    required this.price,
    required this.maxPairs,
    required this.maxBatchSize,
    required this.scanFrequencyMinutes,
    required this.minConfidence,
    required this.features,
  });

  factory SubscriptionPlan.fromJson(Map<String, dynamic> json) {
    final rawFeatures = json['features'];
    final features = rawFeatures is List
        ? rawFeatures.map((e) => e.toString()).toList()
        : <String>[];

    return SubscriptionPlan(
      id: int.tryParse(json['id'].toString()) ?? 0,
      name: json['name']?.toString() ?? '',
      code: json['code']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      price: double.tryParse(json['price'].toString()) ?? 0,
      maxPairs: int.tryParse(json['max_pairs'].toString()) ?? 0,
      maxBatchSize: int.tryParse(json['max_batch_size'].toString()) ?? 0,
      scanFrequencyMinutes: int.tryParse(json['scan_frequency_minutes'].toString()) ?? 0,
      minConfidence: int.tryParse(json['min_confidence'].toString()) ?? 0,
      features: features,
    );
  }
}
