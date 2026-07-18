<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskModule;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Rules\AuthorizedTaskAssignee;
use App\Services\OrganizationHierarchyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'module' => ['required', new Enum(TaskModule::class)],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'taskable_type' => ['nullable', 'string'],
            'taskable_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'exists:users,id', new AuthorizedTaskAssignee($this->user(), app(OrganizationHierarchyService::class))],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
