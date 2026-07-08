<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(protected UserRepository $users)
    {
    }

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->filter($filters, $perPage);
    }

    public function create(array $attributes): User
    {
        $attributes['password'] = Hash::make($attributes['password']);

        return $this->users->create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        return $this->users->update($user, $attributes);
    }

    public function suspend(User $user): User
    {
        return $this->users->update($user, [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function activate(User $user): User
    {
        return $this->users->update($user, [
            'status' => UserStatus::Active,
            'suspended_at' => null,
        ]);
    }

    public function resetPassword(User $user): string
    {
        $newPassword = Str::password(12);

        $this->users->update($user, ['password' => Hash::make($newPassword)]);

        return $newPassword;
    }
}
