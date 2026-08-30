<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrganizationTree
{
    /**
     * Build an arbitrary-depth tree from one eager-loaded flat collection.
     * Corrupt cycles and orphaned nodes are rendered once as safe extra roots.
     */
    public static function build(Collection $positions): array
    {
        $positions = $positions->sortBy([
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])->values();

        $byId = $positions->keyBy('id');
        $children = $positions->groupBy(fn (Model $position) => $position->parent_id ?? 0);
        $rendered = [];
        $tree = [];

        foreach ($children->get(0, collect()) as $root) {
            $node = self::node($root, $children, [], $rendered);
            if ($node !== null) {
                $tree[] = $node;
            }
        }

        foreach ($byId as $position) {
            if (isset($rendered[$position->id])) {
                continue;
            }

            $node = self::node($position, $children, [], $rendered);
            if ($node !== null) {
                $tree[] = $node;
            }
        }

        return $tree;
    }

    public static function parentError(
        ?Model $position,
        ?int $parentId,
        Collection $availablePositions,
        ?int $generationId = null,
    ): ?string {
        if ($parentId === null) {
            return null;
        }

        if ($position && (int) $position->id === $parentId) {
            return 'Posisi tidak dapat menjadi atasan bagi dirinya sendiri.';
        }

        $byId = $availablePositions->keyBy('id');
        $parent = $byId->get($parentId);

        if (! $parent) {
            return 'Posisi atasan yang dipilih tidak tersedia.';
        }

        if ($generationId !== null && (int) $parent->generation_id !== $generationId) {
            return 'Posisi atasan harus berasal dari generasi yang sama.';
        }

        if (! $position) {
            return null;
        }

        $visited = [];
        $cursor = $parent;

        while ($cursor) {
            if ((int) $cursor->id === (int) $position->id) {
                return 'Posisi turunan tidak dapat dipilih sebagai atasan.';
            }

            if (isset($visited[$cursor->id])) {
                return 'Hierarki yang dipilih mengandung relasi melingkar.';
            }

            $visited[$cursor->id] = true;
            $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
        }

        return null;
    }

    private static function node(Model $position, Collection $children, array $path, array &$rendered): ?array
    {
        if (isset($path[$position->id]) || isset($rendered[$position->id])) {
            return null;
        }

        $path[$position->id] = true;
        $rendered[$position->id] = true;
        $nested = [];

        foreach ($children->get($position->id, collect()) as $child) {
            $node = self::node($child, $children, $path, $rendered);
            if ($node !== null) {
                $nested[] = $node;
            }
        }

        return [
            'position' => $position,
            'children' => $nested,
        ];
    }
}
