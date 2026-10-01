<?php

namespace App\Enums;

/**
 * Which leads a campaign pulls in. "None" is for campaigns that only go to
 * hand-picked leads, saved Contacts and/or pasted numbers and emails.
 */
enum CampaignAudience: string
{
    case None = 'none';
    case AllLeads = 'all_leads';
    case Customers = 'customers';
    case LeadStatuses = 'lead_statuses';
    case Industries = 'industries';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No lead group (only picked leads, Contacts or extra contacts)',
            self::AllLeads => 'All Leads',
            self::Customers => 'Customers Only',
            self::LeadStatuses => 'Selected Lead Statuses',
            self::Industries => 'Selected Industries',
        };
    }
}
