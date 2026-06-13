import 'package:dio/dio.dart';

/// Normalized API error consumed by UI and controllers.
///
/// Mirrors the backend envelope `{"error": {"code", "message", "details"}}`
/// (docs/25 §1): `code` is stable and machine-readable, `message` is the
/// backend-localized text used as fallback copy.
class ApiFailure implements Exception {
  const ApiFailure({
    required this.code,
    required this.message,
    this.statusCode,
    this.details,
  });

  final String code;
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? details;

  /// Connectivity-level failure (no HTTP response at all).
  static const String networkCode = 'network_unreachable';

  /// Anything we could not map to the API envelope.
  static const String unexpectedCode = 'unexpected_error';

  bool get isNetwork => code == networkCode;

  bool get isUnauthenticated => statusCode == 401;

  /// Maps any [DioException] to a stable [ApiFailure].
  factory ApiFailure.fromDio(DioException exception) {
    final response = exception.response;

    if (response == null) {
      return const ApiFailure(
        code: networkCode,
        message: 'Connessione non disponibile.',
      );
    }

    final data = response.data;

    if (data is Map<String, dynamic>) {
      final error = data['error'];

      if (error is Map<String, dynamic> && error['code'] is String) {
        return ApiFailure(
          code: error['code'] as String,
          message: (error['message'] as String?) ?? 'Errore inatteso.',
          statusCode: response.statusCode,
          details: error['details'] is Map<String, dynamic>
              ? error['details'] as Map<String, dynamic>
              : null,
        );
      }
    }

    return ApiFailure(
      code: unexpectedCode,
      message: 'Errore inatteso (${response.statusCode}).',
      statusCode: response.statusCode,
    );
  }

  @override
  String toString() => 'ApiFailure($code, $statusCode): $message';
}
