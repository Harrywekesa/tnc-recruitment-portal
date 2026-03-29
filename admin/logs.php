<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';

// Native Strict Interceptor:
require_superadmin();

$pdo = db();

$sql = "SELECT l.*, u.full_name, u.role, u.email 
        FROM activity_logs l 
        JOIN users u ON l.user_id = u.id 
        ORDER BY l.created_at DESC LIMIT 500";
$logs = $pdo->query($sql)->fetchAll();

$page_title = 'System Activity Logs';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'logs';
require_once __DIR__ . '/partials/admin_nav.php';
?>

<div class="page-wrap" style="background:#f4f5f5; padding-bottom:100px;">
  <div class="container" style="max-width:1100px;">
    
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px;">
      <div>
        <h2 style="font-size:22px; font-weight:700; color:var(--green-900);">System Activity Logs</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-top:6px;">This dashboard shows a history of administrative actions taken on the portal. It is only visible to Super Administrators.</p>
      </div>
      <div style="font-size:13px; color:var(--text-light); padding:8px 16px; background:#fff; border-radius:6px; border:1px solid var(--border);">Displaying last <strong>500</strong> system events.</div>
    </div>

    <div class="card fade-up" style="overflow:-moz-hidden-unscrollable; overflow:hidden;">
      <div class="table-responsive" style="max-height:600px; overflow-y:auto; background:#fff;">
        <table class="table" style="margin:0; font-size:13px;">
          <thead style="position:sticky; top:0; background:#f9fafa; z-index:10; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <tr>
              <th style="width:180px;">Timestamp</th>
              <th>Administrator</th>
              <th>Access Level</th>
              <th>Action Details</th>
              <th>Target Record</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr><td colspan="6" style="padding:40px; text-align:center; color:var(--text-muted);">The activity logging system is active, but no actions have been recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <tr>
                  <td style="color:var(--text-light); font-family:monospace;"><?= date('M j, Y - H:i:s', strtotime($log['created_at'])) ?></td>
                  <td style="font-weight:600; color:var(--green-900);">
                    <?= h($log['full_name']) ?>
                    <div style="font-weight:400; font-size:11px; color:var(--text-muted);"><?= h($log['email']) ?></div>
                  </td>
                  <td>
                    <?php if($log['role'] === 'superadmin'): ?>
                        <span class="badge" style="background:var(--gold); color:#000;">Root / Super</span>
                    <?php elseif($log['role'] === 'admin'): ?>
                        <span class="badge" style="background:var(--green-600); color:#fff;">Site Admin</span>
                    <?php else: ?>
                        <span class="badge badge-neutral">HR Officer</span>
                    <?php endif; ?>
                  </td>
                  <td style="color:var(--text-dark); font-weight:500;"><?= h($log['action']) ?></td>
                  <td>
                    <?php if($log['entity_type']): ?>
                        <span style="font-family:monospace; background:#eef0f2; padding:2px 6px; border-radius:4px; font-size:12px;">
                            <?= h($log['entity_type']) ?>: <?= h($log['entity_id'] ?: 'NULL') ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text-light);">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-family:monospace; color:var(--text-muted); font-size:11px;"><?= h($log['ip_address']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
