import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/error_messages.dart';
import '../data/me_repository.dart';

/// Account screen (Fase 2): real profile from GET /me, basic edits, legal
/// links from the tenant config, logout and the Apple-compliant in-app
/// account deletion (guideline 5.1.1(v)) with double confirmation.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  Future<void> _editProfile(
    BuildContext context,
    WidgetRef ref,
    MyProfile profile,
  ) async {
    final firstName = TextEditingController(text: profile.firstName ?? '');
    final lastName = TextEditingController(text: profile.lastName ?? '');
    final phone = TextEditingController(text: profile.phone ?? '');

    final save = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Modifica profilo'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: firstName,
              decoration: const InputDecoration(labelText: 'Nome'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: lastName,
              decoration: const InputDecoration(labelText: 'Cognome'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: phone,
              decoration: const InputDecoration(labelText: 'Telefono'),
              keyboardType: TextInputType.phone,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Annulla'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Salva'),
          ),
        ],
      ),
    );

    if (save != true) {
      return;
    }

    try {
      await ref.read(meRepositoryProvider).update(
            firstName: firstName.text.trim(),
            lastName: lastName.text.trim().isEmpty
                ? null
                : lastName.text.trim(),
            phone: phone.text.trim().isEmpty ? null : phone.text.trim(),
          );

      ref.invalidate(myProfileProvider);
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ErrorMessages.of(error))),
        );
      }
    }
  }

  /// Apple 5.1.1(v): in-app deletion, clearly destructive, double-confirmed.
  Future<void> _deleteAccount(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Eliminare il tuo account?'),
        content: const Text(
          'Questa azione è immediata e irreversibile: i tuoi dati personali '
          'verranno eliminati e non potrai più accedere con questo account.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Annulla'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Elimina definitivamente'),
          ),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) {
      return;
    }

    try {
      await ref.read(sessionControllerProvider.notifier).deleteAccount();

      if (context.mounted) {
        context.go(Routes.login);
      }
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
    final profileAsync = ref.watch(myProfileProvider);
    final legal = ref.watch(whiteLabelConfigProvider).value?.legal;

    return Scaffold(
      appBar: AppBar(title: const Text('Profilo')),
      body: profileAsync.when(
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
                  onPressed: () => ref.invalidate(myProfileProvider),
                  child: const Text('Riprova'),
                ),
              ],
            ),
          ),
        ),
        data: (profile) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person)),
                title: Text(
                  profile.displayName.isEmpty
                      ? (profile.email ?? '')
                      : profile.displayName,
                ),
                subtitle: Text(
                  [
                    profile.email,
                    profile.phone,
                  ].whereType<String>().join('\n'),
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.edit_outlined),
                  tooltip: 'Modifica profilo',
                  onPressed: () => _editProfile(context, ref, profile),
                ),
              ),
            ),
            const SizedBox(height: 16),
            if (legal != null &&
                (legal.privacyPolicyUrl != null ||
                    legal.termsUrl != null ||
                    legal.supportUrl != null))
              Card(
                child: Column(
                  children: [
                    if (legal.privacyPolicyUrl != null)
                      _LinkTile(
                        icon: Icons.privacy_tip_outlined,
                        label: 'Privacy policy',
                        url: legal.privacyPolicyUrl!,
                      ),
                    if (legal.termsUrl != null)
                      _LinkTile(
                        icon: Icons.description_outlined,
                        label: 'Termini e condizioni',
                        url: legal.termsUrl!,
                      ),
                    if (legal.supportUrl != null)
                      _LinkTile(
                        icon: Icons.help_outline,
                        label: 'Assistenza',
                        url: legal.supportUrl!,
                      ),
                  ],
                ),
              ),
            const SizedBox(height: 16),
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.logout),
                    title: const Text('Esci'),
                    onTap: () async {
                      await ref
                          .read(sessionControllerProvider.notifier)
                          .logout();

                      if (context.mounted) {
                        context.go(Routes.login);
                      }
                    },
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: Icon(
                      Icons.delete_forever_outlined,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    title: Text(
                      'Elimina account',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                    onTap: () => _deleteAccount(context, ref),
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

class _LinkTile extends StatelessWidget {
  const _LinkTile({required this.icon, required this.label, required this.url});

  final IconData icon;
  final String label;
  final String url;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon),
      title: Text(label),
      trailing: const Icon(Icons.open_in_new, size: 18),
      onTap: () =>
          launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
    );
  }
}
