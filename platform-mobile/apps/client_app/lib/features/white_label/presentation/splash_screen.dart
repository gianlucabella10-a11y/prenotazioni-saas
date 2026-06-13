import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers.dart';
import '../../../core/utils/error_messages.dart';

/// First screen: loads the white label config. The router moves on as soon
/// as config+session resolve; this screen owns loading and failure-with-
/// retry presentation (an app that cannot reach its config and has no cache
/// cannot do anything else).
class SplashScreen extends ConsumerWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final configAsync = ref.watch(whiteLabelConfigProvider);

    return Scaffold(
      body: Center(
        child: configAsync.when(
          data: (config) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                config.appName,
                style: Theme.of(context).textTheme.headlineMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              const CircularProgressIndicator(),
            ],
          ),
          loading: () => const CircularProgressIndicator(),
          error: (error, _) => Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.cloud_off, size: 48),
                const SizedBox(height: 16),
                Text(
                  ErrorMessages.of(error),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: () =>
                      ref.read(whiteLabelConfigProvider.notifier).refresh(),
                  child: const Text('Riprova'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
