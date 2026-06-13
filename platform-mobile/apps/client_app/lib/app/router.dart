import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/session/session_controller.dart';
import '../features/appointments/presentation/my_appointments_screen.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/register_screen.dart';
import '../features/auth/presentation/verify_email_screen.dart';
import '../features/booking/presentation/booking_schedule_screen.dart';
import '../features/booking/presentation/booking_services_screen.dart';
import '../features/booking/presentation/booking_success_screen.dart';
import '../features/business/presentation/business_info_screen.dart';
import '../features/home/presentation/home_screen.dart';
import '../features/profile/presentation/profile_screen.dart';
import '../features/white_label/presentation/splash_screen.dart';
import '../features/white_label/presentation/tenant_unavailable_screen.dart';
import 'providers.dart';

/// Route names: referenced by screens, never raw strings around the app.
abstract final class Routes {
  static const splash = '/';
  static const unavailable = '/unavailable';
  static const login = '/login';
  static const register = '/register';
  static const verifyEmail = '/verify-email';
  static const home = '/home';
  static const business = '/business';
  static const bookingServices = '/booking/services';
  static const bookingSchedule = '/booking/schedule';
  static const bookingSuccess = '/booking/success';
  static const appointments = '/appointments';
  static const profile = '/profile';
}

/// Router with config/session-aware redirects:
///  - until the white label config is loaded: splash
///  - tenant suspended/terminated: courtesy screen (docs/27 §7)
///  - guest: only login/register
final routerProvider = Provider<GoRouter>((ref) {
  // Re-evaluate redirects whenever config or session change.
  final configAsync = ref.watch(whiteLabelConfigProvider);
  final sessionAsync = ref.watch(sessionControllerProvider);

  return GoRouter(
    initialLocation: Routes.splash,
    redirect: (context, routerState) {
      final location = routerState.matchedLocation;

      // Config not ready (loading or failed): stay on splash, which owns
      // loading/error/retry presentation.
      final config = configAsync.value;

      if (config == null) {
        return location == Routes.splash ? null : Routes.splash;
      }

      if (!config.isOperating) {
        return location == Routes.unavailable ? null : Routes.unavailable;
      }

      final session = sessionAsync.value;

      if (session == null) {
        return location == Routes.splash ? null : Routes.splash;
      }

      final isAuthArea =
          location == Routes.login || location == Routes.register;

      if (session is GuestSession) {
        return isAuthArea ? null : Routes.login;
      }

      // Unverified sessions are parked on the verification gate (Fase 1):
      // identity-bound features stay locked until ownership is proven.
      if (session is AuthenticatedSession && !session.verified) {
        return location == Routes.verifyEmail ? null : Routes.verifyEmail;
      }

      // Authenticated users escape splash/auth/verification screens.
      if (isAuthArea ||
          location == Routes.splash ||
          location == Routes.verifyEmail) {
        return Routes.home;
      }

      return null;
    },
    routes: [
      GoRoute(path: Routes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(
        path: Routes.unavailable,
        builder: (_, _) => const TenantUnavailableScreen(),
      ),
      GoRoute(path: Routes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(
        path: Routes.register,
        builder: (_, _) => const RegisterScreen(),
      ),
      GoRoute(
        path: Routes.verifyEmail,
        builder: (_, _) => const VerifyEmailScreen(),
      ),
      GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen()),
      GoRoute(
        path: Routes.business,
        builder: (_, _) => const BusinessInfoScreen(),
      ),
      GoRoute(
        path: Routes.bookingServices,
        builder: (_, _) => const BookingServicesScreen(),
      ),
      GoRoute(
        path: Routes.bookingSchedule,
        builder: (_, _) => const BookingScheduleScreen(),
      ),
      GoRoute(
        path: Routes.bookingSuccess,
        builder: (_, _) => const BookingSuccessScreen(),
      ),
      GoRoute(
        path: Routes.appointments,
        builder: (_, _) => const MyAppointmentsScreen(),
      ),
      GoRoute(path: Routes.profile, builder: (_, _) => const ProfileScreen()),
    ],
  );
});
