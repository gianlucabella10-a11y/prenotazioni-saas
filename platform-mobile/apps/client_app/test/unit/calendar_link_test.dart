import 'package:client_app/core/utils/calendar_link.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('builds a Google Calendar template URL with UTC basic timestamps', () {
    final uri = googleCalendarTemplateUri(
      title: 'Taglio capelli · Salone Demo',
      startUtc: DateTime.utc(2026, 6, 26, 12, 0, 0),
      endUtc: DateTime.utc(2026, 6, 26, 12, 30, 0),
      location: 'Sede centrale',
    );

    expect(uri.scheme, 'https');
    expect(uri.host, 'calendar.google.com');
    expect(uri.path, '/calendar/render');
    expect(uri.queryParameters['action'], 'TEMPLATE');
    expect(uri.queryParameters['text'], 'Taglio capelli · Salone Demo');
    expect(uri.queryParameters['dates'], '20260626T120000Z/20260626T123000Z');
    expect(uri.queryParameters['location'], 'Sede centrale');
  });

  test('normalizes a local DateTime to its UTC instant', () {
    final start = DateTime(2026, 1, 1, 10, 0, 0); // local wall-clock

    final uri = googleCalendarTemplateUri(
      title: 'x',
      startUtc: start,
      endUtc: start.add(const Duration(minutes: 45)),
    );

    final utc = start.toUtc();
    String p(int value, [int width = 2]) => value.toString().padLeft(width, '0');
    final expected = '${p(utc.year, 4)}${p(utc.month)}${p(utc.day)}'
        'T${p(utc.hour)}${p(utc.minute)}${p(utc.second)}Z';

    expect(uri.queryParameters['dates']!.split('/').first, expected);
  });

  test('omits empty details and location instead of sending blanks', () {
    final uri = googleCalendarTemplateUri(
      title: 'x',
      startUtc: DateTime.utc(2026, 6, 26, 12),
      endUtc: DateTime.utc(2026, 6, 26, 13),
      details: '',
      location: '',
    );

    expect(uri.queryParameters.containsKey('details'), isFalse);
    expect(uri.queryParameters.containsKey('location'), isFalse);
  });
}
