<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'role' => ['required', new Enum(UserRole::class)],
            'status' => ['required', new Enum(UserStatus::class)],
            'team_id' => ['nullable', 'exists:teams,id'],
        ];
    }
}
