<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PopRequest;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EventRequestController extends Controller
{
    private function sendPushNotification($token, $title, $body, $data = [])
    {
        if (!$token) return;

        Http::post('https://exp.host/--/api/v2/push/send', [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
        ]);
    }

    public function storeRequest(Request $request, $popId)
    {
        $pop = Pop::with('user')->findOrFail($popId);
        $user = $request->user();
        $userId = $user->id;

        if ($pop->user_id === $userId) {
            return response()->json(['message' => 'You are the host.'], 400);
        }

        $existingRequest = PopRequest::where('user_id', $userId)
            ->where('pop_id', $popId)
            ->first();

        if ($existingRequest) {
            if ($existingRequest->status === 'pending_invite') {
                $existingRequest->update(['status' => 'accepted']);
                $pop->increment('current_guests');

                $this->sendPushNotification(
                    $pop->user->device_token ?? null,
                    'Invite Accepted',
                    "{$user->name} accepted your invite to '{$pop->title}'.",
                    ['type' => 'invite_accepted', 'pop_id' => $pop->id]
                );

                return response()->json([
                    'message' => 'Invitation accepted.',
                    'status' => 'accepted'
                ]);
            }

            return response()->json([
                'message' => 'You have already requested to join or have already been accepted.'
            ], 400);
        }

        $isOpenAndFree = ((empty($pop->access) || strtolower($pop->access) === 'open') && !$pop->is_ticketed);
        $status = $isOpenAndFree ? 'accepted' : 'pending';

        PopRequest::create([
            'user_id' => $userId,
            'pop_id' => $popId,
            'status' => $status
        ]);

        if ($isOpenAndFree) {
            $pop->increment('current_guests');

            $this->sendPushNotification(
                $pop->user->device_token ?? null,
                'New Guest',
                "{$user->name} joined '{$pop->title}'.",
                ['type' => 'guest_joined', 'pop_id' => $pop->id]
            );

            return response()->json([
                'message' => 'You have joined this open event.',
                'status' => 'accepted'
            ]);
        }

        $this->sendPushNotification(
            $pop->user->device_token ?? null,
            'New Join Request',
            "{$user->name} requested to join '{$pop->title}'.",
            ['type' => 'join_request', 'pop_id' => $pop->id]
        );

        return response()->json([
            'message' => 'Request sent.',
            'status' => 'pending'
        ]);
    }

    public function confirmPayment(Request $request, $popId)
    {
        $userId = $request->user()->id;
        $pop = Pop::with('user')->findOrFail($popId);

        $popRequest = PopRequest::where('pop_id', $popId)
            ->where('user_id', $userId)
            ->first();

        $alreadyCounted = $popRequest && in_array($popRequest->status, ['accepted', 'paid']);

        if (!$popRequest) {
            $popRequest = PopRequest::create([
                'pop_id' => $popId,
                'user_id' => $userId,
                'status' => 'paid'
            ]);
        } else {
            $popRequest->update(['status' => 'paid']);
        }

        if (!$alreadyCounted) {
            $pop->increment('current_guests');

            $this->sendPushNotification(
                $pop->user->device_token ?? null,
                'Ticket Sold',
                "{$request->user()->name} bought a ticket for '{$pop->title}'.",
                ['type' => 'ticket_sold', 'pop_id' => $pop->id]
            );
        }

        return response()->json([
            'message' => 'Payment successfully processed and added to the guest list.',
            'status' => $popRequest->status
        ]);
    }

    public function getPendingRequests(Request $request)
    {
        $userId = $request->user()->id;

        $requests = PopRequest::whereHas('pop', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->whereIn('status', ['pending', 'accepted', 'paid'])
            ->with(['user:id,name,username,profile_image', 'pop:id,title'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'pop_id' => $req->pop_id,
                    'user_id' => $req->user_id,
                    'name' => $req->user->name,
                    'username' => $req->user->username,
                    'pop_title' => $req->pop->title,
                    'profile_image' => $req->user->profile_image,
                    'status' => $req->status
                ];
            });

        return response()->json(['requests' => $requests]);
    }

    public function inviteGuest(Request $request, $id)
    {
        $host = $request->user();
        $invitedUserId = $request->input('user_id');
        $pop = Pop::findOrFail($id);

        $existing = PopRequest::where('pop_id', $id)
            ->where('user_id', $invitedUserId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'User is already invited or has already requested to join.'
            ], 400);
        }

        PopRequest::create([
            'pop_id' => $id,
            'user_id' => $invitedUserId,
            'status' => 'pending_invite'
        ]);

        $invitedUser = User::find($invitedUserId);

        $this->sendPushNotification(
            $invitedUser->device_token ?? null,
            'You Have Been Invited',
            "{$host->name} invited you to '{$pop->title}'.",
            ['type' => 'invite', 'pop_id' => $pop->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent.'
        ]);
    }

    public function acceptRequest($requestId)
    {
        $request = PopRequest::with(['user', 'pop'])->findOrFail($requestId);

        if ($request->pop->user_id !== auth()->id() && $request->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($request->status !== 'accepted' && $request->status !== 'paid') {
            $request->update(['status' => 'accepted']);
            $request->pop->increment('current_guests');

            if ($request->pop->user_id === auth()->id()) {
                $this->sendPushNotification(
                    $request->user->device_token ?? null,
                    'Request Approved',
                    "You have been approved to join '{$request->pop->title}'.",
                    ['type' => 'request_approved', 'pop_id' => $request->pop->id]
                );
            }
        }

        return response()->json([
            'message' => 'Accepted.',
            'status' => 'accepted'
        ]);
    }

    public function getRequestsForPop(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You are not the host.'
            ], 403);
        }

        $requests = PopRequest::where('pop_id', $id)
            ->with(['user:id,name,username,profile_image'])
            ->get()
            ->map(function ($req) {
                $frontendStatus = 'pending';

                if (in_array($req->status, ['accepted', 'paid'])) {
                    $frontendStatus = 'accepted';
                } elseif ($req->status === 'pending_invite') {
                    $frontendStatus = 'invited';
                }

                return [
                    'id' => $req->id,
                    'status' => $frontendStatus,
                    'original_status' => $req->status,
                    'user' => [
                        'id' => $req->user->id,
                        'name' => $req->user->name,
                        'username' => $req->user->username,
                        'profile_image' => $req->user->profile_image,
                    ]
                ];
            });

        return response()->json(['requests' => $requests], 200);
    }

    public function declineRequest(Request $request, $requestId)
    {
        $popRequest = PopRequest::findOrFail($requestId);
        $pop = Pop::findOrFail($popRequest->pop_id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (in_array($popRequest->status, ['accepted', 'paid'])) {
            if ($pop->current_guests > 0) {
                $pop->decrement('current_guests');
            }
        }

        $popRequest->delete();

        return response()->json([
            'message' => 'User successfully removed from the guest list and guest count updated.',
            'current_guests' => $pop->fresh()->current_guests
        ], 200);
    }

    public function getUserInvites(Request $request)
    {
        $userId = $request->user()->id;

        $invites = PopRequest::with(['pop.user'])
            ->where('user_id', $userId)
            ->where('status', 'pending_invite')
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function ($req) {
                return $req->pop !== null && $req->pop->user !== null;
            })
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'pop_id' => $req->pop_id,
                    'user_id' => $req->pop->user_id,
                    'name' => $req->pop->user->name,
                    'username' => $req->pop->user->username,
                    'pop_title' => $req->pop->title,
                    'profile_image' => $req->pop->user->profile_image,
                    'status' => $req->status
                ];
            })
            ->values();

        return response()->json(['invites' => $invites]);
    }
}
