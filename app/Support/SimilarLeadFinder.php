<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Support\Collection;

/**
 * Finds existing leads whose company name is at least $threshold% similar
 * (similar_text, case- and whitespace-insensitive) to a given name — the
 * same measure NotDuplicateLeadName uses, at a much looser threshold, to
 * warn that a Raw Data entry may be a company that's already a lead.
 *
 * Compares in PHP against every lead's name, like RawDataService's
 * matched-lead lookup — fine while the leads table is small.
 */
class SimilarLeadFinder
{
    public const THRESHOLD = 50.0;

    /**
     * Names shorter than this match almost anything at 50%, so they're
     * not checked at all.
     */
    public const MIN_LENGTH = 3;

    /**
     * @return Collection<int, array{lead: Lead, similarity: int}> best match first
     */
    public function find(?string $name, float $threshold = self::THRESHOLD, int $limit = 5): Collection
    {
        $needle = self::normalize($name);

        if (mb_strlen($needle) < self::MIN_LENGTH) {
            return collect();
        }

        $scores = Lead::query()->pluck('company_name', 'id')
            ->map(function (?string $companyName) use ($needle) {
                similar_text($needle, self::normalize($companyName), $percent);

                return $percent;
            })
            ->filter(fn (float $percent) => $percent >= $threshold)
            ->sortDesc()
            ->take($limit);

        if ($scores->isEmpty()) {
            return collect();
        }

        $leads = Lead::query()->with('status')->findMany($scores->keys())->keyBy('id');

        return $scores
            ->map(fn (float $percent, int $id) => ['lead' => $leads[$id], 'similarity' => (int) floor($percent)])
            ->values();
    }

    /**
     * The JSON shape shared by the live lookup and the server-rendered list.
     *
     * @param  Collection<int, array{lead: Lead, similarity: int}>  $matches
     * @return array<int, array<string, mixed>>
     */
    public static function toPayload(Collection $matches): array
    {
        return $matches->map(fn (array $match) => [
            'id' => $match['lead']->id,
            'company_name' => $match['lead']->company_name,
            'contact_person' => $match['lead']->contact_person,
            'status' => $match['lead']->status?->name,
            'created_at' => $match['lead']->created_at->format('M d, Y'),
            'similarity' => $match['similarity'],
            'url' => route('leads.show', $match['lead']),
        ])->all();
    }

    private static function normalize(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
    }
}
