<?php
// =============================================================================
// ChainTrack – User Model
// =============================================================================

class User {
    private PDO $pdo;

    public function __construct() { $this->pdo = getDB(); }

    public function findById(int $id): ?array {
        $s = $this->pdo->prepare(
            "SELECT u.*, r.role_name, r.role_slug, d.dept_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = :id LIMIT 1"
        );
        $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }

    public function getAll(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = :role_id';
            $params[':role_id'] = $filters['role_id'];
        }
        if (!empty($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'u.is_active = :active';
            $params[':active'] = $filters['is_active'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(u.full_name LIKE :s OR u.username LIKE :s OR u.email LIKE :s OR u.employee_id LIKE :s)';
            $params[':s'] = '%' . $filters['search'] . '%';
        }
        $w = implode(' AND ', $where);
        $s = $this->pdo->prepare(
            "SELECT u.id, u.employee_id, u.full_name, u.email, u.username,
                    u.is_active, u.last_login_at, u.created_at,
                    r.role_name, r.role_slug, d.dept_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE $w ORDER BY u.full_name ASC"
        );
        $s->execute($params);
        return $s->fetchAll();
    }

    public function create(array $data): int {
        $s = $this->pdo->prepare(
            "INSERT INTO users
               (employee_id,full_name,email,username,password_hash,role_id,department_id,is_active,created_by)
             VALUES
               (:eid,:name,:email,:uname,:phash,:role,:dept,:active,:cb)"
        );
        $s->execute([
            ':eid'    => $data['employee_id']   ?? null,
            ':name'   => $data['full_name'],
            ':email'  => $data['email'],
            ':uname'  => $data['username'],
            ':phash'  => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'   => $data['role_id'],
            ':dept'   => $data['department_id'] ?? null,
            ':active' => $data['is_active'] ?? 1,
            ':cb'     => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sets   = ['full_name=:name','email=:email','role_id=:role','department_id=:dept','is_active=:active'];
        $params = [
            ':id'     => $id,
            ':name'   => $data['full_name'],
            ':email'  => $data['email'],
            ':role'   => $data['role_id'],
            ':dept'   => $data['department_id'] ?? null,
            ':active' => $data['is_active'],
        ];
        if (!empty($data['password'])) {
            $sets[]        = 'password_hash=:phash';
            $params[':phash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $s = $this->pdo->prepare(
            'UPDATE users SET ' . implode(',', $sets) . ' WHERE id=:id'
        );
        return $s->execute($params);
    }

    public function toggleActive(int $id): bool {
        return $this->pdo->prepare(
            'UPDATE users SET is_active = 1 - is_active WHERE id = :id'
        )->execute([':id' => $id]);
    }

    public function usernameExists(string $username, int $excludeId = 0): bool {
        $s = $this->pdo->prepare(
            'SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1'
        );
        $s->execute([':u' => $username, ':id' => $excludeId]);
        return (bool)$s->fetch();
    }

    public function emailExists(string $email, int $excludeId = 0): bool {
        $s = $this->pdo->prepare(
            'SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1'
        );
        $s->execute([':e' => $email, ':id' => $excludeId]);
        return (bool)$s->fetch();
    }

    /** Returns active users eligible to be transfer recipients */
    public function getActiveTransferableUsers(): array {
        $s = $this->pdo->prepare(
            "SELECT u.id, u.full_name, u.employee_id, r.role_name, r.role_slug
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1 AND r.role_slug != :auditor
             ORDER BY u.full_name ASC"
        );
        $s->execute([':auditor' => ROLE_AUDITOR]);
        return $s->fetchAll();
    }
}
