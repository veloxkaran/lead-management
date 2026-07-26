<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaCommentRequest extends FormRequest
{
    /**
     * Viewing is universal (checked for symmetry with every other action),
     * but posting a new comment is only allowed while the agenda is still
     * Pending — once finalized the thread stays visible but read-only.
     */
    public function authorize(): bool
    {
        $agenda = $this->route('agenda');

        return $this->user()->can('view', $agenda) && $agenda->isPending();
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string'],
            'parent_id' => [
                'nullable',
                Rule::exists('agenda_comments', 'id')->where('agenda_id', $this->route('agenda')->id),
            ],
        ];
    }
}
