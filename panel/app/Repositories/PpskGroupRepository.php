<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PpskGroup;

// Registry access (CLAUDE.md Sections 7 and 18). The only layer that
// queries ppsk_groups; services depend on this, never on Eloquent directly.
class PpskGroupRepository
{
    public function find(int $id): ?PpskGroup
    {
        return PpskGroup::query()->find($id);
    }

    public function findOrFail(int $id): PpskGroup
    {
        return PpskGroup::query()->findOrFail($id);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): PpskGroup
    {
        return PpskGroup::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(PpskGroup $group, array $attributes): PpskGroup
    {
        $group->fill($attributes);
        $group->save();

        return $group;
    }

    public function delete(PpskGroup $group): void
    {
        $group->delete();
    }
}
