import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers.dart';
import '../../../app/router.dart';
import '../../../core/utils/error_messages.dart';
import 'widgets/auth_widgets.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _error = null);

    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _submitting = true);

    try {
      await ref.read(sessionControllerProvider.notifier).login(
            email: _email.text.trim(),
            password: _password.text,
          );
      // Redirect to home is handled by the router on session change.
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
    final config = ref.watch(whiteLabelConfigProvider).value;
    final appName = config?.appName ?? '';
    final tagline = config?.tagline;
    final logoUrl = config?.logoUrl;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: AuthEntrance(
              child: Form(
                key: _formKey,
                child: AutofillGroup(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (logoUrl != null && logoUrl.isNotEmpty) ...[
                        Center(
                          child: Image.network(
                            logoUrl,
                            height: 64,
                            errorBuilder: (_, _, _) => const SizedBox.shrink(),
                          ),
                        ),
                        const SizedBox(height: 20),
                      ],
                      Text(
                        'Bentornato',
                        style: theme.textTheme.headlineMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                        textAlign: TextAlign.center,
                      ),
                      if (appName.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          appName,
                          style: theme.textTheme.titleMedium?.copyWith(
                            color: theme.colorScheme.onSurface
                                .withValues(alpha: 0.7),
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ],
                      if (tagline != null && tagline.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          tagline,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurface
                                .withValues(alpha: 0.6),
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ],
                      const SizedBox(height: 32),
                      TextFormField(
                        controller: _email,
                        autofocus: true,
                        decoration: const InputDecoration(labelText: 'Email'),
                        keyboardType: TextInputType.emailAddress,
                        autofillHints: const [AutofillHints.email],
                        textInputAction: TextInputAction.next,
                        validator: authEmailValidator,
                      ),
                      const SizedBox(height: 16),
                      PasswordField(
                        controller: _password,
                        label: 'Password',
                        autofillHints: const [AutofillHints.password],
                        textInputAction: TextInputAction.done,
                        onFieldSubmitted: (_) => _submit(),
                        validator: (value) => (value ?? '').isEmpty
                            ? 'Inserisci la password'
                            : null,
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 16),
                        AuthErrorBanner(message: _error),
                      ],
                      const SizedBox(height: 24),
                      AuthPrimaryButton(
                        label: 'Accedi',
                        loading: _submitting,
                        onPressed: _submit,
                      ),
                      const SizedBox(height: 12),
                      TextButton(
                        onPressed: _submitting
                            ? null
                            : () => context.go(Routes.register),
                        child: const Text('Non hai un account? Registrati'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
