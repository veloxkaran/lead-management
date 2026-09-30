<?php

namespace App\Http\Requests\User;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePermissions', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => [Rule::enum(PermissionAction::class)],
        ];
    }

    /**
     * The checked boxes as the stored shape User::hasPermission() reads:
     * module => allowed actions, keeping only restricted modules. An
     * unchecked View clears the whole row (the other actions need it), a
     * fully checked row is dropped as "full access", and all-full collapses
     * to null — so an untouched form never pins today's component list
     * into the column.
     *
     * @return array<string, list<string>>|null
     */
    public function permissions(): ?array
    {
        $submitted = $this->validated('permissions') ?? [];
        $restricted = [];

        foreach (PermissionModule::cases() as $module) {
            $allowed = array_values(array_filter(
                array_map(fn (PermissionAction $action) => $action->value, $module->actions()),
                fn (string $action) => in_array($action, $submitted[$module->value] ?? [], true),
            ));

            if (! in_array(PermissionAction::View->value, $allowed, true)) {
                $allowed = [];
            }

            if (count($allowed) < count($module->actions())) {
                $restricted[$module->value] = $allowed;
            }
        }

        return $restricted ?: null;
    }
}
