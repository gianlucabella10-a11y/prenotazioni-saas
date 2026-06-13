import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

import 'app/app.dart';
import 'core/env/app_environment.dart';

/// Crash reporting DSN, injected per-environment by the build pipeline
/// (--dart-define=SENTRY_DSN=...). Empty in dev/test → Sentry disabled,
/// nothing leaves the device.
const String _sentryDsn = String.fromEnvironment('SENTRY_DSN');

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // A white label build without tenant identity is a packaging error:
  // fail loudly at startup, never at first API call.
  AppEnvironment.validate();

  await initializeDateFormatting('it_IT');

  if (_sentryDsn.isEmpty) {
    runApp(const ProviderScope(child: ClientApp()));

    return;
  }

  await SentryFlutter.init(
    (options) {
      options
        ..dsn = _sentryDsn
        ..environment = AppEnvironment.current.name
        ..tracesSampleRate = 0.2
        // Privacy: never attach request bodies or user PII automatically.
        ..sendDefaultPii = false;
    },
    appRunner: () => runApp(const ProviderScope(child: ClientApp())),
  );
}
