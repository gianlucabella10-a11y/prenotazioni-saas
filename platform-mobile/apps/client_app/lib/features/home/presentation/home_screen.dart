import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/formats.dart';
import '../../booking/application/booking_flow_controller.dart';

/// Layout variant "hero" attivata dal template (App Factory): header
/// brandizzato a tutta larghezza invece della sola tagline.
bool _isHeroLayout(String layout) => layout == 'hero_dark' || layout == 'gallery';

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
            // Template Engine: la variante di layout (App Factory) sceglie
            // l'header. Skin "hero"/"gallery" → header brandizzato; le altre
            // mantengono l'header standard. Il motore sotto è identico.
            if (config != null && _isHeroLayout(config.layout))
              _HeroHeader(
                key: const Key('home_hero_header'),
                appName: config.appName,
                tagline: config.tagline,
                logoUrl: config.logoUrl,
              )
            else if (config?.tagline != null)
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

/// Header brandizzato delle skin "hero": logo + nome + tagline su sfondo
/// primario. Solo presentazione — nessun impatto sul motore prenotazioni.
class _HeroHeader extends StatelessWidget {
  const _HeroHeader({
    super.key,
    required this.appName,
    this.tagline,
    this.logoUrl,
  });

  final String appName;
  final String? tagline;
  final String? logoUrl;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),
      decoration: BoxDecoration(
        color: scheme.primary,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        children: [
          if (logoUrl != null)
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: Image.network(
                logoUrl!,
                height: 72,
                width: 72,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => const SizedBox.shrink(),
              ),
            ),
          const SizedBox(height: 12),
          Text(
            appName,
            style: Theme.of(context)
                .textTheme
                .headlineSmall
                ?.copyWith(color: scheme.onPrimary),
            textAlign: TextAlign.center,
          ),
          if (tagline != null) ...[
            const SizedBox(height: 4),
            Text(
              tagline!,
              style: TextStyle(color: scheme.onPrimary.withValues(alpha: 0.85)),
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}
