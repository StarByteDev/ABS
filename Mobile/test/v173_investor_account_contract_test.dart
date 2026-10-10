import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/json_tools.dart';
import 'package:abs_pulse/investor/investor_ui.dart';

void main() {
  test('V15.9.1 investor account fields remain distinct', () {
    final account = <String, dynamic>{
      'currency': 'AED',
      'net_contributions': 20000,
      'total_profit': 4309.09,
      'profit_paid': 4106,
      'profit_reinvested': 0,
      'activity_summary': <String, dynamic>{
        'investment': 0,
        'capital_withdrawal': 0,
      },
      'performance_plan': <String, dynamic>{
        'mtd_provisional': 309.09,
        'target_amount': 1000,
      },
    };
    expect(money(account['net_contributions'], 'AED'), 'AED 20,000.00');
    expect(money(account['profit_paid'], 'AED'), 'AED 4,106.00');
    expect(money(account['total_profit'], 'AED'), 'AED 4,309.09');
    expect(JsonTools.number(JsonTools.at(account, 'activity_summary.capital_withdrawal')), 0);
    expect(JsonTools.number(JsonTools.at(account, 'performance_plan.mtd_provisional')), 309.09);
  });

  test('Laravel paginated private transactions map in order', () {
    final reply = <String, dynamic>{'data': <String, dynamic>{
      'current_page': 1,
      'last_page': 2,
      'data': <Map<String, dynamic>>[
        {'id': 17, 'type': 'profit', 'entry_source': 'automatic_profit_distribution', 'amount': 1106, 'status': 'posted'},
        {'id': 16, 'type': 'withdrawal', 'amount': 1000, 'status': 'posted'},
        {'id': 15, 'type': 'profit', 'entry_source': 'admin_profit_reinvestment', 'amount': 120, 'status': 'posted'},
        {'id': 14, 'type': 'deposit', 'amount': 20000, 'status': 'posted'},
      ],
    }};
    final tx = JsonTools.pageItems(reply);
    expect(tx.length, 4);
    expect(tx[0]['type'], 'profit');
    expect(tx[1]['type'], 'withdrawal');
    expect(tx[2]['entry_source'], 'admin_profit_reinvestment');
    expect(JsonTools.integer(JsonTools.at(reply, 'data.last_page')), 2);
    // Report totals must come from /private/account, not these incomplete pages.
  });
}
