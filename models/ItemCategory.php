<?php
class ItemCategory {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }
    public function getAll(): array { return $this->pdo->query("SELECT * FROM item_categories ORDER BY cat_name")->fetchAll(); }
    public function getActive(): array { return $this->pdo->query("SELECT * FROM item_categories WHERE is_active=1 ORDER BY cat_name")->fetchAll(); }
    public function findById(int $id): ?array { $s=$this->pdo->prepare("SELECT * FROM item_categories WHERE id=:id LIMIT 1"); $s->execute([':id'=>$id]); return $s->fetch()?:null; }
    public function create(array $d): int { $s=$this->pdo->prepare("INSERT INTO item_categories (cat_name,description,is_active) VALUES (:n,:d,:a)"); $s->execute([':n'=>$d['cat_name'],':d'=>$d['description']??null,':a'=>$d['is_active']??1]); return (int)$this->pdo->lastInsertId(); }
    public function update(int $id, array $d): bool { return $this->pdo->prepare("UPDATE item_categories SET cat_name=:n,description=:d,is_active=:a WHERE id=:id")->execute([':n'=>$d['cat_name'],':d'=>$d['description']??null,':a'=>$d['is_active'],':id'=>$id]); }
}
