@extends('admin.layout')
@section('title','Pulse Strategies')
@section('heading','Scanner Strategy Management')
@section('content')
<section class="panel admin-form">
    <h2>Create strategy</h2>
    <p>Only slugs implemented by the Pulse scanner evaluator produce scores. Unknown slugs are kept neutral rather than pretending to work.</p>
    <form method="POST" action="{{ route('admin.pulse.strategies.store') }}" class="admin-form-grid">
        @csrf
        @include('admin.pulse.partials.strategy-fields',['strategy'=>null])
        <div class="full admin-page-actions"><button class="button button-primary">Create Strategy</button></div>
    </form>
</section>

@foreach($strategies as $strategy)
<section class="panel admin-form">
    <div class="panel-title">
        <div><small>{{ $strategy->slug }}</small><h2>{{ $strategy->name }}</h2></div>
        <span>{{ $strategy->plans_count }} plans</span>
    </div>
    <form method="POST" action="{{ route('admin.pulse.strategies.update',$strategy) }}" class="admin-form-grid">
        @csrf
        @method('PUT')
        @include('admin.pulse.partials.strategy-fields',['strategy'=>$strategy])
        <div class="full admin-page-actions"><button class="button button-primary">Save Strategy</button></div>
    </form>
    <form method="POST" action="{{ route('admin.pulse.strategies.destroy',$strategy) }}" class="admin-delete-form">
        @csrf
        @method('DELETE')
        <button class="button button-ghost" onclick="return confirm('Delete this strategy and remove it from plans?')">Delete Strategy</button>
    </form>
</section>
@endforeach
@endsection
