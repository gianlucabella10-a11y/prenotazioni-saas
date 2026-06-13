import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/utils/formats.dart';
import '../../booking/domain/appointment.dart';

/// "Le mie prenotazioni" (SCREEN_ANALYSIS S10-S11): upcoming/past tabs,
/// cancel with confirmation honoring the backend cutoff errors.
class MyAppointmentsScreen extends ConsumerWidget {
  const MyAppointmentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Prenotazioni'),
          bottom: const TabBar(
            tabs: [Tab(text: 'In programma'), Tab(text: 'Passate')],
          ),
        ),
        body: const TabBarView(
          children: [
            _AppointmentsList(upcoming: true),
            _AppointmentsList(upcoming: false),
          ],
        ),
      ),
    );
  }
}

class _AppointmentsList extends ConsumerWidget {
  const _AppointmentsList({required this.upcoming});

  final bool upcoming;

  Future<void> _cancel(
    BuildContext context,
    WidgetRef ref,
    Appointment appointment,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Annullare la prenotazione?'),
        content: Text(
          '${appointment.servicesLabel}\n'
          '${Formats.fullDateTime(appointment.startsAtLocal)}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('No'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Sì, annulla'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await ref
          .read(bookingRepositoryProvider)
          .cancel(appointment.uuid);

      ref.invalidate(appointmentsProvider);
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ErrorMessages.of(error))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appointments = ref.watch(appointmentsProvider(upcoming));
    final theme = Theme.of(context);

    return appointments.when(
      data: (items) {
        if (items.isEmpty) {
          return Center(
            child: Text(
              upcoming
                  ? 'Non ci sono prenotazioni da visualizzare.'
                  : 'Nessuna prenotazione passata.',
              style: theme.textTheme.bodyMedium,
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(appointmentsProvider),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            itemBuilder: (context, index) {
              final appointment = items[index];

              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      Column(
                        children: [
                          Text(
                            Formats.shortDate(appointment.startsAtLocal),
                            style: theme.textTheme.labelMedium,
                          ),
                          Text(
                            Formats.time(appointment.startsAtLocal),
                            style: theme.textTheme.titleLarge,
                          ),
                        ],
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              appointment.servicesLabel,
                              style: theme.textTheme.titleSmall,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${appointment.staffName ?? ''} · '
                              '${appointment.locationName}',
                              style: theme.textTheme.bodySmall,
                            ),
                            if (appointment.isPendingApproval)
                              Padding(
                                padding: const EdgeInsets.only(top: 6),
                                child: Chip(
                                  label: const Text('In attesa di conferma'),
                                  visualDensity: VisualDensity.compact,
                                  labelStyle: theme.textTheme.labelSmall,
                                ),
                              ),
                            if (appointment.status.startsWith('cancelled'))
                              Padding(
                                padding: const EdgeInsets.only(top: 6),
                                child: Text(
                                  'Annullata',
                                  style: theme.textTheme.labelMedium
                                      ?.copyWith(
                                    color: theme.colorScheme.error,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                      if (upcoming && appointment.isCancellable)
                        IconButton(
                          icon: Icon(
                            Icons.close,
                            color: theme.colorScheme.error,
                          ),
                          tooltip: 'Annulla prenotazione',
                          onPressed: () =>
                              _cancel(context, ref, appointment),
                        ),
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(ErrorMessages.of(error), textAlign: TextAlign.center),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => ref.invalidate(appointmentsProvider),
                child: const Text('Riprova'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
