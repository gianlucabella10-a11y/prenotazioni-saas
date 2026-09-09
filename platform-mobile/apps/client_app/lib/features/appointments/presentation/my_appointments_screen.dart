import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/calendar_link.dart';
import '../../../core/utils/error_messages.dart';
import '../../../core/utils/formats.dart';
import '../../booking/application/booking_flow_controller.dart';
import '../../booking/domain/appointment.dart';
import '../../white_label/domain/app_theme_builder.dart';

// ── Design tokens locali ─────────────────────────────────────────────────────
const double _dateBadgeWidth = 52;
const double _cardGap = 12;

Color _muted(ColorScheme scheme, [double alpha = 0.62]) =>
    scheme.onSurface.withValues(alpha: alpha);

Color _tint(ColorScheme scheme, [double alpha = 0.06]) =>
    scheme.onSurface.withValues(alpha: alpha);

/// "Le tue prenotazioni": due schede — prossime e storico. Ogni riga è una
/// porta verso il proprio dettaglio (un foglio contestuale), da cui si
/// consulta e si gestisce. Nessuna funzione fuori dal dominio prenotazioni.
class MyAppointmentsScreen extends ConsumerWidget {
  const MyAppointmentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Le tue prenotazioni'),
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

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appointments = ref.watch(appointmentsProvider(upcoming));

    return appointments.when(
      data: (items) {
        if (items.isEmpty) {
          return _EmptyAppointments(upcoming: upcoming);
        }

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(appointmentsProvider),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            itemCount: items.length,
            itemBuilder: (context, index) => Padding(
              padding: const EdgeInsets.only(bottom: _cardGap),
              child: _AppointmentCard(
                appointment: items[index],
                upcoming: upcoming,
              ),
            ),
          ),
        );
      },
      loading: () => const _AppointmentsSkeleton(),
      error: (error, _) => _ErrorView(
        message: ErrorMessages.of(error),
        onRetry: () => ref.invalidate(appointmentsProvider),
      ),
    );
  }
}

/// One appointment at a glance: a calendar date badge anchors the eye, then
/// service, metadata and an always-present status badge. The whole row is
/// tappable — it opens the appointment's sheet (consult + act). Past and
/// cancelled rows are quietly de-emphasized: what is done weighs less.
class _AppointmentCard extends StatelessWidget {
  const _AppointmentCard({required this.appointment, required this.upcoming});

  final Appointment appointment;
  final bool upcoming;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final cancelled = appointment.status.startsWith('cancelled');
    final dimmed = !upcoming || cancelled;
    final meta = _metaLine(appointment);

    final card = Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _showAppointmentSheet(context, appointment, upcoming),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              _DateBadge(appointment.startsAtLocal),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      appointment.servicesLabel,
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w600),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (meta.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        meta,
                        style: theme.textTheme.bodySmall
                            ?.copyWith(color: _muted(scheme)),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    const SizedBox(height: 8),
                    _StatusBadge(appointment: appointment, upcoming: upcoming),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              ExcludeSemantics(
                child: Icon(Icons.chevron_right_rounded, color: _muted(scheme)),
              ),
            ],
          ),
        ),
      ),
    );

    return dimmed ? Opacity(opacity: 0.72, child: card) : card;
  }

  static String _metaLine(Appointment appointment) {
    final staff = appointment.staffName;
    return [
      if (staff != null && staff.isNotEmpty) staff,
      if (appointment.locationName.isNotEmpty) appointment.locationName,
    ].join(' · ');
  }
}

/// Calendar-style anchor (weekday over day) in a neutral tile derived from the
/// tenant surface — a fixed width so every row's left edge lines up. Kept
/// intact under large Dynamic Type via [FittedBox].
class _DateBadge extends StatelessWidget {
  const _DateBadge(this.date);

  final DateTime date;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return ExcludeSemantics(
      child: Container(
        width: _dateBadgeWidth,
        height: 58,
        decoration: BoxDecoration(
          color: _tint(scheme),
          borderRadius: BorderRadius.circular(14),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
        child: FittedBox(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                DateFormat('EEE', 'it_IT').format(date).toUpperCase(),
                style: theme.textTheme.labelSmall?.copyWith(
                  color: _muted(scheme, 0.7),
                  fontWeight: FontWeight.w600,
                  letterSpacing: 0.5,
                  height: 1,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '${date.day}',
                style: theme.textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w700, height: 1),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A single, always-present status pill so a confirmed booking reassures
/// rather than staying silent — with semantic colours for each state.
class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.appointment, required this.upcoming});

  final Appointment appointment;
  final bool upcoming;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final (label, color) = _resolve(theme);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: theme.textTheme.labelMedium
            ?.copyWith(color: color, fontWeight: FontWeight.w600),
      ),
    );
  }

  (String, Color) _resolve(ThemeData theme) {
    final scheme = theme.colorScheme;
    final brand = theme.extension<BrandColors>();

    if (appointment.status.startsWith('cancelled')) {
      return ('Annullata', scheme.error);
    }
    if (appointment.isPendingApproval) {
      return ('In attesa', brand?.warning ?? scheme.tertiary);
    }
    if (!upcoming) {
      return ('Completato', _muted(scheme, 0.7));
    }
    return ('Confermato', brand?.success ?? scheme.primary);
  }
}

/// Warm empty state. For the upcoming tab it turns the absence into an
/// invitation with the one in-domain action, "Prenota"; the past tab stays a
/// calm statement, no forced CTA.
class _EmptyAppointments extends ConsumerWidget {
  const _EmptyAppointments({required this.upcoming});

  final bool upcoming;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              upcoming ? Icons.event_available_outlined : Icons.history_rounded,
              size: 44,
              color: scheme.primary.withValues(alpha: 0.5),
            ),
            const SizedBox(height: 14),
            Text(
              upcoming
                  ? 'Non hai appuntamenti in programma.'
                  : 'Non hai appuntamenti passati.',
              textAlign: TextAlign.center,
              style: theme.textTheme.titleSmall
                  ?.copyWith(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 6),
            Text(
              upcoming
                  ? 'Prenota quando vuoi — al promemoria pensiamo noi.'
                  : 'Qui troverai lo storico dei tuoi appuntamenti.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(color: _muted(scheme)),
            ),
            if (upcoming) ...[
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: () {
                  HapticFeedback.selectionClick();
                  ref.read(bookingFlowProvider.notifier).reset();
                  context.push(Routes.bookingServices);
                },
                icon: const Icon(Icons.event_available_rounded),
                label: const Text('Prenota'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Skeleton di caricamento che ricalca la forma delle card — un fade-through,
/// non uno spinner, così la lista non sobbalza quando arrivano i dati.
class _AppointmentsSkeleton extends StatefulWidget {
  const _AppointmentsSkeleton();

  @override
  State<_AppointmentsSkeleton> createState() => _AppointmentsSkeletonState();
}

class _AppointmentsSkeletonState extends State<_AppointmentsSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final reduceMotion = MediaQuery.maybeOf(context)?.disableAnimations ?? false;
    if (!reduceMotion && !_pulse.isAnimating) {
      _pulse.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: List.generate(3, (_) {
        return Padding(
          padding: const EdgeInsets.only(bottom: _cardGap),
          child: Card(
            margin: EdgeInsets.zero,
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: AnimatedBuilder(
                animation: _pulse,
                builder: (context, _) {
                  final color = scheme.onSurface
                      .withValues(alpha: 0.05 + 0.05 * _pulse.value);
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: _dateBadgeWidth,
                        height: 58,
                        decoration: BoxDecoration(
                          color: color,
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SkeletonBar(width: 160, height: 15, color: color),
                            const SizedBox(height: 10),
                            _SkeletonBar(width: 210, height: 12, color: color),
                            const SizedBox(height: 10),
                            _SkeletonBar(width: 90, height: 20, color: color),
                          ],
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
          ),
        );
      }),
    );
  }
}

class _SkeletonBar extends StatelessWidget {
  const _SkeletonBar({
    required this.width,
    required this.height,
    required this.color,
  });

  final double width;
  final double height;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(6),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

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
            Icon(
              Icons.cloud_off_rounded,
              size: 40,
              color: _muted(Theme.of(context).colorScheme),
            ),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: onRetry,
              child: const Text('Riprova'),
            ),
          ],
        ),
      ),
    );
  }
}

void _showAppointmentSheet(
  BuildContext context,
  Appointment appointment,
  bool upcoming,
) {
  showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (_) =>
        _AppointmentSheet(appointment: appointment, upcoming: upcoming),
  );
}

/// The appointment's own surface: a calm summary (what, when, who, where, how
/// much, its status) plus the actions the domain allows — add to calendar,
/// and cancel. Not a routed screen: a light sheet that belongs to the list.
class _AppointmentSheet extends ConsumerWidget {
  const _AppointmentSheet({required this.appointment, required this.upcoming});

  final Appointment appointment;
  final bool upcoming;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final cancelled = appointment.status.startsWith('cancelled');
    final canCancel = upcoming && appointment.isCancellable;
    final canAddToCalendar =
        upcoming && !appointment.isPendingApproval && !cancelled;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    appointment.servicesLabel,
                    style: theme.textTheme.titleLarge
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                const SizedBox(width: 12),
                _StatusBadge(appointment: appointment, upcoming: upcoming),
              ],
            ),
            const SizedBox(height: 16),
            _SheetRow(
              icon: Icons.event_rounded,
              text: Formats.fullDateTime(appointment.startsAtLocal),
            ),
            if (appointment.staffName != null &&
                appointment.staffName!.isNotEmpty)
              _SheetRow(
                icon: Icons.person_outline_rounded,
                text: appointment.staffName!,
              ),
            if (appointment.locationName.isNotEmpty)
              _SheetRow(
                icon: Icons.place_outlined,
                text: appointment.locationName,
              ),
            _SheetRow(
              icon: Icons.payments_outlined,
              text: Formats.price(
                appointment.totalPriceCents,
                appointment.currency,
              ),
            ),
            if (canAddToCalendar || canCancel) ...[
              const SizedBox(height: 12),
              Divider(height: 1, color: scheme.outlineVariant),
              const SizedBox(height: 12),
            ],
            if (canAddToCalendar)
              FilledButton.icon(
                onPressed: () => _addToCalendar(context, ref),
                icon: const Icon(Icons.event_available_rounded),
                label: const Text('Aggiungi al calendario'),
              ),
            if (canCancel) ...[
              if (canAddToCalendar) const SizedBox(height: 8),
              TextButton.icon(
                onPressed: () => _confirmAndCancel(context, ref),
                icon: Icon(Icons.close_rounded, color: scheme.error),
                label: Text(
                  'Annulla prenotazione',
                  style: TextStyle(color: scheme.error),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _addToCalendar(BuildContext context, WidgetRef ref) async {
    final messenger = ScaffoldMessenger.of(context);
    final appName = ref.read(whiteLabelConfigProvider).value?.appName;

    final uri = googleCalendarTemplateUri(
      title: (appName == null || appName.isEmpty)
          ? appointment.servicesLabel
          : '${appointment.servicesLabel} · $appName',
      startUtc: appointment.startsAtUtc,
      endUtc: appointment.endsAtUtc,
      location: appointment.locationName,
    );

    var opened = false;
    try {
      opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      opened = false;
    }

    if (!opened) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Non è stato possibile aprire il calendario.'),
        ),
      );
    }
  }

  Future<void> _confirmAndCancel(BuildContext context, WidgetRef ref) async {
    final messenger = ScaffoldMessenger.of(context);
    final sheetNavigator = Navigator.of(context);

    // Destructive confirmation: the SAFE choice is the prominent default; the
    // cancel action is a quiet, error-toned text button.
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Annullare la prenotazione?'),
        content: Text(
          '${appointment.servicesLabel}\n'
          '${Formats.fullDateTime(appointment.startsAtLocal)}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(
              'Sì, annulla',
              style: TextStyle(color: Theme.of(dialogContext).colorScheme.error),
            ),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Mantieni'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await ref.read(bookingRepositoryProvider).cancel(appointment.uuid);
      ref.invalidate(appointmentsProvider);
      HapticFeedback.mediumImpact();
      sheetNavigator.pop();
      messenger.showSnackBar(
        const SnackBar(content: Text('Prenotazione annullata.')),
      );
    } catch (error) {
      messenger.showSnackBar(
        SnackBar(content: Text(ErrorMessages.of(error))),
      );
    }
  }
}

/// One detail line in the sheet: a leading icon and its value.
class _SheetRow extends StatelessWidget {
  const _SheetRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, size: 20, color: _muted(theme.colorScheme)),
          const SizedBox(width: 12),
          Expanded(child: Text(text, style: theme.textTheme.bodyLarge)),
        ],
      ),
    );
  }
}
