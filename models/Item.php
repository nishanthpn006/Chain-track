<?php
// =============================================================================
// ChainTrack – Item Model
// =============================================================================

class Item {
    private PDO $pdo;

    public function __construct() { $this->pdo = getDB(); }

    public function findById(int $id): ?array {
        $s = $this->pdo->prepare(
            "SELECT i.*,
                    inv.inv_reference, inv.title AS investigation_title,
                    ic.cat_name AS category_name,
                    ist.status_name, ist.status_slug, ist.color_badge,
                    loc.location_name AS current_location_name,
                    cu.full_name AS current_custodian_name, cu.employee_id AS custodian_emp_id,
                    rb.full_name AS registered_by_name
             FROM items i
             JOIN investigations inv ON inv.id = i.investigation_id
             JOIN item_categories ic ON ic.id = i.category_id
             JOIN item_statuses ist  ON ist.id = i.current_status_id
             LEFT JOIN locations loc ON loc.id = i.current_location_id
             LEFT JOIN users cu      ON cu.id  = i.current_custodian_id
             LEFT JOIN users rb      ON rb.id  = i.registered_by
             WHERE i.id = :id LIMIT 1"
        );
        $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 25): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(i.item_reference LIKE :s OR i.item_name LIKE :s)';
            $params[':s'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['investigation_id'])) {
            $where[] = 'i.investigation_id = :inv';
            $params[':inv'] = $filters['investigation_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'i.category_id = :cat';
            $params[':cat'] = $filters['category_id'];
        }
        if (!empty($filters['status_id'])) {
            $where[] = 'i.current_status_id = :stat';
            $params[':stat'] = $filters['status_id'];
        }
        if (!empty($filters['custodian_id'])) {
            $where[] = 'i.current_custodian_id = :cust';
            $params[':cust'] = $filters['custodian_id'];
        }
        if (!empty($filters['location_id'])) {
            $where[] = 'i.current_location_id = :loc';
            $params[':loc'] = $filters['location_id'];
        }
        if (isset($filters['is_archived']) && $filters['is_archived'] !== '') {
            $where[] = 'i.is_archived = :arch';
            $params[':arch'] = $filters['is_archived'];
        } else {
            $where[] = 'i.is_archived = 0'; // default: hide archived
        }

        // Role-based visibility: non-admin/auditor only sees their own items
        if (!empty($filters['restrict_to_user'])) {
            $where[] = 'i.current_custodian_id = :ruid';
            $params[':ruid'] = $filters['restrict_to_user'];
        }

        $w      = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM items i WHERE $w");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT i.id, i.item_reference, i.item_name, i.created_at, i.is_archived,
                    inv.inv_reference, inv.title AS investigation_title,
                    ic.cat_name AS category_name,
                    ist.status_name, ist.status_slug, ist.color_badge,
                    loc.location_name AS current_location_name,
                    cu.full_name AS current_custodian_name
             FROM items i
             JOIN investigations inv ON inv.id = i.investigation_id
             JOIN item_categories ic ON ic.id = i.category_id
             JOIN item_statuses ist  ON ist.id = i.current_status_id
             LEFT JOIN locations loc ON loc.id = i.current_location_id
             LEFT JOIN users cu      ON cu.id  = i.current_custodian_id
             WHERE $w
             ORDER BY i.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / $perPage),
        ];
    }

    public function create(array $data): int {
        $s = $this->pdo->prepare(
            "INSERT INTO items
               (item_reference,investigation_id,category_id,item_name,description,
                physical_description,acquisition_date,acquisition_location,
                registered_by,current_custodian_id,current_location_id,current_status_id,notes)
             VALUES
               (:ref,:inv,:cat,:name,:desc,:pdesc,:acqdate,:acqloc,:regby,:custodian,:loc,:status,:notes)"
        );
        $s->execute([
            ':ref'      => $data['item_reference'],
            ':inv'      => $data['investigation_id'],
            ':cat'      => $data['category_id'],
            ':name'     => $data['item_name'],
            ':desc'     => $data['description'] ?? null,
            ':pdesc'    => $data['physical_description'] ?? null,
            ':acqdate'  => $data['acquisition_date'] ?? null,
            ':acqloc'   => $data['acquisition_location'] ?? null,
            ':regby'    => $data['registered_by'],
            ':custodian'=> $data['current_custodian_id'] ?? null,
            ':loc'      => $data['current_location_id'] ?? null,
            ':status'   => $data['current_status_id'],
            ':notes'    => $data['notes'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCurrentState(int $id, int $custodianId, int $locationId, int $statusId): bool {
        return $this->pdo->prepare(
            "UPDATE items SET current_custodian_id=:c, current_location_id=:l, current_status_id=:s
             WHERE id=:id"
        )->execute([':c'=>$custodianId,':l'=>$locationId,':s'=>$statusId,':id'=>$id]);
    }

    public function updateStatus(int $id, int $statusId): bool {
        return $this->pdo->prepare(
            "UPDATE items SET current_status_id=:s WHERE id=:id"
        )->execute([':s'=>$statusId,':id'=>$id]);
    }

    /** Get status history for an item (chronological) */
    public function getStatusHistory(int $itemId): array {
        $s = $this->pdo->prepare(
            "SELECT ish.*,
                    ps.status_name AS prev_status_name, ps.color_badge AS prev_color,
                    ns.status_name AS new_status_name,  ns.color_badge AS new_color,
                    u.full_name AS changed_by_name
             FROM item_status_history ish
             LEFT JOIN item_statuses ps ON ps.id = ish.previous_status_id
             JOIN  item_statuses ns ON ns.id = ish.new_status_id
             JOIN  users u          ON u.id  = ish.changed_by
             WHERE ish.item_id = :id
             ORDER BY ish.changed_at ASC"
        );
        $s->execute([':id' => $itemId]);
        return $s->fetchAll();
    }

    public function referenceExists(string $ref): bool {
        $s = $this->pdo->prepare('SELECT id FROM items WHERE item_reference = :r LIMIT 1');
        $s->execute([':r' => $ref]);
        return (bool)$s->fetch();
    }

    public function getDashboardStats(): array {
        $rows = $this->pdo->query(
            "SELECT ist.status_slug, COUNT(*) AS cnt
             FROM items i
             JOIN item_statuses ist ON ist.id = i.current_status_id
             WHERE i.is_archived = 0
             GROUP BY ist.status_slug"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM items WHERE is_archived=0")->fetchColumn();
        $arch  = (int)$this->pdo->query("SELECT COUNT(*) FROM items WHERE is_archived=1")->fetchColumn();
        return ['by_status' => $rows, 'total' => $total, 'archived' => $arch];
    }
}
