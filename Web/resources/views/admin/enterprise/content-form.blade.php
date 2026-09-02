@extends('admin.layout')
@section('title',($item?'Edit ':'Add ').$definition['title'])
@section('heading',($item?'Edit ':'Add ').$definition['title'])
@section('content')
<section class="panel admin-page-intro"><div><span class="admin-kicker">ENTERPRISE CMS</span><h2>{{ $item ? 'Update record' : 'Create record' }}</h2><p>{{ $definition['description'] }}</p></div><a class="button button-ghost" href="{{ route('admin.enterprise.content.index',$type) }}">Back</a></section>
<form method="POST" action="{{ $item ? route('admin.enterprise.content.update',[$type,$item->id]) : route('admin.enterprise.content.store',$type) }}" class="panel admin-form">
    @csrf @if($item) @method('PUT') @endif
    <div class="admin-form-grid">
        @foreach($definition['fields'] as $field)
            @php
                $value = old($field, $item?->{$field});
                if($field==='features' && is_array($value)) $value = implode("\n",$value);
                $isLong = in_array($field,['body','description','summary','excerpt','features'],true);
                $isBool = $field==='is_featured';
                $options = match($field) {
                    'status' => $type==='products' ? ['draft','live','archived'] : ['draft','published','archived'],
                    'risk_level' => ['low','medium','high','not_rated'],
                    'level' => ['beginner','intermediate','advanced'],
                    'impact' => ['low','medium','high'],
                    default => null,
                };
            @endphp
            <label class="{{ $isLong ? 'full' : '' }}">
                {{ ucwords(str_replace('_',' ',$field)) }}
                @if($isBool)
                    <select name="{{ $field }}"><option value="0">No</option><option value="1" @selected((bool)$value)>Yes</option></select>
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
    <div class="admin-page-actions"><button class="button button-primary">{{ $item ? 'Save Changes' : 'Create Record' }}</button></div>
</form>
@endsection
