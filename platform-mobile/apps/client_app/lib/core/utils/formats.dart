import 'package:intl/intl.dart';

/// Formatting helpers shared by the UI. Centralized so locale handling has
/// exactly one home.
abstract final class Formats {
  /// "18,00 €" from cents — currency-aware, locale it.
  static String price(int cents, String currency) {
    final formatter = NumberFormat.currency(
      locale: 'it_IT',
      symbol: currency == 'EUR' ? '€' : currency,
    );

    return formatter.format(cents / 100);
  }

  /// "ven 26 giu" for the calendar strip and cards.
  static String shortDate(DateTime local) =>
      DateFormat('EEE d MMM', 'it_IT').format(local);

  /// "12:00" wall-clock time.
  static String time(DateTime local) => DateFormat.Hm().format(local);

  /// "venerdì 26 giugno 2026, 12:00" for confirmations.
  static String fullDateTime(DateTime local) =>
      DateFormat("EEEE d MMMM y, HH:mm", 'it_IT').format(local);

  /// Local day key `yyyy-MM-dd` used by the availability API.
  static String dayKey(DateTime local) =>
      DateFormat('yyyy-MM-dd').format(local);

  /// "30 min" / "1 h 15 min".
  static String duration(int minutes) {
    if (minutes < 60) {
      return '$minutes min';
    }

    final hours = minutes ~/ 60;
    final rest = minutes % 60;

    return rest == 0 ? '$hours h' : '$hours h $rest min';
  }
}
