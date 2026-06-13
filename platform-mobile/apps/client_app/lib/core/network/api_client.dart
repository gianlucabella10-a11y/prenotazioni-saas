import 'dart:async';

import 'package:dio/dio.dart';

import '../env/app_environment.dart';
import '../storage/token_storage.dart';
import 'api_failure.dart';

/// Signature invoked when the session is no longer recoverable (refresh
/// token rejected): the session layer logs the user out.
typedef SessionExpiredCallback = Future<void> Function();

/// Configured Dio client for the platform API.
///
/// Responsibilities (docs Flutter foundation):
///  - inject `X-Tenant-Key` on every call (white label identity)
///  - inject `Authorization: Bearer` when a session exists
///  - on 401 with a stored refresh token: rotate it (single-flight, so N
///    concurrent 401s trigger ONE refresh) and replay the request once
///  - normalize every error into [ApiFailure]
class ApiClient {
  ApiClient(
    this._tokenStorage, {
    String? baseUrl,
    Dio? dio,
    this.onSessionExpired,
  }) : _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = baseUrl ?? AppEnvironment.apiBaseUrl
      ..connectTimeout = const Duration(seconds: 10)
      ..receiveTimeout = const Duration(seconds: 20)
      ..headers['Accept'] = 'application/json';

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: _onRequest,
        onError: _onError,
      ),
    );
  }

  final Dio _dio;
  final TokenStorage _tokenStorage;
  final SessionExpiredCallback? onSessionExpired;

  /// In-flight refresh shared by concurrent 401s (single-flight).
  Completer<bool>? _refreshing;

  Dio get raw => _dio;

  Future<void> _onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    options.headers['X-Tenant-Key'] = AppEnvironment.tenantKey;

    final tokens = await _tokenStorage.read();

    if (tokens != null && options.headers['Authorization'] == null) {
      options.headers['Authorization'] = 'Bearer ${tokens.accessToken}';
    }

    handler.next(options);
  }

  Future<void> _onError(
    DioException exception,
    ErrorInterceptorHandler handler,
  ) async {
    final isAuthEndpoint =
        exception.requestOptions.path.startsWith('/auth/');

    if (exception.response?.statusCode == 401 && !isAuthEndpoint) {
      final refreshed = await _refreshSession();

      if (refreshed) {
        try {
          final response = await _retry(exception.requestOptions);

          return handler.resolve(response);
        } on DioException catch (retryError) {
          return handler.reject(retryError);
        }
      }
    }

    handler.reject(exception);
  }

  /// Rotates the refresh token once even under concurrent 401s.
  Future<bool> _refreshSession() async {
    final inFlight = _refreshing;

    if (inFlight != null) {
      return inFlight.future;
    }

    final completer = Completer<bool>();
    _refreshing = completer;

    try {
      final tokens = await _tokenStorage.read();

      if (tokens == null) {
        completer.complete(false);

        return completer.future;
      }

      // Bare client: the refresh call must not recurse into this interceptor.
      final bare = Dio(BaseOptions(
        baseUrl: _dio.options.baseUrl,
        headers: {'Accept': 'application/json'},
      ));

      final response = await bare.post<Map<String, dynamic>>(
        '/auth/refresh',
        data: {'refresh_token': tokens.refreshToken},
      );

      final data = response.data!;

      await _tokenStorage.write(StoredTokens(
        accessToken: data['access_token'] as String,
        refreshToken: data['refresh_token'] as String,
      ));

      completer.complete(true);
    } on DioException {
      // Refresh rejected (expired/reused): the session is over.
      await _tokenStorage.clear();
      await onSessionExpired?.call();
      completer.complete(false);
    } finally {
      _refreshing = null;
    }

    return completer.future;
  }

  Future<Response<dynamic>> _retry(RequestOptions original) async {
    final tokens = await _tokenStorage.read();

    final options = Options(
      method: original.method,
      headers: Map<String, dynamic>.from(original.headers)
        ..remove('Authorization'),
    );

    if (tokens != null) {
      options.headers!['Authorization'] = 'Bearer ${tokens.accessToken}';
    }

    return _dio.request<dynamic>(
      original.path,
      data: original.data,
      queryParameters: original.queryParameters,
      options: options,
    );
  }

  // ---- Typed helpers: every call surfaces ApiFailure, never DioException.

  Future<Map<String, dynamic>> getJson(
    String path, {
    Map<String, dynamic>? query,
    Map<String, String>? headers,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        path,
        queryParameters: query,
        options: headers == null ? null : Options(headers: headers),
      );

      return response.data ?? const {};
    } on DioException catch (e) {
      throw ApiFailure.fromDio(e);
    }
  }

  /// GET that exposes status code and headers (ETag revalidation).
  Future<Response<Map<String, dynamic>>> getRaw(
    String path, {
    Map<String, String>? headers,
  }) async {
    try {
      return await _dio.get<Map<String, dynamic>>(
        path,
        options: Options(
          headers: headers,
          // 304 is a valid outcome for conditional requests, not an error.
          validateStatus: (status) =>
              status != null && (status < 400 || status == 304),
        ),
      );
    } on DioException catch (e) {
      throw ApiFailure.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Object? body,
    Map<String, String>? headers,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: body,
        options: headers == null ? null : Options(headers: headers),
      );

      return response.data ?? const {};
    } on DioException catch (e) {
      throw ApiFailure.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> patchJson(String path, {Object? body}) async {
    try {
      final response =
          await _dio.patch<Map<String, dynamic>>(path, data: body);

      return response.data ?? const {};
    } on DioException catch (e) {
      throw ApiFailure.fromDio(e);
    }
  }
}
