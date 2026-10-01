<?php

namespace App\Support;

/**
 * The outcome of building a campaign's recipient list: who it goes to, and
 * every entry that was left out and why (duplicate, invalid, no contact).
 */
class CampaignRecipientList
{
    /**
     * @param  array<int, array{lead_id: ?int, contact_id: ?int, name: ?string, company_name: ?string, address: string}>  $recipients
     * @param  array<int, array{value: string, reason: string, type: string}>  $skipped  type: duplicate|invalid|unsubscribed
     */
    public function __construct(
        public array $recipients,
        public array $skipped,
        public int $fromLeads,
        public int $manual,
        public int $linkedToLeads,
        public int $fromContacts = 0,
    ) {
    }

    public function count(): int
    {
        return count($this->recipients);
    }

    public function duplicateCount(): int
    {
        return $this->skippedCount('duplicate');
    }

    public function skippedCount(string $type): int
    {
        return count(array_filter($this->skipped, fn (array $entry) => $entry['type'] === $type));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $skippedLimit = 200): array
    {
        return [
            'total' => $this->count(),
            'from_leads' => $this->fromLeads,
            'from_contacts' => $this->fromContacts,
            'manual' => $this->manual,
            'linked_to_leads' => $this->linkedToLeads,
            'duplicates' => $this->duplicateCount(),
            'unsubscribed' => $this->skippedCount('unsubscribed'),
            'invalid' => $this->skippedCount('invalid'),
            'skipped_count' => count($this->skipped),
            'skipped' => array_slice($this->skipped, 0, $skippedLimit),
        ];
    }
}
