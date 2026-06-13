import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Session token pair as persisted on device.
class StoredTokens {
  const StoredTokens({required this.accessToken, required this.refreshToken});

  final String accessToken;
  final String refreshToken;
}

/// Abstraction over secure token persistence (docs/26 §3: secure storage on
/// mobile). Abstracted so controllers/tests never touch the plugin directly.
abstract interface class TokenStorage {
  Future<StoredTokens?> read();

  Future<void> write(StoredTokens tokens);

  Future<void> clear();
}

/// Keychain/Keystore-backed implementation.
class SecureTokenStorage implements TokenStorage {
  SecureTokenStorage([FlutterSecureStorage? storage])
      : _storage = storage ?? const FlutterSecureStorage();

  static const _accessKey = 'auth.access_token';
  static const _refreshKey = 'auth.refresh_token';

  final FlutterSecureStorage _storage;

  @override
  Future<StoredTokens?> read() async {
    final access = await _storage.read(key: _accessKey);
    final refresh = await _storage.read(key: _refreshKey);

    if (access == null || refresh == null) {
      return null;
    }

    return StoredTokens(accessToken: access, refreshToken: refresh);
  }

  @override
  Future<void> write(StoredTokens tokens) async {
    await _storage.write(key: _accessKey, value: tokens.accessToken);
    await _storage.write(key: _refreshKey, value: tokens.refreshToken);
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _accessKey);
    await _storage.delete(key: _refreshKey);
  }
}

/// In-memory implementation for tests and previews.
class InMemoryTokenStorage implements TokenStorage {
  StoredTokens? _tokens;

  @override
  Future<StoredTokens?> read() async => _tokens;

  @override
  Future<void> write(StoredTokens tokens) async => _tokens = tokens;

  @override
  Future<void> clear() async => _tokens = null;
}
