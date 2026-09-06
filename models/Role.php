<?php
class Role {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }
    public function getAll(): array { return $this->pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(); }
    public function findById(int $id): ?array { $s=$this->pdo->prepare("SELECT * FROM roles WHERE id=:id LIMIT 1"); $s->execute([':id'=>$id]); return $s->fetch()?:null; }
}
