<?php

namespace App\Enums;

enum ActivityType: string
{
    case Meeting = 'meeting';
    case PhoneCall = 'phone_call';
    case Email = 'email';
    case Demo = 'demo';
    case FollowUp = 'follow_up';
    case Proposal = 'proposal';
    case RequirementDiscussion = 'requirement_discussion';
    case ContractDiscussion = 'contract_discussion';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Meeting',
            self::PhoneCall => 'Phone Call',
            self::Email => 'Email',
            self::Demo => 'Demo',
            self::FollowUp => 'Follow-up',
            self::Proposal => 'Proposal',
            self::RequirementDiscussion => 'Requirement Discussion',
            self::ContractDiscussion => 'Contract Discussion',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Meeting => 'bi-people',
            self::PhoneCall => 'bi-telephone',
            self::Email => 'bi-envelope',
            self::Demo => 'bi-display',
            self::FollowUp => 'bi-arrow-repeat',
            self::Proposal => 'bi-file-earmark-text',
            self::RequirementDiscussion => 'bi-chat-left-text',
            self::ContractDiscussion => 'bi-file-earmark-ruled',
        };
    }
}
