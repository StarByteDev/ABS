@php($s=$strategy)
<label>Name<input name="name" required value="{{ old('name',$s?->name) }}"></label><label>Slug<input name="slug" value="{{ old('slug',$s?->slug) }}"></label>
<label class="full">Description<textarea name="description" rows="3">{{ old('description',$s?->description) }}</textarea></label>
<label>Default timeframe<select name="timeframe">@foreach(['5m','15m','30m','1h','4h','1d'] as $tf)<option value="{{ $tf }}" @selected(old('timeframe',$s?->timeframe ?? '15m')===$tf)>{{ $tf }}</option>@endforeach</select></label>
<label>Weight<input type="number" name="weight" min="0" max="10" step="0.01" value="{{ old('weight',$s?->weight ?? 1) }}"></label>
<label>Minimum score metadata<input type="number" name="minimum_score" min="0" max="100" step="0.01" value="{{ old('minimum_score',$s?->minimum_score ?? 0) }}"></label>
<label>Sort order<input type="number" name="sort_order" min="0" value="{{ old('sort_order',$s?->sort_order ?? 0) }}"></label>
<label class="full">Settings JSON<textarea name="settings_json" rows="4">{{ old('settings_json',$s ? json_encode($s->settings ?? [],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '{}') }}</textarea></label>
<label class="full admin-check"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled',$s?->is_enabled ?? true))><span>Strategy enabled globally</span></label>
