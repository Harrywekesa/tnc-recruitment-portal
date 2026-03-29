<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $new_status = $_POST['status'] ?? '';
        if (array_key_exists($new_status, APP_STATUSES)) {
            $upd = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
            $upd->execute([$new_status, $id]);
            log_activity("Altered Candidate Status to [{$new_status}]", "Application", (string)$id);
            header("Location: view_application.php?id=$id&updated=1");
            exit;
        }
    }
}

$app = $pdo->prepare("SELECT a.*, u.full_name, u.email, u.phone, u.gender, u.dob, u.username AS nat_id, u.ward, u.sub_county, j.title AS job_title, j.job_code 
                      FROM applications a 
                      JOIN users u ON a.user_id = u.id 
                      JOIN jobs j ON a.job_id = j.id 
                      WHERE a.id = ?");
$app->execute([$id]);
$app = $app->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    header('Location: applications.php');
    exit;
}

$quals = $pdo->prepare("SELECT * FROM application_qualifications WHERE application_id = ? ORDER BY year_completed DESC");
$quals->execute([$id]);
$quals = $quals->fetchAll(PDO::FETCH_ASSOC);

$refs = $pdo->prepare("SELECT * FROM application_referees WHERE application_id = ?");
$refs->execute([$id]);
$refs = $refs->fetchAll(PDO::FETCH_ASSOC);

$docs = $pdo->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY doc_type ASC");
$docs->execute([$id]);
$docs = $docs->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Reviewing ' . $app['full_name'];
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'applications';
require_once __DIR__ . '/partials/admin_nav.php';

$lbl = APP_STATUSES[$app['status']] ?? ['label'=>$app['status'], 'class'=>'badge-neutral'];
?>

<div class="page-wrap" style="background:#f4f5f5;">
  <div class="container">
    
    <div style="margin-bottom:20px;">
      <a href="applications.php" style="color:var(--text-muted); text-decoration:none;">← Back to Application Tracker</a>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success" style="margin-bottom:20px;">
        <span class="alert-icon">✓</span>
        <div><strong>Status Updated</strong><br>The file state was explicitly updated successfully across the schema.</div>
      </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">
      
      <!-- LEFT COLUMN -->
      <div>
        
        <!-- Applicant Identity -->
        <div class="card" style="padding:24px; margin-bottom:24px;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
            <div>
              <h2 style="font-size:24px; font-weight:700; color:var(--green-900); margin-bottom:4px;"><?= h($app['full_name']) ?></h2>
              <div style="font-size:14px; color:var(--text-light);"><?= h($app['job_title']) ?> (<?= h($app['job_code']) ?>)</div>
            </div>
            <span class="badge <?= $lbl['class'] ?>" style="font-size:14px; padding:6px 12px;"><?= h($lbl['label']) ?></span>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; background:#f9fafa; padding:16px; border-radius:8px; border:1px solid var(--border);">
            <div><strong style="color:var(--text-muted);font-size:12px;display:block;">National ID</strong><?= h($app['nat_id']) ?></div>
            <div><strong style="color:var(--text-muted);font-size:12px;display:block;">Gender / DOB</strong><?= h($app['gender']??'—') ?> · <?= h($app['dob']??'—') ?></div>
            <div><strong style="color:var(--text-muted);font-size:12px;display:block;">Phone / Email</strong><?= h($app['phone']) ?><br><?= h($app['email']) ?></div>
            <div><strong style="color:var(--text-muted);font-size:12px;display:block;">Sub-County / Ward</strong><?= h($app['sub_county']??'—') ?> — <?= h($app['ward']??'—') ?></div>
          </div>
        </div>

        <!-- Qualifications -->
        <div class="card" style="padding:24px; margin-bottom:24px;">
          <h3 style="font-size:18px; color:var(--green-900); margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid var(--border);">Academic & Professional Qualifications</h3>
          <?php if(empty($quals)): ?>
            <div style="color:var(--text-light);font-size:14px;">No discrete qualifications natively bound.</div>
          <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:12px;">
              <?php foreach($quals as $q): ?>
                <div style="border-left:3px solid var(--gold); padding-left:12px;">
                  <div style="font-size:12px;color:var(--text-muted);font-weight:600;"><?= h($q['type']) ?> — <?= h($q['level']) ?></div>
                  <div style="font-size:16px;color:var(--green-900);font-weight:600;margin:2px 0;"><?= h($q['title']) ?></div>
                  <div style="font-size:14px;color:var(--text-light);"><?= h($q['institution']) ?> • <strong><?= h($q['year_completed']) ?></strong></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Experience & KISM -->
        <div class="card" style="padding:24px; margin-bottom:24px;">
          <h3 style="font-size:18px; color:var(--green-900); margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid var(--border);">Experience & Affiliations</h3>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div><strong style="color:var(--text-muted);font-size:12px;display:block;">Total Experience</strong><?= h($app['experience']?:'—') ?></div>
            <div><strong style="color:var(--text-muted);font-size:12px;display:block;">Current/Last Employer</strong><?= h($app['current_employer']?:'—') ?></div>
            <?php if($app['kism_no']): ?>
              <div style="grid-column: span 2;"><strong style="color:var(--text-muted);font-size:12px;display:block;">KISM Number</strong><?= h($app['kism_no']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Referees -->
        <div class="card" style="padding:24px; margin-bottom:24px;">
          <h3 style="font-size:18px; color:var(--green-900); margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid var(--border);">Administrative Referees</h3>
          <?php if(empty($refs)): ?>
            <div style="color:var(--text-light);font-size:14px;">No referees securely mapped.</div>
          <?php else: ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
              <?php foreach($refs as $r): ?>
                <div style="background:#f9fafa; border:1px solid var(--border); padding:12px; border-radius:6px;">
                  <strong style="color:var(--green-900);font-size:14px;"><?= h($r['name']) ?></strong>
                  <div style="font-size:12px;color:var(--text-light); margin-bottom:6px;"><?= h($r['designation']) ?> at <?= h($r['organization']) ?></div>
                  <div style="font-size:12px;color:var(--text-muted);">📞 <?= h($r['phone']) ?><br>✉️ <?= h($r['email']?:'N/A') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- RIGHT COLUMN -->
      <div>
        
        <!-- Router Controller -->
        <div class="card" style="padding:20px; margin-bottom:24px; border-top:4px solid var(--green-600);">
          <h3 style="font-size:16px; margin-bottom:12px;">Pipeline Triage Action</h3>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
              <label class="form-label" style="font-size:12px; color:var(--text-muted);">Override Candidate Status</label>
              <select name="status" class="form-select">
                <?php foreach (APP_STATUSES as $s => $cfg): ?>
                  <option value="<?= $s ?>" <?= $app['status']===$s?'selected':'' ?>><?= h($cfg['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%;">Execute Status Update</button>
          </form>
        </div>

        <!-- Bound Files Extractor -->
        <div class="card" style="padding:20px;">
          <h3 style="font-size:16px; margin-bottom:16px;">Secure Blobs Explorer</h3>
          <?php if(empty($docs)): ?>
            <p style="font-size:13px; color:var(--text-muted);">No documents extracted in transmission array.</p>
          <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
              <?php foreach($docs as $doc): ?>
                <a href="<?= $root ?><?= h($doc['filepath']) ?>" target="_blank" style="display:block; padding:12px; background:#f9fafa; border:1px solid var(--border); border-radius:6px; text-decoration:none; transition:0.2s;" onmouseover="this.style.background='#fff';this.style.borderColor='var(--green-400)'" onmouseout="this.style.background='#f9fafa';this.style.borderColor='var(--border)'">
                  <div style="font-size:11px; font-weight:700; color:var(--green-700); text-transform:uppercase; margin-bottom:2px;"><?= h($doc['doc_type']) ?></div>
                  <div style="font-size:13px; color:var(--green-900); font-weight:600; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;"><?= h($doc['original_name']) ?></div>
                  <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">👁 Access Raw View</div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
