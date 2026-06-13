import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/providers.dart';
import '../../features/auth/data/auth_repository.dart';
import '../network/api_failure.dart';

/// Authentication state of the app.
sealed class SessionState {
  const SessionState();
}

class GuestSession extends SessionState {
  const GuestSession();
}

class AuthenticatedSession extends SessionState {
  const AuthenticatedSession({required this.email, required this.verified});

  final String email;

  /// Email ownership proven (Fase 1): gates identity-bound features and
  /// drives the router to the verification screen when false.
  final bool verified;

  AuthenticatedSession copyWith({String? email, bool? verified}) =>
      AuthenticatedSession(
        email: email ?? this.email,
        verified: verified ?? this.verified,
      );
}

/// Owns login/register/verification/logout/deletion and reacts to
/// irrecoverable token expiry signalled by the API client.
class SessionController extends AsyncNotifier<SessionState> {
  AuthRepository get _auth => ref.read(authRepositoryProvider);

  @override
  Future<SessionState> build() async {
    if (! await _auth.hasSession()) {
      return const GuestSession();
    }

    // Hydrate identity from the backend (audit fix F2): the profile is the
    // source of truth for email + verification state across restarts.
    try {
      final profile = await ref.read(meRepositoryProvider).fetch();

      return AuthenticatedSession(
        email: profile.email ?? '',
        verified: profile.emailVerified,
      );
    } on ApiFailure catch (failure) {
      if (failure.isUnauthenticated) {
        await _auth.clearSession();

        return const GuestSession();
      }

      // Offline start: keep the session usable; server-side gates still
      // protect identity-bound calls if verification was pending.
      return const AuthenticatedSession(email: '', verified: true);
    }
  }

  Future<void> login({required String email, required String password}) async {
    final user = await _auth.login(email: email, password: password);

    state = AsyncData(AuthenticatedSession(
      email: user.email,
      verified: user.emailVerified,
    ));
  }

  Future<void> register({
    required String email,
    required String password,
    required String firstName,
    required bool privacyAccepted,
    String? privacyVersion,
    String? lastName,
    String? phone,
  }) async {
    final user = await _auth.register(
      email: email,
      password: password,
      firstName: firstName,
      privacyAccepted: privacyAccepted,
      privacyVersion: privacyVersion,
      lastName: lastName,
      phone: phone,
    );

    state = AsyncData(AuthenticatedSession(
      email: user.email,
      verified: user.emailVerified,
    ));
  }

  /// Completes email verification; on success the router unlocks the app.
  Future<void> verifyEmail(String code) async {
    await _auth.verifyEmail(code);

    final current = state.value;

    if (current is AuthenticatedSession) {
      state = AsyncData(current.copyWith(verified: true));
    }
  }

  Future<void> resendVerificationCode() => _auth.resendVerificationCode();

  Future<void> logout() async {
    await _auth.logout();

    state = const AsyncData(GuestSession());
  }

  /// Apple-compliant account deletion: backend wipes the identity, then the
  /// local session is dropped.
  Future<void> deleteAccount() async {
    await ref.read(meRepositoryProvider).deleteAccount();
    await _auth.clearSession();

    state = const AsyncData(GuestSession());
  }

  /// Invoked by the API client when refresh rotation fails.
  Future<void> onSessionExpired() async {
    state = const AsyncData(GuestSession());
  }
}
