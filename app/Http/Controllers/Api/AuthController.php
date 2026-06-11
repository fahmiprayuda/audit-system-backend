<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::with('department')
        ->where('email', $request->email)
        ->first();

        if (! $user || ! Hash::check(
            $request->password,
            $user->password
        )) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user
            ->createToken('audit-system')
            ->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function profile()
    {
        return auth()->user()
            ->load('department');
    }
    
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Profile updated',
            'data' => $user
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (
            !Hash::check(
                $request->current_password,
                $user->password
            )
        ) {
            return response()->json([
                'message' => 'Current password incorrect'
            ], 422);
        }

        $user->update([
            'password' => bcrypt(
                $request->new_password
            )
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}