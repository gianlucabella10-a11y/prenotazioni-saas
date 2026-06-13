import 'package:client_app/core/network/api_failure.dart';
import 'package:client_app/core/utils/error_messages.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

DioException _withResponse(int status, Object? data) {
  final options = RequestOptions(path: '/x');

  return DioException(
    requestOptions: options,
    response: Response(requestOptions: options, statusCode: status, data: data),
  );
}

void main() {
  group('ApiFailure.fromDio', () {
    test('maps the backend error envelope (docs/25 §1)', () {
      final failure = ApiFailure.fromDio(_withResponse(409, {
        'error': {
          'code': 'slot_unavailable',
          'message': 'The selected time slot is no longer available.',
        },
      }));

      expect(failure.code, 'slot_unavailable');
      expect(failure.statusCode, 409);
    });

    test('no response means network failure', () {
      final failure = ApiFailure.fromDio(
        DioException(requestOptions: RequestOptions(path: '/x')),
      );

      expect(failure.isNetwork, isTrue);
      expect(failure.code, ApiFailure.networkCode);
    });

    test('non-envelope body degrades to unexpected_error with status', () {
      final failure = ApiFailure.fromDio(_withResponse(500, '<html>'));

      expect(failure.code, ApiFailure.unexpectedCode);
      expect(failure.statusCode, 500);
    });
  });

  group('ErrorMessages', () {
    test('known codes map to Italian copy, unknown codes use the backend message',
        () {
      expect(
        ErrorMessages.of(const ApiFailure(
          code: 'slot_unavailable',
          message: 'x',
        )),
        contains('orario'),
      );

      expect(
        ErrorMessages.of(const ApiFailure(
          code: 'some_new_code',
          message: 'Messaggio dal server.',
        )),
        'Messaggio dal server.',
      );

      expect(ErrorMessages.of(StateError('boom')), contains('errore'));
    });
  });
}
