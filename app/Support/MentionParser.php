<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves @mentions in a comment body against real users. There's no
 * separate "username" field in this app, so a handle is derived on the fly
 * from the user's full name (lowercased, spaces stripped) — "Jane Doe"
 * mentions as "@janedoe". Net-new: no prior art elsewhere in the codebase.
 */
class MentionParser
{
    /**
     * @return Collection<int, User>
     */
    public static function extractUsers(string $text): Collection
    {
        preg_match_all('/@([a-zA-Z0-9._]+)/', $text, $matches);

        $handles = collect($matches[1])->map(fn (string $handle) => Str::lower($handle))->unique();

        if ($handles->isEmpty()) {
            return collect();
        }

        return User::all()->filter(fn (User $user) => $handles->contains(self::handle($user)))->values();
    }

    public static function handle(User $user): string
    {
        return Str::lower(Str::replace(' ', '', $user->name));
    }
}
