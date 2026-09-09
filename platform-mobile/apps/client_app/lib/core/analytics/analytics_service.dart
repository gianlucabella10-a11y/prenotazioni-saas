/// Product analytics minimale (FASE 6): SOLO eventi prodotto, nessun dato
/// superfluo. Il sink è pluggable — di default no-op; in produzione si collega
/// a Firebase Analytics o ai breadcrumb di Sentry senza cambiare i call-site.
library;

/// Eventi prodotto tracciati (chiavi stabili snake_case).
enum AnalyticsEvent {
  appOpen('app_open'),
  login('login'),
  bookingCreated('booking_created'),
  bookingCompleted('booking_completed'),
  bookingCancelled('booking_cancelled'),
  bookingFailed('booking_failed'),
  notificationOpen('notification_open'),
  apiError('api_error'),
  error('error');

  const AnalyticsEvent(this.key);

  final String key;
}

/// Destinazione degli eventi. Implementazioni: no-op (default), Firebase, Sentry.
abstract interface class AnalyticsSink {
  void log(String event, Map<String, Object?> params);
}

/// Default sicuro: non invia nulla (beta senza backend analytics configurato).
class NoopAnalyticsSink implements AnalyticsSink {
  const NoopAnalyticsSink();

  @override
  void log(String event, Map<String, Object?> params) {}
}

/// Traccia gli eventi prodotto. Il `tenantKey` (white-label) è allegato a ogni
/// evento per segmentare per cliente lato analytics.
class AnalyticsService {
  AnalyticsService(this._sink, {this.tenantKey});

  final AnalyticsSink _sink;
  final String? tenantKey;

  void track(AnalyticsEvent event, [Map<String, Object?> params = const {}]) {
    final tenant = tenantKey;
    _sink.log(event.key, {
      if (tenant != null && tenant.isNotEmpty) 'tenant': tenant,
      ...params,
    });
  }

  void appOpen() => track(AnalyticsEvent.appOpen);

  void login() => track(AnalyticsEvent.login);

  void bookingCreated(String appointmentId) =>
      track(AnalyticsEvent.bookingCreated, {'appointment_id': appointmentId});

  void bookingCompleted(String appointmentId) =>
      track(AnalyticsEvent.bookingCompleted, {'appointment_id': appointmentId});

  void bookingCancelled(String appointmentId) =>
      track(AnalyticsEvent.bookingCancelled, {'appointment_id': appointmentId});

  void bookingFailed(String reason) =>
      track(AnalyticsEvent.bookingFailed, {'reason': reason});

  void apiError(String endpoint, {int? status}) =>
      track(AnalyticsEvent.apiError, {'endpoint': endpoint, 'status': ?status});

  void notificationOpen(String type) =>
      track(AnalyticsEvent.notificationOpen, {'type': type});

  void error(String message) => track(AnalyticsEvent.error, {'message': message});
}
