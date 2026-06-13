import '../../../core/network/api_client.dart';

/// The customer's own profile as served by GET /me (Fase 2).
class MyProfile {
  const MyProfile({
    required this.uuid,
    required this.email,
    required this.emailVerified,
    this.firstName,
    this.lastName,
    this.phone,
  });

  final String uuid;
  final String? email;
  final bool emailVerified;
  final String? firstName;
  final String? lastName;
  final String? phone;

  String get displayName =>
      [firstName, lastName].whereType<String>().join(' ').trim();

  factory MyProfile.fromJson(Map<String, dynamic> json) => MyProfile(
        uuid: json['uuid'] as String,
        email: json['email'] as String?,
        emailVerified: json['email_verified'] == true,
        firstName: json['first_name'] as String?,
        lastName: json['last_name'] as String?,
        phone: json['phone'] as String?,
      );
}

/// Account management endpoints (Fase 2-4): profile, consents, deletion,
/// push device registration.
class MeRepository {
  MeRepository(this._api);

  final ApiClient _api;

  Future<MyProfile> fetch() async {
    final json = await _api.getJson('/me');

    return MyProfile.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<MyProfile> update({
    String? firstName,
    String? lastName,
    String? phone,
  }) async {
    final json = await _api.patchJson('/me', body: {
      'first_name': ?firstName,
      'last_name': lastName,
      'phone': phone,
    });

    return MyProfile.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<void> updateConsents({bool? marketingPush, bool? marketingEmail}) async {
    await _api.raw.put('/me/consents', data: {
      'marketing_push': ?marketingPush,
      'marketing_email': ?marketingEmail,
    });
  }

  /// Apple-compliant account deletion (5.1.1(v)): immediate, irreversible.
  Future<void> deleteAccount() async {
    await _api.raw.delete('/me');
  }

  /// Push foundation (Fase 4): called once FCM is integrated client-side.
  Future<void> registerDevice({
    required String platform,
    required String fcmToken,
    String? locale,
  }) async {
    await _api.raw.put('/me/devices', data: {
      'platform': platform,
      'fcm_token': fcmToken,
      'locale': ?locale,
    });
  }

  Future<void> unregisterDevice(String fcmToken) async {
    await _api.raw.delete('/me/devices', data: {'fcm_token': fcmToken});
  }
}
