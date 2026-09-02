<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminContentController extends Controller
{
    private function definition(string $type): array
    {
        abort_unless($type === 'news', 404);

        return config('content.news');
    }

    public function index(string $type)
    {
        $definition = $this->definition($type);

        return view('admin.content.index', [
            'type' => 'news',
            'definition' => $definition,
            'items' => NewsArticle::query()
                ->orderByDesc('is_featured')
                ->latest('published_at')
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(string $type)
    {
        return view('admin.content.news-form', [
            'type' => 'news',
            'definition' => $this->definition($type),
            'item' => null,
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->definition($type);
        $item = NewsArticle::create($this->validatedNews($request));

        return redirect()->route('admin.content.index', 'news')->with(
            'success',
            $item->status === 'published' ? 'Headline published successfully.' : 'News draft saved successfully.',
        );
    }

    public function edit(string $type, int $id)
    {
        return view('admin.content.news-form', [
            'type' => 'news',
            'definition' => $this->definition($type),
            'item' => NewsArticle::findOrFail($id),
        ]);
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->definition($type);
        $item = NewsArticle::findOrFail($id);
        $item->update($this->validatedNews($request, $item));

        return redirect()->route('admin.content.index', 'news')->with('success', 'News article updated successfully.');
    }

    public function destroy(string $type, int $id)
    {
        $this->definition($type);
        NewsArticle::findOrFail($id)->delete();

        return back()->with('success', 'News article removed.');
    }

    public function toggleHeadline(NewsArticle $article)
    {
        $makeHeadline = ! $article->is_featured;
        $article->update([
            'is_featured' => $makeHeadline,
            'status' => $makeHeadline ? 'published' : $article->status,
            'published_at' => $makeHeadline ? ($article->published_at ?: now()) : $article->published_at,
        ]);

        return back()->with('success', $makeHeadline
            ? 'Article added to Latest Market Headlines.'
            : 'Article removed from Latest Market Headlines.');
    }

    public function publishNow(NewsArticle $article)
    {
        $article->update(['status' => 'published', 'published_at' => now()]);

        return back()->with('success', 'Article published now.');
    }

    public function unpublish(NewsArticle $article)
    {
        $article->update(['status' => 'draft', 'is_featured' => false]);

        return back()->with('success', 'Article returned to draft and removed from the homepage headline panel.');
    }

    private function validatedNews(Request $request, ?NewsArticle $item = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('news_articles', 'slug')->ignore($item?->id)],
            'excerpt' => ['required', 'string', 'min:50', 'max:500'],
            'body' => ['nullable', 'string', 'required_without:source_url'],
            'category' => ['required', 'string', 'max:80'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'source_name' => ['nullable', 'string', 'max:120', 'required_with:source_url'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'action' => ['nullable', Rule::in(['draft', 'publish'])],
        ]);

        $action = $request->string('action')->toString();
        if ($action === 'draft') {
            $data['status'] = 'draft';
            $data['is_featured'] = false;
        } elseif ($action === 'publish') {
            $data['status'] = 'published';
        }

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? '', $data['title'], $item);
        $data['author_name'] = trim((string) ($data['author_name'] ?? '')) ?: 'ABS Editorial';
        $data['is_featured'] = $request->boolean('is_featured') && $data['status'] === 'published';
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        unset($data['action']);

        return $data;
    }

    private function uniqueSlug(string $requested, string $fallback, ?NewsArticle $item = null): string
    {
        $base = Str::slug(trim($requested) ?: $fallback) ?: 'market-headline';
        $slug = $base;
        $suffix = 2;

        while (NewsArticle::withTrashed()
            ->where('slug', $slug)
            ->when($item, fn ($query) => $query->whereKeyNot($item->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
