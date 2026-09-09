<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\EconomicEvent;
use App\Models\EmailDeliveryLog;
use App\Models\LearningArticle;
use App\Models\MobileDevice;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\PulseSystemSetting;
use App\Models\ResearchReport;
use App\Models\SiteSetting;
use App\Models\UserServiceAccess;
use App\Services\BrandedMailService;
use App\Services\EconomicCalendarService;
use App\Services\MacroImpactInterpreter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminEnterpriseController extends Controller
{
    public function contentIndex(Request $request, string $type)
    {
        [$model, $definition] = $this->contentDefinition($type);
        $query = $model::query();
        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($q) use ($term, $type): void {
                $q->where($type === 'products' ? 'name' : 'title', 'like', $term);
                if ($type !== 'events') $q->orWhere('category', 'like', $term);
                if ($type === 'research') $q->orWhere('asset_symbol', 'like', $term);
                if ($type === 'events') $q->orWhere('country', 'like', $term)->orWhere('currency', 'like', $term);
            });
        }
        if ($request->filled('status')) {
            if ($type === 'events') $query->where('impact', $request->string('status'));
            else $query->where('status', $request->string('status'));
        }
        if ($request->string('featured')->value() === '1' && $type !== 'events') $query->where('is_featured', true);
        if ($type === 'events') $query->orderByDesc('event_at');
        elseif ($type === 'products') $query->orderBy('sort_order')->orderBy('name');
        else $query->latest('updated_at');
        $base = $model::query();
        $summary = $type === 'events'
            ? [
                'total' => (clone $base)->count(),
                'primary' => (clone $base)->where('event_at', '>=', now())->count(),
                'secondary' => (clone $base)->where('impact', 'high')->where('event_at', '>=', now())->count(),
                'featured' => null,
                'updated30' => (clone $base)->where('updated_at', '>=', now()->subDays(30))->count(),
            ]
            : [
                'total' => (clone $base)->count(),
                'primary' => (clone $base)->where('status', $type === 'products' ? 'live' : 'published')->count(),
                'secondary' => (clone $base)->where('status', 'draft')->count(),
                'featured' => (clone $base)->where('is_featured', true)->count(),
                'updated30' => (clone $base)->where('updated_at', '>=', now()->subDays(30))->count(),
            ];
        $filterOptions = $type === 'events' ? ['high','medium','low'] : ($type === 'products' ? ['draft','live','archived'] : ['draft','published','archived']);
        $economicIntegration = null;
        if ($type === 'events') {
            $calendar = app(EconomicCalendarService::class);
            $economicIntegration = [
                'configured' => $calendar->configured(),
                'auto_sync' => $calendar->autoSyncEnabled(),
                'provider' => $calendar->providerName(),
                'last_sync_at' => $calendar->lastSyncAt(),
            ];
        }
        return view('admin.enterprise.content-index', compact('type', 'definition', 'summary', 'filterOptions', 'economicIntegration') + ['items' => $query->paginate(25)->withQueryString()]);
    }

    public function contentCreate(string $type)
    {
        [, $definition] = $this->contentDefinition($type);
        return view('admin.enterprise.content-form', ['type' => $type, 'definition' => $definition, 'item' => null]);
    }

    public function contentStore(Request $request, string $type)
    {
        [$model] = $this->contentDefinition($type);
        $item = $model::create($this->validatedContent($request, $type));
        return redirect()->route('admin.enterprise.content.index', $type)->with('success', $this->label($type).' item created.');
    }

    public function contentEdit(string $type, int $id)
    {
        [$model, $definition] = $this->contentDefinition($type);
        return view('admin.enterprise.content-form', ['type' => $type, 'definition' => $definition, 'item' => $model::query()->findOrFail($id)]);
    }

    public function contentUpdate(Request $request, string $type, int $id)
    {
        [$model] = $this->contentDefinition($type);
        $item = $model::query()->findOrFail($id);
        $item->update($this->validatedContent($request, $type, $item));
        return redirect()->route('admin.enterprise.content.index', $type)->with('success', $this->label($type).' item updated.');
    }

    public function contentDestroy(string $type, int $id)
    {
        [$model] = $this->contentDefinition($type);
        $model::query()->findOrFail($id)->delete();
        return back()->with('success', $this->label($type).' item removed.');
    }

    public function settings()
    {
        // Integration credentials and provider runtime state are managed by their
        // dedicated Admin screens. Never render encrypted values in the generic
        // website/mobile settings editor, even as ciphertext.
        $settings = SiteSetting::query()
            ->where('type', '!=', 'encrypted')
            ->where('key', 'not like', 'economic_calendar_%')
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');

        return view('admin.enterprise.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:10000']]);
        foreach ($data['settings'] as $key => $value) {
            if (str_starts_with((string) $key, 'economic_calendar_')) continue;

            $existing = SiteSetting::query()->where('key', $key)->first();
            if ($existing?->type === 'encrypted') continue;

            if ($existing) {
                $existing->update(['value' => $value]);
            } else {
                SiteSetting::create(['key' => $key, 'value' => $value, 'type' => 'string', 'group' => str_starts_with($key, 'mobile_') ? 'mobile' : 'general']);
            }
        }
        return back()->with('success', 'Website and mobile settings updated.');
    }

    public function newsletters(Request $request)
    {
        $query = NewsletterSubscriber::query()->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('q')) $query->where('email', 'like', '%'.trim((string)$request->string('q')).'%');
        return view('admin.enterprise.newsletters', [
            'items' => $query->paginate(30)->withQueryString(),
            'summary' => [
                'total' => NewsletterSubscriber::query()->count(),
                'active' => NewsletterSubscriber::query()->where('status','active')->count(),
                'unsubscribed' => NewsletterSubscriber::query()->where('status','unsubscribed')->count(),
                'new30' => NewsletterSubscriber::query()->where('created_at','>=',now()->subDays(30))->count(),
            ],
        ]);
    }

    public function updateNewsletter(Request $request, NewsletterSubscriber $subscriber)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'unsubscribed'])]]);
        $subscriber->update([
            'status' => $data['status'],
            'confirmed_at' => $data['status'] === 'active' ? ($subscriber->confirmed_at ?: now()) : $subscriber->confirmed_at,
            'unsubscribed_at' => $data['status'] === 'unsubscribed' ? now() : null,
        ]);
        return back()->with('success', 'Subscriber status updated.');
    }

    public function contacts(Request $request)
    {
        $query = ContactMessage::query()->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('category')) $query->where('category', $request->string('category'));
        if ($request->filled('priority')) $query->where('priority', $request->string('priority'));
        if ($request->filled('q')) {
            $term = '%'.trim((string)$request->string('q')).'%';
            $query->where(fn ($q) => $q->where('name','like',$term)->orWhere('email','like',$term)->orWhere('subject','like',$term));
        }
        return view('admin.enterprise.contacts', [
            'items' => $query->paginate(30)->withQueryString(),
            'summary' => [
                'new' => ContactMessage::query()->where('status','new')->count(),
                'open' => ContactMessage::query()->whereIn('status',['new','open','waiting'])->count(),
                'urgent' => ContactMessage::query()->where('priority','urgent')->whereNotIn('status',['resolved','closed'])->count(),
                'resolved7' => ContactMessage::query()->whereIn('status',['resolved','closed'])->where('updated_at','>=',now()->subDays(7))->count(),
            ],
        ]);
    }

    public function contactShow(ContactMessage $contact)
    {
        if ($contact->status === 'new') $contact->update(['status' => 'open']);
        return view('admin.enterprise.contact-show', compact('contact'));
    }

    public function contactUpdate(Request $request, ContactMessage $contact)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'open', 'waiting', 'resolved', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
        ]);
        if (in_array($data['status'], ['resolved', 'closed'], true) && ! $contact->replied_at) $data['replied_at'] = now();
        $contact->update($data);
        return back()->with('success', 'Support enquiry updated.');
    }

    public function emails()
    {
        $this->ensureAdminEventNotificationSettings();

        $settings = collect([
            'transactional_emails_enabled' => true,
            'pulse_alert_emails_enabled' => true,
            'signal_email_alerts_enabled' => true,
            'trade_email_alerts_enabled' => false,
            'promotion_emails_enabled' => true,
            'expiry_emails_enabled' => true,
            'daily_market_brief_enabled' => false,
        ])->mapWithKeys(fn ($default, $key) => [$key => (bool) PulseSystemSetting::value($key, $default)]);

        $adminEventSettings = [
            'email' => (string) SiteSetting::query()->where('key', 'admin_notification_email')->value('value'),
            'new_registration' => filter_var(SiteSetting::query()->where('key', 'admin_notify_new_registration')->value('value'), FILTER_VALIDATE_BOOL),
            'new_subscription' => filter_var(SiteSetting::query()->where('key', 'admin_notify_new_subscription')->value('value'), FILTER_VALIDATE_BOOL),
        ];

        $logs = EmailDeliveryLog::query()->latest()->paginate(40);
        $stats = [
            'sent_24h' => EmailDeliveryLog::query()->where('status', 'sent')->where('created_at', '>=', now()->subDay())->count(),
            'failed_24h' => EmailDeliveryLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'devices' => MobileDevice::query()->where('is_active', true)->count(),
        ];
        $configuredDays = PulseSystemSetting::value('expiry_reminder_days', [7, 3, 1, 0]);
        if (! is_array($configuredDays)) $configuredDays = explode(',', (string) $configuredDays);
        $expiryDays = collect($configuredDays)->map(fn ($day) => (int) $day)->filter(fn ($day) => $day >= 0 && $day <= 90)->unique()->sortDesc()->values();
        if ($expiryDays->isEmpty()) $expiryDays = collect([7, 3, 1, 0]);
        $today = today();
        $expiryAudience = $expiryDays->mapWithKeys(function (int $day) use ($today): array {
            $date = $today->copy()->addDays($day);
            $count = UserServiceAccess::query()->where('service', 'pulse')->where('status', 'active')->whereDate('ends_at', $date)->count();
            return [(string) $day => $count];
        });
        $expirySent30 = EmailDeliveryLog::query()->where('status', 'sent')->where('event', 'like', 'plan_expiry_%')->where('created_at', '>=', now()->subDays(30))->count();
        return view('admin.enterprise.emails', compact('settings', 'adminEventSettings', 'logs', 'stats', 'expiryDays', 'expiryAudience', 'expirySent30'));
    }

    public function updateEmailSettings(Request $request)
    {
        $data = $request->validate([
            'admin_notification_email' => ['required', 'email', 'max:255'],
            'admin_notify_new_registration' => ['nullable', Rule::in(['0', '1'])],
            'admin_notify_new_subscription' => ['nullable', Rule::in(['0', '1'])],
            'expiry_reminder_days' => ['required', 'string', 'max:100', 'regex:/^\s*\d{1,2}(\s*,\s*\d{1,2})*\s*$/'],
        ]);

        $expiryDays = collect(explode(',', $data['expiry_reminder_days']))
            ->map(fn ($day) => (int) trim($day))->filter(fn ($day) => $day >= 0 && $day <= 90)->unique()->sortDesc()->values();
        if ($expiryDays->isEmpty() || $expiryDays->count() > 8) {
            return back()->withInput()->withErrors(['expiry_reminder_days' => 'Enter between 1 and 8 unique reminder days from 0 to 90, separated by commas.']);
        }

        $keys = ['transactional_emails_enabled','pulse_alert_emails_enabled','signal_email_alerts_enabled','trade_email_alerts_enabled','promotion_emails_enabled','expiry_emails_enabled','daily_market_brief_enabled'];
        foreach ($keys as $key) {
            PulseSystemSetting::updateOrCreate(['key' => $key], [
                'value' => $request->boolean($key) ? '1' : '0',
                'type' => 'boolean',
                'group' => 'email',
                'description' => 'ABS production email control',
            ]);
        }
        PulseSystemSetting::updateOrCreate(['key' => 'expiry_reminder_days'], [
            'value' => $expiryDays->toJson(),
            'type' => 'json',
            'group' => 'email',
            'description' => 'Admin-configured UTC day thresholds for deduplicated Pulse plan expiry reminders.',
        ]);

        SiteSetting::updateOrCreate(['key' => 'admin_notification_email'], [
            'value' => strtolower(trim($data['admin_notification_email'])),
            'type' => 'string',
            'group' => 'communications',
        ]);
        SiteSetting::updateOrCreate(['key' => 'admin_notify_new_registration'], [
            'value' => $request->boolean('admin_notify_new_registration') ? '1' : '0',
            'type' => 'boolean',
            'group' => 'communications',
        ]);
        SiteSetting::updateOrCreate(['key' => 'admin_notify_new_subscription'], [
            'value' => $request->boolean('admin_notify_new_subscription') ? '1' : '0',
            'type' => 'boolean',
            'group' => 'communications',
        ]);

        return back()->with('success', 'Email communication and administrator event-notification controls updated.');
    }

    public function testEmail(Request $request, BrandedMailService $mail)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $mail->testEmail($data['email']);
        return back()->with('success', 'Test email delivery was attempted. Review the delivery log below for the result.');
    }

    public function updateEconomicCalendarSettings(Request $request, EconomicCalendarService $calendar)
    {
        $data = $request->validate([
            'api_key' => ['nullable','string','max:255'],
            'auto_sync' => ['nullable','boolean'],
            'clear_api_key' => ['nullable','boolean'],
        ]);
        if ($request->boolean('clear_api_key')) $calendar->clearApiKey();
        elseif (filled($data['api_key'] ?? null)) $calendar->saveApiKey((string) $data['api_key']);
        $calendar->setAutoSync($request->boolean('auto_sync'));
        return back()->with('success', 'Economic Calendar integration settings updated.');
    }

    public function syncEconomicCalendar(EconomicCalendarService $calendar)
    {
        try {
            $result = $calendar->sync();
            return back()->with('success', 'Economic Calendar synced: '.number_format($result['created']).' new, '.number_format($result['updated']).' updated.');
        } catch (\Throwable $e) {
            return back()->with('warning', 'Economic Calendar sync could not complete: '.$e->getMessage());
        }
    }

    private function ensureAdminEventNotificationSettings(): void
    {
        SiteSetting::firstOrCreate(['key' => 'admin_notification_email'], [
            'value' => 'i@armansabir.com',
            'type' => 'string',
            'group' => 'communications',
        ]);
        SiteSetting::firstOrCreate(['key' => 'admin_notify_new_registration'], [
            'value' => '1',
            'type' => 'boolean',
            'group' => 'communications',
        ]);
        SiteSetting::firstOrCreate(['key' => 'admin_notify_new_subscription'], [
            'value' => '1',
            'type' => 'boolean',
            'group' => 'communications',
        ]);
    }

    private function contentDefinition(string $type): array
    {
        return match ($type) {
            'research' => [ResearchReport::class, ['title' => 'Research CMS', 'description' => 'Publish structured research and market analysis.', 'fields' => ['title','slug','summary','body','category','asset_symbol','risk_level','image_url','status','is_featured','published_at']]],
            'learning' => [LearningArticle::class, ['title' => 'Learning CMS', 'description' => 'Manage educational articles and learning material.', 'fields' => ['title','slug','excerpt','body','category','level','duration_minutes','status','is_featured','published_at']]],
            'events' => [EconomicEvent::class, ['title' => 'Economic Calendar CMS', 'description' => 'Manage CPI, PPI, FOMC, jobs, GDP and other market-moving macro events with previous, forecast, actual and easy crypto context.', 'fields' => ['title','country','currency','impact','event_at','previous_value','forecast_value','actual_value','source','source_url','crypto_impact','easy_explanation','crypto_impact_summary','is_crypto_relevant']]],
            'products' => [Product::class, ['title' => 'Products & Services CMS', 'description' => 'Manage public ABS services and product presentation.', 'fields' => ['name','slug','category','tagline','description','icon','accent','features','status','sort_order','is_featured']]],
            default => abort(404),
        };
    }

    private function validatedContent(Request $request, string $type, ?Model $item = null): array
    {
        if ($type === 'research') {
            $data = $request->validate([
                'title' => ['required','string','max:180'], 'slug' => ['nullable','string','max:190'], 'summary' => ['required','string','max:1000'],
                'body' => ['required','string'], 'category' => ['required','string','max:80'], 'asset_symbol' => ['nullable','string','max:20'],
                'risk_level' => ['required',Rule::in(['low','medium','high','not_rated'])], 'image_url' => ['nullable','url','max:500'],
                'status' => ['required',Rule::in(['draft','published','archived'])], 'is_featured' => ['nullable','boolean'], 'published_at' => ['nullable','date'],
            ]);
            $data['slug'] = $this->uniqueSlug(ResearchReport::class, $data['slug'] ?? '', $data['title'], $item?->id);
            $data['asset_symbol'] = $data['asset_symbol'] ? strtoupper($data['asset_symbol']) : null;
            $data['is_featured'] = $request->boolean('is_featured');
            if ($data['status'] === 'published' && empty($data['published_at'])) $data['published_at'] = now();
            return $data;
        }
        if ($type === 'learning') {
            $data = $request->validate([
                'title' => ['required','string','max:180'], 'slug' => ['nullable','string','max:190'], 'excerpt' => ['required','string','max:700'], 'body' => ['required','string'],
                'category' => ['required','string','max:80'], 'level' => ['required',Rule::in(['beginner','intermediate','advanced'])], 'duration_minutes' => ['required','integer','min:1','max:600'],
                'status' => ['required',Rule::in(['draft','published','archived'])], 'is_featured' => ['nullable','boolean'], 'published_at' => ['nullable','date'],
            ]);
            $data['slug'] = $this->uniqueSlug(LearningArticle::class, $data['slug'] ?? '', $data['title'], $item?->id);
            $data['is_featured'] = $request->boolean('is_featured');
            if ($data['status'] === 'published' && empty($data['published_at'])) $data['published_at'] = now();
            return $data;
        }
        if ($type === 'events') {
            $data = $request->validate([
                'title' => ['required','string','max:180'], 'country' => ['nullable','string','max:80'], 'currency' => ['nullable','string','max:10'],
                'impact' => ['required',Rule::in(['low','medium','high'])], 'event_at' => ['required','date'], 'previous_value' => ['nullable','string','max:100'],
                'forecast_value' => ['nullable','string','max:100'], 'actual_value' => ['nullable','string','max:100'], 'source' => ['nullable','string','max:255'],
                'source_url' => ['nullable','url','max:500'], 'crypto_impact' => ['nullable',Rule::in(['supportive','pressure','volatile','mixed','neutral'])],
                'easy_explanation' => ['nullable','string','max:3000'], 'crypto_impact_summary' => ['nullable','string','max:3000'],
                'is_crypto_relevant' => ['nullable','boolean'],
            ]);
            $data['currency'] = filled($data['currency'] ?? null) ? strtoupper((string) $data['currency']) : null;
            $data['is_crypto_relevant'] = $request->boolean('is_crypto_relevant', true);
            $auto = app(MacroImpactInterpreter::class)->interpret((string) $data['title'], $data['actual_value'] ?? null, $data['forecast_value'] ?? null, $data['previous_value'] ?? null);
            $data['crypto_impact'] = $data['crypto_impact'] ?: $auto['crypto_impact'];
            $data['easy_explanation'] = $data['easy_explanation'] ?: $auto['easy_explanation'];
            $data['crypto_impact_summary'] = $data['crypto_impact_summary'] ?: $auto['crypto_impact_summary'];
            return $data;
        }
        $data = $request->validate([
            'name' => ['required','string','max:180'], 'slug' => ['nullable','string','max:190'], 'category' => ['required','string','max:80'],
            'tagline' => ['required','string','max:255'], 'description' => ['required','string'], 'icon' => ['nullable','string','max:20'], 'accent' => ['nullable','string','max:30'],
            'features' => ['nullable','string'], 'status' => ['required',Rule::in(['draft','live','archived'])], 'sort_order' => ['required','integer','min:0','max:10000'], 'is_featured' => ['nullable','boolean'],
        ]);
        $data['slug'] = $this->uniqueSlug(Product::class, $data['slug'] ?? '', $data['name'], $item?->id);
        $data['features'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($data['features'] ?? '')) ?: [])));
        $data['is_featured'] = $request->boolean('is_featured');
        return $data;
    }

    private function uniqueSlug(string $model, string $requested, string $fallback, ?int $ignoreId): string
    {
        $base = Str::slug(trim($requested) ?: $fallback) ?: 'item';
        $slug = $base; $i = 2;
        while ($model::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }

    private function label(string $type): string
    {
        return match ($type) { 'research' => 'Research', 'learning' => 'Learning', 'events' => 'Economic event', 'products' => 'Product/service', default => 'Content' };
    }
}
