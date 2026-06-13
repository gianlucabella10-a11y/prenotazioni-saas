import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers.dart';

/// Courtesy screen for suspended/terminated tenants (docs/27 §7): the app
/// explains itself instead of failing, preserving the platform's reputation
/// with the end customers.
class TenantUnavailableScreen extends ConsumerWidget {
  const TenantUnavailableScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(whiteLabelConfigProvider).value;

    final isTerminated = config?.unavailableMessageKey == 'service_terminated';

    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.storefront_outlined, size: 56),
              const SizedBox(height: 16),
              Text(
                config?.appName ?? '',
                style: Theme.of(context).textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              Text(
                isTerminated
                    ? 'Questo servizio non è più attivo. Grazie per averci scelto.'
                    : 'Il servizio di prenotazione è momentaneamente non disponibile. Riprova più tardi.',
                textAlign: TextAlign.center,
              ),
              if (!isTerminated) ...[
                const SizedBox(height: 24),
                OutlinedButton(
                  onPressed: () =>
                      ref.read(whiteLabelConfigProvider.notifier).refresh(),
                  child: const Text('Riprova'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
