<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $fks = Company::where(
            "code",
            "FFS"
        )->first();

        $it = $fks
            ->departments()
            ->where("code", "IT")
            ->first();

        $finance = $fks
            ->departments()
            ->where("code", "FIN")
            ->first();
    
        $this->upsertUser('fahmi@manager.local', [
            'name' => 'Fahmi Prayuda',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'level' => 'Audit Head',
            'department_id' => null,
        ]);

        $this->upsertUser('aulia@auditor.local', [
            'name' => 'Aulia',
            'password' => Hash::make('password'),
            'role' => 'auditor',
            'level' => 'Senior Auditor',
            'department_id' => null,
        ]);

        $this->upsertUser('shavazia@auditee.local', [
            'name' => 'Shavazia',
            'password' => Hash::make('password'),
            'role' => 'auditee',
            'level' => 'Staff',
            'department_id' => $it?->id,
        ]);
    }

    private function upsertUser(string $email, array $attributes): User
    {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            $user->fill($attributes);
            $user->deleted_at = null;
            $user->save();
            return $user;
        }

        return User::create(array_merge($attributes, [
            'email' => $email,
        ]));
    }
}  