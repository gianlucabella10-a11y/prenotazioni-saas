import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/network/api_client.dart';
import '../core/session/session_controller.dart';
import '../core/storage/token_storage.dart';
import '../features/auth/data/auth_repository.dart';
import '../features/booking/data/booking_repository.dart';
import '../features/booking/domain/appointment.dart';
import '../features/catalog/data/catalog_repository.dart';
import '../features/catalog/domain/service.dart';
import '../features/profile/data/me_repository.dart';
import '../features/white_label/data/white_label_repository.dart';
import '../features/white_label/domain/white_label_config.dart';

/// Composition root (dependency injection via Riverpod).
///
/// Tests and previews override the leaf providers (storage, repositories)
/// — nothing constructs its own dependencies.

final tokenStorageProvider = Provider<TokenStorage>(
  (ref) => SecureTokenStorage(),
);

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    ref.watch(tokenStorageProvider),
    onSessionExpired: () async =>
        ref.read(sessionControllerProvider.notifier).onSessionExpired(),
  );
});

final whiteLabelRepositoryProvider = Provider<WhiteLabelRepository>((ref) {
  return WhiteLabelRepository(
    ref.watch(apiClientProvider),
    SharedPreferencesAsync(),
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStorageProvider),
  );
});

final catalogRepositoryProvider = Provider<CatalogRepository>(
  (ref) => CatalogRepository(ref.watch(apiClientProvider)),
);

final bookingRepositoryProvider = Provider<BookingRepository>(
  (ref) => BookingRepository(ref.watch(apiClientProvider)),
);

final meRepositoryProvider = Provider<MeRepository>(
  (ref) => MeRepository(ref.watch(apiClientProvider)),
);

/// The customer's own profile (Fase 2); invalidated after edits.
final myProfileProvider = FutureProvider<MyProfile>(
  (ref) => ref.watch(meRepositoryProvider).fetch(),
);

/// The white label configuration: loaded at splash, refreshed on app resume.
/// Everything brand-related in the UI watches this.
final whiteLabelConfigProvider =
    AsyncNotifierProvider<WhiteLabelConfigNotifier, WhiteLabelConfig>(
  WhiteLabelConfigNotifier.new,
);

class WhiteLabelConfigNotifier extends AsyncNotifier<WhiteLabelConfig> {
  @override
  Future<WhiteLabelConfig> build() =>
      ref.read(whiteLabelRepositoryProvider).load();

  Future<void> refresh() async {
    state = await AsyncValue.guard(
      () => ref.read(whiteLabelRepositoryProvider).load(),
    );
  }
}

final sessionControllerProvider =
    AsyncNotifierProvider<SessionController, SessionState>(
  SessionController.new,
);

/// Catalog data, fetched once per app session and invalidated on refresh.
final servicesProvider = FutureProvider<List<CatalogService>>(
  (ref) => ref.watch(catalogRepositoryProvider).services(),
);

final staffProvider = FutureProvider<List<StaffMember>>(
  (ref) => ref.watch(catalogRepositoryProvider).staff(),
);

/// Appointment lists (upcoming/past); invalidated after booking or cancel.
final appointmentsProvider =
    FutureProvider.family<List<Appointment>, bool>((ref, upcoming) {
  return ref.watch(bookingRepositoryProvider).list(upcoming: upcoming);
});
