<?php

namespace App\Http\Requests\Agenda;

use App\Enums\AgendaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaStatusRequest extends FormRequest
{
    /**
     * Only the creator may finalize an agenda — enforced here (who), while
     * AgendaService::changeStatus() enforces the transition matrix (what
     * transitions are legal) — authorization kept separate from
     * business-rule validity.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('agenda'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([AgendaStatus::Closed->value, AgendaStatus::Dismissed->value])],
        ];
    }
}
