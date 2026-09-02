<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PulseSchemaRepair
{
    public const REQUIRED_SCHEMA = [
        'pulse_plans' => ['id','name','slug','description','monthly_price','currency','scanner_runs_per_day','signals_per_day','minimum_signal_score','manual_trades_per_day','auto_trades_per_day','max_open_trades','max_selected_pairs','pair_access_mode','allow_testnet_trading','allow_manual_trading','allow_live_trading','allow_auto_trading','allow_mobile_api','capabilities','is_active','sort_order','is_trial','is_public','request_enabled','requires_payment','access_days','badge','is_featured','created_at','updated_at'],
        'pulse_plan_strategies' => ['id','pulse_plan_id','pulse_strategy_id','is_enabled','weight_override','created_at','updated_at'],
        'pulse_plan_pairs' => ['id','pulse_plan_id','pulse_pair_id','is_enabled','created_at','updated_at'],
        'user_service_access' => ['id','user_id','service','status','pulse_plan_id','approved_by','starts_at','ends_at','trial_used_at','permissions','notes','created_at','updated_at'],
        'pulse_strategies' => ['id','name','slug','version','description','timeframe','weight','minimum_score','settings','is_enabled','sort_order','created_at','updated_at'],
        'pulse_pairs' => ['id','symbol','base_asset','quote_asset','is_enabled','sort_order','price_precision','quantity_precision','tick_size','step_size','minimum_quantity','minimum_notional','last_synced_at','created_at','updated_at'],
        'pulse_user_settings' => ['id','user_id','environment','execution_mode','auto_trade_enabled','emergency_stop','default_leverage','margin_type','position_mode','risk_per_trade_percent','sizing_mode','fixed_notional','fixed_quantity','minimum_signal_score','default_order_type','take_profit_percent','stop_loss_percent','daily_loss_limit','max_open_positions','selected_pairs','notification_preferences','pair_selection_saved_at','pair_selection_locked_until','created_at','updated_at'],
        'binance_connections' => ['id','user_id','environment','label','api_key','api_secret','is_active','permissions','last_tested_at','last_error','created_at','updated_at'],
        'pulse_scanner_runs' => ['id','user_id','status','timeframe','pairs_scanned','signals_created','started_at','completed_at','summary','error_message','created_at','updated_at'],
        'pulse_signals' => ['id','user_id','scanner_run_id','symbol','timeframe','direction','entry_price','stop_loss','take_profit','score','confidence_label','status','strategy_breakdown','generated_at','expires_at','signal_fingerprint','strategy_version','strategy_snapshot','take_profit_levels','technical_score','reliability_score','confidence_score','created_at','updated_at'],
        'pulse_trades' => ['id','user_id','signal_id','symbol','side','environment','order_type','leverage','quantity','entry_price','current_price','stop_loss','take_profit','exchange_order_id','exchange_tp_order_id','exchange_sl_order_id','exchange_close_order_id','exchange_position_side','status','protection_status','realized_pnl','unrealized_pnl','fees','commission_asset','opened_at','closed_at','last_synced_at','close_reason','meta','created_at','updated_at'],
        'pulse_alerts' => ['id','user_id','type','title','message','severity','is_read','action_url','data','created_at','updated_at'],
        'pulse_system_settings' => ['id','key','value','type','group','description'],
        'pulse_audit_logs' => ['id','user_id','action','entity_type','entity_id','environment','ip_address','context','created_at'],
        'pulse_automation_runs' => ['id','user_id','status','environment','signals_reviewed','trades_created','summary','error_message','started_at','completed_at','created_at','updated_at'],
        'pulse_promotion_codes' => ['id','code','label','type','discount_type','discount_value','applicable_plan_id','assigned_user_id','access_days','max_uses','per_user_limit','valid_from','valid_until','auto_activate','is_active','notes','created_by','created_at','updated_at'],
        'pulse_membership_requests' => ['id','user_id','pulse_plan_id','status','base_amount','discount_amount','final_amount','currency','network','wallet_address_snapshot','payment_reference','payment_proof_path','promotion_code_id','promotion_code_snapshot','activation_days','user_notes','admin_notes','reviewed_by','reviewed_at','activated_access_id','created_at','updated_at'],
        'pulse_market_prices' => ['id','symbol','price','change_percent_24h','high_24h','low_24h','volume_24h','source','observed_at','created_at','updated_at'],
        'pulse_market_candles' => ['id','symbol','timeframe','open_time_ms','close_time_ms','open','high','low','close','volume','is_closed','source','created_at','updated_at'],
        'pulse_market_data_runs' => ['id','status','prices_updated','candle_symbols_updated','validation_symbols_updated','summary','error_message','started_at','completed_at','created_at','updated_at'],
        'pulse_signal_validations' => ['id','signal_id','user_id','signal_fingerprint','symbol','timeframe','direction','strategy_version','strategy_snapshot','entry_price','stop_loss','take_profit_levels','technical_score','reliability_score','confidence_score','state','outcome','generated_at','entry_hit_at','resolved_at','last_checked_at','entry_observed_price','mfe_price','mae_price','mfe_r','mae_r','duration_seconds','highest_tp_level_hit','market_regime','context','meta','created_at','updated_at'],
        'pulse_signal_daily_metrics' => ['id','metric_date','user_id','timeframe','direction','signals','entries','wins','losses','ambiguous','expired_no_entry','expired_after_entry','avg_mfe_r','avg_mae_r','avg_duration_seconds','created_at','updated_at'],
        'pulse_strategy_daily_metrics' => ['id','metric_date','strategy_slug','strategy_version','timeframe','direction','market_regime','sample_count','entries','wins','losses','ambiguous','expired_no_entry','avg_mfe_r','avg_mae_r','avg_duration_seconds','created_at','updated_at'],
        'pulse_strategy_learning_states' => ['id','strategy_slug','strategy_version','timeframe','direction','market_regime','sample_size','win_rate','ambiguous_rate','reliability_score','recency_weighted_score','evidence_level','meta','calculated_at','created_at','updated_at'],
        'pulse_promotion_redemptions' => ['id','promotion_code_id','user_id','pulse_plan_id','membership_request_id','discount_amount','redeemed_at','created_at','updated_at'],
    ];

    public static function repair(): void
    {
        self::plans();
        self::access();
        self::strategies();
        self::pairs();
        self::userSettings();
        self::connections();
        self::scannerRuns();
        self::signals();
        self::trades();
        self::alerts();
        self::systemSettings();
        self::auditLogs();
        self::memberships();
        self::priceArchitecture();
        self::enhancements();
    }

    private static function plans(): void
    {
        if (! Schema::hasTable('pulse_plans')) {
            Schema::create('pulse_plans', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 12, 2)->default(0);
                $table->string('currency', 8)->default('USD');
                $table->unsignedInteger('scanner_runs_per_day')->default(5);
                $table->unsignedInteger('signals_per_day')->default(10);
                $table->decimal('minimum_signal_score', 6, 2)->default(70);
                $table->unsignedInteger('auto_trades_per_day')->default(0);
                $table->unsignedInteger('max_open_trades')->default(2);
                $table->unsignedInteger('max_selected_pairs')->default(5);
                $table->boolean('allow_live_trading')->default(false);
                $table->boolean('allow_auto_trading')->default(false);
                $table->json('capabilities')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
            return;
        }

        self::addMissing('pulse_plans', [
            'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
            'slug' => fn (Blueprint $t) => $t->string('slug')->nullable(),
            'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
            'monthly_price' => fn (Blueprint $t) => $t->decimal('monthly_price',12,2)->default(0),
            'currency' => fn (Blueprint $t) => $t->string('currency',8)->default('USD'),
            'scanner_runs_per_day' => fn (Blueprint $t) => $t->unsignedInteger('scanner_runs_per_day')->default(5),
            'signals_per_day' => fn (Blueprint $t) => $t->unsignedInteger('signals_per_day')->default(10),
            'minimum_signal_score' => fn (Blueprint $t) => $t->decimal('minimum_signal_score',6,2)->default(70),
            'auto_trades_per_day' => fn (Blueprint $t) => $t->unsignedInteger('auto_trades_per_day')->default(0),
            'max_open_trades' => fn (Blueprint $t) => $t->unsignedInteger('max_open_trades')->default(2),
            'max_selected_pairs' => fn (Blueprint $t) => $t->unsignedInteger('max_selected_pairs')->default(5),
            'allow_live_trading' => fn (Blueprint $t) => $t->boolean('allow_live_trading')->default(false),
            'allow_auto_trading' => fn (Blueprint $t) => $t->boolean('allow_auto_trading')->default(false),
            'capabilities' => fn (Blueprint $t) => $t->json('capabilities')->nullable(),
            'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
            'sort_order' => fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function access(): void
    {
        if (! Schema::hasTable('user_service_access')) {
            Schema::create('user_service_access', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('service', 50)->default('pulse')->index();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedBigInteger('pulse_plan_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('permissions')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['user_id','service']);
            });
            return;
        }

        self::addMissing('user_service_access', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'service' => fn (Blueprint $t) => $t->string('service',50)->default('pulse'),
            'status' => fn (Blueprint $t) => $t->string('status',30)->default('pending'),
            'pulse_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pulse_plan_id')->nullable(),
            'approved_by' => fn (Blueprint $t) => $t->unsignedBigInteger('approved_by')->nullable(),
            'starts_at' => fn (Blueprint $t) => $t->timestamp('starts_at')->nullable(),
            'ends_at' => fn (Blueprint $t) => $t->timestamp('ends_at')->nullable(),
            'trial_used_at' => fn (Blueprint $t) => $t->timestamp('trial_used_at')->nullable(),
            'permissions' => fn (Blueprint $t) => $t->json('permissions')->nullable(),
            'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function strategies(): void
    {
        if (! Schema::hasTable('pulse_strategies')) {
            Schema::create('pulse_strategies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('timeframe', 12)->default('15m');
                $table->decimal('weight', 6, 2)->default(1);
                $table->decimal('minimum_score', 6, 2)->default(0);
                $table->json('settings')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
            return;
        }

        self::addMissing('pulse_strategies', [
            'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
            'slug' => fn (Blueprint $t) => $t->string('slug')->nullable(),
            'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
            'timeframe' => fn (Blueprint $t) => $t->string('timeframe',12)->default('15m'),
            'weight' => fn (Blueprint $t) => $t->decimal('weight',6,2)->default(1),
            'minimum_score' => fn (Blueprint $t) => $t->decimal('minimum_score',6,2)->default(0),
            'settings' => fn (Blueprint $t) => $t->json('settings')->nullable(),
            'is_enabled' => fn (Blueprint $t) => $t->boolean('is_enabled')->default(true),
            'sort_order' => fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function pairs(): void
    {
        if (! Schema::hasTable('pulse_pairs')) {
            Schema::create('pulse_pairs', function (Blueprint $table): void {
                $table->id();
                $table->string('symbol', 30)->unique();
                $table->string('base_asset', 20);
                $table->string('quote_asset', 20)->default('USDT');
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('price_precision')->default(2);
                $table->unsignedInteger('quantity_precision')->default(3);
                $table->decimal('tick_size', 20, 12)->nullable();
                $table->decimal('step_size', 20, 12)->nullable();
                $table->decimal('minimum_quantity', 20, 12)->nullable();
                $table->decimal('minimum_notional', 20, 8)->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
            return;
        }

        self::addMissing('pulse_pairs', [
            'symbol' => fn (Blueprint $t) => $t->string('symbol',30)->nullable(),
            'base_asset' => fn (Blueprint $t) => $t->string('base_asset',20)->nullable(),
            'quote_asset' => fn (Blueprint $t) => $t->string('quote_asset',20)->default('USDT'),
            'is_enabled' => fn (Blueprint $t) => $t->boolean('is_enabled')->default(true),
            'sort_order' => fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0),
            'price_precision' => fn (Blueprint $t) => $t->unsignedInteger('price_precision')->default(2),
            'quantity_precision' => fn (Blueprint $t) => $t->unsignedInteger('quantity_precision')->default(3),
            'tick_size' => fn (Blueprint $t) => $t->decimal('tick_size',20,12)->nullable(),
            'step_size' => fn (Blueprint $t) => $t->decimal('step_size',20,12)->nullable(),
            'minimum_quantity' => fn (Blueprint $t) => $t->decimal('minimum_quantity',20,12)->nullable(),
            'minimum_notional' => fn (Blueprint $t) => $t->decimal('minimum_notional',20,8)->nullable(),
            'last_synced_at' => fn (Blueprint $t) => $t->timestamp('last_synced_at')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function userSettings(): void
    {
        if (! Schema::hasTable('pulse_user_settings')) {
            Schema::create('pulse_user_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('environment', 20)->default('testnet');
                $table->boolean('auto_trade_enabled')->default(false);
                $table->unsignedInteger('default_leverage')->default(3);
                $table->decimal('risk_per_trade_percent', 6, 2)->default(1);
                $table->string('default_order_type', 20)->default('MARKET');
                $table->decimal('take_profit_percent', 6, 2)->default(2);
                $table->decimal('stop_loss_percent', 6, 2)->default(1);
                $table->decimal('daily_loss_limit', 14, 2)->default(0);
                $table->unsignedInteger('max_open_positions')->default(2);
                $table->json('selected_pairs')->nullable();
                $table->json('notification_preferences')->nullable();
                $table->timestamp('pair_selection_saved_at')->nullable();
                $table->timestamp('pair_selection_locked_until')->nullable();
                $table->timestamps();
            });
            return;
        }

        self::addMissing('user_service_access', [
            'trial_used_at' => fn (Blueprint $t) => $t->timestamp('trial_used_at')->nullable(),
        ]);

        self::addMissing('pulse_user_settings', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'environment' => fn (Blueprint $t) => $t->string('environment',20)->default('testnet'),
            'auto_trade_enabled' => fn (Blueprint $t) => $t->boolean('auto_trade_enabled')->default(false),
            'default_leverage' => fn (Blueprint $t) => $t->unsignedInteger('default_leverage')->default(3),
            'risk_per_trade_percent' => fn (Blueprint $t) => $t->decimal('risk_per_trade_percent',6,2)->default(1),
            'default_order_type' => fn (Blueprint $t) => $t->string('default_order_type',20)->default('MARKET'),
            'take_profit_percent' => fn (Blueprint $t) => $t->decimal('take_profit_percent',6,2)->default(2),
            'stop_loss_percent' => fn (Blueprint $t) => $t->decimal('stop_loss_percent',6,2)->default(1),
            'daily_loss_limit' => fn (Blueprint $t) => $t->decimal('daily_loss_limit',14,2)->default(0),
            'max_open_positions' => fn (Blueprint $t) => $t->unsignedInteger('max_open_positions')->default(2),
            'selected_pairs' => fn (Blueprint $t) => $t->json('selected_pairs')->nullable(),
            'notification_preferences' => fn (Blueprint $t) => $t->json('notification_preferences')->nullable(),
            'pair_selection_saved_at' => fn (Blueprint $t) => $t->timestamp('pair_selection_saved_at')->nullable(),
            'pair_selection_locked_until' => fn (Blueprint $t) => $t->timestamp('pair_selection_locked_until')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function connections(): void
    {
        if (! Schema::hasTable('binance_connections')) {
            Schema::create('binance_connections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('environment', 20)->default('testnet');
                $table->string('label')->default('Binance USD-M Futures');
                $table->text('api_key');
                $table->text('api_secret');
                $table->boolean('is_active')->default(true);
                $table->json('permissions')->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->unique(['user_id','environment']);
            });
            return;
        }

        self::addMissing('binance_connections', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'environment' => fn (Blueprint $t) => $t->string('environment',20)->default('testnet'),
            'label' => fn (Blueprint $t) => $t->string('label')->default('Binance USD-M Futures'),
            'api_key' => fn (Blueprint $t) => $t->text('api_key')->nullable(),
            'api_secret' => fn (Blueprint $t) => $t->text('api_secret')->nullable(),
            'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
            'permissions' => fn (Blueprint $t) => $t->json('permissions')->nullable(),
            'last_tested_at' => fn (Blueprint $t) => $t->timestamp('last_tested_at')->nullable(),
            'last_error' => fn (Blueprint $t) => $t->text('last_error')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function scannerRuns(): void
    {
        if (! Schema::hasTable('pulse_scanner_runs')) {
            Schema::create('pulse_scanner_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('status', 30)->default('running')->index();
                $table->string('timeframe', 12)->default('15m');
                $table->unsignedInteger('pairs_scanned')->default(0);
                $table->unsignedInteger('signals_created')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
            return;
        }
        self::addMissing('pulse_scanner_runs', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'status' => fn (Blueprint $t) => $t->string('status',30)->default('running'),
            'timeframe' => fn (Blueprint $t) => $t->string('timeframe',12)->default('15m'),
            'pairs_scanned' => fn (Blueprint $t) => $t->unsignedInteger('pairs_scanned')->default(0),
            'signals_created' => fn (Blueprint $t) => $t->unsignedInteger('signals_created')->default(0),
            'started_at' => fn (Blueprint $t) => $t->timestamp('started_at')->nullable(),
            'completed_at' => fn (Blueprint $t) => $t->timestamp('completed_at')->nullable(),
            'summary' => fn (Blueprint $t) => $t->json('summary')->nullable(),
            'error_message' => fn (Blueprint $t) => $t->text('error_message')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function signals(): void
    {
        if (! Schema::hasTable('pulse_signals')) {
            Schema::create('pulse_signals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('scanner_run_id')->nullable()->index();
                $table->string('symbol', 30)->index();
                $table->string('timeframe', 12)->default('15m');
                $table->string('direction', 12)->index();
                $table->decimal('entry_price', 24, 12);
                $table->decimal('stop_loss', 24, 12)->nullable();
                $table->decimal('take_profit', 24, 12)->nullable();
                $table->decimal('score', 6, 2)->default(0);
                $table->string('confidence_label', 30)->default('Review');
                $table->string('status', 30)->default('active')->index();
                $table->json('strategy_breakdown')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
            return;
        }
        self::addMissing('pulse_signals', [
            'scanner_run_id' => fn (Blueprint $t) => $t->unsignedBigInteger('scanner_run_id')->nullable(),
            'symbol' => fn (Blueprint $t) => $t->string('symbol',30)->nullable(),
            'timeframe' => fn (Blueprint $t) => $t->string('timeframe',12)->default('15m'),
            'direction' => fn (Blueprint $t) => $t->string('direction',12)->default('NEUTRAL'),
            'entry_price' => fn (Blueprint $t) => $t->decimal('entry_price',24,12)->default(0),
            'stop_loss' => fn (Blueprint $t) => $t->decimal('stop_loss',24,12)->nullable(),
            'take_profit' => fn (Blueprint $t) => $t->decimal('take_profit',24,12)->nullable(),
            'score' => fn (Blueprint $t) => $t->decimal('score',6,2)->default(0),
            'confidence_label' => fn (Blueprint $t) => $t->string('confidence_label',30)->default('Review'),
            'status' => fn (Blueprint $t) => $t->string('status',30)->default('active'),
            'strategy_breakdown' => fn (Blueprint $t) => $t->json('strategy_breakdown')->nullable(),
            'generated_at' => fn (Blueprint $t) => $t->timestamp('generated_at')->nullable(),
            'expires_at' => fn (Blueprint $t) => $t->timestamp('expires_at')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function trades(): void
    {
        if (! Schema::hasTable('pulse_trades')) {
            Schema::create('pulse_trades', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('signal_id')->nullable()->index();
                $table->string('symbol', 30)->index();
                $table->string('side', 12);
                $table->string('environment', 20)->default('testnet');
                $table->string('order_type', 20)->default('MARKET');
                $table->unsignedInteger('leverage')->default(1);
                $table->decimal('quantity', 24, 12);
                $table->decimal('entry_price', 24, 12)->nullable();
                $table->decimal('current_price', 24, 12)->nullable();
                $table->decimal('stop_loss', 24, 12)->nullable();
                $table->decimal('take_profit', 24, 12)->nullable();
                $table->string('exchange_order_id', 100)->nullable();
                $table->string('exchange_tp_order_id', 100)->nullable();
                $table->string('exchange_sl_order_id', 100)->nullable();
                $table->string('exchange_position_side', 20)->default('BOTH');
                $table->string('status', 30)->default('pending')->index();
                $table->decimal('realized_pnl', 20, 8)->default(0);
                $table->decimal('fees', 20, 8)->default(0);
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('close_reason')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
            return;
        }
        self::addMissing('pulse_trades', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'signal_id' => fn (Blueprint $t) => $t->unsignedBigInteger('signal_id')->nullable(),
            'symbol' => fn (Blueprint $t) => $t->string('symbol',30)->nullable(),
            'side' => fn (Blueprint $t) => $t->string('side',12)->nullable(),
            'environment' => fn (Blueprint $t) => $t->string('environment',20)->default('testnet'),
            'order_type' => fn (Blueprint $t) => $t->string('order_type',20)->default('MARKET'),
            'leverage' => fn (Blueprint $t) => $t->unsignedInteger('leverage')->default(1),
            'quantity' => fn (Blueprint $t) => $t->decimal('quantity',24,12)->default(0),
            'entry_price' => fn (Blueprint $t) => $t->decimal('entry_price',24,12)->nullable(),
            'current_price' => fn (Blueprint $t) => $t->decimal('current_price',24,12)->nullable(),
            'stop_loss' => fn (Blueprint $t) => $t->decimal('stop_loss',24,12)->nullable(),
            'take_profit' => fn (Blueprint $t) => $t->decimal('take_profit',24,12)->nullable(),
            'exchange_order_id' => fn (Blueprint $t) => $t->string('exchange_order_id',100)->nullable(),
            'exchange_tp_order_id' => fn (Blueprint $t) => $t->string('exchange_tp_order_id',100)->nullable(),
            'exchange_sl_order_id' => fn (Blueprint $t) => $t->string('exchange_sl_order_id',100)->nullable(),
            'exchange_position_side' => fn (Blueprint $t) => $t->string('exchange_position_side',20)->default('BOTH'),
            'status' => fn (Blueprint $t) => $t->string('status',30)->default('pending'),
            'realized_pnl' => fn (Blueprint $t) => $t->decimal('realized_pnl',20,8)->default(0),
            'fees' => fn (Blueprint $t) => $t->decimal('fees',20,8)->default(0),
            'opened_at' => fn (Blueprint $t) => $t->timestamp('opened_at')->nullable(),
            'closed_at' => fn (Blueprint $t) => $t->timestamp('closed_at')->nullable(),
            'close_reason' => fn (Blueprint $t) => $t->string('close_reason')->nullable(),
            'meta' => fn (Blueprint $t) => $t->json('meta')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function alerts(): void
    {
        if (! Schema::hasTable('pulse_alerts')) {
            Schema::create('pulse_alerts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 40)->index();
                $table->string('title');
                $table->text('message');
                $table->string('severity', 20)->default('info');
                $table->boolean('is_read')->default(false)->index();
                $table->string('action_url')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();
            });
            return;
        }
        self::addMissing('pulse_alerts', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'type' => fn (Blueprint $t) => $t->string('type',40)->default('system'),
            'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
            'message' => fn (Blueprint $t) => $t->text('message')->nullable(),
            'severity' => fn (Blueprint $t) => $t->string('severity',20)->default('info'),
            'is_read' => fn (Blueprint $t) => $t->boolean('is_read')->default(false),
            'action_url' => fn (Blueprint $t) => $t->string('action_url')->nullable(),
            'data' => fn (Blueprint $t) => $t->json('data')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ]);
    }

    private static function systemSettings(): void
    {
        if (! Schema::hasTable('pulse_system_settings')) {
            Schema::create('pulse_system_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('type', 20)->default('string');
                $table->string('group', 50)->default('general');
                $table->text('description')->nullable();
            });
            return;
        }
        self::addMissing('pulse_system_settings', [
            'key' => fn (Blueprint $t) => $t->string('key')->nullable(),
            'value' => fn (Blueprint $t) => $t->longText('value')->nullable(),
            'type' => fn (Blueprint $t) => $t->string('type',20)->default('string'),
            'group' => fn (Blueprint $t) => $t->string('group',50)->default('general'),
            'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
        ]);
    }

    private static function auditLogs(): void
    {
        if (! Schema::hasTable('pulse_audit_logs')) {
            Schema::create('pulse_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->string('entity_type', 80)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('environment', 20)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
            return;
        }
        self::addMissing('pulse_audit_logs', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'action' => fn (Blueprint $t) => $t->string('action',100)->nullable(),
            'entity_type' => fn (Blueprint $t) => $t->string('entity_type',80)->nullable(),
            'entity_id' => fn (Blueprint $t) => $t->unsignedBigInteger('entity_id')->nullable(),
            'environment' => fn (Blueprint $t) => $t->string('environment',20)->nullable(),
            'ip_address' => fn (Blueprint $t) => $t->string('ip_address',45)->nullable(),
            'context' => fn (Blueprint $t) => $t->json('context')->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
        ]);
    }


    private static function memberships(): void
    {
        if (! Schema::hasTable('pulse_promotion_codes')) {
            Schema::create('pulse_promotion_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('label', 120)->nullable();
                $table->string('type', 30)->default('coupon');
                $table->string('discount_type', 30)->default('percent');
                $table->decimal('discount_value', 12, 2)->default(0);
                $table->unsignedBigInteger('applicable_plan_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->unsignedInteger('access_days')->nullable();
                $table->unsignedInteger('max_uses')->default(0);
                $table->unsignedInteger('per_user_limit')->default(1);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->boolean('auto_activate')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        } else {
            self::addMissing('pulse_promotion_codes', [
                'code' => fn (Blueprint $t) => $t->string('code',80)->nullable(),
                'label' => fn (Blueprint $t) => $t->string('label',120)->nullable(),
                'type' => fn (Blueprint $t) => $t->string('type',30)->default('coupon'),
                'discount_type' => fn (Blueprint $t) => $t->string('discount_type',30)->default('percent'),
                'discount_value' => fn (Blueprint $t) => $t->decimal('discount_value',12,2)->default(0),
                'applicable_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('applicable_plan_id')->nullable(),
                'assigned_user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('assigned_user_id')->nullable(),
                'access_days' => fn (Blueprint $t) => $t->unsignedInteger('access_days')->nullable(),
                'max_uses' => fn (Blueprint $t) => $t->unsignedInteger('max_uses')->default(0),
                'per_user_limit' => fn (Blueprint $t) => $t->unsignedInteger('per_user_limit')->default(1),
                'valid_from' => fn (Blueprint $t) => $t->timestamp('valid_from')->nullable(),
                'valid_until' => fn (Blueprint $t) => $t->timestamp('valid_until')->nullable(),
                'auto_activate' => fn (Blueprint $t) => $t->boolean('auto_activate')->default(false),
                'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
                'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
                'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('pulse_membership_requests')) {
            Schema::create('pulse_membership_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('pulse_plan_id')->index();
                $table->string('status', 30)->default('submitted')->index();
                $table->decimal('base_amount', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('final_amount', 12, 2)->default(0);
                $table->string('currency', 12)->default('USDT');
                $table->string('network', 80)->nullable();
                $table->text('wallet_address_snapshot')->nullable();
                $table->string('payment_reference', 190)->nullable()->unique();
                $table->string('payment_proof_path', 255)->nullable();
                $table->unsignedBigInteger('promotion_code_id')->nullable()->index();
                $table->string('promotion_code_snapshot', 80)->nullable();
                $table->unsignedInteger('activation_days')->default(30);
                $table->text('user_notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('activated_access_id')->nullable()->index();
                $table->timestamps();
            });
        } else {
            self::addMissing('pulse_membership_requests', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'pulse_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pulse_plan_id')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status',30)->default('submitted'),
                'base_amount' => fn (Blueprint $t) => $t->decimal('base_amount',12,2)->default(0),
                'discount_amount' => fn (Blueprint $t) => $t->decimal('discount_amount',12,2)->default(0),
                'final_amount' => fn (Blueprint $t) => $t->decimal('final_amount',12,2)->default(0),
                'currency' => fn (Blueprint $t) => $t->string('currency',12)->default('USDT'),
                'network' => fn (Blueprint $t) => $t->string('network',80)->nullable(),
                'wallet_address_snapshot' => fn (Blueprint $t) => $t->text('wallet_address_snapshot')->nullable(),
                'payment_reference' => fn (Blueprint $t) => $t->string('payment_reference',190)->nullable(),
                'payment_proof_path' => fn (Blueprint $t) => $t->string('payment_proof_path',255)->nullable(),
                'promotion_code_id' => fn (Blueprint $t) => $t->unsignedBigInteger('promotion_code_id')->nullable(),
                'promotion_code_snapshot' => fn (Blueprint $t) => $t->string('promotion_code_snapshot',80)->nullable(),
                'activation_days' => fn (Blueprint $t) => $t->unsignedInteger('activation_days')->default(30),
                'user_notes' => fn (Blueprint $t) => $t->text('user_notes')->nullable(),
                'admin_notes' => fn (Blueprint $t) => $t->text('admin_notes')->nullable(),
                'reviewed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('reviewed_by')->nullable(),
                'reviewed_at' => fn (Blueprint $t) => $t->timestamp('reviewed_at')->nullable(),
                'activated_access_id' => fn (Blueprint $t) => $t->unsignedBigInteger('activated_access_id')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('pulse_promotion_redemptions')) {
            Schema::create('pulse_promotion_redemptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('promotion_code_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('pulse_plan_id')->index();
                $table->unsignedBigInteger('membership_request_id')->nullable()->index();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->timestamp('redeemed_at')->nullable();
                $table->timestamps();
                $table->unique(['promotion_code_id','membership_request_id'], 'pulse_promo_request_unique');
            });
        } else {
            self::addMissing('pulse_promotion_redemptions', [
                'promotion_code_id' => fn (Blueprint $t) => $t->unsignedBigInteger('promotion_code_id')->nullable(),
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'pulse_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pulse_plan_id')->nullable(),
                'membership_request_id' => fn (Blueprint $t) => $t->unsignedBigInteger('membership_request_id')->nullable(),
                'discount_amount' => fn (Blueprint $t) => $t->decimal('discount_amount',12,2)->default(0),
                'redeemed_at' => fn (Blueprint $t) => $t->timestamp('redeemed_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }
    }


    private static function priceArchitecture(): void
    {
        if (! Schema::hasTable('pulse_market_prices')) {
            Schema::create('pulse_market_prices', function (Blueprint $t): void {
                $t->id(); $t->string('symbol',30)->unique(); $t->decimal('price',24,12); $t->decimal('change_percent_24h',12,6)->nullable();
                $t->decimal('high_24h',24,12)->nullable(); $t->decimal('low_24h',24,12)->nullable(); $t->decimal('volume_24h',32,8)->nullable();
                $t->string('source',30)->default('binance_futures'); $t->timestamp('observed_at')->nullable()->index(); $t->timestamps();
            });
        }
        if (! Schema::hasTable('pulse_market_candles')) {
            Schema::create('pulse_market_candles', function (Blueprint $t): void {
                $t->id(); $t->string('symbol',30)->index(); $t->string('timeframe',8)->index(); $t->unsignedBigInteger('open_time_ms'); $t->unsignedBigInteger('close_time_ms')->nullable();
                $t->decimal('open',24,12); $t->decimal('high',24,12); $t->decimal('low',24,12); $t->decimal('close',24,12); $t->decimal('volume',32,8)->default(0);
                $t->boolean('is_closed')->default(true); $t->string('source',30)->default('binance_futures'); $t->timestamps();
                $t->unique(['symbol','timeframe','open_time_ms'],'pulse_candle_symbol_tf_open_unique');
            });
        }
        if (! Schema::hasTable('pulse_market_data_runs')) {
            Schema::create('pulse_market_data_runs', function (Blueprint $t): void {
                $t->id(); $t->string('status',20)->default('running')->index(); $t->unsignedInteger('prices_updated')->default(0); $t->unsignedInteger('candle_symbols_updated')->default(0);
                $t->unsignedInteger('validation_symbols_updated')->default(0); $t->json('summary')->nullable(); $t->text('error_message')->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
            });
        }
        if (! Schema::hasTable('pulse_signal_validations')) {
            Schema::create('pulse_signal_validations', function (Blueprint $t): void {
                $t->id(); $t->unsignedBigInteger('signal_id')->unique(); $t->unsignedBigInteger('user_id')->nullable()->index(); $t->string('signal_fingerprint',64)->index(); $t->string('symbol',30)->index();
                $t->string('timeframe',8)->index(); $t->string('direction',12)->index(); $t->string('strategy_version',40)->default('1.0')->index(); $t->json('strategy_snapshot')->nullable();
                $t->decimal('entry_price',24,12); $t->decimal('stop_loss',24,12)->nullable(); $t->json('take_profit_levels')->nullable(); $t->decimal('technical_score',6,2)->default(0);
                $t->decimal('reliability_score',6,2)->nullable(); $t->decimal('confidence_score',6,2)->nullable(); $t->string('state',30)->default('waiting_entry')->index(); $t->string('outcome',30)->nullable()->index();
                $t->timestamp('generated_at')->nullable(); $t->timestamp('entry_hit_at')->nullable(); $t->timestamp('resolved_at')->nullable(); $t->timestamp('last_checked_at')->nullable(); $t->decimal('entry_observed_price',24,12)->nullable();
                $t->decimal('mfe_price',24,12)->nullable(); $t->decimal('mae_price',24,12)->nullable(); $t->decimal('mfe_r',12,6)->nullable(); $t->decimal('mae_r',12,6)->nullable(); $t->unsignedInteger('duration_seconds')->nullable();
                $t->unsignedTinyInteger('highest_tp_level_hit')->default(0); $t->string('market_regime',30)->nullable()->index(); $t->json('context')->nullable(); $t->json('meta')->nullable(); $t->timestamps();
            });
        }
        if (! Schema::hasTable('pulse_signal_daily_metrics')) {
            Schema::create('pulse_signal_daily_metrics', function (Blueprint $t): void {
                $t->id(); $t->date('metric_date')->index(); $t->unsignedBigInteger('user_id')->nullable()->index(); $t->string('timeframe',8)->index(); $t->string('direction',12)->index();
                $t->unsignedInteger('signals')->default(0); $t->unsignedInteger('entries')->default(0); $t->unsignedInteger('wins')->default(0); $t->unsignedInteger('losses')->default(0); $t->unsignedInteger('ambiguous')->default(0);
                $t->unsignedInteger('expired_no_entry')->default(0); $t->unsignedInteger('expired_after_entry')->default(0); $t->decimal('avg_mfe_r',12,6)->nullable(); $t->decimal('avg_mae_r',12,6)->nullable(); $t->unsignedInteger('avg_duration_seconds')->nullable(); $t->timestamps();
                $t->unique(['metric_date','user_id','timeframe','direction'],'pulse_signal_daily_user_tf_dir_unique');
            });
        }
        if (! Schema::hasTable('pulse_strategy_daily_metrics')) {
            Schema::create('pulse_strategy_daily_metrics', function (Blueprint $t): void {
                $t->id(); $t->date('metric_date')->index(); $t->string('strategy_slug',100)->index(); $t->string('strategy_version',40)->default('1.0')->index(); $t->string('timeframe',8)->index(); $t->string('direction',12)->index();
                $t->string('market_regime',30)->default('ALL')->index(); $t->unsignedInteger('sample_count')->default(0); $t->unsignedInteger('entries')->default(0); $t->unsignedInteger('wins')->default(0); $t->unsignedInteger('losses')->default(0); $t->unsignedInteger('ambiguous')->default(0);
                $t->unsignedInteger('expired_no_entry')->default(0); $t->decimal('avg_mfe_r',12,6)->nullable(); $t->decimal('avg_mae_r',12,6)->nullable(); $t->unsignedInteger('avg_duration_seconds')->nullable(); $t->timestamps();
                $t->unique(['metric_date','strategy_slug','strategy_version','timeframe','direction','market_regime'],'pulse_strategy_daily_metric_unique');
            });
        }
        if (! Schema::hasTable('pulse_strategy_learning_states')) {
            Schema::create('pulse_strategy_learning_states', function (Blueprint $t): void {
                $t->id(); $t->string('strategy_slug',100)->index(); $t->string('strategy_version',40)->default('1.0')->index(); $t->string('timeframe',8)->index(); $t->string('direction',12)->index(); $t->string('market_regime',30)->default('ALL')->index();
                $t->unsignedInteger('sample_size')->default(0); $t->decimal('win_rate',8,4)->nullable(); $t->decimal('ambiguous_rate',8,4)->nullable(); $t->decimal('reliability_score',6,2)->default(50); $t->decimal('recency_weighted_score',6,2)->default(50);
                $t->string('evidence_level',20)->default('insufficient'); $t->json('meta')->nullable(); $t->timestamp('calculated_at')->nullable(); $t->timestamps();
                $t->unique(['strategy_slug','strategy_version','timeframe','direction','market_regime'],'pulse_strategy_learning_unique');
            });
        }

        self::addMissing('pulse_strategy_daily_metrics', ['entries' => fn (Blueprint $t) => $t->unsignedInteger('entries')->default(0)]);

        self::addMissing('pulse_strategies', ['version' => fn (Blueprint $t) => $t->string('version',40)->default('1.0')]);
        self::addMissing('pulse_signals', [
            'signal_fingerprint' => fn (Blueprint $t) => $t->string('signal_fingerprint',64)->nullable(),
            'strategy_version' => fn (Blueprint $t) => $t->string('strategy_version',40)->default('1.0'),
            'strategy_snapshot' => fn (Blueprint $t) => $t->json('strategy_snapshot')->nullable(),
            'take_profit_levels' => fn (Blueprint $t) => $t->json('take_profit_levels')->nullable(),
            'technical_score' => fn (Blueprint $t) => $t->decimal('technical_score',6,2)->nullable(),
            'reliability_score' => fn (Blueprint $t) => $t->decimal('reliability_score',6,2)->nullable(),
            'confidence_score' => fn (Blueprint $t) => $t->decimal('confidence_score',6,2)->nullable(),
        ]);
    }

    private static function enhancements(): void
    {
        self::addMissing('user_service_access', [
            'trial_used_at' => fn (Blueprint $t) => $t->timestamp('trial_used_at')->nullable(),
        ]);

        self::addMissing('pulse_plans', [
            'manual_trades_per_day' => fn (Blueprint $t) => $t->unsignedInteger('manual_trades_per_day')->default(10),
            'pair_access_mode' => fn (Blueprint $t) => $t->string('pair_access_mode',16)->default('all'),
            'allow_testnet_trading' => fn (Blueprint $t) => $t->boolean('allow_testnet_trading')->default(true),
            'allow_manual_trading' => fn (Blueprint $t) => $t->boolean('allow_manual_trading')->default(true),
            'allow_mobile_api' => fn (Blueprint $t) => $t->boolean('allow_mobile_api')->default(true),
            'capabilities' => fn (Blueprint $t) => $t->json('capabilities')->nullable(),
            'is_trial' => fn (Blueprint $t) => $t->boolean('is_trial')->default(false),
            'is_public' => fn (Blueprint $t) => $t->boolean('is_public')->default(true),
            'request_enabled' => fn (Blueprint $t) => $t->boolean('request_enabled')->default(true),
            'requires_payment' => fn (Blueprint $t) => $t->boolean('requires_payment')->default(true),
            'access_days' => fn (Blueprint $t) => $t->unsignedInteger('access_days')->default(30),
            'badge' => fn (Blueprint $t) => $t->string('badge',50)->nullable(),
            'is_featured' => fn (Blueprint $t) => $t->boolean('is_featured')->default(false),
        ]);

        if (! Schema::hasTable('pulse_plan_strategies')) {
            Schema::create('pulse_plan_strategies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('pulse_plan_id')->index();
                $table->unsignedBigInteger('pulse_strategy_id')->index();
                $table->boolean('is_enabled')->default(true);
                $table->decimal('weight_override', 6, 2)->nullable();
                $table->timestamps();
                $table->unique(['pulse_plan_id', 'pulse_strategy_id'], 'pulse_plan_strategy_unique');
            });
        } else {
            self::addMissing('pulse_plan_strategies', [
                'pulse_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pulse_plan_id')->nullable(),
                'pulse_strategy_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pulse_strategy_id')->nullable(),
                'is_enabled' => fn (Blueprint $t) => $t->boolean('is_enabled')->default(true),
                'weight_override' => fn (Blueprint $t) => $t->decimal('weight_override', 6, 2)->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('pulse_plan_pairs')) {
            Schema::create('pulse_plan_pairs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('pulse_plan_id')->index();
                $table->unsignedBigInteger('pulse_pair_id')->index();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
                $table->unique(['pulse_plan_id', 'pulse_pair_id'], 'pulse_plan_pair_unique');
            });
        } else {
            self::addMissing('pulse_plan_pairs', [
                'pulse_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pulse_plan_id')->nullable(),
                'pulse_pair_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pulse_pair_id')->nullable(),
                'is_enabled' => fn (Blueprint $t) => $t->boolean('is_enabled')->default(true),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        self::addMissing('pulse_user_settings', [
            'execution_mode' => fn (Blueprint $t) => $t->string('execution_mode', 30)->default('signal_only'),
            'emergency_stop' => fn (Blueprint $t) => $t->boolean('emergency_stop')->default(false),
            'margin_type' => fn (Blueprint $t) => $t->string('margin_type', 20)->default('ISOLATED'),
            'position_mode' => fn (Blueprint $t) => $t->string('position_mode', 20)->default('BOTH'),
            'sizing_mode' => fn (Blueprint $t) => $t->string('sizing_mode', 30)->default('fixed_notional'),
            'fixed_notional' => fn (Blueprint $t) => $t->decimal('fixed_notional', 20, 8)->default(25),
            'fixed_quantity' => fn (Blueprint $t) => $t->decimal('fixed_quantity', 24, 12)->nullable(),
            'minimum_signal_score' => fn (Blueprint $t) => $t->decimal('minimum_signal_score', 6, 2)->default(70),
            'pair_selection_saved_at' => fn (Blueprint $t) => $t->timestamp('pair_selection_saved_at')->nullable(),
            'pair_selection_locked_until' => fn (Blueprint $t) => $t->timestamp('pair_selection_locked_until')->nullable(),
        ]);

        self::addMissing('pulse_signals', [
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
        ]);

        self::addMissing('pulse_trades', [
            'exchange_close_order_id' => fn (Blueprint $t) => $t->string('exchange_close_order_id', 100)->nullable(),
            'protection_status' => fn (Blueprint $t) => $t->string('protection_status', 30)->default('not_required'),
            'unrealized_pnl' => fn (Blueprint $t) => $t->decimal('unrealized_pnl', 20, 8)->default(0),
            'commission_asset' => fn (Blueprint $t) => $t->string('commission_asset', 20)->nullable(),
            'last_synced_at' => fn (Blueprint $t) => $t->timestamp('last_synced_at')->nullable(),
        ]);

        if (! Schema::hasTable('pulse_automation_runs')) {
            Schema::create('pulse_automation_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('status', 30)->default('running')->index();
                $table->string('environment', 20)->default('testnet');
                $table->unsignedInteger('signals_reviewed')->default(0);
                $table->unsignedInteger('trades_created')->default(0);
                $table->json('summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('pulse_automation_runs', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('running'),
                'environment' => fn (Blueprint $t) => $t->string('environment', 20)->default('testnet'),
                'signals_reviewed' => fn (Blueprint $t) => $t->unsignedInteger('signals_reviewed')->default(0),
                'trades_created' => fn (Blueprint $t) => $t->unsignedInteger('trades_created')->default(0),
                'summary' => fn (Blueprint $t) => $t->json('summary')->nullable(),
                'error_message' => fn (Blueprint $t) => $t->text('error_message')->nullable(),
                'started_at' => fn (Blueprint $t) => $t->timestamp('started_at')->nullable(),
                'completed_at' => fn (Blueprint $t) => $t->timestamp('completed_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }
    }

    private static function addMissing(string $table, array $definitions): void
    {
        $missing = array_filter(
            $definitions,
            fn (callable $definition, string $column): bool => ! Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
            foreach ($missing as $definition) {
                $definition($blueprint);
            }
        });
    }
}
