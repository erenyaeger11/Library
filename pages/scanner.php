<?php require_once __DIR__ . '/_guard.php'; ?>
<div class="content-grid scanner-grid">
  <section class="card">
    <div class="page-heading">
      <div>
        <p class="eyebrow">Scan station</p>
        <h1>Library Entry Scanner</h1>
      </div>
    </div>
    <div class="scanner-controls"><label>Camera<select id="camera-select" disabled>
          <option>Finding cameras…</option>
        </select></label><label>Purpose<select id="scan-purpose">
          <option value="STUDY">Study</option>
          <option value="BORROW_BOOK">Borrow book</option>
        </select></label><button class="button secondary" id="restart-camera" type="button">Restart Camera</button>
    </div>
    <div id="qr-reader" class="qr-reader"></div>
    <p id="scan-message" class="scan-message" role="status">Requesting camera access…</p>
    <div id="scan-result" class="scan-result" hidden></div>
  </section>
  <section class="card">
    <h2>Recent Successful Scans</h2>
    <div id="recent-scans" class="recent-scans"><?php foreach (array_slice($logs, 0, 5) as $visit): ?>
        <div class="recent-scan">
          <strong><?= escapeHtml($visit['full_name']) ?></strong><span><?= escapeHtml($visit['time_in']) ?> ·
            <?= escapeHtml($visit['purpose']) ?></span></div><?php endforeach; ?>
    </div>
  </section>
</div>