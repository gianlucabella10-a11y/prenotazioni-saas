import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/formats.dart';
import '../../booking/application/booking_flow_controller.dart';
import '../../booking/domain/appointment.dart';
import '../../white_label/domain/app_theme_builder.dart';

// ── Design tokens locali alla Home ──────────────────────────────────────────
// Ritmo verticale a base 4/8: ogni spazio è un multiplo, niente valori a caso.
const double _gapXs = 4;
const double _gapSm = 8;
const double _gapMd = 12;
const double _gapLg = 20;
const double _gapXl = 28;

/// Colonna di lettura confortevole: su tablet/foldable/telefoni grandi il
/// contenuto non si stira a tutta larghezza.
const double _contentMaxWidth = 480;

/// Il CTA è l'unica azione: touch target generoso, sopra i 48dp minimi.
const double _ctaHeight = 56;
const double _brandMarkSize = 64;

/// Testo attenuato derivato dal brand del tenant (non un grigio fisso), così
/// il rapporto di contrasto resta corretto per qualunque palette e in dark.
Color _muted(ColorScheme scheme, [double alpha = 0.62]) =>
    scheme.onSurface.withValues(alpha: alpha);

/// Superficie/segno neutro tenue, sempre derivato dal `onSurface` del tenant.
Color _tint(ColorScheme scheme, [double alpha = 0.06]) =>
    scheme.onSurface.withValues(alpha: alpha);

/// Layout variant "hero" attivata dal template (App Factory): header
/// brandizzato a tutta larghezza invece della sola identità compatta.
bool _isHeroLayout(String layout) => layout == 'hero_dark' || layout == 'gallery';

/// La Home: il volto del negozio. Dice una cosa sola — "prenotare è semplice" —
/// quindi guida con l'identità del negozio, un unico grande pulsante, e il
/// prossimo appuntamento. Niente compete con la CTA; nessun sapore di
/// dashboard o marketplace.
///
/// È stateful solo per la comparsa in apertura (una sequenza morbida a
/// cascata, rispettosa di "reduce motion"). La sezione "prossimo appuntamento"
/// è un [ConsumerWidget] isolato: i suoi stati di caricamento non ridisegnano
/// identità e CTA.
class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _entrance;
  bool _revealed = false;

  @override
  void initState() {
    super.initState();
    _entrance = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 520),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_revealed) {
      return;
    }
    _revealed = true;
    final reduceMotion = MediaQuery.maybeOf(context)?.disableAnimations ?? false;
    if (reduceMotion) {
      _entrance.value = 1;
    } else {
      _entrance.forward();
    }
  }

  @override
  void dispose() {
    _entrance.dispose();
    super.dispose();
  }

  void _startBooking() {
    HapticFeedback.selectionClick();
    ref.read(bookingFlowProvider.notifier).reset();
    context.push(Routes.bookingServices);
  }

  /// Comparsa a cascata (fade + micro-slide) di un blocco, guidata dall'unico
  /// controller. Comunica la gerarchia (prima l'identità, poi l'azione) in
  /// pochi centesimi; una sola primitiva, nessun effetto decorativo.
  Widget _reveal(double start, double end, Widget child) {
    final anim = CurvedAnimation(
      parent: _entrance,
      curve: Interval(start, end, curve: Curves.easeOutCubic),
    );
    return FadeTransition(
      opacity: anim,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 0.04),
          end: Offset.zero,
        ).animate(anim),
        child: child,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(whiteLabelConfigProvider).value;
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final content = config?.content;
    final isHero = config != null && _isHeroLayout(config.layout);

    final welcome = content?.welcomeMessage;
    final subtitle = content?.homeSubtitle;
    final emptyMessage =
        content?.emptyAppointments ?? 'Non hai appuntamenti in programma.';

    return Scaffold(
      // Chrome minimo: nessun titolo (l'identità sotto è l'eroe), nessuna
      // barra colorata pesante — solo l'accesso al profilo. Così la prima
      // cosa che l'occhio incontra è il negozio, non l'app.
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        foregroundColor: scheme.onSurface,
        actions: [
          IconButton(
            tooltip: 'Profilo',
            icon: const Icon(Icons.person_outline_rounded),
            onPressed: () => context.push(Routes.profile),
          ),
          const SizedBox(width: _gapXs),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(appointmentsProvider);
          await ref.read(whiteLabelConfigProvider.notifier).refresh();
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
          children: [
            Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: _contentMaxWidth),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // 1° — Identità del negozio, garantita in ogni layout.
                    if (config != null)
                      _reveal(
                        0,
                        0.55,
                        isHero
                            ? _HeroHeader(
                                key: const Key('home_hero_header'),
                                appName: config.appName,
                                tagline: config.tagline,
                                logoUrl: config.logoUrl,
                                heroImageUrl: config.content.heroImageUrl,
                              )
                            : _BrandIdentity(
                                appName: config.appName,
                                tagline: config.tagline,
                                logoUrl: config.logoUrl,
                              ),
                      ),

                    // Saluto del negozio (se configurato): presente ma lieve,
                    // non compete con l'identità né allontana la CTA.
                    if (welcome != null && welcome.isNotEmpty)
                      _reveal(
                        0.08,
                        0.62,
                        Padding(
                          padding: const EdgeInsets.only(top: _gapMd),
                          child: Column(
                            children: [
                              Text(
                                welcome,
                                style: theme.textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w500),
                                textAlign: TextAlign.center,
                              ),
                              if (subtitle != null && subtitle.isNotEmpty) ...[
                                const SizedBox(height: _gapXs),
                                Text(
                                  subtitle,
                                  style: theme.textTheme.bodySmall
                                      ?.copyWith(color: _muted(scheme)),
                                  textAlign: TextAlign.center,
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),

                    const SizedBox(height: _gapXl),

                    // 2° — L'unica azione della schermata.
                    _reveal(
                      0.16,
                      0.7,
                      FilledButton.icon(
                        onPressed: _startBooking,
                        icon: const Icon(Icons.event_available_rounded),
                        label: Text(content?.primaryCtaLabel ?? 'Prenota ora'),
                        style: FilledButton.styleFrom(
                          minimumSize: const Size.fromHeight(_ctaHeight),
                          textStyle: theme.textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w600),
                        ),
                      ),
                    ),

                    const SizedBox(height: _gapXl),

                    // 3° — Il prossimo appuntamento (isolato: vedi widget).
                    _reveal(
                      0.26,
                      0.82,
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _SectionLabel(
                            content?.homeTitle ?? 'Il tuo prossimo appuntamento',
                          ),
                          const SizedBox(height: _gapMd),
                          _NextAppointmentSection(emptyMessage: emptyMessage),
                        ],
                      ),
                    ),

                    const SizedBox(height: _gapLg),

                    // Coda — un solo accesso tranquillo alle info del negozio.
                    // "Le mie prenotazioni" non è più un link a sé: si apre
                    // toccando la card qui sopra (niente doppioni che diluiscono
                    // la CTA — blueprint §5).
                    _reveal(0.34, 0.9, const _InfoLink()),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Etichetta di sezione: peso e colore distinti dall'identità e dal corpo,
/// così la gerarchia non si appiattisce riusando lo stesso ruolo Material.
class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Semantics(
      header: true,
      child: Text(
        text,
        style: theme.textTheme.titleSmall?.copyWith(
          fontWeight: FontWeight.w600,
          color: _muted(theme.colorScheme, 0.7),
          letterSpacing: 0.2,
        ),
      ),
    );
  }
}

/// Identità compatta del negozio (layout non-hero): marchio (logo o
/// monogramma), nome curato, tagline sommessa. È l'elemento che deve
/// dominare — "questa è l'app del mio posto".
class _BrandIdentity extends StatelessWidget {
  const _BrandIdentity({
    required this.appName,
    this.tagline,
    this.logoUrl,
  });

  final String appName;
  final String? tagline;
  final String? logoUrl;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Column(
      children: [
        const SizedBox(height: _gapSm),
        _BrandMark(
          size: _brandMarkSize,
          label: appName,
          logoUrl: logoUrl,
          background: scheme.primary,
          foreground: scheme.onPrimary,
        ),
        const SizedBox(height: _gapMd),
        Semantics(
          header: true,
          child: Text(
            appName,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: -0.2,
            ),
            textAlign: TextAlign.center,
          ),
        ),
        if (tagline != null && tagline!.isNotEmpty) ...[
          const SizedBox(height: _gapXs),
          Text(
            tagline!,
            style: theme.textTheme.bodyMedium?.copyWith(color: _muted(scheme)),
            textAlign: TextAlign.center,
          ),
        ],
      ],
    );
  }
}

/// Header brandizzato delle skin "hero": marchio + nome + tagline su sfondo
/// primario (o immagine hero). Solo presentazione — nessun impatto sul motore
/// prenotazioni.
class _HeroHeader extends StatelessWidget {
  const _HeroHeader({
    super.key,
    required this.appName,
    this.tagline,
    this.logoUrl,
    this.heroImageUrl,
  });

  final String appName;
  final String? tagline;
  final String? logoUrl;

  /// Optional hero background image (Fase 5). Falls back to the solid brand
  /// primary when absent, so the header is always on-brand.
  final String? heroImageUrl;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final hasHeroImage = heroImageUrl != null && heroImageUrl!.isNotEmpty;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 30, horizontal: 20),
      decoration: BoxDecoration(
        color: scheme.primary,
        borderRadius: BorderRadius.circular(24),
        image: hasHeroImage
            ? DecorationImage(
                image: NetworkImage(heroImageUrl!),
                fit: BoxFit.cover,
                // Scurisce l'immagine così il testo su primario resta leggibile.
                colorFilter: ColorFilter.mode(
                  Colors.black.withValues(alpha: 0.35),
                  BlendMode.darken,
                ),
              )
            : null,
      ),
      child: Column(
        children: [
          _BrandMark(
            size: 72,
            label: appName,
            logoUrl: logoUrl,
            background: scheme.onPrimary.withValues(alpha: 0.18),
            foreground: scheme.onPrimary,
          ),
          const SizedBox(height: _gapMd),
          Text(
            appName,
            style: theme.textTheme.headlineSmall?.copyWith(
              color: scheme.onPrimary,
              fontWeight: FontWeight.w700,
              letterSpacing: -0.2,
            ),
            textAlign: TextAlign.center,
          ),
          if (tagline != null && tagline!.isNotEmpty) ...[
            const SizedBox(height: _gapXs),
            Text(
              tagline!,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: scheme.onPrimary.withValues(alpha: 0.85)),
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}

/// Il marchio: il logo del tenant quando c'è, altrimenti un monogramma con
/// l'iniziale del negozio. Il logo remoto sfuma sopra il monogramma e, in
/// caso di errore, degrada su di esso — così l'identità non è mai un buco né
/// appare di colpo. Decorativo per gli screen reader (il nome porta il
/// significato).
class _BrandMark extends StatelessWidget {
  const _BrandMark({
    required this.size,
    required this.label,
    required this.background,
    required this.foreground,
    this.logoUrl,
  });

  final double size;
  final String label;
  final Color background;
  final Color foreground;
  final String? logoUrl;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(size * 0.3);

    final monogram = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: background, borderRadius: radius),
      child: FittedBox(
        child: Padding(
          padding: EdgeInsets.all(size * 0.22),
          child: Text(
            _initial(label),
            style: TextStyle(
              color: foreground,
              fontWeight: FontWeight.w700,
              height: 1,
            ),
          ),
        ),
      ),
    );

    if (logoUrl == null || logoUrl!.isEmpty) {
      return ExcludeSemantics(child: monogram);
    }

    return ExcludeSemantics(
      child: SizedBox(
        width: size,
        height: size,
        child: ClipRRect(
          borderRadius: radius,
          child: Stack(
            fit: StackFit.expand,
            children: [
              monogram,
              Image.network(
                logoUrl!,
                fit: BoxFit.cover,
                // Fade-in quando i byte arrivano; su errore resta il monogramma.
                frameBuilder: (context, child, frame, wasSynchronouslyLoaded) {
                  if (wasSynchronouslyLoaded) {
                    return child;
                  }
                  return AnimatedOpacity(
                    opacity: frame == null ? 0 : 1,
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.easeOut,
                    child: child,
                  );
                },
                errorBuilder: (_, _, _) => const SizedBox.shrink(),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _initial(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? '?' : trimmed.substring(0, 1).toUpperCase();
  }
}

/// The "next appointment" block, isolated as its own consumer so its
/// loading/data/error transitions never rebuild the identity or the CTA.
class _NextAppointmentSection extends ConsumerWidget {
  const _NextAppointmentSection({required this.emptyMessage});

  final String emptyMessage;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final upcoming = ref.watch(appointmentsProvider(true));

    return upcoming.when(
      // When there IS a next appointment, tapping its card is the single path
      // to the bookings list (no redundant link — blueprint §5). When there
      // is none, that path is absent, so a lone quiet link keeps past
      // bookings reachable without competing with the CTA.
      data: (appointments) => appointments.isEmpty
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _EmptyNextAppointment(message: emptyMessage),
                const SizedBox(height: _gapSm),
                Center(
                  child: TextButton(
                    onPressed: () => context.push(Routes.appointments),
                    child: const Text('Le mie prenotazioni'),
                  ),
                ),
              ],
            )
          : _NextAppointmentCard(appointment: appointments.first),
      loading: () => const _NextAppointmentSkeleton(),
      error: (_, _) => Card(
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
        child: ListTile(
          leading: const Icon(Icons.cloud_off_rounded),
          title: const Text('Non riusciamo a caricare l\'appuntamento'),
          trailing: TextButton(
            onPressed: () => ref.invalidate(appointmentsProvider),
            child: const Text('Riprova'),
          ),
        ),
      ),
    );
  }
}

/// The next appointment at a glance: a calendar-style date badge anchors the
/// eye, then service, a relative time ("Domani, 12:00"), and only the metadata
/// that exists. Tapping opens the full bookings list — the single, non-
/// redundant path to "my bookings".
class _NextAppointmentCard extends StatelessWidget {
  const _NextAppointmentCard({required this.appointment});

  final Appointment appointment;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final meta = _metaLine(appointment);
    final pending = appointment.isPendingApproval;

    return Card(
      clipBehavior: Clip.antiAlias,
      margin: EdgeInsets.zero,
      child: InkWell(
        onTap: () => context.push(Routes.appointments),
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
                    const SizedBox(height: 3),
                    Text(
                      _relativeDateTime(appointment.startsAtLocal),
                      style: theme.textTheme.bodyMedium,
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
                  ],
                ),
              ),
              const SizedBox(width: _gapSm),
              if (pending)
                const _PendingChip()
              else
                ExcludeSemantics(
                  child: Icon(Icons.chevron_right_rounded, color: _muted(scheme)),
                ),
            ],
          ),
        ),
      ),
    );
  }

  /// Joins only the present segments — a missing operator leaves no dangling
  /// separator.
  static String _metaLine(Appointment appointment) {
    final staff = appointment.staffName;
    return [
      if (staff != null && staff.isNotEmpty) staff,
      if (appointment.locationName.isNotEmpty) appointment.locationName,
    ].join(' · ');
  }

  /// "Oggi, 12:00" / "Domani, 12:00" / "ven 26 giu, 12:00" — scannable at a
  /// glance; the full form lives in the detail/confirmation elsewhere.
  static String _relativeDateTime(DateTime local) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final day = DateTime(local.year, local.month, local.day);
    final diff = day.difference(today).inDays;
    final time = Formats.time(local);

    return switch (diff) {
      0 => 'Oggi, $time',
      1 => 'Domani, $time',
      _ => '${Formats.shortDate(local)}, $time',
    };
  }
}

/// Calendar-style anchor: weekday over day number, in a neutral tile derived
/// from the tenant surface (guaranteed contrast, no dependency on the brand
/// hue). [FittedBox] keeps it intact under large Dynamic Type.
class _DateBadge extends StatelessWidget {
  const _DateBadge(this.date);

  final DateTime date;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return ExcludeSemantics(
      child: Container(
        width: 52,
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
                _weekdayShort(date),
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
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                  height: 1,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _weekdayShort(DateTime local) =>
      DateFormat('EEE', 'it_IT').format(local).toUpperCase();
}

/// A calm, warm empty state: turns "no appointments" into a gentle nudge
/// toward the single CTA already above, without adding a competing button.
class _EmptyNextAppointment extends StatelessWidget {
  const _EmptyNextAppointment({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 26),
        child: Column(
          children: [
            Icon(
              Icons.event_available_outlined,
              size: 40,
              color: scheme.primary.withValues(alpha: 0.55),
            ),
            const SizedBox(height: _gapMd),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: _gapXs),
            Text(
              'Prenota dal pulsante qui sopra, quando vuoi.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(color: _muted(scheme)),
            ),
          ],
        ),
      ),
    );
  }
}

/// Placeholder that reserves the card's shape while the appointment loads —
/// a fade-through, not a spinner, so the layout never jumps when data lands.
class _NextAppointmentSkeleton extends StatefulWidget {
  const _NextAppointmentSkeleton();

  @override
  State<_NextAppointmentSkeleton> createState() =>
      _NextAppointmentSkeletonState();
}

class _NextAppointmentSkeletonState extends State<_NextAppointmentSkeleton>
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

    return Card(
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
                  width: 52,
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
                      _SkeletonBar(width: 150, height: 15, color: color),
                      const SizedBox(height: 10),
                      _SkeletonBar(width: 210, height: 12, color: color),
                      const SizedBox(height: 6),
                      _SkeletonBar(width: 120, height: 12, color: color),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
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

/// The "awaiting approval" state, given the theme's warning weight so it reads
/// as "not guaranteed yet" at a glance instead of a neutral grey chip.
class _PendingChip extends StatelessWidget {
  const _PendingChip();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final warning =
        theme.extension<BrandColors>()?.warning ?? theme.colorScheme.tertiary;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: warning.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        'In attesa',
        style: theme.textTheme.labelMedium?.copyWith(
          color: warning,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

/// The one quiet access at the foot of the hub — the shop's own info. It never
/// competes with the CTA: no fill, no colour, just a clear row.
class _InfoLink extends StatelessWidget {
  const _InfoLink();

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: EdgeInsets.zero,
      child: ListTile(
        leading: const Icon(Icons.storefront_outlined),
        title: const Text('Informazioni e contatti'),
        trailing: const ExcludeSemantics(child: Icon(Icons.chevron_right_rounded)),
        onTap: () => context.push(Routes.business),
      ),
    );
  }
}
