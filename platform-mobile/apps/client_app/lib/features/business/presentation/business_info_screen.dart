import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/providers.dart';
import '../../catalog/domain/service.dart';
import '../../white_label/domain/white_label_config.dart';

/// Premium "scheda attività": header brandizzato, contatti diretti, social +
/// WhatsApp + Google Maps, orari settimanali e team. Tutto dinamico dal
/// tenant (GET /app/config + GET /staff); ogni sezione si nasconde se i dati
/// mancano (fallback elegante, nessuno spazio vuoto).
class BusinessInfoScreen extends ConsumerWidget {
  const BusinessInfoScreen({super.key});

  static const _weekdays = [
    'Lunedì',
    'Martedì',
    'Mercoledì',
    'Giovedì',
    'Venerdì',
    'Sabato',
    'Domenica',
  ];

  Future<void> _launch(String url) async {
    final uri = Uri.tryParse(url);

    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(whiteLabelConfigProvider).value;

    if (config == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Informazioni')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    final location = config.locations.isEmpty ? null : config.locations.first;

    return Scaffold(
      appBar: AppBar(title: const Text('Informazioni')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _Header(config: config),
          if (config.contacts.hasAny || config.social.hasAny)
            _ContactsCard(config: config, onLaunch: _launch),
          if (location != null && location.hasOpeningHours)
            _HoursCard(location: location, weekdays: _weekdays),
          if (config.locations.isNotEmpty)
            _LocationsCard(locations: config.locations, onLaunch: _launch),
          _StaffSection(),
          if (config.vatNumber != null && config.vatNumber!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 16, bottom: 8),
              child: Text(
                'P. IVA ${config.vatNumber}',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.6),
                    ),
              ),
            ),
        ],
      ),
    );
  }
}

String _initials(String name) {
  final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty);

  if (parts.isEmpty) {
    return '?';
  }

  return parts.take(2).map((p) => p[0].toUpperCase()).join();
}

class _Header extends StatelessWidget {
  const _Header({required this.config});

  final WhiteLabelConfig config;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final fallbackAvatar = CircleAvatar(
      radius: 44,
      backgroundColor: theme.colorScheme.secondary,
      child: Text(
        _initials(config.appName),
        style: theme.textTheme.headlineMedium?.copyWith(
          color: theme.colorScheme.onSecondary,
        ),
      ),
    );

    final logo = config.logoUrl == null
        ? fallbackAvatar
        : ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: Image.network(
              config.logoUrl!,
              width: 88,
              height: 88,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => fallbackAvatar,
            ),
          );

    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
        child: Column(
          children: [
            logo,
            const SizedBox(height: 16),
            Text(
              config.appName,
              style: theme.textTheme.headlineSmall,
              textAlign: TextAlign.center,
            ),
            if (config.tagline != null && config.tagline!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                config.tagline!,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ContactsCard extends StatelessWidget {
  const _ContactsCard({required this.config, required this.onLaunch});

  final WhiteLabelConfig config;
  final Future<void> Function(String url) onLaunch;

  @override
  Widget build(BuildContext context) {
    final contacts = config.contacts;
    final social = config.social;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (contacts.phone != null)
              _ContactTile(
                icon: Icons.phone_outlined,
                label: contacts.phone!,
                onTap: () => onLaunch('tel:${contacts.phone}'),
              ),
            if (contacts.email != null)
              _ContactTile(
                icon: Icons.email_outlined,
                label: contacts.email!,
                onTap: () => onLaunch('mailto:${contacts.email}'),
              ),
            if (contacts.website != null)
              _ContactTile(
                icon: Icons.language_outlined,
                label: contacts.website!,
                onTap: () => onLaunch(contacts.website!),
              ),
            if (social.hasAny) ...[
              const SizedBox(height: 8),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Wrap(
                  spacing: 16,
                  runSpacing: 12,
                  children: [
                    if (social.whatsapp != null)
                      _SocialButton(
                        icon: Icons.chat_bubble_outline,
                        label: 'WhatsApp',
                        onTap: () => onLaunch(social.whatsapp!.uri.toString()),
                      ),
                    if (social.instagramUrl != null)
                      _SocialButton(
                        icon: Icons.camera_alt_outlined,
                        label: 'Instagram',
                        onTap: () => onLaunch(social.instagramUrl!),
                      ),
                    if (social.facebookUrl != null)
                      _SocialButton(
                        icon: Icons.facebook_outlined,
                        label: 'Facebook',
                        onTap: () => onLaunch(social.facebookUrl!),
                      ),
                    if (social.tiktokUrl != null)
                      _SocialButton(
                        icon: Icons.music_note_outlined,
                        label: 'TikTok',
                        onTap: () => onLaunch(social.tiktokUrl!),
                      ),
                    if (social.mapsUrl != null)
                      _SocialButton(
                        icon: Icons.map_outlined,
                        label: 'Maps',
                        onTap: () => onLaunch(social.mapsUrl!),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
          ],
        ),
      ),
    );
  }
}

class _ContactTile extends StatelessWidget {
  const _ContactTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon),
      title: Text(label),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}

class _SocialButton extends StatelessWidget {
  const _SocialButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircleAvatar(
            radius: 24,
            backgroundColor: scheme.secondary.withValues(alpha: 0.15),
            child: Icon(icon, color: scheme.primary),
          ),
          const SizedBox(height: 4),
          Text(label, style: Theme.of(context).textTheme.labelSmall),
        ],
      ),
    );
  }
}

class _HoursCard extends StatelessWidget {
  const _HoursCard({required this.location, required this.weekdays});

  final TenantLocation location;
  final List<String> weekdays;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final today = DateTime.now().weekday - 1; // 0 = Monday

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Orari di apertura', style: theme.textTheme.titleMedium),
            const SizedBox(height: 12),
            for (var day = 0; day < 7; day++)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      weekdays[day],
                      style: day == today
                          ? theme.textTheme.bodyMedium
                              ?.copyWith(fontWeight: FontWeight.bold)
                          : theme.textTheme.bodyMedium,
                    ),
                    Text(
                      _hoursLabel(location.openingHours[day] ?? const []),
                      style: day == today
                          ? theme.textTheme.bodyMedium?.copyWith(
                              fontWeight: FontWeight.bold,
                              color: theme.colorScheme.primary,
                            )
                          : theme.textTheme.bodyMedium?.copyWith(
                              color: theme.colorScheme.onSurface
                                  .withValues(alpha: 0.7),
                            ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _hoursLabel(List<OpeningInterval> intervals) {
    if (intervals.isEmpty) {
      return 'Chiuso';
    }

    return intervals.map((i) => '${i.start}–${i.end}').join('   ');
  }
}

class _LocationsCard extends StatelessWidget {
  const _LocationsCard({required this.locations, required this.onLaunch});

  final List<TenantLocation> locations;
  final Future<void> Function(String url) onLaunch;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              locations.length > 1 ? 'Le nostre sedi' : 'Dove siamo',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            for (final location in locations) ...[
              const SizedBox(height: 8),
              Text(location.name, style: theme.textTheme.titleSmall),
              if (location.address != null) ...[
                const SizedBox(height: 4),
                InkWell(
                  onTap: () => onLaunch(
                    'https://www.google.com/maps/search/?api=1&query='
                    '${Uri.encodeComponent(location.address!)}',
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.place_outlined, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(location.address!)),
                      const Icon(Icons.open_in_new, size: 16),
                    ],
                  ),
                ),
              ],
              if (location.phone != null) ...[
                const SizedBox(height: 8),
                InkWell(
                  onTap: () => onLaunch('tel:${location.phone}'),
                  child: Row(
                    children: [
                      const Icon(Icons.phone_outlined, size: 18),
                      const SizedBox(width: 8),
                      Text(location.phone!),
                    ],
                  ),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _StaffSection extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final staffAsync = ref.watch(staffProvider);
    final theme = Theme.of(context);

    return staffAsync.when(
      data: (staff) {
        if (staff.isEmpty) {
          return const SizedBox.shrink();
        }

        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Il nostro team', style: theme.textTheme.titleMedium),
                const SizedBox(height: 8),
                for (final member in staff) _StaffTile(member: member),
              ],
            ),
          ),
        );
      },
      // Staff is non-critical for this screen: stay silent on loading/error
      // instead of breaking the page.
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
    );
  }
}

class _StaffTile extends StatelessWidget {
  const _StaffTile({required this.member});

  final StaffMember member;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: CircleAvatar(
        backgroundColor: scheme.secondary.withValues(alpha: 0.2),
        child: Text(
          _initials(member.displayName),
          style: TextStyle(color: scheme.primary),
        ),
      ),
      title: Text(member.displayName),
      subtitle: member.roleLabel == null ? null : Text(member.roleLabel!),
    );
  }
}
