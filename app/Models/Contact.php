<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An address-book entry (Contacts menu): anyone worth reaching who isn't
 * necessarily a lead. Every field is optional, but a contact always has
 * at least one. Email is stored lowercased so duplicates are easy to spot.
 */
class Contact extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** The fields a person fills in, in display order. */
    public const FIELDS = ['company_name', 'name', 'email', 'phone'];

    protected $fillable = ['company_id', 'company_name', 'name', 'email', 'phone', 'created_by'];

    protected static function booted(): void
    {
        static::saving(function (Contact $contact) {
            foreach (self::FIELDS as $field) {
                $value = trim((string) $contact->{$field});
                $contact->{$field} = $value === '' ? null : $value;
            }

            if ($contact->email !== null) {
                $contact->email = mb_strtolower($contact->email);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Matches any of the four fields; phone digits are compared without
     * spaces or dashes, so "980-000" finds "9800001234".
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $term);

        return $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('company_name', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");

            if (strlen($digits) >= 3) {
                $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$digits}%"]);
            }
        });
    }

    /**
     * What to call the contact in lists and messages: the person, else the
     * company, else the email or phone.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->company_name ?? $this->email ?? $this->phone ?? 'Contact #'.$this->id;
    }
}
