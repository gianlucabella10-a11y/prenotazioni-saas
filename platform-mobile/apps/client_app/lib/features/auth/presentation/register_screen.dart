import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/error_messages.dart';
import 'widgets/auth_widgets.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  static const _consentMessage =
      'Per registrarti devi accettare la privacy policy.';

  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();

  bool _submitting = false;
  bool _privacyAccepted = false;
  bool _consentMissing = false;
  String? _error;

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
    setState(() => _error = null);

    final formValid = _formKey.currentState!.validate();
    final consentOk = _privacyAccepted;

    setState(() => _consentMissing = !consentOk);

    if (!formValid || !consentOk) {
      HapticFeedback.lightImpact();
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
        HapticFeedback.heavyImpact();
        setState(() => _error = ErrorMessages.of(error));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        foregroundColor: theme.colorScheme.onSurface,
        title: const Text('Crea il tuo account'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          child: AuthEntrance(
            child: Form(
              key: _formKey,
              child: AutofillGroup(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Ti bastano pochi secondi: ci serve solo per confermare '
                      'le tue prenotazioni.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color:
                            theme.colorScheme.onSurface.withValues(alpha: 0.7),
                      ),
                    ),
                    const SizedBox(height: 20),
                    TextFormField(
                      controller: _firstName,
                      decoration: const InputDecoration(labelText: 'Nome'),
                      autofillHints: const [AutofillHints.givenName],
                      textCapitalization: TextCapitalization.words,
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
                      autofillHints: const [AutofillHints.familyName],
                      textCapitalization: TextCapitalization.words,
                      textInputAction: TextInputAction.next,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _email,
                      decoration: const InputDecoration(labelText: 'Email'),
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.email],
                      textInputAction: TextInputAction.next,
                      validator: authEmailValidator,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _phone,
                      decoration: const InputDecoration(
                        labelText: 'Telefono (facoltativo)',
                      ),
                      keyboardType: TextInputType.phone,
                      autofillHints: const [AutofillHints.telephoneNumber],
                      textInputAction: TextInputAction.next,
                    ),
                    const SizedBox(height: 16),
                    PasswordField(
                      controller: _password,
                      label: 'Password',
                      helperText: 'Almeno 10 caratteri',
                      autofillHints: const [AutofillHints.newPassword],
                      onFieldSubmitted: (_) => _submit(),
                      validator: (v) => (v ?? '').length < 10
                          ? 'La password deve avere almeno 10 caratteri'
                          : null,
                    ),
                    const SizedBox(height: 16),
                    _PrivacyConsentTile(
                      accepted: _privacyAccepted,
                      errorText: _consentMissing ? _consentMessage : null,
                      onChanged: (value) => setState(() {
                        _privacyAccepted = value;
                        if (value) {
                          _consentMissing = false;
                        }
                      }),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 16),
                      AuthErrorBanner(message: _error),
                    ],
                    const SizedBox(height: 20),
                    AuthPrimaryButton(
                      label: 'Registrati',
                      loading: _submitting,
                      onPressed: _submit,
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
        ),
      ),
    );
  }
}

/// GDPR consent (Fase 3): explicit, with a tappable link to the tenant's
/// privacy policy. The whole row toggles the consent (bigger, kinder target);
/// a persistent [errorText] replaces the old transient SnackBar.
class _PrivacyConsentTile extends ConsumerWidget {
  const _PrivacyConsentTile({
    required this.accepted,
    required this.onChanged,
    this.errorText,
  });

  final bool accepted;
  final ValueChanged<bool> onChanged;
  final String? errorText;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final legal = ref.watch(whiteLabelConfigProvider).value?.legal;
    final privacyUrl = legal?.privacyPolicyUrl;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Checkbox(
              value: accepted,
              onChanged: (value) => onChanged(value ?? false),
            ),
            // Only the label is the extra tap target — the checkbox keeps its
            // own tap, so tapping it never toggles twice.
            Expanded(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => onChanged(!accepted),
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
                                  color: theme.colorScheme.primary,
                                  decoration: TextDecoration.underline,
                                ),
                              ),
                            ),
                            const Text(
                              ' per la gestione delle mie prenotazioni.',
                            ),
                          ],
                        ),
                ),
              ),
            ),
          ],
        ),
        if (errorText != null)
          Padding(
            padding: const EdgeInsets.only(left: 12, top: 4),
            child: Text(
              errorText!,
              style: theme.textTheme.bodySmall
                  ?.copyWith(color: theme.colorScheme.error),
            ),
          ),
      ],
    );
  }
}
