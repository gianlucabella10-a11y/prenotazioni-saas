import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/utils/formats.dart';
import '../../catalog/domain/service.dart';
import '../application/booking_flow_controller.dart';

/// Step 1: multi-select of services (SCREEN_ANALYSIS S5). Each card carries
/// the default variant; the info icon opens variant details. Services not
/// performable together with the current selection (no staff covers both)
/// are visually disabled.
class BookingServicesScreen extends ConsumerWidget {
  const BookingServicesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final services = ref.watch(servicesProvider);
    final staff = ref.watch(staffProvider);
    final flow = ref.watch(bookingFlowProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Nuova prenotazione')),
      body: services.when(
        data: (items) {
          if (items.isEmpty) {
            return const _EmptyState(
              message: 'Nessun servizio disponibile al momento.',
            );
          }

          final staffList = staff.value ?? const <StaffMember>[];

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  'Seleziona uno o più servizi',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              Expanded(
                child: ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: items.length,
                  itemBuilder: (context, index) {
                    final service = items[index];
                    final variant = service.defaultVariant;
                    final selected = flow.selectedVariantUuids
                        .contains(variant.uuid);

                    final compatible = _isCompatible(
                      service: service,
                      flow: flow,
                      staffList: staffList,
                      selected: selected,
                    );

                    return Card(
                      margin: const EdgeInsets.only(bottom: 10),
                      child: ListTile(
                        enabled: compatible,
                        onTap: compatible
                            ? () => ref
                                .read(bookingFlowProvider.notifier)
                                .toggleVariant(
                                  variantUuid: variant.uuid,
                                  serviceUuid: service.uuid,
                                )
                            : null,
                        leading: Icon(
                          selected
                              ? Icons.check_circle
                              : Icons.radio_button_unchecked,
                          color: selected
                              ? Theme.of(context).colorScheme.secondary
                              : null,
                        ),
                        title: Text(service.name),
                        subtitle: Text(
                          '${Formats.duration(variant.durationMinutes)} · '
                          '${Formats.price(variant.priceCents, variant.currency)}',
                        ),
                        trailing: service.description == null
                            ? null
                            : IconButton(
                                icon: const Icon(Icons.info_outline),
                                onPressed: () =>
                                    _showDetail(context, service),
                              ),
                      ),
                    );
                  },
                ),
              ),
              SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: FilledButton.icon(
                    icon: const Icon(Icons.arrow_forward),
                    label: const Text('Continua'),
                    onPressed: flow.hasSelection
                        ? () => context.push(Routes.bookingSchedule)
                        : null,
                  ),
                ),
              ),
            ],
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _ErrorState(
          message: ErrorMessages.of(error),
          onRetry: () => ref.invalidate(servicesProvider),
        ),
      ),
    );
  }

  /// A service is selectable when at least one staff member performs it
  /// together with everything already selected (chained visit, one
  /// operator — docs/30 §6).
  bool _isCompatible({
    required CatalogService service,
    required BookingFlowState flow,
    required List<StaffMember> staffList,
    required bool selected,
  }) {
    if (selected || !flow.hasSelection || staffList.isEmpty) {
      return true;
    }

    final wanted = {...flow.selectedServiceUuids, service.uuid};

    return staffList.any((member) => member.performsAll(wanted));
  }

  void _showDetail(BuildContext context, CatalogService service) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(service.name,
                style: Theme.of(context).textTheme.titleLarge),
            if (service.category != null) ...[
              const SizedBox(height: 4),
              Text(service.category!,
                  style: Theme.of(context).textTheme.labelMedium),
            ],
            const SizedBox(height: 12),
            Text(service.description ?? ''),
            const SizedBox(height: 16),
            for (final variant in service.variants)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(variant.name),
                trailing: Text(
                  '${Formats.duration(variant.durationMinutes)} · '
                  '${Formats.price(variant.priceCents, variant.currency)}',
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Text(message, textAlign: TextAlign.center),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 40),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            OutlinedButton(onPressed: onRetry, child: const Text('Riprova')),
          ],
        ),
      ),
    );
  }
}
