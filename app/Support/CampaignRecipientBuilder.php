<?php

namespace App\Support;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Models\CampaignUnsubscribe;
use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Support\Collection;

/**
 * Turns a campaign's audience, hand-picked leads and pasted contacts into
 * one de-duplicated recipient list. Shared by the create form's live
 * preview and the actual send, so the numbers the user reviews are exactly
 * what goes out.
 *
 * Order matters for which copy of a duplicate is kept: audience leads,
 * then picked leads, then saved Contacts (all, or picked), then pasted contacts — so a pasted number that
 * belongs to a lead is sent as that lead (with its name) rather than as an
 * anonymous extra. A pasted contact that isn't in the campaign yet but
 * does belong to an existing lead is linked to that lead too.
 *
 * Addresses that unsubscribed are always left out.
 */
class CampaignRecipientBuilder
{
    /** @var array<string, array{lead_id: ?int, contact_id: ?int, name: ?string, company_name: ?string, address: string}> keyed by address */
    private array $recipients = [];

    /** @var array<int, array{value: string, reason: string, type: string}> type: duplicate|invalid|unsubscribed */
    private array $skipped = [];

    /** @var array<string, string> unsubscribed address => date it happened */
    private array $unsubscribed = [];

    /** @var array<string, string> address => who it was first added as, for duplicate messages */
    private array $addedAs = [];

    private int $fromLeads = 0;

    private int $fromContacts = 0;

    private int $manual = 0;

    private int $linked = 0;

    private CampaignChannel $channel;

    /**
     * @param  array<int, int|string>  $filter  lead status ids, or industry names
     * @param  array<int, int|string>  $leadIds  hand-picked leads
     * @param  bool  $allContacts  every saved contact (Contacts menu)
     * @param  array<int, int|string>  $contactIds  hand-picked saved contacts
     */
    public function build(CampaignChannel $channel, CampaignAudience $audience, array $filter, array $leadIds, ?string $extraContacts, bool $allContacts = false, array $contactIds = []): CampaignRecipientList
    {
        $this->reset($channel);

        foreach ($this->audienceLeads($audience, $filter) as $lead) {
            $this->addLead($lead);
        }

        $leadIds = array_values(array_unique(array_map('intval', $leadIds)));
        if ($leadIds) {
            foreach (Lead::whereIn('id', $leadIds)->orderBy('id')->get() as $lead) {
                $this->addLead($lead);
            }
        }

        $contactIds = array_values(array_unique(array_map('intval', $contactIds)));
        if ($allContacts || $contactIds) {
            Contact::query()
                ->when(! $allContacts, fn ($q) => $q->whereIn('id', $contactIds))
                ->orderBy('id')
                ->each(fn (Contact $contact) => $this->addContact($contact));
        }

        $this->addExtraContacts((string) $extraContacts);

        return new CampaignRecipientList(array_values($this->recipients), $this->skipped, $this->fromLeads, $this->manual, $this->linked, $this->fromContacts);
    }

    /**
     * Splits pasted text into entries: one per line, or several per line
     * separated by commas/semicolons. A line of exactly "Name, contact"
     * (one part a valid contact, the other not) is read as a named contact.
     *
     * @return array<int, array{name: ?string, value: string}>
     */
    public static function parseExtraContacts(string $text, CampaignChannel $channel): array
    {
        $entries = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $parts = array_values(array_filter(array_map('trim', preg_split('/[,;\t]+/', $line)), fn ($part) => $part !== ''));

            if (count($parts) === 2) {
                [$first, $second] = $parts;
                $firstValid = self::normalizeFor($channel, $first) !== null;
                $secondValid = self::normalizeFor($channel, $second) !== null;

                if ($firstValid xor $secondValid) {
                    $entries[] = $firstValid ? ['name' => $second, 'value' => $first] : ['name' => $first, 'value' => $second];

                    continue;
                }
            }

            foreach ($parts as $part) {
                $entries[] = ['name' => null, 'value' => $part];
            }
        }

        return $entries;
    }

    public static function normalizeFor(CampaignChannel $channel, mixed $value): ?string
    {
        return $channel === CampaignChannel::Email ? ContactNormalizer::email($value) : ContactNormalizer::phone($value);
    }

    private function reset(CampaignChannel $channel): void
    {
        $this->channel = $channel;
        $this->recipients = $this->skipped = $this->addedAs = [];
        $this->fromLeads = $this->fromContacts = $this->manual = $this->linked = 0;
        $this->unsubscribed = CampaignUnsubscribe::where('channel', $channel)->pluck('created_at', 'address')
            ->map(fn ($at) => $at?->format('M d, Y') ?? 'earlier')->all();
    }

    /**
     * @return Collection<int, Lead>
     */
    private function audienceLeads(CampaignAudience $audience, array $filter): Collection
    {
        if ($audience === CampaignAudience::None) {
            return collect();
        }

        $query = Lead::active()->orderBy('id');

        match ($audience) {
            CampaignAudience::Customers => $query->whereHas('status', fn ($q) => $q->where('is_closed_won', true)),
            CampaignAudience::LeadStatuses => $query->whereIn('lead_status_id', array_map('intval', $filter)),
            CampaignAudience::Industries => $query->whereIn('industry', array_map('strval', $filter)),
            default => null,
        };

        return $query->get();
    }

    private function addLead(Lead $lead): void
    {
        $raw = $this->channel === CampaignChannel::Email ? $lead->email : $lead->phone;
        $address = self::normalizeFor($this->channel, $raw);
        $label = "lead \"{$lead->company_name}\"";

        if ($address === null) {
            $this->skip($lead->company_name, blank($raw)
                ? 'Lead has no '.$this->contactNoun()
                : "Lead's ".$this->contactNoun()." \"{$raw}\" isn't valid", 'invalid');

            return;
        }

        if (isset($this->unsubscribed[$address])) {
            $this->skipUnsubscribed("{$lead->company_name} ({$raw})", $address);

            return;
        }

        if (isset($this->recipients[$address])) {
            // The same lead reached through both the audience and the picked list isn't a real duplicate.
            if ($this->recipients[$address]['lead_id'] !== $lead->id) {
                $this->skip("{$lead->company_name} ({$raw})", "Same {$this->contactNoun()} as {$this->addedAs[$address]} — sent once", 'duplicate');
            }

            return;
        }

        $this->add($address, $lead->id, $lead->contact_person, $lead->company_name, $label);
        $this->fromLeads++;
    }

    private function addContact(Contact $contact): void
    {
        $raw = $this->channel === CampaignChannel::Email ? $contact->email : $contact->phone;
        $address = self::normalizeFor($this->channel, $raw);
        $who = $contact->displayName();

        if ($address === null) {
            $this->skip($who, blank($raw)
                ? 'Contact has no '.$this->contactNoun()
                : "Contact's ".$this->contactNoun()." \"{$raw}\" isn't valid", 'invalid');

            return;
        }

        if (isset($this->unsubscribed[$address])) {
            $this->skipUnsubscribed("{$who} ({$raw})", $address);

            return;
        }

        if (isset($this->recipients[$address])) {
            if ($this->recipients[$address]['contact_id'] !== $contact->id) {
                $this->skip("{$who} ({$raw})", "Same {$this->contactNoun()} as {$this->addedAs[$address]} — sent once", 'duplicate');
            }

            return;
        }

        $this->add($address, null, $contact->name, $contact->company_name, "contact \"{$who}\"", $contact->id);
        $this->fromContacts++;
    }

    private function addExtraContacts(string $text): void
    {
        $entries = self::parseExtraContacts($text, $this->channel);

        if (! $entries) {
            return;
        }

        $existingLeads = $this->leadsByAddress();

        foreach ($entries as $entry) {
            $address = self::normalizeFor($this->channel, $entry['value']);

            if ($address === null) {
                $otherChannel = $this->channel === CampaignChannel::Email ? ContactNormalizer::phone($entry['value']) : ContactNormalizer::email($entry['value']);

                $this->skip($entry['value'], $otherChannel !== null
                    ? 'Looks like '.($this->channel === CampaignChannel::Email ? 'a phone number' : 'an email address').' — this is an '.$this->channel->label().' campaign'
                    : 'Not a valid '.$this->contactNoun(), 'invalid');

                continue;
            }

            if (isset($this->unsubscribed[$address])) {
                $this->skipUnsubscribed($entry['value'], $address);

                continue;
            }

            if (isset($this->recipients[$address])) {
                $this->skip($entry['value'], "Duplicate — already included as {$this->addedAs[$address]}", 'duplicate');

                continue;
            }

            if ($lead = $existingLeads[$address] ?? null) {
                $this->add($address, $lead->id, $entry['name'] ?: $lead->contact_person, $lead->company_name, "lead \"{$lead->company_name}\"");
                $this->linked++;
            } else {
                $this->add($address, null, $entry['name'], null, "\"{$entry['value']}\" earlier in the list");
            }

            $this->manual++;
        }
    }

    /**
     * Every lead keyed by its normalized contact, newest first winning —
     * compared in PHP (like RawDataService's matched-lead lookup) so the
     * same normalization decides both sides.
     *
     * @return array<string, Lead>
     */
    private function leadsByAddress(): array
    {
        $column = $this->channel === CampaignChannel::Email ? 'email' : 'phone';
        $map = [];

        foreach (Lead::query()->whereNotNull($column)->oldest('id')->get(['id', 'company_name', 'contact_person', $column]) as $lead) {
            if ($address = self::normalizeFor($this->channel, $lead->{$column})) {
                $map[$address] = $lead;
            }
        }

        return $map;
    }

    private function add(string $address, ?int $leadId, ?string $name, ?string $companyName, string $label, ?int $contactId = null): void
    {
        $this->recipients[$address] = [
            'lead_id' => $leadId,
            'contact_id' => $contactId,
            'name' => $name !== null && trim($name) !== '' ? trim($name) : null,
            'company_name' => $companyName,
            'address' => $address,
        ];
        $this->addedAs[$address] = $label;
    }

    private function skip(string $value, string $reason, string $type): void
    {
        $this->skipped[] = ['value' => $value, 'reason' => $reason, 'type' => $type];
    }

    private function skipUnsubscribed(string $value, string $address): void
    {
        // A lead reached through both the audience and the picked list is reported once.
        foreach ($this->skipped as $entry) {
            if ($entry['type'] === 'unsubscribed' && $entry['value'] === $value) {
                return;
            }
        }

        $this->skip($value, "Unsubscribed on {$this->unsubscribed[$address]} — won't be sent", 'unsubscribed');
    }

    private function contactNoun(): string
    {
        return $this->channel === CampaignChannel::Email ? 'email address' : 'phone number';
    }
}
