import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_failure.dart';
import '../domain/white_label_config.dart';

/// Loads the runtime white label configuration with ETag revalidation and
/// an offline-safe local cache (docs/27 §2):
///
///  - first launch online: GET → cache JSON+ETag → render
///  - next launches: render from cache immediately is NOT done here (the
///    splash awaits the network result with the cache as fallback), keeping
///    a single source of truth for freshness
///  - offline / backend down: last cached config keeps the app usable in
///    read-only terms
class WhiteLabelRepository {
  WhiteLabelRepository(this._api, this._preferences);

  static const _cacheKey = 'white_label.config';
  static const _etagKey = 'white_label.etag';

  final ApiClient _api;
  final SharedPreferencesAsync _preferences;

  Future<WhiteLabelConfig> load() async {
    final cachedEtag = await _preferences.getString(_etagKey);

    try {
      final response = await _api.getRaw(
        '/app/config',
        headers: cachedEtag == null ? null : {'If-None-Match': cachedEtag},
      );

      if (response.statusCode == 304) {
        final cached = await _readCache();

        if (cached != null) {
          return cached;
        }

        // Cache evaporated but the server says "unchanged": refetch clean.
        return _fetchFresh();
      }

      return _store(response.data ?? const {}, response.headers.value('etag'));
    } on ApiFailure catch (failure) {
      if (failure.isNetwork) {
        final cached = await _readCache();

        if (cached != null) {
          return cached;
        }
      }

      rethrow;
    }
  }

  Future<WhiteLabelConfig> _fetchFresh() async {
    final response = await _api.getRaw('/app/config');

    return _store(response.data ?? const {}, response.headers.value('etag'));
  }

  Future<WhiteLabelConfig> _store(
    Map<String, dynamic> json,
    String? etag,
  ) async {
    await _preferences.setString(_cacheKey, jsonEncode(json));

    if (etag != null) {
      await _preferences.setString(_etagKey, etag);
    }

    return WhiteLabelConfig.fromJson(json);
  }

  Future<WhiteLabelConfig?> _readCache() async {
    final raw = await _preferences.getString(_cacheKey);

    if (raw == null) {
      return null;
    }

    try {
      return WhiteLabelConfig.fromJson(
        jsonDecode(raw) as Map<String, dynamic>,
      );
    } on FormatException {
      return null; // corrupt cache: treat as absent
    }
  }
}
