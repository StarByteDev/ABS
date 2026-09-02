<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LiveNewsService
{
    public function latest(int $limit = 12, bool $force = false): array
    {
        $limit = min(max($limit, 1), 40);
        if (! config('services.news_feeds.enabled', true)) {
            return [];
        }

        $key = 'abs.live-news.v2.all';
        if ($force) {
            try {
                Cache::forget($key);
            } catch (Throwable $e) {
                Log::warning('ABS live-news cache clear skipped', ['message' => $e->getMessage()]);
            }
        }

        $resolver = function (): array {
            $items = collect();
            $sources = collect((array) config('services.news_feeds.sources', []))
                ->map(fn (array $source, int $index) => [
                    'key' => 'feed_'.$index,
                    'name' => trim((string) ($source['name'] ?? '')),
                    'url' => trim((string) ($source['url'] ?? '')),
                ])
                ->filter(fn (array $source) => $source['name'] !== '' && $source['url'] !== '')
                ->values();

            if ($sources->isNotEmpty()) {
                $verify = config('services.market.ssl_verify', true);
                $verify = is_bool($verify) ? $verify : filter_var($verify, FILTER_VALIDATE_BOOL);
                $timeout = max(2, (int) config('services.news_feeds.timeout', 6));
                $connectTimeout = max(1, (int) config('services.news_feeds.connect_timeout', 2));

                try {
                    $responses = Http::pool(function (Pool $pool) use ($sources, $verify, $timeout, $connectTimeout): array {
                        return $sources->map(fn (array $source) => $pool
                            ->as($source['key'])
                            ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml, */*')
                            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-NewsReader/13.9'])
                            ->withOptions(['verify' => $verify])
                            ->connectTimeout($connectTimeout)
                            ->timeout($timeout)
                            ->get($source['url']))
                            ->all();
                    });

                    foreach ($sources as $source) {
                        $response = $responses[$source['key']] ?? null;
                        if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                            $items = $items->concat($this->parse((string) $response->body(), $source['name'], $source['url']));
                            continue;
                        }

                        $message = is_object($response) && method_exists($response, 'status')
                            ? 'HTTP '.$response->status()
                            : 'Connection unavailable';
                        Log::warning('ABS live-news feed unavailable', ['source' => $source['name'], 'url' => $source['url'], 'message' => $message]);
                    }
                } catch (Throwable $e) {
                    Log::warning('ABS concurrent live-news refresh failed', ['message' => $e->getMessage()]);
                }
            }

            return $items
                ->filter(fn (array $item) => $item['title'] !== '' && $item['url'] !== '')
                ->unique(fn (array $item) => strtolower($item['url'].'|'.$item['title']))
                ->sortByDesc(fn (array $item) => $item['published_at']?->timestamp ?? 0)
                ->take(40)
                ->values()
                ->all();
        };

        try {
            $items = Cache::remember(
                $key,
                now()->addSeconds((int) config('services.news_feeds.cache_seconds', 300)),
                $resolver,
            );
            return array_slice(is_array($items) ? $items : [], 0, $limit);
        } catch (Throwable $e) {
            Log::warning('ABS live-news cache unavailable; uncached response used', ['message' => $e->getMessage()]);
            return array_slice($resolver(), 0, $limit);
        }
    }

    public function cached(int $limit = 12): array
    {
        $limit = min(max($limit, 1), 40);
        try {
            $items = Cache::get('abs.live-news.v2.all', []);
            return array_slice(is_array($items) ? $items : [], 0, $limit);
        } catch (Throwable $e) {
            Log::debug('ABS live-news cache read skipped', ['message' => $e->getMessage()]);
            return [];
        }
    }

    private function parse(string $xml, string $sourceName, string $sourceUrl): array
    {
        if (function_exists('simplexml_load_string')) {
            return $this->parseWithSimpleXml($xml, $sourceName, $sourceUrl);
        }

        return $this->parseWithRegex($xml, $sourceName, $sourceUrl);
    }

    private function parseWithSimpleXml(string $xml, string $sourceName, string $sourceUrl): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            if ($feed === false) {
                return [];
            }

            $items = [];
            if (isset($feed->channel->item)) {
                foreach ($feed->channel->item as $item) {
                    $namespaces = $item->getNameSpaces(true);
                    $media = isset($namespaces['media']) ? $item->children($namespaces['media']) : null;
                    $content = isset($namespaces['content']) ? $item->children($namespaces['content']) : null;
                    $dc = isset($namespaces['dc']) ? $item->children($namespaces['dc']) : null;
                    $link = trim((string) $item->link);
                    $image = $this->firstNonEmpty([
                        (string) ($item->enclosure['url'] ?? ''),
                        (string) ($media?->content['url'] ?? ''),
                        (string) ($media?->thumbnail['url'] ?? ''),
                    ]);
                    $description = $this->firstNonEmpty([
                        (string) ($item->description ?? ''),
                        (string) ($content?->encoded ?? ''),
                    ]);
                    $items[] = $this->normalizeItem([
                        'title' => (string) ($item->title ?? ''),
                        'url' => $link,
                        'description' => $description,
                        'category' => (string) ($item->category ?? 'Industry News'),
                        'author' => (string) ($dc?->creator ?? ''),
                        'published_at' => (string) ($item->pubDate ?? $item->date ?? ''),
                        'image_url' => $image,
                    ], $sourceName, $sourceUrl);
                }
            } elseif (isset($feed->entry)) {
                foreach ($feed->entry as $entry) {
                    $link = '';
                    foreach ($entry->link as $linkNode) {
                        $attributes = $linkNode->attributes();
                        if ((string) ($attributes['rel'] ?? 'alternate') === 'alternate' || $link === '') {
                            $link = trim((string) ($attributes['href'] ?? $linkNode));
                        }
                    }
                    $items[] = $this->normalizeItem([
                        'title' => (string) ($entry->title ?? ''),
                        'url' => $link,
                        'description' => (string) ($entry->summary ?? $entry->content ?? ''),
                        'category' => (string) ($entry->category['term'] ?? 'Industry News'),
                        'author' => (string) ($entry->author->name ?? ''),
                        'published_at' => (string) ($entry->published ?? $entry->updated ?? ''),
                        'image_url' => '',
                    ], $sourceName, $sourceUrl);
                }
            }

            return array_values(array_filter($items));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function parseWithRegex(string $xml, string $sourceName, string $sourceUrl): array
    {
        preg_match_all('/<item\b[^>]*>(.*?)<\/item>/is', $xml, $matches);
        $items = [];
        foreach ($matches[1] ?? [] as $block) {
            $items[] = $this->normalizeItem([
                'title' => $this->tagValue($block, 'title'),
                'url' => $this->tagValue($block, 'link'),
                'description' => $this->tagValue($block, 'description'),
                'category' => $this->tagValue($block, 'category') ?: 'Industry News',
                'author' => $this->tagValue($block, 'dc:creator'),
                'published_at' => $this->tagValue($block, 'pubDate'),
                'image_url' => $this->attributeValue($block, 'enclosure', 'url'),
            ], $sourceName, $sourceUrl);
        }

        return array_values(array_filter($items));
    }

    private function normalizeItem(array $item, string $sourceName, string $sourceUrl): array
    {
        $title = $this->cleanText((string) ($item['title'] ?? ''));
        $url = trim(html_entity_decode((string) ($item['url'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return [];
        }

        $publishedAt = null;
        try {
            $rawDate = trim((string) ($item['published_at'] ?? ''));
            if ($rawDate !== '') {
                $publishedAt = CarbonImmutable::parse($rawDate);
            }
        } catch (Throwable) {
            $publishedAt = null;
        }

        return [
            'id' => hash('sha256', $sourceName.'|'.$url),
            'title' => Str::limit($title, 180, '…'),
            'excerpt' => Str::limit($this->cleanText((string) ($item['description'] ?? '')), 220, '…'),
            'category' => Str::limit($this->cleanText((string) ($item['category'] ?? 'Industry News')), 60, ''),
            'author_name' => Str::limit($this->cleanText((string) ($item['author'] ?? '')), 80, ''),
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
            'url' => $url,
            'image_url' => trim((string) ($item['image_url'] ?? '')),
            'published_at' => $publishedAt,
            'is_external' => true,
        ];
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function tagValue(string $block, string $tag): string
    {
        $quoted = preg_quote($tag, '/');
        if (preg_match('/<'.$quoted.'\b[^>]*>(.*?)<\/'.$quoted.'>/is', $block, $match)) {
            return trim(preg_replace('/^<!\[CDATA\[(.*)\]\]>$/s', '$1', $match[1]) ?? $match[1]);
        }
        return '';
    }

    private function attributeValue(string $block, string $tag, string $attribute): string
    {
        $quotedTag = preg_quote($tag, '/');
        $quotedAttribute = preg_quote($attribute, '/');
        if (preg_match('/<'.$quotedTag.'\b[^>]*\b'.$quotedAttribute.'=["\']([^"\']+)["\'][^>]*>/is', $block, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }
}
