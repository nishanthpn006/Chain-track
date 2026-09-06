<?php
class Investigation {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }

    public function findById(int $id): ?array {
        $s = $this->pdo->prepare(
            "SELECT inv.*, d.dept_name, u.full_name AS lead_name, cb.full_name AS created_by_name
             FROM investigations inv
             LEFT JOIN departments d ON d.id = inv.department_id
             LEFT JOIN users u       ON u.id = inv.lead_user_id
             LEFT JOIN users cb      ON cb.id = inv.created_by
             WHERE inv.id = :id LIMIT 1"
        );
        $s->execute([':id'=>$id]);
        return $s->fetch() ?: null;
    }

    public function getAll(array $filters=[]): array {
        $where=['1=1']; $params=[];
        if (!empty($filters['status'])) { $where[]='inv.status=:st'; $params[':st']=$filters['status']; }
        if (!empty($filters['department_id'])) { $where[]='inv.department_id=:did'; $params[':did']=$filters['department_id']; }
        if (!empty($filters['search'])) { $where[]='(inv.inv_reference LIKE :s OR inv.title LIKE :s)'; $params[':s']='%'.$filters['search'].'%'; }
        $w = implode(' AND ',$where);
        $s = $this->pdo->prepare(
            "SELECT inv.id, inv.inv_reference, inv.title, inv.status, inv.start_date,
                    d.dept_name, u.full_name AS lead_name,
                    (SELECT COUNT(*) FROM items WHERE investigation_id=inv.id) AS item_count
             FROM investigations inv
             LEFT JOIN departments d ON d.id=inv.department_id
             LEFT JOIN users u ON u.id=inv.lead_user_id
             WHERE $w ORDER BY inv.created_at DESC"
        );
        $s->execute($params);
        return $s->fetchAll();
    }

    public function create(array $d): int {
        $s=$this->pdo->prepare(
            "INSERT INTO investigations (inv_reference,title,description,department_id,lead_user_id,start_date,end_date,status,created_by)
             VALUES (:ref,:title,:desc,:dept,:lead,:start,:end,:status,:cb)"
        );
        $s->execute([':ref'=>$d['inv_reference'],':title'=>$d['title'],':desc'=>$d['description']??null,
            ':dept'=>$d['department_id']??null,':lead'=>$d['lead_user_id']??null,
            ':start'=>$d['start_date'],':end'=>$d['end_date']??null,
            ':status'=>$d['status']??'open',':cb'=>$d['created_by']]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): bool {
        return $this->pdo->prepare(
            "UPDATE investigations SET title=:title,description=:desc,department_id=:dept,lead_user_id=:lead,
             start_date=:start,end_date=:end,status=:status WHERE id=:id"
        )->execute([':title'=>$d['title'],':desc'=>$d['description']??null,':dept'=>$d['department_id']??null,
            ':lead'=>$d['lead_user_id']??null,':start'=>$d['start_date'],':end'=>$d['end_date']??null,
            ':status'=>$d['status'],':id'=>$id]);
    }

    public function getForDropdown(): array {
        return $this->pdo->query("SELECT id, CONCAT(inv_reference,' – ',title) AS label FROM investigations WHERE status='open' ORDER BY inv_reference DESC")->fetchAll();
    }
}
