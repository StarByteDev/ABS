import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'api_config.dart';

class ApiClient {
  static const _tokenKey = 'pulse_token';
  static const _nameKey = 'pulse_user_name';
  static const _emailKey = 'pulse_user_email';

  Future<String?> token() async {
    final p = await SharedPreferences.getInstance();
    return p.getString(_tokenKey);
  }

  Future<void> saveToken(String token) async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_tokenKey, token);
  }

  Future<void> saveUser(Map<String, dynamic> user) async {
    final p = await SharedPreferences.getInstance();
    final name = user['name']?.toString();
    final email = user['email']?.toString();
    if (name != null && name.trim().isNotEmpty) await p.setString(_nameKey, name.trim());
    if (email != null && email.trim().isNotEmpty) await p.setString(_emailKey, email.trim());
  }

  Future<String> displayName() async {
    final p = await SharedPreferences.getInstance();
    final name = p.getString(_nameKey);
    if (name != null && name.trim().isNotEmpty && name.toLowerCase() != 'user') return name.trim();
    final email = p.getString(_emailKey);
    if (email != null && email.contains('@')) return email.split('@').first;
    return 'Trader';
  }

  Future<void> clearToken() async {
    final p = await SharedPreferences.getInstance();
    await p.remove(_tokenKey);
  }

  Future<Map<String, dynamic>> get(String path) async {
    final t = await token();
    final r = await http.get(ApiConfig.uri(path), headers: _headers(t));
    return _decode(r);
  }

  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) async {
    final t = await token();
    final r = await http.post(ApiConfig.uri(path), headers: _headers(t), body: jsonEncode(body));
    return _decode(r);
  }

  Map<String, String> _headers(String? token) => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
      };

  Map<String, dynamic> _decode(http.Response response) {
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        if (response.statusCode >= 200 && response.statusCode < 300) return decoded;
        return <String, dynamic>{
          ...decoded,
          'success': false,
          'message': decoded['message']?.toString() ?? 'Server returned status ${response.statusCode}',
        };
      }
      return {'success': false, 'message': 'Unexpected response format'};
    } catch (_) {
      return {
        'success': false,
        'message': response.body.isNotEmpty ? response.body : 'Server returned status ${response.statusCode}',
      };
    }
  }
}
