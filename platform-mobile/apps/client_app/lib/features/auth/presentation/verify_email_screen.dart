import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/utils/error_messages.dart';
import 'widgets/auth_widgets.dart';

/// Email verification gate (Fase 1 — S1): the router parks unverified sessions
/// here. The 6-digit code proves ownership of the address and unlocks
/// identity-bound features. Designed to feel like *closing* a step, not opening
/// one: the code is the single centre of gravity, autofilled and auto-checked.
class VerifyEmailScreen extends ConsumerStatefulWidget {
  const VerifyEmailScreen({super.key});

  @override
  ConsumerState<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends ConsumerState<VerifyEmailScreen> {
  static const _resendCooldown = Duration(seconds: 30);

  final _code = TextEditingController();

  bool _submitting = false;
  String? _error;
  Timer? _cooldownTimer;
  int _cooldownLeft = 0;

  @override
  void dispose() {
    _code.dispose();
    _cooldownTimer?.cancel();
    super.dispose();
  }

  void _onCodeChanged(String value) {
    if (_error != null) {
      setState(() => _error = null);
    }
    // Auto-submit the moment the sixth digit lands — no need to hunt the
    // button (which stays as a visible fallback).
    if (value.length == 6 && !_submitting) {
      _verify();
    }
  }

  Future<void> _verify() async {
    final code = _code.text.trim();

    if (code.length != 6) {
      setState(() => _error = 'Inserisci il codice di 6 cifre.');
      return;
    }

    setState(() {
      _error = null;
      _submitting = true;
    });

    try {
      await ref
          .read(sessionControllerProvider.notifier)
          .verifyEmail(code);
      if (mounted) {
        HapticFeedback.mediumImpact();
      }
      // Router redirects to home on the session state change.
    } catch (error) {
      if (mounted) {
        HapticFeedback.lightImpact();
        setState(() => _error = ErrorMessages.of(error));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  Future<void> _resend() async {
    try {
      await ref
          .read(sessionControllerProvider.notifier)
          .resendVerificationCode();

      setState(() => _cooldownLeft = _resendCooldown.inSeconds);

      _cooldownTimer?.cancel();
      _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted) {
          timer.cancel();
          return;
        }

        setState(() {
          _cooldownLeft--;
          if (_cooldownLeft <= 0) {
            timer.cancel();
          }
        });
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Ti abbiamo inviato un nuovo codice.')),
        );
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = ErrorMessages.of(error));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final session = ref.watch(sessionControllerProvider).value;
    final email = session is AuthenticatedSession ? session.email : '';

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        foregroundColor: scheme.onSurface,
        title: const Text('Verifica email'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: AuthEntrance(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Icon(
                  Icons.mark_email_read_outlined,
                  size: 56,
                  color: scheme.primary,
                ),
                const SizedBox(height: 16),
                Text(
                  'Confermiamo che sei tu',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                Text(
                  email.isEmpty
                      ? 'Abbiamo inviato un codice di verifica alla tua email.'
                      : 'Abbiamo inviato un codice a\n$email',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurface.withValues(alpha: 0.7),
                  ),
                ),
                const SizedBox(height: 28),
                TextField(
                  controller: _code,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  autofocus: true,
                  textAlign: TextAlign.center,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  onChanged: _onCodeChanged,
                  onSubmitted: (_) => _verify(),
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w600,
                    letterSpacing: 8,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Codice di verifica',
                    counterText: '',
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  AuthErrorBanner(message: _error),
                ],
                const SizedBox(height: 16),
                AuthPrimaryButton(
                  label: 'Verifica',
                  loading: _submitting,
                  onPressed: _verify,
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: _cooldownLeft > 0 ? null : _resend,
                  child: Text(
                    _cooldownLeft > 0
                        ? 'Invia di nuovo tra $_cooldownLeft s'
                        : 'Non hai ricevuto il codice? Invia di nuovo',
                  ),
                ),
                const Divider(height: 32),
                TextButton(
                  onPressed: () =>
                      ref.read(sessionControllerProvider.notifier).logout(),
                  child: const Text('Hai usato l\'email sbagliata? Esci e riprova'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
