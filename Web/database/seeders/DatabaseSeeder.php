<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\EconomicEvent;
use App\Models\LearningArticle;
use App\Models\MonthlyStatement;
use App\Models\NewsArticle;
use App\Models\PortfolioAccount;
use App\Models\PortfolioTransaction;
use App\Models\Product;
use App\Models\PulsePair;
use App\Models\PulsePlan;
use App\Models\PulseStrategy;
use App\Models\PulseSystemSetting;
use App\Models\PulseUserSetting;
use App\Models\ResearchReport;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Models\Watchlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->removeLegacySampleRecords();

        // Database Fix and release upgrades run this seeder on live systems. Never
        // manufacture a default production administrator or reactivate/reset an
        // existing administrator as a side effect of routine repair.
        $configuredAdminEmail = strtolower(trim((string) env('ABS_ADMIN_EMAIL', '')));
        $admin = $configuredAdminEmail !== ''
            ? User::query()->whereRaw('LOWER(email) = ?', [$configuredAdminEmail])->first()
            : User::query()->where('role', 'admin')->orderBy('id')->first();

        if (! $admin && $configuredAdminEmail !== '') {
            $configuredPassword = (string) env('ABS_ADMIN_PASSWORD', '');
            if ($configuredPassword === '' && app()->environment('production')) {
                throw new \RuntimeException('ABS_ADMIN_PASSWORD must be configured before Database Fix can create a new production administrator.');
            }

            $admin = User::create([
                'name' => env('ABS_ADMIN_NAME', 'ABS Administrator'),
                'email' => $configuredAdminEmail,
                'password' => Hash::make($configuredPassword !== '' ? $configuredPassword : 'Admin@12345'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        if (! $admin && ! app()->environment('production')) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@alphablocksolutions.local'],
                [
                    'name' => 'ABS Administrator',
                    'password' => Hash::make((string) env('ABS_ADMIN_PASSWORD', 'Admin@12345')),
                    'role' => 'admin',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );
        }

        Product::whereIn('slug', [
            'blockchain-development',
            'utility-token-development',
            'nft-digital-assets',
            'ai-automation-solutions',
        ])->delete();

        $products = [
            [
                'name' => 'Pulse Trading Intelligence',
                'slug' => 'pulse-trading-intelligence',
                'category' => 'Trading Intelligence',
                'tagline' => 'Market intelligence, risk controls and connected trading tools',
                'description' => 'Pulse brings live market scanning, explainable signals, strategy evidence, personal risk controls, secure Binance connectivity, order monitoring, trade history, alerts and performance reporting into one focused platform.',
                'icon' => '⌁',
                'accent' => 'violet',
                'features' => ['Market scanner', 'Explainable signals', 'Risk controls', 'Practice and Live exchange connections', 'Orders and positions', 'Trade history', 'Alerts and reports', 'Versioned mobile API'],
                'status' => 'live',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'name' => 'Private Member Portal',
                'slug' => 'private-member-portal',
                'category' => 'Invitation-Only Reporting',
                'tagline' => 'Secure reporting for invited private members',
                'description' => 'A secure, invitation-only area where members can review administrator-maintained account values, contributions, transactions, monthly statements and downloadable reports assigned to their account.',
                'icon' => '▣',
                'accent' => 'orange',
                'features' => ['Secure member dashboard', 'Current account value', 'Contribution history', 'Transaction records', 'Monthly statements', 'Downloadable reports', 'Administrator-controlled access'],
                'status' => 'live',
                'sort_order' => 2,
                'is_featured' => false,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['slug' => $product['slug']], $product);
        }

        SiteSetting::updateOrCreate(['key' => 'brand_tagline'], ['value' => 'Pulse Trading Intelligence & Private Member Reporting', 'type' => 'string', 'group' => 'brand']);
        SiteSetting::updateOrCreate(['key' => 'content_disclaimer'], ['value' => 'Market information, signals and calculators are general tools and do not guarantee a trading outcome.', 'type' => 'string', 'group' => 'legal']);
        SiteSetting::updateOrCreate(['key' => 'market_data_attribution'], ['value' => 'Live prices and charts use public provider endpoints. External headlines link to their original publishers.', 'type' => 'string', 'group' => 'legal']);
        SiteSetting::updateOrCreate(['key' => 'mobile_minimum_version'], ['value' => '1.0.0', 'type' => 'string', 'group' => 'mobile']);
        SiteSetting::updateOrCreate(['key' => 'mobile_recommended_version'], ['value' => '1.0.0', 'type' => 'string', 'group' => 'mobile']);
        SiteSetting::updateOrCreate(['key' => 'mobile_maintenance_mode'], ['value' => 'false', 'type' => 'boolean', 'group' => 'mobile']);
        SiteSetting::updateOrCreate(['key' => 'mobile_maintenance_message'], ['value' => 'Alpha Block Solutions is temporarily undergoing scheduled maintenance. Please try again shortly.', 'type' => 'string', 'group' => 'mobile']);
        SiteSetting::firstOrCreate(['key' => 'admin_notification_email'], ['value' => 'i@armansabir.com', 'type' => 'string', 'group' => 'communications']);
        SiteSetting::firstOrCreate(['key' => 'admin_notify_new_registration'], ['value' => '1', 'type' => 'boolean', 'group' => 'communications']);
        SiteSetting::firstOrCreate(['key' => 'admin_notify_new_subscription'], ['value' => '1', 'type' => 'boolean', 'group' => 'communications']);

        $this->seedPulse($admin);
    }

    /**
     * Seed Pulse configuration and access controls only.
     * Public content contains no fabricated market data. V14.0 does not create sample user accounts.
     */
    private function seedPulse(?User $admin): void
    {
        $required = [
            'pulse_plans', 'pulse_plan_strategies', 'pulse_strategies', 'pulse_pairs',
            'pulse_system_settings', 'user_service_access', 'pulse_user_settings',
        ];

        foreach ($required as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        // V13.7 publishes two customer membership packages and keeps Trial as a
        // concise onboarding entitlement instead of a third pricing card.
        $fullCapabilities = array_fill_keys(array_keys(PulsePlan::CAPABILITIES), true);
        $intelligenceCapabilities = array_fill_keys(array_keys(PulsePlan::CAPABILITIES), false);
        foreach (['scanner','signals','reports','alerts','plan_view','settings','mobile_api'] as $capability) {
            $intelligenceCapabilities[$capability] = true;
        }

        $professionalCapabilities = $fullCapabilities;
        $plans = [
            [
                'name' => 'Pulse Trial',
                'slug' => 'pulse-trial',
                'description' => 'Explore Pulse market intelligence, signals, risk controls and trading tools through a guided evaluation experience.',
                'monthly_price' => 0,
                'currency' => 'USDT',
                'scanner_runs_per_day' => 50,
                'signals_per_day' => 100,
                'manual_trades_per_day' => 25,
                'auto_trades_per_day' => 10,
                'max_open_trades' => 5,
                'max_selected_pairs' => 20,
                'allow_testnet_trading' => true,
                'allow_manual_trading' => true,
                'allow_live_trading' => true,
                'allow_auto_trading' => true,
                'allow_mobile_api' => true,
                'capabilities' => $fullCapabilities,
                'is_active' => true,
                'sort_order' => 1,
                'is_trial' => true,
                'is_public' => false,
                'request_enabled' => false,
                'requires_payment' => false,
                'access_days' => 7,
                'badge' => null,
                'is_featured' => false,
            ],
            [
                'name' => 'Pulse Intelligence',
                'slug' => 'pulse-intelligence',
                'description' => 'Market scanning, explainable strategy scoring, signal review, alerts and reporting without exchange execution.',
                'monthly_price' => 29,
                'currency' => 'USDT',
                'scanner_runs_per_day' => 5,
                'signals_per_day' => 10,
                'manual_trades_per_day' => 0,
                'auto_trades_per_day' => 0,
                'max_open_trades' => 0,
                'max_selected_pairs' => 5,
                'allow_testnet_trading' => false,
                'allow_manual_trading' => false,
                'allow_live_trading' => false,
                'allow_auto_trading' => false,
                'allow_mobile_api' => true,
                'capabilities' => $intelligenceCapabilities,
                'is_active' => true,
                'sort_order' => 10,
                'is_trial' => false,
                'is_public' => true,
                'request_enabled' => true,
                'requires_payment' => true,
                'access_days' => 30,
                'badge' => null,
                'is_featured' => false,
            ],
            [
                'name' => 'Pulse Professional',
                'slug' => 'pulse-professional',
                'description' => 'Expanded limits, advanced market intelligence and permission-ready trading workflows protected by platform safety controls.',
                'monthly_price' => 79,
                'currency' => 'USDT',
                'scanner_runs_per_day' => 100,
                'signals_per_day' => 200,
                'manual_trades_per_day' => 50,
                'auto_trades_per_day' => 25,
                'max_open_trades' => 5,
                'max_selected_pairs' => 20,
                'allow_testnet_trading' => true,
                'allow_manual_trading' => true,
                'allow_live_trading' => true,
                'allow_auto_trading' => true,
                'allow_mobile_api' => true,
                'capabilities' => $professionalCapabilities,
                'is_active' => true,
                'sort_order' => 20,
                'is_trial' => false,
                'is_public' => true,
                'request_enabled' => true,
                'requires_payment' => true,
                'access_days' => 30,
                'badge' => 'Professional',
                'is_featured' => true,
            ],
        ];

        $planModels = [];
        $newPlanIds = [];
        foreach ($plans as $plan) {
            $model = PulsePlan::firstOrCreate(['slug' => $plan['slug']], $plan);
            $planModels[$model->slug] = $model;
            if ($model->wasRecentlyCreated) $newPlanIds[] = $model->id;
        }

        // V14.6.13: repair legacy paid-plan rows that were created with a zero
        // price in earlier local databases. Only the two canonical paid plans
        // are repaired, and only when their stored price is missing/zero.
        // Administrator-managed non-zero prices are preserved.
        foreach (['pulse-intelligence' => 29.0, 'pulse-professional' => 79.0] as $slug => $defaultPrice) {
            $planModel = $planModels[$slug] ?? null;
            if ($planModel && (float) $planModel->monthly_price <= 0.0) {
                $planModel->forceFill([
                    'monthly_price' => $defaultPrice,
                    'currency' => $planModel->currency ?: 'USDT',
                    'requires_payment' => true,
                ])->save();
            }
        }

        // Upgrade the known legacy Trial classification without overwriting any
        // administrator-managed limits, capabilities or descriptive fields.
        if (isset($planModels['pulse-trial'])) {
            $planModels['pulse-trial']->forceFill([
                'is_trial' => true,
                'is_public' => false,
                'request_enabled' => false,
                'requires_payment' => false,
            ])->save();
        }

        PulsePlan::query()->whereIn('slug', ['pulse-testnet-trader'])->update(['is_public' => false, 'request_enabled' => false]);

        // Preserve one-time Trial history when upgrading existing V13.x databases.
        UserServiceAccess::query()
            ->where('service','pulse')
            ->whereIn('pulse_plan_id', collect($planModels)->filter(fn($p)=>$p->is_trial)->pluck('id'))
            ->whereNull('trial_used_at')
            ->update(['trial_used_at' => now()]);

        $strategies = [
            ['Trend Alignment', 'trend-alignment', 'Checks whether price, EMA20 and EMA50 agree on a directional trend.', 1.00, 1],
            ['EMA Crossover', 'ema-crossover', 'Compares EMA9 with EMA20 to identify short-term momentum alignment.', 1.00, 2],
            ['RSI Recovery', 'rsi-recovery', 'Uses RSI recovery zones to avoid treating extreme readings as automatic entries.', 1.00, 3],
            ['MACD Momentum', 'macd-momentum', 'Compares MACD with its signal line for directional momentum confirmation.', 1.00, 4],
            ['Breakout Confirmation', 'breakout-confirmation', 'Looks for a close beyond the previous twenty-candle trading range.', 1.00, 5],
            ['Volume Expansion', 'volume-expansion', 'Requires materially higher volume before awarding participation points.', 1.00, 6],
            ['ATR Volatility Filter', 'atr-volatility', 'Keeps the setup inside a configurable volatility operating range.', 1.00, 7],
            ['Market Structure', 'market-structure', 'Reviews recent highs and lows for directional structure.', 1.00, 8],
            ['Trend Pullback', 'trend-pullback', 'Looks for price holding near EMA20 while the broader EMA trend remains aligned.', 1.00, 9],
            ['Bollinger Reversion', 'bollinger-reversion', 'Identifies controlled mean-reversion conditions using Bollinger bands and RSI.', 1.00, 10],
            ['Momentum Continuation', 'momentum-continuation', 'Checks five-candle momentum in the direction of the EMA20 trend.', 1.00, 11],
            ['Range Compression', 'range-compression', 'Detects compressed Bollinger width that can precede directional expansion.', 1.00, 12],
            ['Candle Strength', 'candle-strength', 'Measures whether the latest candle body is decisive relative to its full range.', 1.00, 13],
            ['Swing Sequence', 'swing-sequence', 'Compares recent closing sequences for consistent directional progression.', 1.00, 14],
            ['Risk/Reward Quality', 'risk-reward-quality', 'Confirms that ATR supports calculable stop-loss and take-profit distances.', 1.00, 15],
        ];

        $strategyModels = [];
        foreach ($strategies as [$name, $slug, $description, $weight, $sortOrder]) {
            $strategyModels[] = PulseStrategy::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'timeframe' => '15m',
                    'weight' => $weight,
                    'minimum_score' => 0,
                    'settings' => [],
                    'is_enabled' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        foreach ($planModels as $plan) {
            // Seed default strategies only for newly created plans. Re-running
            // the repair/seeder must not erase administrator plan assignments.
            if (! in_array($plan->id, $newPlanIds, true)) continue;
            $sync = [];
            foreach ($strategyModels as $strategy) {
                $sync[$strategy->id] = ['is_enabled' => true, 'weight_override' => null];
            }
            $plan->strategies()->sync($sync);
        }

        $pairs = [
            ['BTCUSDT', 'BTC', 1],
            ['ETHUSDT', 'ETH', 2],
            ['BNBUSDT', 'BNB', 3],
            ['SOLUSDT', 'SOL', 4],
            ['XRPUSDT', 'XRP', 5],
        ];
        foreach ($pairs as [$symbol, $base, $sortOrder]) {
            PulsePair::firstOrCreate(
                ['symbol' => $symbol],
                [
                    'base_asset' => $base,
                    'quote_asset' => 'USDT',
                    'is_enabled' => true,
                    'sort_order' => $sortOrder,
                    'price_precision' => 8,
                    'quantity_precision' => 8,
                    'tick_size' => 0,
                    'step_size' => 0,
                    'minimum_quantity' => 0,
                    'minimum_notional' => 0,
                    'last_synced_at' => null,
                ],
            );
        }

        $systemSettings = [
            ['scanner_enabled', 'true', 'boolean', 'scanner', 'Allow Pulse users to run the market scanner.'],
            ['execution_enabled', 'true', 'boolean', 'execution', 'Master switch for user-requested exchange execution.'],
            ['testnet_trading_enabled', 'true', 'boolean', 'execution', 'Allow Practice-mode Binance execution for users whose plan includes manual trading.'],
            ['live_trading_enabled', 'false', 'boolean', 'execution', 'Platform-wide Live trading switch. Enable only after deployment, account and risk controls are complete.'],
            ['automatic_trading_enabled', 'false', 'boolean', 'automation', 'Platform-wide automatic trading switch. Enable only after scheduler, risk and reconciliation controls are complete.'],
            ['emergency_stop_all', 'false', 'boolean', 'risk', 'Global emergency stop for new Pulse execution.'],
            ['minimum_signal_score', '70', 'float', 'scanner', 'Default minimum score before a generated signal is considered actionable.'],
            ['signal_expiry_minutes', '90', 'integer', 'scanner', 'Minutes before an unexecuted signal is marked expired.'],
            ['risk_disclaimer', 'Pulse provides market-intelligence and user-authorized execution tools. Signals are not guarantees. Users remain responsible for exchange credentials, risk limits and trading decisions.', 'string', 'legal', 'Pulse risk notice displayed to users.'],
            ['membership_requests_enabled', 'true', 'boolean', 'membership', 'Allow customers to submit Pulse membership requests.'],
            ['usdt_wallet_address', '', 'string', 'membership', 'USDT wallet address displayed only during authenticated membership checkout.'],
            ['usdt_network', '', 'string', 'membership', 'USDT network customers must use for membership transfers.'],
            ['usdt_payment_instructions', 'Send the exact amount shown and submit the transaction reference for verification.', 'string', 'membership', 'Customer-facing membership payment instructions.'],
            ['payment_proof_required', 'false', 'boolean', 'membership', 'Require a screenshot or PDF payment proof in addition to the transaction reference.'],
            ['promotion_codes_enabled', 'true', 'boolean', 'membership', 'Allow coupons and gift vouchers during membership checkout.'],
            ['trial_auto_assign_enabled', 'true', 'boolean', 'membership', 'Automatically assign the active Trial to eligible new registrations.'],
            ['trial_banner_enabled', 'true', 'boolean', 'membership', 'Show a concise Trial availability banner on the public Pulse page.'],
            ['trial_duration_days', '7', 'integer', 'membership', 'Trial duration applied to newly registered accounts.'],
            ['transactional_emails_enabled', 'true', 'boolean', 'communications', 'Send branded account, plan request, activation and service-status emails.'],
            ['pulse_alert_emails_enabled', 'true', 'boolean', 'communications', 'Allow Pulse alerts selected for email delivery to be sent to users.'],
            ['promotion_emails_enabled', 'true', 'boolean', 'communications', 'Notify users when an account-specific coupon or gift voucher is assigned.'],
            ['trade_email_alerts_enabled', 'false', 'boolean', 'communications', 'Send trade-event email alerts in addition to in-platform alerts. Disabled by default to avoid unnecessary email volume.'],
            ['signal_email_alerts_enabled', 'true', 'boolean', 'communications', 'Send qualified Pulse signal email alerts to users who have enabled signal email notifications.'],
            ['expiry_emails_enabled', 'true', 'boolean', 'communications', 'Send Pulse access expiry reminders and expired-access notifications.'],
            ['daily_market_brief_enabled', 'false', 'boolean', 'communications', 'Send the optional Daily Market Brief to users who opt in. Disabled by default until the administrator enables it.'],
        ];
        foreach ($systemSettings as [$key, $value, $type, $group, $description]) {
            PulseSystemSetting::firstOrCreate(['key' => $key], compact('value', 'type', 'group', 'description'));
        }

        if ($admin) {
            $adminPlan = $planModels['pulse-trial'];
            UserServiceAccess::firstOrCreate(
                ['user_id' => $admin->id, 'service' => 'pulse'],
                [
                    'status' => 'active',
                    'pulse_plan_id' => $adminPlan->id,
                    'approved_by' => $admin->id,
                    'starts_at' => now(),
                    'ends_at' => null,
                    'trial_used_at' => now(),
                    'permissions' => [],
                    'notes' => 'Pulse Trial assigned to the ABS administrator. The plan supplies the complete Pulse feature set; platform-wide safety controls still govern Live and automatic execution.',
                ],
            );

            PulseUserSetting::firstOrCreate(
                ['user_id' => $admin->id],
                [
                    'environment' => 'testnet',
                    'execution_mode' => 'signal_only',
                    'auto_trade_enabled' => false,
                    'emergency_stop' => false,
                    'default_leverage' => 3,
                    'margin_type' => 'ISOLATED',
                    'position_mode' => 'BOTH',
                    'risk_per_trade_percent' => 1,
                    'sizing_mode' => 'fixed_notional',
                    'fixed_notional' => 25,
                    'fixed_quantity' => null,
                    'minimum_signal_score' => 70,
                    'default_order_type' => 'MARKET',
                    'take_profit_percent' => 2,
                    'stop_loss_percent' => 1,
                    'daily_loss_limit' => 0,
                    'max_open_positions' => 2,
                    'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
                    'notification_preferences' => ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true],
                ],
            );
        }


        // V14.0 never creates sample Standard User or Private Member accounts.
        // Remove any legacy local-review identities if they exist from an older build.
        $this->removeLocalReviewAccounts();
    }

    private function removeLocalReviewAccounts(): void
    {
        $reviewUsers = User::whereIn('email', [
            'pulse@alphablocksolutions.local',
            'member@alphablocksolutions.local',
        ])->get();

        foreach ($reviewUsers as $reviewUser) {
            if (Schema::hasTable('portfolio_accounts')) {
                $accountIds = PortfolioAccount::where('user_id', $reviewUser->id)->pluck('id');
                if ($accountIds->isNotEmpty()) {
                    if (Schema::hasTable('portfolio_transactions')) {
                        PortfolioTransaction::whereIn('portfolio_account_id', $accountIds)->delete();
                    }
                    if (Schema::hasTable('monthly_statements')) {
                        MonthlyStatement::whereIn('portfolio_account_id', $accountIds)->delete();
                    }
                    PortfolioAccount::whereIn('id', $accountIds)->delete();
                }
            }
            if (Schema::hasTable('watchlists')) {
                Watchlist::where('user_id', $reviewUser->id)->delete();
            }
            foreach (['binance_connections', 'pulse_scanner_runs', 'pulse_signals', 'pulse_trades', 'pulse_alerts', 'pulse_audit_logs', 'pulse_automation_runs'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                    DB::table($table)->where('user_id', $reviewUser->id)->delete();
                }
            }
            if (Schema::hasTable('user_service_access')) {
                UserServiceAccess::where('user_id', $reviewUser->id)->delete();
            }
            if (Schema::hasTable('pulse_user_settings')) {
                PulseUserSetting::where('user_id', $reviewUser->id)->delete();
            }
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $reviewUser->id)
                    ->delete();
            }
            $reviewUser->delete();
        }
    }

    private function removeLegacySampleRecords(): void
    {
        if (Schema::hasTable('news_articles')) {
            NewsArticle::whereIn('slug', [
                'daily-crypto-market-brief-reading-momentum-without-chasing-price',
                'why-bitcoin-dominance-matters-during-altcoin-rotations',
                'smart-contract-security-five-checks-before-mainnet',
                'how-economic-events-can-increase-crypto-volatility',
                'utility-tokens-versus-speculative-tokens',
                'building-a-trading-plan-around-risk-not-predictions',
            ])->forceDelete();
        }

        if (Schema::hasTable('research_reports')) {
            ResearchReport::whereIn('slug', [
                'digital-asset-market-review-framework',
                'blockchain-project-due-diligence-framework',
                'web3-infrastructure-landscape',
            ])->forceDelete();
        }

        if (Schema::hasTable('learning_articles')) {
            LearningArticle::whereIn('slug', [
                'blockchain-basics-how-a-shared-ledger-works',
                'risk-management-before-entering-a-trade',
                'understanding-smart-contract-permissions',
                'market-structure-liquidity-and-execution',
            ])->forceDelete();
        }

        if (Schema::hasTable('community_posts')) {
            CommunityPost::where('title', 'Welcome to the Alpha Block Solutions Community')->delete();
        }

        if (Schema::hasTable('economic_events')) {
            EconomicEvent::where('source', 'Admin maintained')->delete();
        }

        if (Schema::hasTable('users')) {
            $legacySampleUsers = User::whereIn('email', [
                'user@alphablocksolutions.local',
            ])->get();

            foreach ($legacySampleUsers as $legacySampleUser) {
                if (Schema::hasTable('portfolio_accounts')) {
                    $accountIds = PortfolioAccount::where('user_id', $legacySampleUser->id)->pluck('id');
                    if ($accountIds->isNotEmpty()) {
                        if (Schema::hasTable('portfolio_transactions')) {
                            PortfolioTransaction::whereIn('portfolio_account_id', $accountIds)->delete();
                        }
                        if (Schema::hasTable('monthly_statements')) {
                            MonthlyStatement::whereIn('portfolio_account_id', $accountIds)->delete();
                        }
                        PortfolioAccount::whereIn('id', $accountIds)->delete();
                    }
                }
                if (Schema::hasTable('watchlists')) {
                    Watchlist::where('user_id', $legacySampleUser->id)->delete();
                }
                if (Schema::hasTable('community_posts')) {
                    CommunityPost::where('user_id', $legacySampleUser->id)->delete();
                }
                $legacySampleUser->delete();
            }
        }
    }
}
