<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Nette\Utils\Random;

final class UserRepository implements Authenticator
{
    public function __construct(
        private readonly Explorer $db,
        private readonly Passwords $passwords,
    ) {
    }

    public function authenticate(string $user, string $password): SimpleIdentity
    {
        $row = $this->findByUsername($user);

        if (!$row) {
            throw new AuthenticationException('Uživatel nenalezen.', self::IDENTITY_NOT_FOUND);
        }
        if (!$row->is_active) {
            throw new AuthenticationException('Účet je deaktivován.', self::NOT_APPROVED);
        }
        if (!$this->passwords->verify($password, $row->password_hash)) {
            throw new AuthenticationException('Nesprávné heslo.', self::INVALID_CREDENTIAL);
        }
        if ($this->passwords->needsRehash($row->password_hash)) {
            $row->update(['password_hash' => $this->passwords->hash($password)]);
        }

        $this->updateLastLogin($row->uid);

        return new SimpleIdentity($row->uid, [], [
            'username'     => $row->username,
            'display_name' => $row->display_name,
        ]);
    }

    public function findByUsername(string $username): ?ActiveRow
    {
        return $this->db->table('users')->where('username', $username)->fetch();
    }

    public function findByUid(string $uid): ?ActiveRow
    {
        return $this->db->table('users')->where('uid', $uid)->fetch();
    }

    public function findAll(): Selection
    {
        return $this->db->table('users')->order('created_at ASC');
    }

    public function create(array $data): ActiveRow
    {
        $data['uid'] = Random::generate(16);
        $data['password_hash'] = $this->passwords->hash($data['password']);
        unset($data['password']);
        $data['created_at'] = new \DateTime;

        $row = $this->db->table('users')->insert($data);
        assert($row instanceof ActiveRow);
        return $row;
    }

    public function update(string $uid, array $data): void
    {
        if (isset($data['password']) && $data['password'] !== '') {
            $data['password_hash'] = $this->passwords->hash($data['password']);
        }
        unset($data['password']);

        $this->db->table('users')->where('uid', $uid)->update($data);
    }

    public function delete(string $uid): void
    {
        $this->db->table('users')->where('uid', $uid)->delete();
    }

    public function updateLastLogin(string $uid): void
    {
        $this->db->table('users')->where('uid', $uid)->update([
            'last_login' => new \DateTime,
        ]);
    }
}
