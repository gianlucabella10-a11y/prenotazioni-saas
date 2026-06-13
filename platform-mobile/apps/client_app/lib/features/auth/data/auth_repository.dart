import '../../../core/network/api_client.dart';
import '../../../core/storage/token_storage.dart';

/// Authenticated user as returned by the auth endpoints.
class SessionUser {
  const SessionUser({
    required this.uuid,
    required this.email,
    required this.emailVerified,
  });

  final String uuid;
  final String email;
  final bool emailVerified;

  factory SessionUser.fromJson(Map<String, dynamic> json) => SessionUser(
        uuid: json['uuid'] as String,
        email: json['email'] as String? ?? '',
        emailVerified: json['email_verified'] == true,
      );
}

/// Customer authentication against the platform API (docs/26 + Fase 1):
/// registration yields an UNVERIFIED session — identity-bound features
/// unlock only after the email ownership is proven with the 6-digit code.
class AuthRepository {
  AuthRepository(this._api, this._tokens);

  final ApiClient _api;
  final TokenStorage _tokens;

  Future<SessionUser> register({
    required String email,
    required String password,
    required String firstName,
    required bool privacyAccepted,
    String? privacyVersion,
    String? lastName,
    String? phone,
  }) async {
    final data = await _api.postJson('/auth/register', body: {
      'email': email,
      'password': password,
      'first_name': firstName,
      'privacy_accepted': privacyAccepted,
      'privacy_version': ?privacyVersion,
      if (lastName != null && lastName.isNotEmpty) 'last_name': lastName,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
    });

    return _persistSession(data);
  }

  Future<SessionUser> login({
    required String email,
    required String password,
  }) async {
    final data = await _api.postJson('/auth/login', body: {
      'email': email,
      'password': password,
    });

    return _persistSession(data);
  }

  /// Submits the 6-digit verification code (Fase 1).
  Future<void> verifyEmail(String code) async {
    await _api.postJson('/auth/email/verify', body: {'code': code});
  }

  /// Requests a fresh code, invalidating previous ones.
  Future<void> resendVerificationCode() async {
    await _api.postJson('/auth/email/resend');
  }

  /// Best-effort server-side revocation; local state is cleared regardless.
  Future<void> logout() async {
    final stored = await _tokens.read();

    if (stored != null) {
      try {
        await _api.postJson('/auth/logout', body: {
          'refresh_token': stored.refreshToken,
        });
      } catch (_) {
        // Offline logout stays a local logout; the refresh token will
        // expire server-side (docs/26 §3).
      }
    }

    await _tokens.clear();
  }

  Future<bool> hasSession() async => await _tokens.read() != null;

  Future<void> clearSession() => _tokens.clear();

  Future<SessionUser> _persistSession(Map<String, dynamic> data) async {
    await _tokens.write(StoredTokens(
      accessToken: data['access_token'] as String,
      refreshToken: data['refresh_token'] as String,
    ));

    return SessionUser.fromJson(data['user'] as Map<String, dynamic>);
  }
}
