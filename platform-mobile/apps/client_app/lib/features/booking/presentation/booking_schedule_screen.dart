import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/utils/formats.dart';
import '../../catalog/domain/service.dart';
import '../application/booking_flow_controller.dart';
import '../domain/availability.dart';

/// Step 2 (SCREEN_ANALYSIS S6): horizontal day strip, staff selector,
/// slots grouped Morning/Afternoon, confirm.
class BookingScheduleScreen extends ConsumerStatefulWidget {
  const BookingScheduleScreen({super.key});

  @override
  ConsumerState<BookingScheduleScreen> createState() =>
      _BookingScheduleScreenState();
}

class _BookingScheduleScreenState
    extends ConsumerState<BookingScheduleScreen> {
  static const _daysShown = 30;

  @override
  void initState() {
    super.initState();

    // Defaults: first location, today, first capable staff member — chosen
    // once the frame is built so providers are readable.
    WidgetsBinding.instance.addPostFrameCallback((_) => _applyDefaults());
  }

  void _applyDefaults() {
    final flow = ref.read(bookingFlowProvider);
    final notifier = ref.read(bookingFlowProvider.notifier);

    if (flow.location == null) {
      final locations =
          ref.read(whiteLabelConfigProvider).value?.locations;

      if (locations != null && locations.isNotEmpty) {
        notifier.setLocation(locations.first);
      }
    }

    if (flow.selectedDay == null) {
      notifier.selectDay(Formats.dayKey(DateTime.now()));
    }

    if (flow.staffUuid == null) {
      final capable = _capableStaff(ref.read(staffProvider).value);

      if (capable.isNotEmpty) {
        notifier.selectStaff(capable.first.uuid);
      }
    }
  }

  List<StaffMember> _capableStaff(List<StaffMember>? all) {
    final flow = ref.read(bookingFlowProvider);

    return (all ?? const [])
        .where((m) => m.performsAll(flow.selectedServiceUuids))
        .toList();
  }

  Future<void> _submit() async {
    try {
      await ref.read(bookingFlowProvider.notifier).submit();

      if (mounted) {
        context.go(Routes.bookingSuccess);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ErrorMessages.of(error))),
        );
        // On slot_unavailable the controller cleared the slot: the watch on
        // availabilityProvider below refetches automatically.
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final flow = ref.watch(bookingFlowProvider);
    final staffAsync = ref.watch(staffProvider);
    final theme = Theme.of(context);

    final day = flow.selectedDay ?? Formats.dayKey(DateTime.now());

    final availability = ref.watch(
      availabilityProvider((day: day, staffUuid: flow.staffUuid)),
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Scegli data e orario')),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _DayStrip(
            selectedDay: day,
            daysShown: _daysShown,
            bookingWindowDays:
                flow.location?.bookingWindowDays ?? _daysShown,
            onSelect: (d) =>
                ref.read(bookingFlowProvider.notifier).selectDay(d),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
            child: Text('Con chi vuoi prenotare?',
                style: theme.textTheme.titleMedium),
          ),
          SizedBox(
            height: 48,
            child: staffAsync.when(
              data: (all) {
                final capable = _capableStaff(all);

                if (capable.isEmpty) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: Text(
                        'Nessun operatore disponibile per questi servizi.'),
                  );
                }

                return ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: capable.length,
                  separatorBuilder: (_, _) => const SizedBox(width: 8),
                  itemBuilder: (context, index) {
                    final member = capable[index];

                    return ChoiceChip(
                      label: Text(member.displayName),
                      selected: flow.staffUuid == member.uuid,
                      onSelected: (_) => ref
                          .read(bookingFlowProvider.notifier)
                          .selectStaff(member.uuid),
                    );
                  },
                );
              },
              loading: () =>
                  const Center(child: CircularProgressIndicator()),
              error: (error, _) => Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Text(ErrorMessages.of(error)),
              ),
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: availability.when(
              data: (result) {
                final slots = result.forDay(day);

                if (slots.isEmpty) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(32),
                      child: Text(
                        'Non ci sono orari disponibili per questo giorno.\n'
                        'Prova un altro giorno o un altro operatore.',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  );
                }

                final grouped = groupSlotsByDayPart(slots);

                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (grouped.morning.isNotEmpty) ...[
                      Text('Mattina', style: theme.textTheme.titleSmall),
                      const SizedBox(height: 8),
                      _SlotWrap(
                        slots: grouped.morning,
                        selected: flow.selectedSlot,
                        onSelect: (slot) => ref
                            .read(bookingFlowProvider.notifier)
                            .selectSlot(slot),
                      ),
                      const SizedBox(height: 16),
                    ],
                    if (grouped.afternoon.isNotEmpty) ...[
                      Text('Pomeriggio', style: theme.textTheme.titleSmall),
                      const SizedBox(height: 8),
                      _SlotWrap(
                        slots: grouped.afternoon,
                        selected: flow.selectedSlot,
                        onSelect: (slot) => ref
                            .read(bookingFlowProvider.notifier)
                            .selectSlot(slot),
                      ),
                    ],
                  ],
                );
              },
              loading: () =>
                  const Center(child: CircularProgressIndicator()),
              error: (error, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(ErrorMessages.of(error),
                          textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: () =>
                            ref.invalidate(availabilityProvider),
                        child: const Text('Riprova'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton(
                onPressed: flow.readyToSubmit ? _submit : null,
                child: flow.submitting
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(
                        flow.selectedSlot == null
                            ? 'Scegli un orario'
                            : 'Conferma per le '
                                '${Formats.time(flow.selectedSlot!.startsAtLocal)}',
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Horizontal strip of selectable days (today → booking window).
class _DayStrip extends StatelessWidget {
  const _DayStrip({
    required this.selectedDay,
    required this.daysShown,
    required this.bookingWindowDays,
    required this.onSelect,
  });

  final String selectedDay;
  final int daysShown;
  final int bookingWindowDays;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final today = DateTime.now();
    final count =
        bookingWindowDays < daysShown ? bookingWindowDays : daysShown;

    return SizedBox(
      height: 76,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        itemCount: count,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final date = today.add(Duration(days: index));
          final key = Formats.dayKey(date);
          final isSelected = key == selectedDay;
          final scheme = Theme.of(context).colorScheme;

          return GestureDetector(
            onTap: () => onSelect(key),
            child: Container(
              width: 64,
              decoration: BoxDecoration(
                color: isSelected ? scheme.secondary : scheme.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: scheme.outlineVariant),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    Formats.shortDate(date).split(' ').first,
                    style: TextStyle(
                      color: isSelected
                          ? scheme.onSecondary
                          : scheme.onSurface,
                    ),
                  ),
                  Text(
                    '${date.day}',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: isSelected
                          ? scheme.onSecondary
                          : scheme.onSurface,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _SlotWrap extends StatelessWidget {
  const _SlotWrap({
    required this.slots,
    required this.selected,
    required this.onSelect,
  });

  final List<AvailabilitySlot> slots;
  final AvailabilitySlot? selected;
  final ValueChanged<AvailabilitySlot> onSelect;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final slot in slots)
          ChoiceChip(
            label: Text(Formats.time(slot.startsAtLocal)),
            selected: selected?.startsAtUtc == slot.startsAtUtc,
            onSelected: (_) => onSelect(slot),
          ),
      ],
    );
  }
}
