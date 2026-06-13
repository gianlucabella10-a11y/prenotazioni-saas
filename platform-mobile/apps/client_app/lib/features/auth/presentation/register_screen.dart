import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/error_messages.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();

  bool _submitting = false;
  bool _privacyAccepted = false;

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (!_privacyAccepted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Per registrarti devi accettare la privacy policy.'),
        ),
      );

      return;
    }

    setState(() => _submitting = true);

    try {
      await ref.read(sessionControllerProvider.notifier).register(
            email: _email.text.trim(),
            password: _password.text,
            firstName: _firstName.text.trim(),
            privacyAccepted: _privacyAccepted,
            lastName: _lastName.text.trim(),
            phone: _phone.text.trim(),
          );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ErrorMessages.of(error))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Crea il tuo account')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _firstName,
                  decoration: const InputDecoration(labelText: 'Nome'),
                  textInputAction: TextInputAction.next,
                  validator: (v) =>
                      (v ?? '').trim().isEmpty ? 'Inserisci il nome' : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _lastName,
                  decoration: const InputDecoration(
                    labelText: 'Cognome (facoltativo)',
                  ),
                  textInputAction: TextInputAction.next,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _email,
                  decoration: const InputDecoration(labelText: 'Email'),
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  validator: (value) {
                    final v = value?.trim() ?? '';

                    if (v.isEmpty || !v.contains('@')) {
                      return 'Inserisci un indirizzo email valido';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _phone,
                  decoration: const InputDecoration(
                    labelText: 'Telefono (facoltativo)',
                  ),
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.next,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _password,
                  decoration: const InputDecoration(
                    labelText: 'Password (minimo 10 caratteri)',
                  ),
                  obscureText: true,
                  onFieldSubmitted: (_) => _submit(),
                  validator: (v) => (v ?? '').length < 10
                      ? 'La password deve avere almeno 10 caratteri'
                      : null,
                ),
                const SizedBox(height: 16),
                _PrivacyConsentTile(
                  accepted: _privacyAccepted,
                  onChanged: (value) =>
                      setState(() => _privacyAccepted = value),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Registrati'),
                ),
                const SizedBox(height: 12),
                TextButton(
                  onPressed:
                      _submitting ? null : () => context.go(Routes.login),
                  child: const Text('Hai già un account? Accedi'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// GDPR consent checkbox (Fase 3): explicit acceptance with a tappable link
/// to the tenant's privacy policy when configured in the white label config.
class _PrivacyConsentTile extends ConsumerWidget {
  const _PrivacyConsentTile({required this.accepted, required this.onChanged});

  final bool accepted;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final legal = ref.watch(whiteLabelConfigProvider).value?.legal;
    final privacyUrl = legal?.privacyPolicyUrl;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Checkbox(
          value: accepted,
          onChanged: (value) => onChanged(value ?? false),
        ),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 12),
            child: privacyUrl == null
                ? const Text(
                    'Accetto il trattamento dei miei dati per la gestione '
                    'delle prenotazioni (privacy policy).',
                  )
                : Wrap(
                    children: [
                      const Text('Accetto la '),
                      GestureDetector(
                        onTap: () => launchUrl(
                          Uri.parse(privacyUrl),
                          mode: LaunchMode.externalApplication,
                        ),
                        child: Text(
                          'privacy policy',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.primary,
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ),
                      const Text(
                          ' per la gestione delle mie prenotazioni.'),
                    ],
                  ),
          ),
        ),
      ],
    );
  }
}
