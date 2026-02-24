<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class BlockRepository
{
    public function __construct(
        private readonly Explorer $db,
    ) {
    }

    public function findByUser(string $userUid, ?int $groupId = -1): Selection
    {
        $sel = $this->db->table('blocks')
            ->where('user_uid', $userUid)
            ->order('sort_order ASC, id ASC');

        if ($groupId !== -1) {
            // -1 = all groups; null = root; int = specific group
            $sel->where('group_id', $groupId);
        }

        return $sel;
    }

    public function findById(int $id): ?ActiveRow
    {
        return $this->db->table('blocks')->get($id);
    }

    /**
     * Returns all blocks for a user with their group info, formatted for the user view JS app.
     * @return array<int, array<string, mixed>>
     */
    public function findAllForUser(string $userUid): array
    {
        $rows = $this->db->table('blocks')
            ->where('user_uid', $userUid)
            ->order('group_id ASC, sort_order ASC, id ASC')
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'          => $row->id,
                'text'        => $row->text,
                'image_path'  => $row->image_path,
                'block_type'  => $row->block_type,
                'audio_path'  => $row->audio_path,
                'group_id'    => $row->group_id,
                'sort_order'  => $row->sort_order,
            ];
        }
        return $result;
    }

    public function create(array $data): ActiveRow
    {
        // Auto sort_order = max + 1 within group
        $maxOrder = $this->db->table('blocks')
            ->where('user_uid', $data['user_uid'])
            ->where('group_id', $data['group_id'] ?? null)
            ->max('sort_order') ?? -1;
        $data['sort_order'] = (int) $maxOrder + 1;
        $data['created_at'] = new \DateTime;

        $row = $this->db->table('blocks')->insert($data);
        assert($row instanceof ActiveRow);
        return $row;
    }

    public function update(int $id, array $data): void
    {
        $this->db->table('blocks')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        $this->db->table('blocks')->where('id', $id)->delete();
    }

    public function updateOrder(array $items): void
    {
        foreach ($items as $item) {
            $this->db->table('blocks')
                ->where('id', (int) $item['id'])
                ->update(['sort_order' => (int) $item['sort_order']]);
        }
    }
}
