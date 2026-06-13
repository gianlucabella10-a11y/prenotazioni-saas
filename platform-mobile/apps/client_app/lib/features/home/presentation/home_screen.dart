import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/formats.dart';
import '../../booking/application/booking_flow_controller.dart';

/// Home: brand header, primary CTA, next appointment, quick links.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(whiteLabelConfigProvider).value;
    final upcoming = ref.watch(appointmentsProvider(true));
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(config?.appName ?? ''),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_outline),
            onPressed: () => context.push(Routes.profile),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(appointmentsProvider);
          await ref.read(whiteLabelConfigProvider.notifier).refresh();
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (config?.tagline != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: Text(
                  config!.tagline!,
                  style: theme.textTheme.titleMedium,
                  textAlign: TextAlign.center,
                ),
              ),
            FilledButton.icon(
              icon: const Icon(Icons.event_available),
              label: const Text('Prenota ora'),
              onPressed: () {
                ref.read(bookingFlowProvider.notifier).reset();
                context.push(Routes.bookingServices);
              },
            ),
            const SizedBox(height: 24),
            Text('Il tuo prossimo appuntamento',
                style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            upcoming.when(
              data: (appointments) {
                if (appointments.isEmpty) {
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Text(
                        'Nessun appuntamento in programma.',
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  );
                }

                final next = appointments.first;

                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(16),
                    title: Text(next.servicesLabel),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        '${Formats.fullDateTime(next.startsAtLocal)}\n'
                        '${next.staffName ?? ''} · ${next.locationName}',
                      ),
                    ),
                    trailing: next.isPendingApproval
                        ? const Chip(label: Text('In attesa'))
                        : null,
                    onTap: () => context.push(Routes.appointments),
                  ),
                );
              },
              loading: () => const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (_, _) => Card(
                child: ListTile(
                  leading: const Icon(Icons.cloud_off),
                  title: const Text('Impossibile caricare gli appuntamenti'),
                  trailing: TextButton(
                    onPressed: () => ref.invalidate(appointmentsProvider),
                    child: const Text('Riprova'),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 24),
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.calendar_month_outlined),
                    title: const Text('Le mie prenotazioni'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push(Routes.appointments),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.storefront_outlined),
                    title: const Text('Informazioni e contatti'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push(Routes.business),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
