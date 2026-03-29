<?php
declare(strict_types=1);
// includes/partials/footer.php
$root = $root ?? '';
?>
<!-- Info Strip -->
<div class="info-strip">
  <div class="container">
    <div class="info-block fade-up">
      <div class="info-block-title">Contact Information</div>
      <address>
        County Government of Trans Nzoia<br>
        Public Service Management Department<br>
        <?= COUNTY_BOX ?? 'P.O. Box 4211 – 30200, Kitale, Kenya' ?><br><br>
        <a href="tel:+25405320604"><?= COUNTY_PHONE ?? '+254 (053) 20604' ?></a><br>
        <a href="mailto:<?= COUNTY_EMAIL ?? 'recruitment@transnzoia.go.ke' ?>"><?= COUNTY_EMAIL ?? 'recruitment@transnzoia.go.ke' ?></a>
      </address>
    </div>
    <div class="info-block fade-up">
      <div class="info-block-title">Sub-Counties</div>
      <ul>
        <?php foreach ((defined('SUB_COUNTIES') ? SUB_COUNTIES : []) as $sc => $wards): ?>
          <li><?= function_exists('h') ? h($sc) : htmlspecialchars($sc) ?> <span><?= count($wards) ?> wards</span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="info-block fade-up">
      <div class="info-block-title">Important Notices</div>
      <p>
        The County Government of Trans Nzoia does <strong style="color:#fff">not</strong> charge
        any fee at any stage of the recruitment process.<br><br>
        Only shortlisted candidates will be contacted for interviews.<br><br>
        Canvassing in any form will lead to <strong style="color:#fff">automatic disqualification</strong>.<br><br>
        Trans Nzoia County is an equal opportunity employer.
      </p>
    </div>
  </div>
</div>

<footer>
  <div class="container">
    <div class="footer-copy">
      &copy; <?= date('Y') ?> County Government of Trans Nzoia. All rights reserved.
      <span style="margin:0 8px;opacity:.4">|</span>
      Unity in Diversity
    </div>
    <div class="footer-links">
      <a href="<?= $root ?>index.php">Home</a>
      <a href="<?= $root ?>jobs.php">Vacancies</a>
      <a href="<?= $root ?>shortlist.php">Shortlists</a>
      <a href="<?= $root ?>status.php">Check Status</a>
      <a href="https://transnzoia.go.ke" target="_blank" rel="noopener">Official Website</a>
      <?php if (!is_logged_in()): ?>
      <a href="<?= $root ?>admin/login.php">Staff Login</a>
      <?php endif; ?>
    </div>
  </div>
</footer>

<script>
// Shared: fade-up intersection observer
(function(){
  const obs = new IntersectionObserver(entries => {
    entries.forEach((e, i) => {
      if (e.isIntersecting) {
        setTimeout(() => e.target.classList.add('visible'), i * 70);
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.08 });
  document.querySelectorAll('.fade-up').forEach(el => obs.observe(el));
})();
</script>
</body>
</html>
