<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pulse_strategies')) return;
        $defaults = [
            'trend-alignment'=>['Core Strategy',2.00,1.25], 'ema-crossover'=>['Entry Trigger',1.75,1.20],
            'rsi-recovery'=>['Confirmation',1.50,1.20], 'macd-momentum'=>['Confirmation',2.00,1.25],
            'breakout-confirmation'=>['Core Strategy',2.50,1.35], 'volume-expansion'=>['Confirmation',2.00,1.25],
            'atr-volatility'=>['Market Regime Filter',2.00,1.25], 'market-structure'=>['Core Strategy',2.25,1.30],
            'trend-pullback'=>['Core Strategy',2.00,1.20], 'bollinger-reversion'=>['Core Strategy',1.50,1.10],
            'momentum-continuation'=>['Core Strategy',2.25,1.30], 'range-compression'=>['Market Regime Filter',2.25,1.30],
            'candle-strength'=>['Confirmation',1.75,1.20], 'swing-sequence'=>['Confirmation',2.00,1.25],
            'risk-reward-quality'=>['Risk Filter',2.00,1.25],
        ];
        foreach ($defaults as $slug=>$cfg) {
            $row=DB::table('pulse_strategies')->where('slug',$slug)->first(); if(!$row) continue;
            $settings=json_decode((string)($row->settings ?? '{}'),true); if(!is_array($settings))$settings=[];
            $settings += ['category'=>$cfg[0],'risk_reward_target'=>$cfg[1],'tp1_r'=>1.0,'tp2_r'=>1.5,'tp3_r'=>$cfg[1],'stop_atr_multiplier'=>$cfg[2],'learning_enabled'=>true];
            DB::table('pulse_strategies')->where('id',$row->id)->update(['settings'=>json_encode($settings)]);
        }
        $new = [
            ['Trend + Volume Confluence','trend-volume-confluence','Confirms directional EMA trend only when participation is above its recent average.','Core Strategy',2.25,1.30,16],
            ['MACD + RSI Confluence','macd-rsi-confluence','Requires MACD direction and RSI regime to agree before adding conviction.','Confirmation',2.00,1.25,17],
            ['Breakout Momentum','breakout-momentum','Requires a range break to be supported by short-term momentum and volume.','Core Strategy',2.50,1.35,18],
        ];
        foreach($new as [$name,$slug,$description,$category,$rr,$stopAtr,$sort]){
            $exists=DB::table('pulse_strategies')->where('slug',$slug)->first();
            if(!$exists){
                $id=DB::table('pulse_strategies')->insertGetId(['name'=>$name,'slug'=>$slug,'version'=>'1.0','description'=>$description,'timeframe'=>'15m','weight'=>1.00,'minimum_score'=>0,'settings'=>json_encode(['category'=>$category,'risk_reward_target'=>$rr,'tp1_r'=>1.0,'tp2_r'=>1.5,'tp3_r'=>$rr,'stop_atr_multiplier'=>$stopAtr,'learning_enabled'=>true]),'is_enabled'=>true,'sort_order'=>$sort,'created_at'=>now(),'updated_at'=>now()]);
                if(Schema::hasTable('pulse_plan_strategies') && Schema::hasTable('pulse_plans')){
                    foreach(DB::table('pulse_plans')->where('is_active',true)->pluck('id') as $planId){
                        DB::table('pulse_plan_strategies')->updateOrInsert(['pulse_plan_id'=>$planId,'pulse_strategy_id'=>$id],['is_enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
                    }
                }
            }
        }
    }
    public function down(): void
    {
        // Production-safe migration: no destructive rollback of strategy history/settings.
    }
};
