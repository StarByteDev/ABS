<?php
namespace App\Services;

use App\Models\PulseAlert;
use App\Models\MobileDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Throwable;

/** One alert per device, with bounded retries. No outbound FCM call during DB insertion. */
class PulseMobilePushDispatcher
{
    public function __construct(private readonly PulseFirebasePushService $firebase) {}

    public function queueForAlert(PulseAlert $alert): int
    {
        if (! Schema::hasTable('pulse_push_deliveries')) return 0;
        $user = $alert->user;
        if (! $user || $user->status !== 'active') return 0;
        $preferences = $user->pulseSettings?->notification_preferences ?? [];
        $category = match ((string)$alert->type) {
            'signal' => 'signals', 'trade' => 'trades', 'risk' => 'risk',
            'market' => 'market', 'plan' => 'plan_expiry', default => 'system',
        };
        if (array_key_exists($category, (array)$preferences) && ! $preferences[$category]) return 0;
        $count=0;
        foreach (MobileDevice::query()->where('user_id', $alert->user_id)->where('is_active',true)
                ->whereIn('platform',['ios','android'])->whereNotNull('push_token')->where('push_token','<>','')->get() as $device) {
            $created = DB::table('pulse_push_deliveries')->insertOrIgnore([
                'pulse_alert_id'=>$alert->id,'mobile_device_id'=>$device->id,
                'status'=>'pending','attempts'=>0,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $count += $created;
        }
        return $count;
    }

    public function deliver(int $maximum=40): array
    {
        $stats=['sent'=>0,'failed'=>0,'invalid'=>0,'configured'=>$this->firebase->configured()];
        if (! $stats['configured'] || ! Schema::hasTable('pulse_push_deliveries')) return $stats;
        $limit=max(1,min(100,$maximum));
        $rows=DB::table('pulse_push_deliveries')->whereIn('status',['pending','failed'])
            ->where('attempts','<',3)->orderBy('id')->limit($limit)->get();
        foreach ($rows as $row) {
            // Atomic claim: multiple cron runners cannot send the same notification.
            $claimed=DB::table('pulse_push_deliveries')->where('id',$row->id)
                ->whereIn('status',['pending','failed'])->where('attempts',$row->attempts)
                ->update(['status'=>'sending','attempts'=>$row->attempts+1,'updated_at'=>now()]);
            if (! $claimed) continue;
            $alert=PulseAlert::query()->find($row->pulse_alert_id);
            $device=MobileDevice::query()->find($row->mobile_device_id);
            if (! $alert || ! $device || ! $device->is_active || ! $device->push_token ||
                $alert->created_at?->lt(now()->subDays(7))) {
                DB::table('pulse_push_deliveries')->where('id',$row->id)->update(['status'=>'invalid','updated_at'=>now()]);
                $stats['invalid']++; continue;
            }
            try {
                $result=$this->firebase->send((string)$device->push_token,
                    (string)$alert->title, mb_substr((string)$alert->message,0,220),
                    ['type'=>'pulse_alert','alert_id'=>(string)$alert->id,'screen'=>'alerts',
                     'url'=>(string)($alert->action_url ?: url('/pulse/alerts'))]);
                $status=$result['ok'] ? 'sent' : ($result['invalid_token'] ? 'invalid' : 'failed');
                if ($result['invalid_token']) $device->update(['is_active'=>false]);
                $stats[$status==='sent'?'sent':($status==='invalid'?'invalid':'failed')]++;
                DB::table('pulse_push_deliveries')->where('id',$row->id)->update([
                    'status'=>$status,'error_code'=>$result['ok']?null:(string)$result['error'],
                    'sent_at'=>$result['ok']?now():null,'updated_at'=>now(),
                ]);
            } catch(Throwable $e) {
                DB::table('pulse_push_deliveries')->where('id',$row->id)->update(['status'=>'failed','error_code'=>'TRANSPORT_ERROR','updated_at'=>now()]);
                $stats['failed']++;
                Log::warning('Pulse push transport error',['delivery_id'=>$row->id,'message'=>$e->getMessage()]);
            }
        }
        return $stats;
    }
}
