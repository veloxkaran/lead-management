<?php

namespace App\Enums;

enum PolicyDocumentType: string
{
    case Sop = 'sop';
    case DepartmentJd = 'department_jd';
    case IndividualJd = 'individual_jd';

    public function label(): string
    {
        return match ($this) {
            self::Sop => 'SOP',
            self::DepartmentJd => 'Department Job Description',
            self::IndividualJd => 'Individual Job Description',
        };
    }

    /**
     * Sop and DepartmentJd are assigned to a Department; IndividualJd is
     * assigned to a single User — this drives which field is required in
     * PolicyDocumentService/FormRequest validation.
     */
    public function isDepartmentAssigned(): bool
    {
        return $this !== self::IndividualJd;
    }
}
