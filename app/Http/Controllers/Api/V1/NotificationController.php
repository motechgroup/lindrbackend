<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $notifications = DB::table('notifications')
            ->where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->input('per_page', 20));

        $list = collect($notifications->items())->map(function ($item) {
            $payload = json_decode($item->data, true) ?? [];

            return [
                'id' => (string) $item->id,
                'type' => $payload['notification_type'] ?? 'SYSTEM',
                'category' => $payload['category'] ?? 'system',
                'title' => $payload['title'] ?? 'Notification',
                'body' => $payload['body'] ?? '',
                'data' => $payload['metadata'] ?? null,
                'read_at' => $item->read_at,
                'created_at' => $item->created_at,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved.',
            'data' => $list,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = DB::table('notifications')
            ->where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Unread notifications count retrieved.',
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->update(['read_at' => now()]);

        return $this->successResponse(null, 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = DB::table('notifications')
            ->where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->successResponse(['updated_count' => $updated], 'All notifications marked as read.');
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $defaults = [
            'messages' => true,
            'calls' => true,
            'matches' => true,
            'gifts' => true,
            'earnings' => true,
            'withdrawals' => true,
            'levels' => true,
            'spotlight' => true,
        ];

        $settings = array_merge($defaults, $user->notification_settings ?? []);

        return $this->successResponse(['categories' => $settings], 'Notification preferences retrieved.');
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $input = $request->has('categories') ? $request->input('categories') : $request->all();

        $defaults = [
            'messages' => true,
            'calls' => true,
            'matches' => true,
            'gifts' => true,
            'earnings' => true,
            'withdrawals' => true,
            'levels' => true,
            'spotlight' => true,
        ];

        $current = array_merge($defaults, $user->notification_settings ?? []);
        $updated = array_merge($current, is_array($input) ? $input : []);

        $user->update(['notification_settings' => $updated]);

        return $this->successResponse(['categories' => $updated], 'Notification preferences updated.');
    }
}
