import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/session/session_controller.dart';
import '../features/white_label/domain/app_theme_builder.dart';
import '../features/white_label/domain/white_label_config.dart';
import 'providers.dart';
import 'router.dart';

/// Root widget: theme and title come from the tenant config — the visible
/// proof of the white label promise. Before the first config arrives the
/// curated fallback theme keeps the splash on-brand-neutral.
///
/// Also the single place that drives the push lifecycle (Fase 4): Firebase is
/// initialized once, and the FCM token is (un)registered on the backend as the
/// session becomes authenticated/guest. All guarded — a no-op without Firebase.
class ClientApp extends ConsumerStatefulWidget {
  const ClientApp({super.key});

  @override
  ConsumerState<ClientApp> createState() => _ClientAppState();
}

class _ClientAppState extends ConsumerState<ClientApp> {
  @override
  void initState() {
    super.initState();

    // Initialize push once; safe no-op if Firebase isn't configured.
    Future.microtask(() => ref.read(pushNotificationServiceProvider).init());
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(whiteLabelConfigProvider).value;

    // Register the device only for a verified, authenticated user; remove it
    // on logout/deletion (a new account never inherits the previous token).
    ref.listen(sessionControllerProvider, (previous, next) {
      final session = next.value;
      final push = ref.read(pushNotificationServiceProvider);

      if (session is AuthenticatedSession && session.verified) {
        push.registerForUser(locale: config?.localeDefault);
      } else if (session is GuestSession) {
        push.unregister();
      }
    });

    final theme = AppThemeBuilder.build(
      config?.theme ?? BrandTheme.fallback(),
      fontStyle: config?.fontStyle,
    );

    return MaterialApp.router(
      title: config?.appName ?? '',
      theme: theme,
      debugShowCheckedModeBanner: false,
      locale: const Locale('it'),
      supportedLocales: const [Locale('it'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: ref.watch(routerProvider),
    );
  }
}
