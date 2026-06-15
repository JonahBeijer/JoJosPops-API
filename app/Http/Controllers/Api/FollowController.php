<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function follow(Request $request, $id)
    {
        $userToFollow = User::findOrFail($id);
        $me = $request->user();

        // Je kunt jezelf niet volgen
        if ($me->id === $userToFollow->id) {
            return response()->json(['message' => 'Je kunt jezelf niet volgen.'], 400);
        }

        // syncWithoutDetaching zorgt ervoor dat er geen duplicaten komen
        $me->following()->syncWithoutDetaching([$userToFollow->id]);

        return response()->json([
            'message' => "Je volgt nu {$userToFollow->name}"
        ], 200);
    }

    public function unfollow(Request $request, $id)
    {
        $userToUnfollow = User::findOrFail($id);
        $me = $request->user();

        $me->following()->detach($userToUnfollow->id);

        return response()->json([
            'message' => "Je volgt {$userToUnfollow->name} niet meer"
        ], 200);
    }
}
