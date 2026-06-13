import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/utils/error_messages.dart';

/// Email verification gate (Fase 1 — S1): the router parks unverified
/// sessions here. The 6-digit code proves ownership of the address and
/// unlocks identity-bound features (and the CRM history linking).
class VerifyEmailScreen extends ConsumerStatefulWidget {
  const VerifyEmailScreen({super.key});

  @override
  ConsumerState<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends ConsumerState<VerifyEmailScreen> {
  static const _resendCooldown = Duration(seconds: 30);

  final _code = TextEditingController();

  bool _submitting = false;
  Timer? _cooldownTimer;
  int _cooldownLeft = 0;

  @override
  void dispose() {
    _code.dispose();
    _cooldownTimer?.cancel();
    super.dispose();
  }

  Future<void> _verify() async {
    if (_code.text.trim().length != 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Inserisci il codice di 6 cifre.')),
      );

      return;
    }

    setState(() => _submitting = true);

    try {
      await ref
          .read(sessionControllerProvider.notifier)
          .verifyEmail(_code.text.trim());
      // Router redirects to home on the session state change.
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
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ErrorMessages.of(error))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionControllerProvider).value;
    final email = session is AuthenticatedSession ? session.email : '';

    return Scaffold(
      appBar: AppBar(title: const Text('Verifica la tua email')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(Icons.mark_email_unread_outlined, size: 56),
              const SizedBox(height: 16),
              Text(
                email.isEmpty
                    ? 'Ti abbiamo inviato un codice di verifica via email.'
                    : 'Ti abbiamo inviato un codice di verifica a\n$email',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 8),
              Text(
                'Inseriscilo per completare la registrazione e prenotare.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 24),
              TextField(
                controller: _code,
                keyboardType: TextInputType.number,
                maxLength: 6,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall,
                decoration: const InputDecoration(
                  labelText: 'Codice di verifica',
                  counterText: '',
                ),
                onSubmitted: (_) => _verify(),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _submitting ? null : _verify,
                child: _submitting
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Verifica'),
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
                child: const Text('Esci e usa un altro account'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
