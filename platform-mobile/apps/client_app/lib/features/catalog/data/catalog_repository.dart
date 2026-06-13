import '../../../core/network/api_client.dart';
import '../domain/service.dart';

/// Public catalog of the tenant (services with variants, bookable staff).
class CatalogRepository {
  CatalogRepository(this._api);

  final ApiClient _api;

  Future<List<CatalogService>> services() async {
    final json = await _api.getJson('/catalog/services');

    return ((json['data'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(CatalogService.fromJson)
        .where((service) => service.variants.isNotEmpty)
        .toList();
  }

  Future<List<StaffMember>> staff() async {
    final json = await _api.getJson('/staff');

    return ((json['data'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(StaffMember.fromJson)
        .toList();
  }
}
