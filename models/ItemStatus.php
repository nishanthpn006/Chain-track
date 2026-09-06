<?php
class ItemStatus {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }
    public function getAll(): array { return $this->pdo->query("SELECT * FROM item_statuses ORDER BY sort_order,status_name")->fetchAll(); }
    public function getActive(): array { return $this->pdo->query("SELECT * FROM item_statuses WHERE is_active=1 ORDER BY sort_order")->fetchAll(); }
    public function findById(int $id): ?array { $s=$this->pdo->prepare("SELECT * FROM item_statuses WHERE id=:id LIMIT 1"); $s->execute([':id'=>$id]); return $s->fetch()?:null; }
    public function findBySlug(string $slug): ?array { $s=$this->pdo->prepare("SELECT * FROM item_statuses WHERE status_slug=:sl LIMIT 1"); $s->execute([':sl'=>$slug]); return $s->fetch()?:null; }
    public function create(array $d): int { $s=$this->pdo->prepare("INSERT INTO item_statuses (status_name,status_slug,color_badge,description,is_active,sort_order) VALUES (:n,:sl,:c,:d,:a,:so)"); $s->execute([':n'=>$d['status_name'],':sl'=>$d['status_slug'],':c'=>$d['color_badge']??'secondary',':d'=>$d['description']??null,':a'=>$d['is_active']??1,':so'=>$d['sort_order']??0]); return (int)$this->pdo->lastInsertId(); }
    public function update(int $id, array $d): bool { return $this->pdo->prepare("UPDATE item_statuses SET status_name=:n,color_badge=:c,description=:d,is_active=:a,sort_order=:so WHERE id=:id")->execute([':n'=>$d['status_name'],':c'=>$d['color_badge']??'secondary',':d'=>$d['description']??null,':a'=>$d['is_active'],':so'=>$d['sort_order']??0,':id'=>$id]); }
}
