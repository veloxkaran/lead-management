<?php

namespace App\Enums;

enum PolicyDocumentType: string
{
    case Sop = 'sop';
    case IndividualJd = 'individual_jd';

    public function label(): string
    {
        return match ($this) {
            self::Sop => 'SOP',
            self::IndividualJd => 'Individual Job Description',
        };
    }

    /**
     * Sop applies to every active user in the company; IndividualJd is
     * assigned to a single User — this drives which field is required in
     * PolicyDocumentService/FormRequest validation and how assignedUsers()
     * resolves.
     */
    public function isCompanyWide(): bool
    {
        return $this === self::Sop;
    }
}
