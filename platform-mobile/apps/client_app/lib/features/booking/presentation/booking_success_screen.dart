import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/calendar_link.dart';
import '../../../core/utils/formats.dart';
import '../../white_label/domain/app_theme_builder.dart';
import '../domain/appointment.dart';
import '../application/booking_flow_controller.dart';

/// Confirmation after a successful booking — the single expressive moment of
/// the app. The recap is read from the REAL persisted appointment returned by
/// the backend (never from local selections), so what the customer sees is
/// exactly what was saved.
///
/// It has one job, and two seconds to do it: make the customer feel *relief*
/// and understand *when* they are expected. So the screen leads with an
/// animated confirmation mark (with a single confirmation haptic), then a
/// recap whose visual hierarchy puts the date/time first, and closes with the
/// most valuable next step — putting the appointment in their own calendar.
class BookingSuccessScreen extends ConsumerStatefulWidget {
  const BookingSuccessScreen({super.key});

  @override
  ConsumerState<BookingSuccessScreen> createState() =>
      _BookingSuccessScreenState();
}

class _BookingSuccessScreenState extends ConsumerState<BookingSuccessScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _entrance;

  /// Guards the one-shot entrance + haptic so they never replay on rebuild.
  bool _revealed = false;

  @override
  void initState() {
    super.initState();
    _entrance = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 720),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    if (_revealed) {
      return;
    }

    final appointment = ref.read(bookingFlowProvider).confirmed;

    if (appointment == null) {
      // Nothing to celebrate: build() redirects home, don't kick the moment.
      return;
    }

    _revealed = true;

    // Honour "reduce motion": land on the final frame instead of animating.
    final reduceMotion = MediaQuery.maybeOf(context)?.disableAnimations ?? false;

    if (reduceMotion) {
      _entrance.value = 1;
    } else {
      _entrance.forward();
    }

    // One physical confirmation: a firmer tick when it's a done deal, a
    // lighter one when the request still awaits approval.
    if (appointment.isPendingApproval) {
      HapticFeedback.lightImpact();
    } else {
      HapticFeedback.mediumImpact();
    }
  }

  @override
  void dispose() {
    _entrance.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
    final brand = theme.extension<BrandColors>();

    // Semantic status colour: success for a done booking, warning-toned for a
    // request still pending — with a theme fallback so a missing extension can
    // never crash the screen.
    final accent = pending
        ? (brand?.warning ?? theme.colorScheme.tertiary)
        : (brand?.success ?? theme.colorScheme.primary);

    final appName = ref.watch(whiteLabelConfigProvider).value?.appName;

    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 460),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(24, 32, 24, 28),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _badge(accent, pending),
                          const SizedBox(height: 22),
                          _reveal(
                            start: 0.25,
                            end: 0.7,
                            child: _title(theme, pending),
                          ),
                          const SizedBox(height: 22),
                          _reveal(
                            start: 0.4,
                            end: 0.85,
                            child: _RecapCard(appointment: appointment),
                          ),
                          const SizedBox(height: 14),
                          _reveal(
                            start: 0.5,
                            end: 0.9,
                            child: _reassurance(theme, accent, pending),
                          ),
                          const SizedBox(height: 30),
                          _reveal(
                            start: 0.6,
                            end: 1,
                            child: _actions(appointment, appName, pending),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  // ── Pieces ────────────────────────────────────────────────────────────────

  /// The confirmation mark: a soft tinted disc that scales in with a gentle
  /// overshoot — the "moment". Decorative for screen readers (the title
  /// carries the meaning).
  Widget _badge(Color accent, bool pending) {
    final pop = CurvedAnimation(
      parent: _entrance,
      curve: const Interval(0, 0.55, curve: Curves.easeOutBack),
    );
    final fade = CurvedAnimation(
      parent: _entrance,
      curve: const Interval(0, 0.45, curve: Curves.easeOut),
    );

    return Center(
      child: FadeTransition(
        opacity: fade,
        child: ScaleTransition(
          scale: Tween<double>(begin: 0.6, end: 1).animate(pop),
          child: Container(
            width: 96,
            height: 96,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: ExcludeSemantics(
              child: Icon(
                pending
                    ? Icons.hourglass_bottom_rounded
                    : Icons.check_rounded,
                size: 52,
                color: accent,
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _title(ThemeData theme, bool pending) {
    return Semantics(
      header: true,
      child: Text(
        pending ? 'Richiesta inviata' : 'Prenotazione confermata',
        textAlign: TextAlign.center,
        style: theme.textTheme.headlineSmall?.copyWith(
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  /// A calm, always-present promise. The confirmed case used to say nothing;
  /// now both states close the trust loop.
  Widget _reassurance(ThemeData theme, Color accent, bool pending) {
    final text = pending
        ? 'Ti avviseremo appena l\'attività conferma la richiesta.'
        : 'Ti invieremo un promemoria prima dell\'appuntamento.';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            pending
                ? Icons.hourglass_empty_rounded
                : Icons.notifications_none_rounded,
            size: 20,
            color: accent,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _actions(Appointment appointment, String? appName, bool pending) {
    // Confirmed → the most valuable next step is owning the appointment in
    // one's own calendar; the app doesn't try to keep the customer inside.
    // Pending → adding an unconfirmed slot would be a false promise, so the
    // list is the primary action instead.
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (!pending) ...[
          FilledButton.icon(
            onPressed: () => _addToCalendar(appointment, appName),
            icon: const Icon(Icons.event_available_rounded),
            label: const Text('Aggiungi al calendario'),
          ),
          const SizedBox(height: 10),
          OutlinedButton(
            onPressed: () => _leave(Routes.appointments),
            child: const Text('Le mie prenotazioni'),
          ),
        ] else
          FilledButton(
            onPressed: () => _leave(Routes.appointments),
            child: const Text('Le mie prenotazioni'),
          ),
        const SizedBox(height: 4),
        TextButton(
          onPressed: () => _leave(Routes.home),
          child: const Text('Torna alla home'),
        ),
      ],
    );
  }

  // ── Behaviour ───────────────────────────────────────────────────────────

  /// Ends the flow and navigates away — the confirmation is a terminal step,
  /// so the booking state is always reset on the way out.
  void _leave(String route) {
    ref.read(bookingFlowProvider.notifier).reset();
    context.go(route);
  }

  Future<void> _addToCalendar(Appointment appointment, String? appName) async {
    final title = (appName == null || appName.isEmpty)
        ? appointment.servicesLabel
        : '${appointment.servicesLabel} · $appName';

    final uri = googleCalendarTemplateUri(
      title: title,
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

    // Unlike the silent-failure launchers elsewhere, a failed calendar hand-off
    // tells the customer instead of looking broken.
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Non è stato possibile aprire il calendario.'),
        ),
      );
    }
  }

  /// Fade + soft upward slide, staggered by [start]/[end] over the shared
  /// entrance controller.
  Widget _reveal({
    required double start,
    required double end,
    required Widget child,
  }) {
    final anim = CurvedAnimation(
      parent: _entrance,
      curve: Interval(start, end, curve: Curves.easeOut),
    );

    return FadeTransition(
      opacity: anim,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 0.06),
          end: Offset.zero,
        ).animate(anim),
        child: child,
      ),
    );
  }
}

/// The recap. Its whole point is hierarchy: the date/time — the one thing the
/// customer must remember — wins the eye; the service supports it; operator,
/// place and price are quiet metadata below.
class _RecapCard extends StatelessWidget {
  const _RecapCard({required this.appointment});

  final Appointment appointment;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    final day = _capitalize(Formats.weekdayDayMonth(appointment.startsAtLocal));
    final timeRange =
        Formats.timeRange(appointment.startsAtLocal, appointment.endsAtLocal);
    final duration = Formats.duration(appointment.plannedDuration.inMinutes);
    final staff = appointment.staffName;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // WHEN — the hero of the card.
            Text(
              day,
              style: theme.textTheme.titleSmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 2),
            Row(
              crossAxisAlignment: CrossAxisAlignment.baseline,
              textBaseline: TextBaseline.alphabetic,
              children: [
                Flexible(
                  child: Text(
                    timeRange,
                    style: theme.textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  '· $duration',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),

            // WHAT.
            Text(
              appointment.servicesLabel,
              style: theme.textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 12),

            if (staff != null && staff.isNotEmpty)
              _MetaRow(icon: Icons.person_outline_rounded, value: staff),
            if (appointment.locationName.isNotEmpty)
              _MetaRow(
                icon: Icons.place_outlined,
                value: appointment.locationName,
              ),

            Padding(
              padding: const EdgeInsets.symmetric(vertical: 14),
              child: Divider(height: 1, color: scheme.outlineVariant),
            ),

            // Total — clear, but subordinate to the WHEN.
            Row(
              children: [
                Text(
                  'Totale',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
                const Spacer(),
                Text(
                  Formats.price(
                    appointment.totalPriceCents,
                    appointment.currency,
                  ),
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  static String _capitalize(String value) => value.isEmpty
      ? value
      : '${value[0].toUpperCase()}${value.substring(1)}';
}

/// One quiet detail line: a leading icon and its value, sized for comfortable
/// reading and a 48dp-friendly rhythm.
class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.icon, required this.value});

  final IconData icon;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(icon, size: 20, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 12),
          Expanded(
            child: Text(value, style: theme.textTheme.bodyLarge),
          ),
        ],
      ),
    );
  }
}
