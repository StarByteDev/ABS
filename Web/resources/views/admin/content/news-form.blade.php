@extends('admin.layout')
@section('title',($item?'Edit ':'Create ').'Market Headline')
@section('heading',($item?'Edit ':'Publish ').'Market Headline')
@section('content')
<section class="admin-editor-shell">
    <form class="panel admin-form admin-news-editor" method="POST" action="{{ $item ? route('admin.content.update',[$type,$item->id]) : route('admin.content.store',$type) }}" data-news-editor>
        @csrf
        @if($item) @method('PUT') @endif

        <div class="admin-editor-intro">
            <div>
                <span class="admin-kicker">NEWS CMS</span>
                <h2>{{ $item ? 'Update a verified market story' : 'Publish a verified market headline' }}</h2>
                <p>Featured published articles appear first in the homepage <strong>Latest Market Headlines</strong> panel. Add a clear source whenever the article relies on third-party reporting.</p>
            </div>
            <div class="admin-editor-status">
                <span class="status-pill status-{{ $item->status ?? 'draft' }}">{{ ucfirst($item->status ?? 'draft') }}</span>
                @if($item?->is_featured)<span class="status-pill status-featured">Homepage headline</span>@endif
            </div>
        </div>

        <div class="admin-news-layout">
            <div class="admin-news-main">
                <fieldset class="editor-section">
                    <legend>Headline</legend>
                    <label>Headline title <span>Required</span>
                        <input name="title" maxlength="180" required value="{{ old('title',$item?->title) }}" placeholder="Write a specific, factual headline" data-preview-title>
                    </label>
                    <label>Short summary <span>Required · up to 500 characters</span>
                        <textarea name="excerpt" rows="4" maxlength="500" required placeholder="Explain the development and why it matters without promotional language" data-preview-excerpt>{{ old('excerpt',$item?->excerpt) }}</textarea>
                    </label>
                    <div class="editor-two-col">
                        <label>Category
                            @php($category = old('category',$item?->category ?? 'Market News'))
                            <select name="category" required data-preview-category>
                                @foreach(['Market News','Bitcoin','Ethereum','Altcoins','Blockchain','DeFi','Web3','AI & Technology','Regulation','Security','Institutional','Economic & Macro'] as $option)
                                    <option value="{{ $option }}" {{ $category===$option?'selected':'' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Slug <span>Optional</span>
                            <input name="slug" value="{{ old('slug',$item?->slug) }}" placeholder="Generated automatically from the title">
                        </label>
                    </div>
                </fieldset>

                <fieldset class="editor-section">
                    <legend>Article content</legend>
                    <label>Article body <span>Required for an ABS article; optional when linking to an original source</span>
                        <textarea name="body" rows="14" placeholder="Write original ABS analysis or a concise source-based summary. Basic HTML is supported.">{{ old('body',$item?->body) }}</textarea>
                    </label>
                    <label>Article image URL <span>Optional</span>
                        <input type="url" name="image_url" value="{{ old('image_url',$item?->image_url) }}" placeholder="Paste an HTTPS image URL" data-preview-image>
                    </label>
                </fieldset>

                <fieldset class="editor-section">
                    <legend>Source and attribution</legend>
                    <div class="editor-two-col">
                        <label>Source / publisher name
                            <input name="source_name" maxlength="120" value="{{ old('source_name',$item?->source_name) }}" placeholder="Official source or publisher">
                        </label>
                        <label>Original source URL
                            <input type="url" name="source_url" value="{{ old('source_url',$item?->source_url) }}" placeholder="Paste the original HTTPS source URL">
                        </label>
                    </div>
                    <div class="editor-two-col">
                        <label>Author name
                            <input name="author_name" maxlength="120" value="{{ old('author_name',$item?->author_name ?? 'ABS Editorial') }}">
                        </label>
                        <label>Publish date and time
                            <input type="datetime-local" name="published_at" value="{{ old('published_at',$item?->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}">
                        </label>
                    </div>
                </fieldset>
            </div>

            <aside class="admin-news-sidebar">
                <div class="editor-section publish-settings">
                    <h3>Publishing</h3>
                    @php($status = old('status',$item?->status ?? 'draft'))
                    <label>Status
                        <select name="status">
                            @foreach(['draft'=>'Draft','published'=>'Published','archived'=>'Archived'] as $value=>$label)
                                <option value="{{ $value }}" {{ $status===$value?'selected':'' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="check-label headline-check">
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured',$item?->is_featured) ? 'checked' : '' }}>
                        <span><b>Show in Latest Market Headlines</b><small>Places this article before external feed stories on the homepage.</small></span>
                    </label>
                    <div class="admin-publish-actions">
                        <button class="button button-primary button-wide" name="action" value="publish">{{ $item ? 'Save & Publish' : 'Publish Headline' }}</button>
                        <button class="button button-ghost button-wide" name="action" value="draft">Save as Draft</button>
                        <a class="button button-ghost button-wide" href="{{ route('admin.content.index','news') }}">Cancel</a>
                    </div>
                </div>

                <div class="editor-section admin-preview-card" data-news-preview>
                    <span class="preview-label">HOMEPAGE PREVIEW</span>
                    <img src="{{ old('image_url',$item?->image_url) ?: asset('assets/images/home/news-1.png') }}" alt="" data-preview-image-output>
                    <em data-preview-category-output>{{ old('category',$item?->category ?? 'Market News') }}</em>
                    <b data-preview-title-output>{{ old('title',$item?->title ?? 'Enter the verified headline above') }}</b>
                    <p data-preview-excerpt-output>{{ old('excerpt',$item?->excerpt ?? 'Enter a concise, factual summary explaining why the development matters.') }}</p>
                </div>

                <div class="editor-section admin-checklist">
                    <h3>Before publishing</h3>
                    <ul>
                        <li>Confirm the headline matches the source.</li>
                        <li>Use the original publication URL.</li>
                        <li>Separate fact, analysis and opinion.</li>
                        <li>Do not promise returns or guaranteed outcomes.</li>
                        <li>Check the publication date and timezone.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </form>
</section>
@endsection
