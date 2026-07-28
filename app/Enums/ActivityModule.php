<?php

namespace App\Enums;

use App\Support\ActivityModules\ActivityModuleRegistry;

enum ActivityModule: string
{
    case Lead = 'lead';
    case Requirement = 'requirement';
    case FollowUp = 'follow_up';
    case Whatsapp = 'whatsapp';
    case Goal = 'goal';
    case KnowledgeBase = 'knowledge_base';
    case Meeting = 'meeting';
    case Task = 'task';
    case Email = 'email';
    case Note = 'note';
    case Agenda = 'agenda';

    /**
     * Label/icon live in ActivityModuleRegistry (the single source of truth
     * for module metadata) — these delegate there rather than hardcoding a
     * second `match` here, so existing call sites (`$module->label()`) keep
     * working unchanged.
     */
    public function label(): string
    {
        return ActivityModuleRegistry::definition($this)->label;
    }

    public function icon(): string
    {
        return ActivityModuleRegistry::definition($this)->icon;
    }
}
