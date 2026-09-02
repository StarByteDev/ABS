<?php

namespace App\Services;

use App\Models\PulseAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class PulseAuditService
{
    public function record(
        string $action,
        ?User $user = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $environment = null,
        array $context = [],
        ?Request $request = null,
    ): PulseAuditLog {
        return PulseAuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'environment' => $environment,
            'ip_address' => $request?->ip(),
            'context' => $this->sanitize($context),
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $context): array
    {
        foreach (['api_key', 'api_secret', 'secret', 'signature', 'password'] as $sensitive) {
            if (array_key_exists($sensitive, $context)) {
                $context[$sensitive] = '[redacted]';
            }
        }

        return $context;
    }
}
