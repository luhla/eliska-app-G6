<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class BlockGroupRepository
{
    public function __construct(
        private readonly Explorer $db,
    ) {
    }

    public function findByUser(string $userUid): Selection
    {
        return $this->db->table('block_groups')
            ->where('user_uid', $userUid)
            ->order('sort_order ASC, id ASC');
    }

    public function findById(int $id): ?ActiveRow
    {
        return $this->db->table('block_groups')->get($id);
    }

    /** Returns nested tree structure for given user */
    public function getTree(string $userUid): array
    {
        $all = $this->findByUser($userUid)->fetchAll();
        return $this->buildTree($all, null);
    }

    private function buildTree(array $all, ?int $parentId): array
    {
        $result = [];
        foreach ($all as $row) {
            if ($row->parent_id === $parentId) {
                $item = $row->toArray();
                $item['children'] = $this->buildTree($all, $row->id);
                $result[] = $item;
            }
        }
        return $result;
    }

    /** Flat list with nesting info for select boxes */
    public function getFlatList(string $userUid, ?int $excludeId = null): array
    {
        $all = $this->findByUser($userUid)->fetchAll();
        $flat = [];
        $this->flattenTree($all, null, $flat, 0, $excludeId);
        return $flat;
    }

    private function flattenTree(array $all, ?int $parentId, array &$flat, int $depth, ?int $excludeId): void
    {
        foreach ($all as $row) {
            if ($row->parent_id !== $parentId) {
                continue;
            }
            if ($excludeId !== null && $row->id === $excludeId) {
                continue;
            }
            $flat[$row->id] = str_repeat('— ', $depth) . $row->name;
            $this->flattenTree($all, $row->id, $flat, $depth + 1, $excludeId);
        }
    }

    public function create(array $data): ActiveRow
    {
        // Auto sort_order = max + 1 within parent
        $maxOrder = $this->db->table('block_groups')
            ->where('user_uid', $data['user_uid'])
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort_order') ?? -1;
        $data['sort_order'] = (int) $maxOrder + 1;
        $data['created_at'] = new \DateTime;

        $row = $this->db->table('block_groups')->insert($data);
        assert($row instanceof ActiveRow);
        return $row;
    }

    public function update(int $id, array $data): void
    {
        $this->db->table('block_groups')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        // Children and FK constraints handle cascade
        $this->db->table('block_groups')->where('id', $id)->delete();
    }

    public function updateOrder(array $items): void
    {
        foreach ($items as $item) {
            $this->db->table('block_groups')
                ->where('id', (int) $item['id'])
                ->update(['sort_order' => (int) $item['sort_order']]);
        }
    }

    /** Direct children of a group (null = root) */
    public function getChildren(string $userUid, ?int $parentId): array
    {
        return $this->db->table('block_groups')
            ->where('user_uid', $userUid)
            ->where('parent_id', $parentId)
            ->order('sort_order ASC, id ASC')
            ->fetchAll();
    }

    /** Breadcrumb ancestors: [['id'=>null,'name'=>'Kořen'], ['id'=>3,'name'=>'Jídlo'], ...] */
    public function getAncestors(string $userUid, ?int $groupId): array
    {
        $crumbs = [['id' => null, 'name' => 'Kořen']];
        if ($groupId === null) {
            return $crumbs;
        }
        $path = [];
        $current = $this->findById($groupId);
        while ($current !== null && $current->user_uid === $userUid) {
            array_unshift($path, ['id' => $current->id, 'name' => $current->name]);
            $current = $current->parent_id !== null ? $this->findById($current->parent_id) : null;
        }
        return array_merge($crumbs, $path);
    }

    /** Check whether $descendantId is a descendant of $ancestorId (cycle detection) */
    public function isDescendant(int $ancestorId, int $descendantId): bool
    {
        $all = $this->db->table('block_groups')->fetchAll();
        return $this->checkDescendant($all, $ancestorId, $descendantId);
    }

    private function checkDescendant(array $all, int $ancestorId, int $nodeId): bool
    {
        foreach ($all as $row) {
            if ($row->id === $nodeId) {
                if ($row->parent_id === $ancestorId) {
                    return true;
                }
                if ($row->parent_id !== null) {
                    return $this->checkDescendant($all, $ancestorId, $row->parent_id);
                }
                return false;
            }
        }
        return false;
    }
}
