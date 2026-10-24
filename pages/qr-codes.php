<?php require_once __DIR__ . '/_guard.php'; ?>
<div class="page-heading">
  <div>
    <p class="eyebrow">Student credentials</p>
    <h1>QR Code Management</h1>
  </div>
</div>
<p class="muted">Generate a secure QR code for an active student. A regenerated code immediately revokes the previous
  code.</p>
<?php if ($generatedQr): ?>
  <section class="card qr-confirmation">
    <div class="generated-qr" data-qr-token="<?= escapeHtml($generatedQr['qr_identifier']) ?>"></div>
    <div><strong><?= escapeHtml($generatedQr['full_name']) ?></strong>
      <p class="muted"><?= escapeHtml($generatedQr['student_number']) ?> · Save or print this QR code now.</p><button
        type="button" class="button primary download-qr">Download PNG</button>
    </div>
  </section><?php endif; ?>
<section class="student-cards">
  <?php $activeCount = 0;
  foreach ($students as $student):
    if ($student['account_status'] !== 'active')
      continue;
    $activeCount++; ?>
    <article class="card student-qr-card">
      <div class="qr-placeholder" aria-hidden="true">▦</div>
      <h2><?= escapeHtml($student['full_name']) ?></h2>
      <p class="muted"><?= escapeHtml($student['student_number']) ?></p>
      <form method="post" class="confirm-action"
        data-confirm="<?= escapeHtml('Generate a new QR code for ' . $student['full_name'] . '? The current code will stop working.') ?>">
        <input type="hidden" name="action" value="generate_qr"><input type="hidden" name="page" value="qr-codes"><input
          type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="id"
          value="<?= (int) $student['id'] ?>"><button class="button primary"
          type="submit"><?= $student['qr_status'] === 'active' ? 'Regenerate QR' : 'Generate QR' ?></button></form>
    </article>
  <?php endforeach; ?>
  <?php if (!$activeCount): ?>
    <p class="muted">No active student accounts are available.</p><?php endif; ?>
</section>