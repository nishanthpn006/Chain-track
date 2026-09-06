<?php
// =============================================================================
// ChainTrack – AuditLog Model
// =============================================================================

class AuditLog {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Record an audit event.
     * Never throws — audit logging must not break a user action.
     */
    public function log(
        ?int   $userId,
        string $action,
        ?string $entityType = null,
        ?int   $entityId   = null,
        ?string $description = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO audit_logs
                    (user_id, action, entity_type, entity_id, description, ip_address, user_agent)
                 VALUES
                    (:user_id, :action, :entity_type, :entity_id, :description, :ip, :ua)"
            );
            $stmt->execute([
                ':user_id'     => $userId,
                ':action'      => $action,
                ':entity_type' => $entityType,
                ':entity_id'   => $entityId,
                ':description' => $description,
                ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'          => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('AuditLog::log failed: ' . $e->getMessage());
        }
    }

    /**
     * Paginated list for the audit viewer.
     */
    public function getPaginated(int $page = 1, int $perPage = 50, array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]              = 'al.user_id = :uid';
            $params[':uid']       = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[]              = 'al.action LIKE :action';
            $params[':action']    = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['entity_type'])) {
            $where[]              = 'al.entity_type = :etype';
            $params[':etype']     = $filters['entity_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'DATE(al.created_at) >= :dfrom';
            $params[':dfrom']     = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]              = 'DATE(al.created_at) <= :dto';
            $params[':dto']       = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs al WHERE $whereClause"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT al.*, u.full_name AS user_name, u.username
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE $whereClause
             ORDER BY al.created_at DESC
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
}
