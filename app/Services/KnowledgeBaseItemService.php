<?php

namespace App\Services;

use App\Enums\KnowledgeBaseType;
use App\Models\KnowledgeBaseItem;
use App\Models\KnowledgeBaseTag;
use App\Repositories\KnowledgeBaseItemRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KnowledgeBaseItemService
{
    public function __construct(protected KnowledgeBaseItemRepository $items)
    {
    }

    public function list(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->items->filter($filters, $perPage);
    }

    public function create(array $attributes, ?UploadedFile $file, ?string $tagsInput, int $uploaderId): KnowledgeBaseItem
    {
        return DB::transaction(function () use ($attributes, $file, $tagsInput, $uploaderId) {
            $type = KnowledgeBaseType::from($attributes['type']);
            $attributes['uploaded_by'] = $uploaderId;

            if ($type === KnowledgeBaseType::Link) {
                $attributes['disk_path'] = null;
                $attributes['original_name'] = null;
                $attributes['mime_type'] = null;
                $attributes['size'] = null;
            } elseif ($file instanceof UploadedFile) {
                $attributes['link_url'] = null;
                $attributes['disk_path'] = $file->store('knowledge-base', 'public');
                $attributes['original_name'] = $file->getClientOriginalName();
                $attributes['mime_type'] = $file->getClientMimeType();
                $attributes['size'] = $file->getSize();
            }

            /** @var KnowledgeBaseItem $item */
            $item = $this->items->create($attributes);

            $item->tags()->sync($this->resolveTagIds($tagsInput));

            return $item->refresh();
        });
    }

    public function update(KnowledgeBaseItem $item, array $attributes, ?UploadedFile $file, ?string $tagsInput): KnowledgeBaseItem
    {
        return DB::transaction(function () use ($item, $attributes, $file, $tagsInput) {
            $type = KnowledgeBaseType::from($attributes['type']);

            if ($type === KnowledgeBaseType::Link) {
                if ($item->disk_path) {
                    Storage::disk('public')->delete($item->disk_path);
                }
                $attributes['disk_path'] = null;
                $attributes['original_name'] = null;
                $attributes['mime_type'] = null;
                $attributes['size'] = null;
            } elseif ($file instanceof UploadedFile) {
                if ($item->disk_path) {
                    Storage::disk('public')->delete($item->disk_path);
                }
                $attributes['link_url'] = null;
                $attributes['disk_path'] = $file->store('knowledge-base', 'public');
                $attributes['original_name'] = $file->getClientOriginalName();
                $attributes['mime_type'] = $file->getClientMimeType();
                $attributes['size'] = $file->getSize();
            } else {
                // Keep the existing file; just make sure link_url is cleared for non-link types.
                $attributes['link_url'] = null;
                unset($attributes['disk_path'], $attributes['original_name'], $attributes['mime_type'], $attributes['size']);
            }

            $item = $this->items->update($item, $attributes);

            $item->tags()->sync($this->resolveTagIds($tagsInput));

            return $item;
        });
    }

    public function delete(KnowledgeBaseItem $item): bool
    {
        if ($item->disk_path) {
            Storage::disk('public')->delete($item->disk_path);
        }

        return $this->items->delete($item);
    }

    protected function resolveTagIds(?string $tagsInput): array
    {
        if (! $tagsInput) {
            return [];
        }

        $names = collect(explode(',', $tagsInput))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        return $names->map(function ($name) {
            $tag = KnowledgeBaseTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => Str::lower($name)]
            );

            return $tag->id;
        })->all();
    }
}
