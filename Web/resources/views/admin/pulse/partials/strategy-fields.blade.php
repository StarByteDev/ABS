@php
    $s=$strategy;
    $settings=(array)($s?->settings ?? []);
@endphp
<label>Name<input name="name" required value="{{ old('name',$s?->name) }}"></label>
<label>Slug<input name="slug" value="{{ old('slug',$s?->slug) }}"></label>
<label class="full">Description<textarea name="description" rows="3">{{ old('description',$s?->description) }}</textarea></label>
<label>Default timeframe<select name="timeframe">@foreach(['5m','15m','30m','1h','4h','1d'] as $tf)<option value="{{ $tf }}" @selected(old('timeframe',$s?->timeframe ?? '15m')===$tf)>{{ strtoupper($tf) }}</option>@endforeach</select></label>
<label>Strategy role<select name="strategy_category">@foreach(['Core Strategy','Entry Trigger','Confirmation','Market Regime Filter','Risk Filter','Exit Model'] as $cat)<option value="{{ $cat }}" @selected(old('strategy_category',data_get($settings,'category','Confirmation'))===$cat)>{{ $cat }}</option>@endforeach</select></label>
<label>Scoring weight<input type="number" name="weight" min="0" max="10" step="0.01" value="{{ old('weight',$s?->weight ?? 1) }}"><small>Contribution to the technical score. Learning is applied separately.</small></label>
<label>Minimum score metadata<input type="number" name="minimum_score" min="0" max="100" step="0.01" value="{{ old('minimum_score',$s?->minimum_score ?? 0) }}"></label>
<label>Preferred R:R<input type="number" name="risk_reward_target" min="1" max="5" step="0.05" value="{{ old('risk_reward_target',data_get($settings,'risk_reward_target',2.0)) }}"><small>Used as this evaluator's research reward target when it contributes.</small></label>
<label>Stop ATR multiplier<input type="number" name="stop_atr_multiplier" min="0.5" max="5" step="0.05" value="{{ old('stop_atr_multiplier',data_get($settings,'stop_atr_multiplier',1.25)) }}"></label>
<label>TP1 (R)<input type="number" name="tp1_r" min="0.5" max="5" step="0.05" value="{{ old('tp1_r',data_get($settings,'tp1_r',1.0)) }}"></label>
<label>TP2 (R)<input type="number" name="tp2_r" min="0.5" max="5" step="0.05" value="{{ old('tp2_r',data_get($settings,'tp2_r',1.5)) }}"></label>
<label>TP3 / final (R)<input type="number" name="tp3_r" min="0.5" max="5" step="0.05" value="{{ old('tp3_r',data_get($settings,'tp3_r',data_get($settings,'risk_reward_target',2.0))) }}"></label>
<label>Sort order<input type="number" name="sort_order" min="0" value="{{ old('sort_order',$s?->sort_order ?? 0) }}"></label>
<label class="full admin-check"><input type="checkbox" name="learning_enabled" value="1" @checked(old('learning_enabled',data_get($settings,'learning_enabled',true)))><span><b>Learning enabled</b><small>TP/SL evidence and partial TP milestones can adjust the strategy learning score over time.</small></span></label>
<details class="full strategy-advanced-json"><summary>Advanced configuration JSON</summary><label class="full">Settings JSON<textarea name="settings_json" rows="5">{{ old('settings_json',$s ? json_encode($settings,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '{}') }}</textarea><small>Developer/advanced fields only. The controls above are saved into this settings object automatically.</small></label></details>
<label class="full admin-check"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled',$s?->is_enabled ?? true))><span><b>Strategy enabled globally</b><small>Disabled strategies are excluded from new scoring.</small></span></label>
