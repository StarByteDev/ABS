<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Services\PulseFirebasePushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PulsePushAdminController extends Controller
{
    public function status(Request $request, PulseFirebasePushService $firebase)
    {
        return response()->json(['data'=>[
            'configured'=>$firebase->configured(),
            'active_mobile_devices'=>MobileDevice::query()->where('is_active',true)
               ->whereIn('platform',['ios','android'])->whereNotNull('push_token')->where('push_token','<>','')->count(),
            'outbox_ready'=>Schema::hasTable('pulse_push_deliveries'),
            'test_send_path'=>'POST /api/v1/admin/pulse/push/test',
            'broadcast_path'=>'POST /api/v1/admin/pulse/alerts/broadcast',
        ]]);
    }

    public function test(Request $request, PulseFirebasePushService $firebase)
    {
        $data=$request->validate(['device_uuid'=>['nullable','string','max:190']]);
        if (! $firebase->configured()) return response()->json(['message'=>'Firebase service account not configured.'],422);
        $devices=MobileDevice::query()->where('user_id',$request->user()->id)->where('is_active',true)
          ->whereIn('platform',['ios','android'])->whereNotNull('push_token')->where('push_token','<>','')
          ->when(!empty($data['device_uuid']),fn($q)=>$q->where('device_uuid',$data['device_uuid']))
          ->limit(3)->get();
        if ($devices->isEmpty()) return response()->json(['message'=>'Register a device with an FCM token using POST /api/v1/devices.'],422);
        $results=[];
        foreach($devices as $device) {
            $sent=$firebase->send((string)$device->push_token,'Pulse · Test Notification',
                'Firebase delivery test from your ABS administrator account.',
                ['type'=>'push_test','screen'=>'alerts']);
            $results[]=['device_id'=>$device->id,'ok'=>$sent['ok'],'error'=>$sent['error']];
        }
        return response()->json(['data'=>$results]);
    }
}
