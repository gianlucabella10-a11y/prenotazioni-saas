import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/utils/formats.dart';
import '../application/booking_flow_controller.dart';

/// Confirmation after a successful booking: recap from the REAL persisted
/// appointment returned by the backend (not from local selections).
class BookingSuccessScreen extends ConsumerWidget {
  const BookingSuccessScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appointment = ref.watch(bookingFlowProvider).confirmed;
    final theme = Theme.of(context);

    if (appointment == null) {
      // Deep-linked here without a flow: nothing to show.
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => context.go(Routes.home),
      );

      return const Scaffold(body: SizedBox.shrink());
    }

    final pending = appointment.isPendingApproval;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const Spacer(),
              Icon(
                pending ? Icons.hourglass_top : Icons.check_circle,
                size: 72,
                color: pending
                    ? theme.colorScheme.secondary
                    : theme.colorScheme.primary,
              ),
              const SizedBox(height: 16),
              Text(
                pending ? 'Richiesta inviata' : 'Prenotazione confermata',
                style: theme.textTheme.headlineSmall,
              ),
              const SizedBox(height: 24),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(appointment.servicesLabel,
                          style: theme.textTheme.titleMedium),
                      const SizedBox(height: 8),
                      Text(Formats.fullDateTime(appointment.startsAtLocal)),
                      const SizedBox(height: 4),
                      Text(
                        '${appointment.staffName ?? ''} · '
                        '${appointment.locationName}',
                      ),
                      const SizedBox(height: 8),
                      Text(
                        Formats.price(
                          appointment.totalPriceCents,
                          appointment.currency,
                        ),
                        style: theme.textTheme.titleMedium,
                      ),
                    ],
                  ),
                ),
              ),
              if (pending) ...[
                const SizedBox(height: 12),
                Text(
                  'Riceverai una notifica quando la richiesta sarà confermata.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium,
                ),
              ],
              const Spacer(),
              FilledButton(
                onPressed: () {
                  ref.read(bookingFlowProvider.notifier).reset();
                  context.go(Routes.appointments);
                },
                child: const Text('Vedi le mie prenotazioni'),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () {
                  ref.read(bookingFlowProvider.notifier).reset();
                  context.go(Routes.home);
                },
                child: const Text('Torna alla home'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
