import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/json_tools.dart';

void main() {
  test('JsonTools navigates nested maps safely', () {
    final value = <String, dynamic>{
      'data': <String, dynamic>{
        'user': <String, dynamic>{'name': 'ABS Member'},
      },
    };
    expect(JsonTools.at(value, 'data.user.name'), 'ABS Member');
    expect(JsonTools.at(value, 'data.missing', 'fallback'), 'fallback');
  });

  test('JsonTools converts common scalar types', () {
    expect(JsonTools.number('12.50'), 12.5);
    expect(JsonTools.integer('7'), 7);
    expect(JsonTools.boolean('true'), isTrue);
    expect(JsonTools.boolean('0'), isFalse);
  });

  test('JsonTools strips basic HTML from CMS text', () {
    expect(JsonTools.plain('<p>Alpha &amp; Beta</p>'), 'Alpha & Beta');
  });

  test('JsonTools reads nested paginated news collections', () {
    final response = {
      'data': {
        'news': {
          'data': [
            {'id': 1, 'title': 'ABS market update'},
          ],
        },
      },
    };
    final rows = JsonTools.collectionItems(
      response,
      keys: const ['news', 'articles', 'items', 'results'],
    );
    expect(rows, hasLength(1));
    expect(rows.first['title'], 'ABS market update');
  });

  test('JsonTools reads nested calendar event collections', () {
    final response = {
      'data': {
        'upcoming': [
          {'id': 7, 'title': 'RBA Rate Decision'},
        ],
      },
    };
    final rows = JsonTools.collectionItems(
      response,
      keys: const ['events', 'upcoming', 'items'],
    );
    expect(rows, hasLength(1));
    expect(rows.first['title'], 'RBA Rate Decision');
  });
}
