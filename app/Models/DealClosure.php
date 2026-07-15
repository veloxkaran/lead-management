<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealClosure extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'lead_id', 'closed_by', 'closed_date', 'deal_value', 'closing_comment'];

    protected function casts(): array
    {
        return [
            'closed_date' => 'date',
            'deal_value' => 'decimal:2',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
