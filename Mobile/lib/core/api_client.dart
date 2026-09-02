import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'app_config.dart';
import 'json_tools.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode = 0, this.body});

  final String message;
  final int statusCode;
  final dynamic body;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({String? baseUrl}) : baseUrl = (baseUrl ?? AppConfig.apiBaseUrl).replaceAll(RegExp(r'/+$'), '');

  final String baseUrl;
  String? token;

  Map<String, String> get _headers => <String, String>{
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null && token!.isNotEmpty) 'Authorization': 'Bearer $token',
      };

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _request('GET', path, query: query);

  Future<dynamic> post(String path, {Map<String, dynamic>? body, Map<String, dynamic>? query}) =>
      _request('POST', path, body: body, query: query);

  Future<dynamic> put(String path, {Map<String, dynamic>? body}) =>
      _request('PUT', path, body: body);

  Future<dynamic> patch(String path, {Map<String, dynamic>? body}) =>
      _request('PATCH', path, body: body);

  Future<dynamic> delete(String path) => _request('DELETE', path);

  Future<dynamic> multipartPost(
    String path, {
    required Map<String, String> fields,
    String? fileField,
    File? file,
  }) async {
    final request = http.MultipartRequest('POST', _uri(path));
    request.headers['Accept'] = 'application/json';
    if (token != null && token!.isNotEmpty) {
      request.headers['Authorization'] = 'Bearer $token';
    }
    request.fields.addAll(fields);
    if (fileField != null && file != null) {
      request.files.add(await http.MultipartFile.fromPath(fileField, file.path));
    }
    try {
      final streamed = await request.send().timeout(AppConfig.requestTimeout);
      final response = await http.Response.fromStream(streamed);
      return _decode(response);
    } on TimeoutException {
      throw const ApiException('ABS did not respond in time. Please try again.');
    } on SocketException {
      throw const ApiException('Unable to reach ABS. Check your internet connection.');
    }
  }

  Future<dynamic> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, dynamic>? query,
  }) async {
    final uri = _uri(path, query);
    try {
      late http.Response response;
      final encoded = body == null ? null : jsonEncode(body);
      switch (method) {
        case 'GET':
          response = await http.get(uri, headers: _headers).timeout(AppConfig.requestTimeout);
          break;
        case 'POST':
          response = await http.post(uri, headers: _headers, body: encoded).timeout(AppConfig.requestTimeout);
          break;
        case 'PUT':
          response = await http.put(uri, headers: _headers, body: encoded).timeout(AppConfig.requestTimeout);
          break;
        case 'PATCH':
          response = await http.patch(uri, headers: _headers, body: encoded).timeout(AppConfig.requestTimeout);
          break;
        case 'DELETE':
          response = await http.delete(uri, headers: _headers).timeout(AppConfig.requestTimeout);
          break;
        default:
          throw ApiException('Unsupported request method: $method');
      }
      return _decode(response);
    } on TimeoutException {
      throw const ApiException('ABS did not respond in time. Please try again.');
    } on SocketException {
      throw const ApiException('Unable to reach ABS. Check your internet connection.');
    }
  }

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final normalized = path.startsWith('/') ? path : '/$path';
    final uri = Uri.parse('$baseUrl$normalized');
    if (query == null || query.isEmpty) return uri;
    final flat = <String, String>{};
    query.forEach((key, value) {
      if (value == null) return;
      if (value is Iterable) {
        flat[key] = value.join(',');
      } else {
        flat[key] = value.toString();
      }
    });
    return uri.replace(queryParameters: flat);
  }

  dynamic _decode(http.Response response) {
    dynamic decoded;
    if (response.body.trim().isNotEmpty) {
      try {
        decoded = jsonDecode(response.body);
      } catch (_) {
        decoded = response.body;
      }
    }
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded ?? <String, dynamic>{};
    }
    final body = JsonTools.map(decoded);
    String message = JsonTools.text(body['message'], 'ABS request failed.');
    final errors = body['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) message = first.first.toString();
      if (first is String) message = first;
    }
    throw ApiException(message, statusCode: response.statusCode, body: decoded);
  }
}
