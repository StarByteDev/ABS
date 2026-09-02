<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => $request->user()->mobileDevices()->latest('last_seen_at')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'max:190'],
            'platform' => ['required', Rule::in(['ios', 'android', 'web', 'unknown'])],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:80'],
            'push_token' => ['nullable', 'string', 'max:4096'],
        ]);
        $device = MobileDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_uuid' => $data['device_uuid']],
            array_merge($data, ['is_active' => true, 'last_seen_at' => now()]),
        );
        return response()->json(['message' => 'Device registration updated.', 'data' => $device], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, MobileDevice $device)
    {
        abort_unless($device->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'device_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'os_version' => ['sometimes', 'nullable', 'string', 'max:80'],
            'push_token' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $device->update(array_merge($data, ['last_seen_at' => now()]));
        return response()->json(['message' => 'Device updated.', 'data' => $device->fresh()]);
    }

    public function destroy(Request $request, MobileDevice $device)
    {
        abort_unless($device->user_id === $request->user()->id, 403);
        $device->delete();
        return response()->json(status: 204);
    }
}
