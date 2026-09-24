<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case AllLeads = 'all_leads';
    case Customers = 'customers';
    case LeadStatuses = 'lead_statuses';

    public function label(): string
    {
        return match ($this) {
            self::AllLeads => 'All Leads',
            self::Customers => 'Customers Only',
            self::LeadStatuses => 'Selected Lead Statuses',
        };
    }
}
