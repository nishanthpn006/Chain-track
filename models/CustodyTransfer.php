<?php
// =============================================================================
// ChainTrack – CustodyTransfer Model
// CORE chain-of-custody logic lives here.
// All writes enforce BR-03 to BR-08.
// =============================================================================

class CustodyTransfer {
    private PDO $pdo;

    public function __construct() { $this->pdo = getDB(); }

    public function findById(int $id): ?array {
        $s = $this->pdo->prepare(
            "SELECT ct.*,
                    i.item_reference, i.item_name,
                    fu.full_name AS from_user_name, fu.employee_id AS from_emp_id,
                    tu.full_name AS to_user_name,   tu.employee_id AS to_emp_id,
                    iu.full_name AS initiated_by_name,
                    cu.full_name AS confirmed_by_name,
                    fl.location_name AS from_location_name,
                    tl.location_name AS to_location_name
             FROM custody_transfers ct
             JOIN items i           ON i.id  = ct.item_id
             LEFT JOIN users fu     ON fu.id = ct.from_user_id
             JOIN users tu          ON tu.id = ct.to_user_id
             JOIN users iu          ON iu.id = ct.initiated_by
             LEFT JOIN users cu     ON cu.id = ct.confirmed_by
             LEFT JOIN locations fl ON fl.id = ct.from_location_id
             LEFT JOIN locations tl ON tl.id = ct.to_location_id
             WHERE ct.id = :id LIMIT 1"
        );
        $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }

    public function getForItem(int $itemId): array {
        $s = $this->pdo->prepare(
            "SELECT ct.*,
                    fu.full_name AS from_user_name,
                    tu.full_name AS to_user_name,
                    iu.full_name AS initiated_by_name,
                    cu.full_name AS confirmed_by_name,
                    fl.location_name AS from_location_name,
                    tl.location_name AS to_location_name
             FROM custody_transfers ct
             LEFT JOIN users fu     ON fu.id = ct.from_user_id
             JOIN users tu          ON tu.id = ct.to_user_id
             JOIN users iu          ON iu.id = ct.initiated_by
             LEFT JOIN users cu     ON cu.id = ct.confirmed_by
             LEFT JOIN locations fl ON fl.id = ct.from_location_id
             LEFT JOIN locations tl ON tl.id = ct.to_location_id
             WHERE ct.item_id = :id
             ORDER BY ct.initiated_at ASC"
        );
        $s->execute([':id' => $itemId]);
        return $s->fetchAll();
    }

    public function getPending(int $userId): array {
        $s = $this->pdo->prepare(
            "SELECT ct.*,
                    i.item_reference, i.item_name,
                    fu.full_name AS from_user_name,
                    fl.location_name AS from_location_name,
                    tl.location_name AS to_location_name
             FROM custody_transfers ct
             JOIN items i           ON i.id  = ct.item_id
             LEFT JOIN users fu     ON fu.id = ct.from_user_id
             LEFT JOIN locations fl ON fl.id = ct.from_location_id
             LEFT JOIN locations tl ON tl.id = ct.to_location_id
             WHERE ct.to_user_id = :uid
               AND ct.transfer_status = 'pending'
             ORDER BY ct.initiated_at ASC"
        );
        $s->execute([':uid' => $userId]);
        return $s->fetchAll();
    }

    public function getAllPaginated(int $page = 1, int $perPage = 25, array $filters = []): array {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['transfer_status'])) {
            $where[]  = 'ct.transfer_status = :ts';
            $params[':ts'] = $filters['transfer_status'];
        }
        if (!empty($filters['item_id'])) {
            $where[]  = 'ct.item_id = :iid';
            $params[':iid'] = $filters['item_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[]  = '(ct.from_user_id = :uid OR ct.to_user_id = :uid)';
            $params[':uid'] = $filters['user_id'];
        }
        $w      = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM custody_transfers ct WHERE $w");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT ct.id, ct.transfer_reference, ct.transfer_status, ct.initiated_at, ct.confirmed_at, ct.reason,
                    i.item_reference, i.item_name,
                    fu.full_name AS from_user_name,
                    tu.full_name AS to_user_name
             FROM custody_transfers ct
             JOIN items i       ON i.id  = ct.item_id
             LEFT JOIN users fu ON fu.id = ct.from_user_id
             JOIN users tu      ON tu.id = ct.to_user_id
             WHERE $w
             ORDER BY ct.initiated_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'pages'=>(int)ceil($total/$perPage)];
    }

    /**
     * INITIATE a transfer.
     * Business Rules enforced:
     *   BR-03: from != to
     *   BR-07: item must not have an active pending transfer
     *   BR-08: to_user must be active
     *
     * Returns the new transfer ID on success, or throws RuntimeException.
     */
    public function initiate(array $data): int {
        // BR-03
        if ((int)$data['from_user_id'] === (int)$data['to_user_id']) {
            throw new RuntimeException('An item cannot be transferred to the same custodian.');
        }
        // BR-07
        $chk = $this->pdo->prepare(
            "SELECT id FROM custody_transfers
             WHERE item_id = :iid AND transfer_status IN ('initiated','pending') LIMIT 1"
        );
        $chk->execute([':iid' => $data['item_id']]);
        if ($chk->fetch()) {
            throw new RuntimeException('This item already has an active pending transfer. Confirm or reject it first.');
        }
        // BR-08
        $ua = $this->pdo->prepare("SELECT is_active FROM users WHERE id = :uid LIMIT 1");
        $ua->execute([':uid' => $data['to_user_id']]);
        $toUser = $ua->fetch();
        if (!$toUser || !$toUser['is_active']) {
            throw new RuntimeException('Cannot transfer to an inactive or non-existent user.');
        }

        $ref = generateTransferReference();

        $s = $this->pdo->prepare(
            "INSERT INTO custody_transfers
               (transfer_reference,item_id,from_user_id,to_user_id,
                from_location_id,to_location_id,transfer_status,reason,initiated_by,initiated_at)
             VALUES
               (:ref,:item,:from,:to,:fromloc,:toloc,'pending',:reason,:init,NOW())"
        );
        $s->execute([
            ':ref'     => $ref,
            ':item'    => $data['item_id'],
            ':from'    => $data['from_user_id'] ?? null,
            ':to'      => $data['to_user_id'],
            ':fromloc' => $data['from_location_id'] ?? null,
            ':toloc'   => $data['to_location_id'] ?? null,
            ':reason'  => $data['reason'],
            ':init'    => $data['initiated_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * CONFIRM a transfer.
     * Business Rules enforced:
     *   BR-04: only to_user (or admin) can confirm
     *   BR-05 + BR-06: wrapped in a transaction
     *
     * Returns true on success or throws RuntimeException.
     */
    public function confirm(int $transferId, int $confirmedByUserId, string $confirmedByRole): bool {
        $transfer = $this->findById($transferId);
        if (!$transfer) throw new RuntimeException('Transfer not found.');
        if ($transfer['transfer_status'] !== 'pending') {
            throw new RuntimeException('Only pending transfers can be confirmed.');
        }
        // BR-04
        if ($confirmedByRole !== ROLE_ADMIN && (int)$transfer['to_user_id'] !== $confirmedByUserId) {
            throw new RuntimeException('Only the designated recipient (or an Administrator) can confirm this transfer.');
        }

        // Find the "Received" status id
        $statusStmt = $this->pdo->prepare("SELECT id FROM item_statuses WHERE status_slug = 'in_storage' LIMIT 1");
        $statusStmt->execute();
        $inStorageStatus = $statusStmt->fetchColumn();

        // Get current item status for history record
        $itemStmt = $this->pdo->prepare("SELECT current_status_id FROM items WHERE id = :id LIMIT 1");
        $itemStmt->execute([':id' => $transfer['item_id']]);
        $prevStatusId = (int)$itemStmt->fetchColumn();

        $this->pdo->beginTransaction();
        try {
            // 1. Update transfer record
            $this->pdo->prepare(
                "UPDATE custody_transfers
                 SET transfer_status='confirmed', confirmed_by=:cb, confirmed_at=NOW()
                 WHERE id=:id"
            )->execute([':cb'=>$confirmedByUserId,':id'=>$transferId]);

            // 2. Update item current state (BR-05)
            $this->pdo->prepare(
                "UPDATE items SET
                    current_custodian_id = :cust,
                    current_location_id  = :loc,
                    current_status_id    = :stat
                 WHERE id = :iid"
            )->execute([
                ':cust' => $transfer['to_user_id'],
                ':loc'  => $transfer['to_location_id'],
                ':stat' => $inStorageStatus,
                ':iid'  => $transfer['item_id'],
            ]);

            // 3. Append status history (BR-06)
            $this->pdo->prepare(
                "INSERT INTO item_status_history
                   (item_id,previous_status_id,new_status_id,changed_by,related_transfer_id,reason,changed_at)
                 VALUES
                   (:iid,:prev,:new,:cb,:tid,:reason,NOW())"
            )->execute([
                ':iid'    => $transfer['item_id'],
                ':prev'   => $prevStatusId,
                ':new'    => $inStorageStatus,
                ':cb'     => $confirmedByUserId,
                ':tid'    => $transferId,
                ':reason' => 'Transfer confirmed. Custody updated.',
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * REJECT a transfer.
     */
    public function reject(int $transferId, int $rejectedByUserId, string $reason): bool {
        $transfer = $this->findById($transferId);
        if (!$transfer) throw new RuntimeException('Transfer not found.');
        if ($transfer['transfer_status'] !== 'pending') {
            throw new RuntimeException('Only pending transfers can be rejected.');
        }
        // Revert item status to in_storage
        $statusStmt = $this->pdo->prepare("SELECT id FROM item_statuses WHERE status_slug = 'in_storage' LIMIT 1");
        $statusStmt->execute();
        $inStorage = $statusStmt->fetchColumn();

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                "UPDATE custody_transfers SET transfer_status='rejected', rejection_reason=:r WHERE id=:id"
            )->execute([':r'=>$reason,':id'=>$transferId]);

            // Revert item status
            $this->pdo->prepare("UPDATE items SET current_status_id=:s WHERE id=:iid")
                ->execute([':s'=>$inStorage,':iid'=>$transfer['item_id']]);

            $itemStmt = $this->pdo->prepare("SELECT current_status_id FROM items WHERE id=:id LIMIT 1");
            $itemStmt->execute([':id'=>$transfer['item_id']]);
            $prevStat = (int)$itemStmt->fetchColumn();

            $this->pdo->prepare(
                "INSERT INTO item_status_history
                   (item_id,previous_status_id,new_status_id,changed_by,related_transfer_id,reason,changed_at)
                 VALUES (:iid,:prev,:new,:cb,:tid,:reason,NOW())"
            )->execute([
                ':iid'    => $transfer['item_id'],
                ':prev'   => $prevStat,
                ':new'    => $inStorage,
                ':cb'     => $rejectedByUserId,
                ':tid'    => $transferId,
                ':reason' => 'Transfer rejected: ' . $reason,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getDashboardStats(): array {
        $pending   = (int)$this->pdo->query("SELECT COUNT(*) FROM custody_transfers WHERE transfer_status='pending'")->fetchColumn();
        $today     = (int)$this->pdo->query("SELECT COUNT(*) FROM custody_transfers WHERE DATE(initiated_at)=CURDATE()")->fetchColumn();
        $confirmed = (int)$this->pdo->query("SELECT COUNT(*) FROM custody_transfers WHERE transfer_status='confirmed'")->fetchColumn();
        return ['pending'=>$pending,'today'=>$today,'confirmed'=>$confirmed];
    }
}
