<?php
class Department {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }
    public function getAll(): array {
        return $this->pdo->query("SELECT * FROM departments ORDER BY dept_name")->fetchAll();
    }
    public function getActive(): array {
        return $this->pdo->query("SELECT * FROM departments WHERE is_active=1 ORDER BY dept_name")->fetchAll();
    }
    public function findById(int $id): ?array {
        $s=$this->pdo->prepare("SELECT * FROM departments WHERE id=:id LIMIT 1");
        $s->execute([':id'=>$id]); return $s->fetch()?:null;
    }
    public function create(array $d): int {
        $s=$this->pdo->prepare("INSERT INTO departments (dept_code,dept_name,description,is_active) VALUES (:c,:n,:desc,:a)");
        $s->execute([':c'=>$d['dept_code']??null,':n'=>$d['dept_name'],':desc'=>$d['description']??null,':a'=>$d['is_active']??1]);
        return (int)$this->pdo->lastInsertId();
    }
    public function update(int $id, array $d): bool {
        return $this->pdo->prepare("UPDATE departments SET dept_code=:c,dept_name=:n,description=:desc,is_active=:a WHERE id=:id")
            ->execute([':c'=>$d['dept_code']??null,':n'=>$d['dept_name'],':desc'=>$d['description']??null,':a'=>$d['is_active'],':id'=>$id]);
    }
}
