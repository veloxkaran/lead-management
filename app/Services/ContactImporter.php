<?php

namespace App\Services;

use App\Http\Requests\Contact\ContactRequest;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Adds contacts in bulk from rows pasted out of Excel / Google Sheets or
 * read from an uploaded spreadsheet. Columns are matched by a header row
 * when there is one ("Company", "Name", "Email", "Phone" and common
 * variants); without one, email and phone columns are recognised by their
 * contents and the rest read as company then name. Each row is checked the same
 * way the add form checks one contact; rows that fail are skipped (not
 * the whole import) and reported with the reason.
 */
class ContactImporter
{
    /** Header cell (lowercased, punctuation stripped) => field. */
    private const HEADERS = [
        'company' => 'company_name', 'company name' => 'company_name', 'organization' => 'company_name', 'organisation' => 'company_name', 'business' => 'company_name', 'firm' => 'company_name',
        'name' => 'name', 'person' => 'name', 'person name' => 'name', 'contact' => 'name', 'contact person' => 'name', 'contact name' => 'name', 'full name' => 'name',
        'email' => 'email', 'e mail' => 'email', 'email address' => 'email', 'mail' => 'email',
        'phone' => 'phone', 'phone no' => 'phone', 'phone number' => 'phone', 'mobile' => 'phone', 'mobile no' => 'phone', 'mobile number' => 'phone', 'contact no' => 'phone', 'contact number' => 'phone', 'number' => 'phone', 'cell' => 'phone', 'tel' => 'phone', 'telephone' => 'phone',
    ];

    private const DEFAULT_ORDER = ['company_name', 'name', 'email', 'phone'];

    /** Rows accepted per import — keeps one request well inside PHP's time limit. */
    public const MAX_ROWS = 5000;

    /**
     * Pasted text → rows of cells. Spreadsheet copies are tab-separated;
     * anything else is read as CSV (commas, quotes).
     *
     * @return array<int, array<int, string>>
     */
    public static function parsePaste(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $tabbed = str_contains($text, "\t");

        return array_map(fn (string $line) => $tabbed ? explode("\t", $line) : str_getcsv($line, ',', '"', '\\'), $lines);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  raw cells, header row (if any) first
     * @return array{imported: int, skipped: array<int, array{row: int, value: string, reason: string}>, total: int}
     */
    public function import(array $rows, User $creator): array
    {
        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && collect($row)->contains(fn ($cell) => trim($this->cell($cell)) !== '')));
        [$columns, $hasHeader] = $this->columns($rows[0] ?? []);

        if (! $hasHeader) {
            $columns = $this->guessColumns($rows);
        }

        if ($hasHeader) {
            array_shift($rows);
        }

        $skipped = [];
        $imported = 0;
        $existing = Contact::whereNotNull('email')->pluck('email')->flip()->all();
        $seen = [];

        DB::transaction(function () use ($rows, $columns, $hasHeader, $creator, $existing, &$seen, &$skipped, &$imported) {
            foreach (array_slice($rows, 0, self::MAX_ROWS) as $index => $row) {
                $line = $index + ($hasHeader ? 2 : 1);
                $data = [];
                foreach ($columns as $position => $field) {
                    $data[$field] = trim($this->cell($row[$position] ?? ''));
                }
                $data = array_merge(array_fill_keys(Contact::FIELDS, ''), $data);
                $data['email'] = mb_strtolower($data['email']);
                $label = collect($data)->filter()->implode(' · ');

                $reason = match (true) {
                    collect($data)->filter()->isEmpty() => 'Nothing in the company, name, email or phone columns',
                    $data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false => "Email \"{$data['email']}\" isn't valid",
                    $data['phone'] !== '' && ! preg_match(ContactRequest::PHONE_PATTERN, $data['phone']) => "Phone \"{$data['phone']}\" isn't valid",
                    mb_strlen($data['company_name']) > 255 || mb_strlen($data['name']) > 255 || mb_strlen($data['email']) > 255 => 'A value is longer than 255 characters',
                    $data['email'] !== '' && isset($existing[$data['email']]) => 'Email is already saved as a contact',
                    $data['email'] !== '' && isset($seen[$data['email']]) => "Same email as row {$seen[$data['email']]}",
                    default => null,
                };

                if ($reason) {
                    $skipped[] = ['row' => $line, 'value' => $label, 'reason' => $reason];

                    continue;
                }

                Contact::create([...$data, 'created_by' => $creator->id]);
                $imported++;

                if ($data['email'] !== '') {
                    $seen[$data['email']] = $line;
                }
            }
        });

        if (count($rows) > self::MAX_ROWS) {
            $skipped[] = ['row' => self::MAX_ROWS + ($hasHeader ? 2 : 1), 'value' => '', 'reason' => 'Only the first '.number_format(self::MAX_ROWS).' rows are imported at a time — import the rest separately'];
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'total' => min(count($rows), self::MAX_ROWS)];
    }

    /**
     * Column position => field, from the header row if the first row is
     * one, else the default order.
     *
     * @return array{0: array<int, string>, 1: bool}
     */
    private function columns(array $firstRow): array
    {
        $columns = [];

        foreach ($firstRow as $position => $cell) {
            $key = trim(preg_replace('/[^a-z]+/', ' ', mb_strtolower($this->cell($cell))));
            if (isset(self::HEADERS[$key]) && ! in_array(self::HEADERS[$key], $columns, true)) {
                $columns[$position] = self::HEADERS[$key];
            }
        }

        return $columns ? [$columns, true] : [self::DEFAULT_ORDER, false];
    }

    /**
     * No header row: a column whose filled cells are mostly emails is the
     * email, mostly phone-like digits the phone. Of the other columns, a
     * lone one is the person's name; two or more are company, then name.
     *
     * @return array<int, string>
     */
    private function guessColumns(array $rows): array
    {
        $width = max(array_map('count', $rows) ?: [0]);
        $columns = [];
        $text = [];

        for ($position = 0; $position < $width; $position++) {
            $cells = array_values(array_filter(array_map(fn ($row) => trim($this->cell($row[$position] ?? '')), $rows), fn ($c) => $c !== ''));
            $share = fn (callable $test) => $cells ? count(array_filter($cells, $test)) / count($cells) : 0;

            if (! in_array('email', $columns, true) && $share(fn ($c) => str_contains($c, '@')) >= 0.5) {
                $columns[$position] = 'email';
            } elseif (! in_array('phone', $columns, true) && $share(fn ($c) => preg_match(ContactRequest::PHONE_PATTERN, $c) && strlen(preg_replace('/\D+/', '', $c)) >= 6) >= 0.5) {
                $columns[$position] = 'phone';
            } elseif ($cells) {
                $text[] = $position;
            }
        }

        $names = count($text) === 1 ? ['name'] : ['company_name', 'name'];
        foreach ($text as $i => $position) {
            if (isset($names[$i])) {
                $columns[$position] = $names[$i];
            }
        }

        return $columns ?: self::DEFAULT_ORDER;
    }

    /**
     * Spreadsheets type phone numbers as numbers (9800000000 → 9800000000.0);
     * turn those back into the digits that were typed.
     */
    private function cell(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return (string) $value;
    }
}
