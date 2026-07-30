<?php

namespace Tests\Feature;

use App\Enums\RawDataStatus;
use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_sorts_new_entries_first_by_default(): void
    {
        $user = User::factory()->create();

        $converted = RawData::factory()->create(['status' => RawDataStatus::ConvertedToLead]);
        $notValid = RawData::factory()->create(['status' => RawDataStatus::NotValid]);
        $hold = RawData::factory()->create(['status' => RawDataStatus::Hold]);
        $new = RawData::factory()->create(['status' => RawDataStatus::New]);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $response->assertOk();

        $ids = $response->viewData('entries')->pluck('id')->all();

        $this->assertSame([
            $new->id,
            $hold->id,
            $notValid->id,
            $converted->id,
        ], $ids);
    }
}
