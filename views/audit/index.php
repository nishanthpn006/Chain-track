<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_role(ROLE_ADMIN, ROLE_AUDITOR);
$auditModel = new AuditLog();
$filters = [
    'action'      => trim(input('action')),
    'entity_type' => input('entity_type'),
    'date_from'   => input('date_from'),
    'date_to'     => input('date_to'),
    'user_id'     => (int)input('user_id') ?: null,
];
$page = max(1,(int)input('page',1));
$data = $auditModel->getPaginated($page, 50, $filters);
$userModel = new User();
$allUsers  = $userModel->getAll(['is_active'=>'']);
$pageTitle = 'Audit Log';
require_once __DIR__ . '/../layouts/header.php';
?>
<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<div id="ct-main">
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>
<div id="ct-content">
<div class="d-flex justify-between align-center mb-2">
  <h2 style="margin:0;font-size:1.2rem;">System Audit Log</h2>
  <span class="small text-muted"><?= number_format($data['total']) ?> total records</span>
</div>
<div class="ct-card">
  <form method="GET" class="ct-search-bar">
    <div class="ct-form-group"><label class="ct-label">Action</label><input name="action" type="text" class="ct-input" placeholder="e.g. transfer.confirmed" value="<?= e($filters['action']) ?>" style="min-width:180px;"></div>
    <div class="ct-form-group"><label class="ct-label">Entity Type</label>
      <select name="entity_type" class="ct-select"><option value="">All</option><option value="item" <?= $filters['entity_type']=='item'?'selected':'' ?>>Item</option><option value="user" <?= $filters['entity_type']=='user'?'selected':'' ?>>User</option><option value="custody_transfers" <?= $filters['entity_type']=='custody_transfers'?'selected':'' ?>>Transfer</option><option value="investigation" <?= $filters['entity_type']=='investigation'?'selected':'' ?>>Investigation</option></select>
    </div>
    <div class="ct-form-group"><label class="ct-label">User</label>
      <select name="user_id" class="ct-select" style="min-width:160px;"><option value="">All Users</option><?php foreach($allUsers as $u): ?><option value="<?= $u['id'] ?>" <?= $filters['user_id']==$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="ct-form-group"><label class="ct-label">From Date</label><input name="date_from" type="date" class="ct-input" value="<?= e($filters['date_from']) ?>"></div>
    <div class="ct-form-group"><label class="ct-label">To Date</label><input name="date_to" type="date" class="ct-input" value="<?= e($filters['date_to']) ?>"></div>
    <button type="submit" class="ct-btn ct-btn-primary">Filter</button>
    <a href="?" class="ct-btn ct-btn-secondary">Reset</a>
  </form>
  <div class="ct-table-wrap">
    <table class="ct-table">
      <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach($data['rows'] as $log): ?>
        <tr>
          <td class="small text-muted" style="white-space:nowrap;"><?= fmtDt($log['created_at']) ?></td>
          <td class="small"><?= e($log['full_name']??'System') ?><br><span class="text-muted" style="font-size:.72rem;"><?= e($log['username']??'') ?></span></td>
          <td><code style="background:var(--ct-surface2);padding:2px 7px;border-radius:4px;font-size:.75rem;color:var(--ct-accent);"><?= e($log['action']) ?></code></td>
          <td class="small text-muted"><?= e($log['entity_type']??'—') ?><?= $log['entity_id'] ? ' #'.$log['entity_id'] : '' ?></td>
          <td class="small"><?= e(truncate($log['description']??'',70)) ?></td>
          <td class="small text-muted mono"><?= e($log['ip_address']??'—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($data['rows'])): ?><tr><td colspan="6" style="text-align:center;color:var(--ct-muted);padding:28px;">No audit records match your filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($data['pages']>1): ?>
  <div style="padding:14px 22px;border-top:1px solid var(--ct-border);">
    <div class="ct-pagination">
      <?php $qp=$_GET; for($p=1;$p<=$data['pages'];$p++){$qp['page']=$p; echo '<a href="?'.http_build_query($qp).'" class="ct-page-btn '.($p===$data['page']?'active':'').'">'.$p.'</a>'; } ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
