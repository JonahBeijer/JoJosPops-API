<?php

use App\Http\Controllers\Api\EventRequestController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FriendshipController;

Route::get('/pops/nearby', [EventController::class, 'nearby']);
Route::get('/pops', [EventController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/places', function (Request $request) {
    $query = $request->input('q');
    if (!$query) {
        return response()->json(['predictions' => []]);
    }
    $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
        'input' => $query,
        'language' => 'nl',
        'types' => '(cities)',
        'key' => config('services.google.places_key'),
    ]);
    return response()->json($response->json());
});

Route::get('/place-details', function (Request $request) {
    $placeId = $request->input('place_id');
    $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
        'place_id' => $placeId,
        'key' => config('services.google.places_key'),
    ]);
    return response()->json($response->json());
});

// Everything inside here requires a logged-in user
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/pops/fyp', [EventController::class, 'fypFeed']);

    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

    // 🔥 MOVED HERE: The push token route is now safely authenticated
    Route::post('/user/push-token', function (Illuminate\Http\Request $request) {
        $request->validate(['device_token' => 'required|string']);

        $user = $request->user();
        $user->device_token = $request->device_token;
        $user->save();

        return response()->json(['message' => 'Token saved']);
    });

    Route::post('/pops/{id}/invite', [EventRequestController::class, 'inviteGuest']);
    Route::get('/user/invites/pending', [EventRequestController::class, 'getUserInvites']);

    Route::post('/pops/{id}/confirm-payment', [EventRequestController::class, 'confirmPayment']);
    Route::post('/pops/{id}/confirm-ticket', [EventController::class, 'confirmTicket']);
    Route::post('/profile/sync-premium', [ProfileController::class, 'syncPremium']);
    Route::get('/host/requests/pending', [EventRequestController::class, 'getPendingRequests']);
    Route::post('/pops/{id}/join-request', [EventRequestController::class, 'storeRequest']);
    Route::post('/pops/requests/{id}/accept', [EventRequestController::class, 'acceptRequest']);
    Route::get('/friends', [FriendshipController::class, 'index']);
    Route::get('/friends/search', [FriendshipController::class, 'search']);
    Route::post('/friends/request', [FriendshipController::class, 'sendRequest']);
    Route::post('/friends/accept', [FriendshipController::class, 'acceptRequest']);
    Route::get('/friends/requests/pending', [FriendshipController::class, 'getPendingRequests']);
    Route::post('/pops', [EventController::class, 'store']);
    Route::post('/pops/{id}/buy-ticket', [EventController::class, 'buyTicket']);
    Route::put('/pops/{id}', [EventController::class, 'update']);
    Route::delete('/pops/{id}', [EventController::class, 'destroy']);
    Route::get('/pops/{id}/requests', [EventRequestController::class, 'getRequestsForPop']);
    Route::post('/pops/requests/{id}/decline', [EventRequestController::class, 'declineRequest']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/users/{id}/follow', [FollowController::class, 'follow']);
    Route::post('/users/{id}/unfollow', [FollowController::class, 'unfollow']);
});

Route::get('/pops/{id}', [EventController::class, 'show']);
