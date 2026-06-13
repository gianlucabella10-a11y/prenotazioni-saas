/// Build-time environment of the white label shell (docs/27 §1).
///
/// The ONLY tenant-specific values compiled into a build are injected here
/// via --dart-define by the build pipeline: everything else (theme, texts,
/// catalog, features) arrives at runtime from `GET /app/config`.
///
/// Example local run against the dev backend:
///   flutter run \
///     --dart-define=ENV=development \
///     --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1 \
///     --dart-define=TENANT_KEY=`tenant api key`
library;

enum BuildEnvironment { development, staging, production }

abstract final class AppEnvironment {
  static const String _env = String.fromEnvironment(
    'ENV',
    defaultValue: 'development',
  );

  /// Base URL of the API, version prefix included.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000/api/v1',
  );

  /// Public tenant key compiled into the build (X-Tenant-Key header).
  static const String tenantKey = String.fromEnvironment('TENANT_KEY');

  static BuildEnvironment get current => switch (_env) {
        'production' => BuildEnvironment.production,
        'staging' => BuildEnvironment.staging,
        _ => BuildEnvironment.development,
      };

  static bool get isProduction => current == BuildEnvironment.production;

  /// Fails fast at startup when the shell is built without its tenant
  /// identity: a white label app without tenant key is a packaging error.
  static void validate() {
    if (tenantKey.isEmpty) {
      throw StateError(
        'TENANT_KEY is missing. White label builds must inject it via '
        '--dart-define (see build pipeline, docs/27 §3).',
      );
    }
  }
}
