<?php

namespace App\Http\Requests\SystemModule;

use App\Models\SystemModule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSystemModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SystemModule::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:system_modules,name'],
        ];
    }
}
