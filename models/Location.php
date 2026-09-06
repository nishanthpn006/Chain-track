<?php
class Location {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }
    public function getAll(): array { return $this->pdo->query("SELECT l.*,p.location_name AS parent_name,d.dept_name FROM locations l LEFT JOIN locations p ON p.id=l.parent_id LEFT JOIN departments d ON d.id=l.department_id ORDER BY l.location_name")->fetchAll(); }
    public function getActive(): array { return $this->pdo->query("SELECT * FROM locations WHERE is_active=1 ORDER BY location_name")->fetchAll(); }
    public function findById(int $id): ?array { $s=$this->pdo->prepare("SELECT l.*,p.location_name AS parent_name FROM locations l LEFT JOIN locations p ON p.id=l.parent_id WHERE l.id=:id LIMIT 1"); $s->execute([':id'=>$id]); return $s->fetch()?:null; }
    public function create(array $d): int { $s=$this->pdo->prepare("INSERT INTO locations (location_code,location_name,location_type,parent_id,department_id,description,is_active) VALUES (:c,:n,:t,:p,:dept,:d,:a)"); $s->execute([':c'=>$d['location_code']??null,':n'=>$d['location_name'],':t'=>$d['location_type']??'storage',':p'=>$d['parent_id']??null,':dept'=>$d['department_id']??null,':d'=>$d['description']??null,':a'=>$d['is_active']??1]); return (int)$this->pdo->lastInsertId(); }
    public function update(int $id, array $d): bool { return $this->pdo->prepare("UPDATE locations SET location_code=:c,location_name=:n,location_type=:t,parent_id=:p,department_id=:dept,description=:d,is_active=:a WHERE id=:id")->execute([':c'=>$d['location_code']??null,':n'=>$d['location_name'],':t'=>$d['location_type'],':p'=>$d['parent_id']??null,':dept'=>$d['department_id']??null,':d'=>$d['description']??null,':a'=>$d['is_active'],':id'=>$id]); }
}
