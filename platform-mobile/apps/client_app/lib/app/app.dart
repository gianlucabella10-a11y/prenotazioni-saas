import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/white_label/domain/app_theme_builder.dart';
import '../features/white_label/domain/white_label_config.dart';
import 'providers.dart';
import 'router.dart';

/// Root widget: theme and title come from the tenant config — the visible
/// proof of the white label promise. Before the first config arrives the
/// curated fallback theme keeps the splash on-brand-neutral.
class ClientApp extends ConsumerWidget {
  const ClientApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(whiteLabelConfigProvider).value;

    final theme = AppThemeBuilder.build(
      config?.theme ?? BrandTheme.fallback(),
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
