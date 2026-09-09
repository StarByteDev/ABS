@extends('admin.layout')
@section('title',($item?'Edit ':'Add ').$definition['title'])
@section('heading',($item?'Edit ':'Add ').$definition['title'])
@section('description','Create and maintain a structured ABS content record with clear classification, publishing state and customer-facing presentation controls.')
@section('content')
<section class="enterprise-command-bar compact admin-report-command"><div><span class="admin-report-eyebrow">CONTENT STUDIO · {{ strtoupper($type) }}</span><h2>{{ $item ? 'Maintain an existing record' : 'Create a new content record' }}</h2><p>{{ $definition['description'] }} Required operational metadata and publishing controls are kept together for a reliable review workflow.</p></div><a class="button button-ghost" href="{{ route('admin.enterprise.content.index',$type) }}">← Back to Register</a></section>
<div class="enterprise-section-grid two-one cms-editor-grid">
<form method="POST" action="{{ $item ? route('admin.enterprise.content.update',[$type,$item->id]) : route('admin.enterprise.content.store',$type) }}" class="enterprise-surface admin-form cms-structured-editor">
    @csrf @if($item) @method('PUT') @endif
    <div class="enterprise-section-head"><div><h2>Content & classification</h2><p>Write clear customer-facing information and complete the relevant market or service classification.</p></div><span class="admin-status {{ $item?'info':'good' }}">{{ $item?'Editing #'.$item->id:'New record' }}</span></div>
    <div class="admin-form-grid">
        @foreach($definition['fields'] as $field)
            @php
                $value = old($field, $item?->{$field});
                if($field==='features' && is_array($value)) $value = implode("\n",$value);
                $isLong = in_array($field,['body','description','summary','excerpt','features','easy_explanation','crypto_impact_summary'],true);
                $isBool = in_array($field,['is_featured','is_crypto_relevant'],true);
                $options = match($field) {
                    'status' => $type==='products' ? ['draft','live','archived'] : ['draft','published','archived'],
                    'risk_level' => ['low','medium','high','not_rated'],
                    'level' => ['beginner','intermediate','advanced'],
                    'impact' => ['low','medium','high'],
                    'crypto_impact' => ['supportive','pressure','volatile','mixed','neutral'],
                    default => null,
                };
            @endphp
            <label class="{{ $isLong ? 'full' : '' }}">
                {{ ucwords(str_replace('_',' ',$field)) }}
                @if($isBool)
                    <select name="{{ $field }}">@if($field==='is_featured')<option value="0">Standard placement</option><option value="1" @selected((bool)$value)>Featured placement</option>@else<option value="1" @selected($value===null || (bool)$value)>Shown in ABS News / mobile</option><option value="0" @selected($value!==null && !(bool)$value)>Hide from crypto-focused calendar</option>@endif</select>
                @elseif($options)
                    <select name="{{ $field }}">@foreach($options as $option)<option value="{{ $option }}" @selected((string)$value===$option)>{{ ucwords(str_replace('_',' ',$option)) }}</option>@endforeach</select>
                @elseif($isLong)
                    <textarea name="{{ $field }}" rows="{{ $field==='body' || $field==='description' ? 10 : 5 }}">{{ $value }}</textarea>
                @elseif($field==='published_at' || $field==='event_at')
                    <input type="datetime-local" name="{{ $field }}" value="{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : '' }}">
                @elseif(in_array($field,['duration_minutes','sort_order'],true))
                    <input type="number" name="{{ $field }}" value="{{ $value ?? 0 }}" min="0">
                @else
                    <input name="{{ $field }}" value="{{ $value }}">
                @endif
            </label>
        @endforeach
    </div>
    <div class="cms-editor-footer"><p>Review customer-facing language, status and schedule before saving.</p><button class="button button-primary">{{ $item ? 'Save Content Changes' : 'Create Content Record' }}</button></div>
</form>
<aside class="enterprise-surface cms-editor-guidance">
    <div class="enterprise-section-head"><div><h2>Publishing checklist</h2><p>Use this standard across every ABS CMS channel.</p></div></div>
    <ol><li><b>Accurate title</b><span>Use a specific title that matches the content.</span></li><li><b>Clear classification</b><span>Select the correct category, asset, risk, level or event impact.</span></li><li><b>Customer-safe language</b><span>Separate facts from analysis and never promise returns.</span></li><li><b>Publishing state</b><span>Keep incomplete material in Draft; archive content that should no longer be shown.</span></li><li><b>Final presentation</b><span>Check featured placement and date before saving.</span></li></ol>
    <div class="cms-channel-map"><small>CHANNEL</small><b>{{ $definition['title'] }}</b><p>{{ $definition['description'] }}</p></div>
</aside>
</div>
@endsection
