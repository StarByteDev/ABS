<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulseSystemSetting;
use App\Services\AdCmsService;
use App\Services\PulseAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminAdsController extends Controller
{
    public function index(AdCmsService $ads)
    {
        return view('admin.ads.index', [
            'settings' => $ads->settings(),
            'placements' => AdCmsService::PLACEMENTS,
        ]);
    }

    public function update(Request $request, AdCmsService $ads, PulseAuditService $audit)
    {
        $rules = [
            'enabled' => ['required','in:true,false'],
            'head_code' => ['nullable','string','max:20000'],
        ];
        foreach (array_keys(AdCmsService::PLACEMENTS) as $key) {
            $rules[$key.'_enabled'] = ['required','in:true,false'];
            $rules[$key.'_code'] = ['nullable','string','max:30000'];
        }
        $data = $request->validate($rules);

        try {
            $head = $ads->validateGoogleSnippet($data['head_code'] ?? '', true);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['head_code' => $e->getMessage()]);
        }

        $snippets = [];
        foreach (array_keys(AdCmsService::PLACEMENTS) as $key) {
            try {
                $snippets[$key] = $ads->validateGoogleSnippet($data[$key.'_code'] ?? '', true);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([$key.'_code' => $e->getMessage()]);
            }
        }

        $this->put('ads_cms_enabled', $data['enabled'] === 'true' ? '1' : '0', 'boolean', 'ads', 'Master switch for Admin-managed Google display advertisements.');
        $this->put('ads_google_head_code', $head, 'text', 'ads', 'Google AdSense / Google Ad Manager loader or head code.');
        foreach (AdCmsService::PLACEMENTS as $key => $label) {
            $this->put('ads_'.$key.'_enabled', $data[$key.'_enabled'] === 'true' ? '1' : '0', 'boolean', 'ads', $label.' enabled state.');
            $this->put('ads_'.$key.'_code', $snippets[$key], 'text', 'ads', $label.' Google ad snippet.');
        }

        $audit->record('admin.ads_cms_updated', $request->user(), null, null, null, [
            'enabled' => $data['enabled'] === 'true',
            'placements' => collect(array_keys(AdCmsService::PLACEMENTS))->mapWithKeys(fn ($key) => [$key => $data[$key.'_enabled'] === 'true'])->all(),
        ], $request);

        return back()->with('success', 'Ads CMS saved. Enabled Google snippets are now rendered in their selected Free Signal placements.');
    }

    private function put(string $key, string $value, string $type, string $group, string $description): void
    {
        PulseSystemSetting::updateOrCreate(['key'=>$key], compact('value','type','group','description'));
    }
}
