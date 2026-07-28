<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return User::with([
            'department.company'
        ])
        ->orderBy('name')
        ->get()
        ->map(function ($user) {

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'department' => $user->department
                    ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name,
                    ]
                    : null,
                'company' => $user->department?->company
                    ? [
                        'id' => $user->department->company->id,
                        'name' => $user->department->company->name,
                    ]
                    : null,
            ];
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:manager,auditor,auditee',
            'department_id' => [
                'nullable',
                'exists:departments,id'
            ],
        ]);

        if (
                $request->role === 'auditee' &&
                !$request->department_id
            ) {

                return response()->json([
                    'message' => 'Department is required for Auditee.'
                ], 422);

            }

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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,$id",
            'role' => 'required|in:manager,auditor,auditee',
            'department_id' => [
                'nullable',
                'exists:departments,id'
            ],
        ]);

        if (
            $request->role === 'auditee' &&
            !$request->department_id
        ) {

            return response()->json([
                'message' => 'Department is required for Auditee.'
            ], 422);

        }

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

        return $user->load('department.company');
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
