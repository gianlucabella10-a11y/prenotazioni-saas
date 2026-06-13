import '../network/api_failure.dart';

/// Maps stable backend error codes (docs/25 §1) to user-facing copy.
///
/// Falls back to the backend message (already human-readable) and finally
/// to a generic one — never shows raw codes to the customer.
abstract final class ErrorMessages {
  static const Map<String, String> _byCode = {
    ApiFailure.networkCode:
        'Connessione assente. Controlla la rete e riprova.',
    'slot_unavailable':
        'Questo orario è appena stato prenotato da qualcun altro. Scegli un altro orario.',
    'cutoff_passed':
        'Il termine per la cancellazione è passato. Contatta direttamente l\'attività.',
    'too_late_to_book': 'Questo orario non è più prenotabile.',
    'beyond_booking_window': 'Questa data non è ancora prenotabile.',
    'invalid_credentials': 'Email o password non corretti.',
    'email_taken': 'Esiste già un account con questa email.',
    'tenant_not_operating': 'Il servizio è momentaneamente non disponibile.',
    'validation_failed': 'Controlla i dati inseriti e riprova.',
    'unauthenticated': 'Sessione scaduta. Accedi di nuovo.',
    'email_not_verified':
        'Verifica la tua email per continuare: controlla la posta in arrivo.',
    'invalid_verification_code': 'Il codice inserito non è valido.',
    'verification_code_expired':
        'Il codice è scaduto. Richiedine uno nuovo.',
    'too_many_verification_attempts':
        'Troppi tentativi. Richiedi un nuovo codice.',
    'already_verified': 'Il tuo account è già verificato.',
  };

  static String of(Object error) {
    if (error is ApiFailure) {
      return _byCode[error.code] ??
          (error.message.isNotEmpty
              ? error.message
              : 'Si è verificato un errore. Riprova.');
    }

    return 'Si è verificato un errore. Riprova.';
  }
}
