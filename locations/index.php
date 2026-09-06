<?php
// =============================================================================
// ChainTrack — Location Management: Listing
// locations/index.php
// =============================================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('administrator');

$db = getMySQL();
$search = trim($_GET['search'] ?? '');
$type   = trim($_GET['type'] ?? '');
$validTypes = ['storage', 'lab', 'office', 'field', 'external', 'other'];
if (!in_array($type, $validTypes, true)) $type = '';

$locations = getAllLocations($db, $search, $type);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Location Management';

function locTypeBadge(string $type): string {
    $map = [
        'storage'  => ['Storage',  'primary'],
        'lab'      => ['Lab',      'info'],
        'office'   => ['Office',   'secondary'],
        'field'    => ['Field',    'warning'],
        'external' => ['External', 'dark'],
        'other'    => ['Other',    'secondary'],
    ];
    $s = $map[$type] ?? [ucfirst($type), 'secondary'];
    return "<span class=\"badge bg-{$s[1]}\">{$s[0]}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Manage storage vaults, examination labs, and evidence locations.">
  <link rel="stylesheet" href="/public/css/chaintrack.css">
</head>
<body>

<div id="ct-sidebar-wrap">
<?php require_once __DIR__ . '/../views/layouts/sidebar.php'; ?>
<div id="ct-main">

  <div id="ct-topbar">
    <span class="page-title"><?= esc($pageTitle) ?></span>
    <div class="topbar-right">
      <?php if ($flash): ?>
      <div class="ct-alert ct-alert-<?= esc($flash['type']) ?>" style="margin:0;padding:8px 14px;font-size:.8rem;">
        <?= esc($flash['message']) ?>
      </div>
      <?php endif; ?>
      <div class="ct-user-pill">
        <div class="ct-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
        <span><?= esc($_SESSION['user_name'] ?? '') ?></span>
      </div>
      <a href="/views/auth/logout.php" class="ct-btn ct-btn-secondary ct-btn-sm" style="border-radius:99px;">Sign Out</a>
    </div>
  </div>

  <div id="ct-content">

    <div class="d-flex justify-between align-center mb-2">
      <div>
        <h2 style="margin:0;font-size:1.25rem;">Locations & Storage Facilities</h2>
        <span class="small text-muted">Evidence vaults, laboratories, archive rooms, and transfer destinations</span>
      </div>
      <a href="/locations/add.php" class="ct-btn ct-btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Location
      </a>
    </div>

    <!-- Filters -->
    <div class="ct-card mb-2" style="padding:14px 18px;">
      <form method="GET" class="d-flex align-center gap-2" style="margin:0;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;">
          <input type="text" name="search" class="ct-input" placeholder="Search by location name or code…" value="<?= esc($search) ?>">
        </div>
        <div style="width:160px;">
          <select name="type" class="ct-select">
            <option value="">All Types</option>
            <?php foreach ($validTypes as $vt): ?>
            <option value="<?= $vt ?>" <?= $type === $vt ? 'selected' : '' ?>><?= ucfirst($vt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="ct-btn ct-btn-primary">Filter</button>
        <?php if ($search !== '' || $type !== ''): ?>
        <a href="/locations/index.php" class="ct-btn ct-btn-secondary">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table Card -->
    <div class="ct-card">
      <div class="ct-table-wrap">
        <table class="ct-table">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th style="width:110px;">Code</th>
              <th>Location Name</th>
              <th style="width:100px;">Type</th>
              <th>Parent Facility</th>
              <th>Department</th>
              <th style="width:90px;text-align:center;">Items</th>
              <th style="width:90px;text-align:center;">Status</th>
              <th style="width:160px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($locations as $l): ?>
            <tr>
              <td class="mono small">#<?= (int)$l['id'] ?></td>
              <td><span class="mono fw-600" style="color:var(--ct-accent);"><?= esc($l['location_code'] ?: '—') ?></span></td>
              <td class="fw-600"><?= esc($l['location_name']) ?></td>
              <td><?= locTypeBadge($l['location_type']) ?></td>
              <td class="small text-muted"><?= esc($l['parent_name'] ?: '—') ?></td>
              <td class="small"><?= esc($l['dept_name'] ?: '—') ?></td>
              <td style="text-align:center;">
                <span class="badge bg-secondary"><?= (int)$l['active_item_count'] ?></span>
              </td>
              <td style="text-align:center;">
                <?php if ((int)$l['is_active'] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <div class="d-flex gap-1 justify-end">
                  <a href="/locations/edit.php?id=<?= (int)$l['id'] ?>" class="ct-btn ct-btn-secondary ct-btn-sm">
                    Edit
                  </a>
                  <a href="/locations/toggle_status.php?id=<?= (int)$l['id'] ?>"
                     class="ct-btn <?= (int)$l['is_active'] === 1 ? 'ct-btn-warning' : 'ct-btn-primary' ?> ct-btn-sm"
                     onclick="return confirm('Are you sure you want to <?= (int)$l['is_active'] === 1 ? 'deactivate' : 'activate' ?> this location?');">
                    <?= (int)$l['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($locations)): ?>
            <tr>
              <td colspan="9" style="text-align:center;color:var(--ct-muted);padding:32px;">
                No locations found.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
</div>

</body>
</html>
