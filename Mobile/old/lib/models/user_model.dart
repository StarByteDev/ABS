class UserModel {
  final int id;
  final String name;
  final String email;
  final String role;
  final String? referralCode;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.referralCode,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      role: json['role']?.toString() ?? 'user',
      referralCode: json['referral_code']?.toString(),
    );
  }
}
