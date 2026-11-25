<?php

namespace App\Services;

use App\Models\SavedFilter;
use App\Models\Tag;
use App\Models\Transaction;
use App\Rules\PlainText;
use App\Support\NameNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TagService
{
    public function __construct(private NameNormalizer $names) {}

    /**
     * @param  array<int, string>  $names
     */
    public function sync(Transaction $transaction, array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }

            $label = PlainText::collapse($name);

            if ($label === '') {
                continue;
            }

            $tag = $this->findOrCreate($label);

            if ($tag instanceof Tag) {
                $ids[] = (int) $tag->id;
            }
        }

        $transaction->tags()->sync(array_slice(array_values(array_unique($ids)), 0, 10));
    }

    public function rename(Tag $tag, string $name): Tag
    {
        $label = PlainText::collapse($name);
        $key = $this->names->key($label);

        return DB::transaction(function () use ($tag, $label, $key) {
            $locked = Tag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
            $other = Tag::query()->where('name_key', $key)->whereKeyNot($locked->id)->lockForUpdate()->first();

            if ($other instanceof Tag) {
                $this->mergeInto($locked, $other);
                $this->rewriteSavedFilters((int) $locked->id, (int) $other->id);

                return $other;
            }

            $locked->name = $label;
            $locked->name_key = $key;
            $locked->save();

            return $locked;
        });
    }

    public function delete(Tag $tag): void
    {
        DB::transaction(function () use ($tag): void {
            $this->rewriteSavedFilters((int) $tag->id, null);
            $tag->delete();
        });
    }

    private function findOrCreate(string $label): ?Tag
    {
        $key = $this->names->key($label);
        $existing = Tag::query()->where('name_key', $key)->first();

        if ($existing instanceof Tag) {
            return $existing;
        }

        $tag = new Tag;
        $tag->name = $label;
        $tag->name_key = $key;

        try {
            $tag->save();
        } catch (UniqueConstraintViolationException) {
            return Tag::query()->where('name_key', $key)->first();
        }

        return $tag;
    }

    private function mergeInto(Tag $from, Tag $into): void
    {
        $kept = DB::table('tag_transaction')->where('tag_id', $into->id)->pluck('transaction_id');

        if ($kept->isNotEmpty()) {
            DB::table('tag_transaction')
                ->where('tag_id', $from->id)
                ->whereIn('transaction_id', $kept)
                ->delete();
        }

        DB::table('tag_transaction')->where('tag_id', $from->id)->update(['tag_id' => $into->id]);
        $from->delete();
    }

    private function rewriteSavedFilters(int $fromId, ?int $intoId): void
    {
        SavedFilter::query()->get()->each(function (SavedFilter $filter) use ($fromId, $intoId): void {
            $filters = $filter->filters ?? [];
            $tags = $filters['tags'] ?? null;

            if (! is_array($tags)) {
                return;
            }

            $next = [];
            $touched = false;

            foreach ($tags as $id) {
                $id = (int) $id;

                if ($id === $fromId) {
                    $touched = true;

                    if ($intoId !== null) {
                        $next[] = $intoId;
                    }

                    continue;
                }

                $next[] = $id;
            }

            if (! $touched) {
                return;
            }

            $next = array_values(array_unique($next));

            if ($next === []) {
                unset($filters['tags']);
            } else {
                $filters['tags'] = $next;
            }

            $filter->filters = $filters;
            $filter->save();
        });
    }
}
