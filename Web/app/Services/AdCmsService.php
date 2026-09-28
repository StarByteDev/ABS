<?php

namespace App\Services;

use App\Models\PulseSystemSetting;

class AdCmsService
{
    public const PLACEMENTS = [
        'free_signal_top' => 'Free Signal · Above workspace',
        'free_signal_inline' => 'Free Signal · Between teaser/result and membership CTA',
        'free_signal_footer' => 'Free Signal · Above risk notice',
    ];

    public function settings(): array
    {
        $placements = [];
        foreach (self::PLACEMENTS as $key => $label) {
            $placements[$key] = [
                'label' => $label,
                'enabled' => (bool) PulseSystemSetting::value('ads_'.$key.'_enabled', false),
                'code' => (string) PulseSystemSetting::value('ads_'.$key.'_code', ''),
            ];
        }

        return [
            'enabled' => (bool) PulseSystemSetting::value('ads_cms_enabled', false),
            'head_code' => (string) PulseSystemSetting::value('ads_google_head_code', ''),
            'placements' => $placements,
        ];
    }

    public function headCode(): string
    {
        $settings = $this->settings();
        return $settings['enabled'] ? trim((string) $settings['head_code']) : '';
    }

    public function snippet(string $placement): string
    {
        $settings = $this->settings();
        if (! $settings['enabled'] || ! isset($settings['placements'][$placement])) return '';
        $row = $settings['placements'][$placement];
        return $row['enabled'] ? trim((string) $row['code']) : '';
    }

    public function validateGoogleSnippet(?string $code, bool $allowEmpty = true): string
    {
        $code = trim((string) $code);
        if ($code === '' && $allowEmpty) return '';

        $blocked = ['<?php', '<?=','@php','@endphp','{{','{!!'];
        foreach ($blocked as $token) {
            if (str_contains(strtolower($code), strtolower($token))) {
                throw new \InvalidArgumentException('Ad code cannot contain PHP or Blade template instructions. Paste the Google-issued HTML/JavaScript snippet only.');
            }
        }

        $googleMarkers = [
            'googlesyndication.com',
            'doubleclick.net',
            'googletagservices.com',
            'googletag.',
            'adsbygoogle',
        ];
        $looksGoogle = false;
        $lower = strtolower($code);
        foreach ($googleMarkers as $marker) {
            if (str_contains($lower, $marker)) { $looksGoogle = true; break; }
        }
        if (! $looksGoogle) {
            throw new \InvalidArgumentException('This field accepts Google AdSense / Google Ad Manager code. The pasted snippet does not look like Google ad code.');
        }

        return $code;
    }
}
