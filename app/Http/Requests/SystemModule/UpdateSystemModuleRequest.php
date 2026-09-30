<?php

namespace App\Http\Requests\SystemModule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('system_module'));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('system_modules', 'name')->ignore($this->route('system_module')),
            ],
        ];
    }
}
