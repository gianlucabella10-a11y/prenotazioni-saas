import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
    final summary = ref.watch(bookingSelectionSummaryProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Cosa prenoti?')),
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
              Expanded(
                child: ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
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

                    final theme = Theme.of(context);
                    final scheme = theme.colorScheme;
                    final hasDetail = service.description != null ||
                        service.variants.length > 1;

                    return Semantics(
                      enabled: compatible,
                      hint: compatible
                          ? null
                          : 'Non prenotabile insieme ai servizi già scelti',
                      child: Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        child: ListTile(
                          enabled: compatible,
                          onTap: compatible
                              ? () {
                                  HapticFeedback.selectionClick();
                                  ref
                                      .read(bookingFlowProvider.notifier)
                                      .toggleVariant(
                                        variantUuid: variant.uuid,
                                        serviceUuid: service.uuid,
                                      );
                                }
                              : null,
                          leading: Icon(
                            selected
                                ? Icons.check_circle
                                : Icons.circle_outlined,
                            color: selected
                                ? scheme.secondary
                                : scheme.onSurfaceVariant,
                          ),
                          title: Text(service.name),
                          subtitle: Text.rich(
                            TextSpan(
                              style: theme.textTheme.bodyMedium
                                  ?.copyWith(color: scheme.onSurfaceVariant),
                              children: [
                                TextSpan(
                                  text:
                                      Formats.duration(variant.durationMinutes),
                                ),
                                const TextSpan(text: '   ·   '),
                                TextSpan(
                                  text: Formats.price(
                                    variant.priceCents,
                                    variant.currency,
                                  ),
                                  style: TextStyle(
                                    color: scheme.onSurface,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          trailing: hasDetail
                              ? IconButton(
                                  icon: const Icon(Icons.info_outline),
                                  tooltip: 'Dettagli e varianti',
                                  onPressed: () => _showDetail(context, service),
                                )
                              : null,
                        ),
                      ),
                    );
                  },
                ),
              ),
              SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (!summary.isEmpty) ...[
                        _SelectionSummaryBar(summary: summary),
                        const SizedBox(height: 12),
                      ],
                      FilledButton.icon(
                        icon: const Icon(Icons.arrow_forward),
                        label: const Text('Continua'),
                        onPressed: flow.hasSelection
                            ? () => context.push(Routes.bookingSchedule)
                            : null,
                      ),
                    ],
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

/// Running total of the selection, read from the shared summary provider so
/// the customer always knows what — and how much — they've chosen.
class _SelectionSummaryBar extends StatelessWidget {
  const _SelectionSummaryBar({required this.summary});

  final BookingSelectionSummary summary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final services = summary.count == 1 ? 'servizio' : 'servizi';

    return Row(
      children: [
        Expanded(
          child: Text(
            '${summary.count} $services · '
            '${Formats.duration(summary.totalDurationMinutes)}',
            style: theme.textTheme.bodyMedium
                ?.copyWith(color: scheme.onSurfaceVariant),
          ),
        ),
        Text(
          Formats.price(summary.totalPriceCents, summary.currency),
          style: theme.textTheme.titleMedium
              ?.copyWith(fontWeight: FontWeight.w700),
        ),
      ],
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.event_note_outlined,
              size: 44,
              color: theme.colorScheme.primary.withValues(alpha: 0.5),
            ),
            const SizedBox(height: 14),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
          ],
        ),
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
