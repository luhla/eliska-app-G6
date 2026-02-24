<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;

final class SettingsRepository
{
    private const DEFAULTS = [
        'max_sentence_blocks' => '7',
    ];

    public function __construct(
        private readonly Explorer $db,
    ) {
    }

    public function get(string $userUid, string $key, mixed $default = null): mixed
    {
        $row = $this->db->table('settings')
            ->where('user_uid', $userUid)
            ->where('setting_key', $key)
            ->fetch();

        if ($row) {
            return $row->setting_value;
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function set(string $userUid, string $key, mixed $value): void
    {
        $this->db->query(
            'INSERT INTO settings (user_uid, setting_key, setting_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            $userUid,
            $key,
            (string) $value
        );
    }

    /** Returns all settings as key => value map */
    public function getAll(string $userUid): array
    {
        $rows = $this->db->table('settings')
            ->where('user_uid', $userUid)
            ->fetchPairs('setting_key', 'setting_value');

        return array_merge(self::DEFAULTS, $rows);
    }

    /** Insert default settings for a new user */
    public function initDefaults(string $userUid): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            $this->set($userUid, $key, $value);
        }
    }
}
