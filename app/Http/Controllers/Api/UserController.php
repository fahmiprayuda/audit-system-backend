<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return User::with('department')
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required',
        ]);

        return User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt(
                $request->password
            ),
            'role' => $request->role,
            'department_id' =>
                $request->role === 'auditee'
                    ? $request->department_id
                    : null,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'department_id' =>
                $request->role === 'auditee'
                    ? $request->department_id
                    : null,
        ]);

        return $user;
    }

    public function destroy($id)
    {
        User::destroy($id);

        return [
            'message' => 'Deleted'
        ];
    }

    public function resetPassword(
        Request $request,
        $id
    )
    {
        $request->validate([
            'password' => 'required|min:6'
        ]);

        $user = User::findOrFail($id);

        $user->update([
            'password' => bcrypt(
                $request->password
            )
        ]);

        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }

}
