/// "Add to calendar" link for a booked appointment.
///
/// Uses the Google Calendar *template* endpoint: a plain HTTPS URL that every
/// platform opens through the already-bundled `url_launcher`, so the feature
/// needs no native calendar plugin (and none of its per-platform permission
/// setup). The URL is pure and deterministic, hence unit-testable without a
/// platform channel.
library;

/// Builds the Google Calendar "create event" URL for the given event.
///
/// [startUtc]/[endUtc] are normalized to UTC and emitted in the basic
/// `yyyyMMddTHHmmssZ` form the endpoint expects. Empty [details]/[location]
/// are omitted rather than sent blank.
Uri googleCalendarTemplateUri({
  required String title,
  required DateTime startUtc,
  required DateTime endUtc,
  String? details,
  String? location,
}) {
  String pad(int value, [int width = 2]) => value.toString().padLeft(width, '0');

  String stamp(DateTime dateTime) {
    final utc = dateTime.toUtc();

    return '${pad(utc.year, 4)}${pad(utc.month)}${pad(utc.day)}'
        'T${pad(utc.hour)}${pad(utc.minute)}${pad(utc.second)}Z';
  }

  return Uri.https('calendar.google.com', '/calendar/render', {
    'action': 'TEMPLATE',
    'text': title,
    'dates': '${stamp(startUtc)}/${stamp(endUtc)}',
    if (details != null && details.isNotEmpty) 'details': details,
    if (location != null && location.isNotEmpty) 'location': location,
  });
}
