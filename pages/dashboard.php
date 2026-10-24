<?php require_once __DIR__ . '/_guard.php'; ?>
<?php if ($dashboard): ?>
  <div class="page-heading">
    <div>
      <p class="eyebrow">Overview</p>
      <h1>Admin Dashboard</h1>
    </div><a class="button primary" href="index.php?page=students">Manage Students</a>
  </div>
  <p class="muted small">Live database data · Refreshes every 15 seconds · Updated <?= escapeHtml(date('g:i:s A')) ?></p>
  <section class="stats-grid">
    <?php foreach ([
      ['Total Registered Students', $dashboard['metrics']['total_students']],
      ["Today's Library Visits", $dashboard['metrics']['today_visits']],
      ['Active Student Accounts', $dashboard['metrics']['active_student_accounts']],
      ["Today's Study Visits", $dashboard['metrics']['study_visits_today']],
      ["Today's Borrow Book Visits", $dashboard['metrics']['borrow_book_visits_today']]
    ] as [$label, $value]): ?>
      <article class="card stat-card">
        <p class="muted"><?= escapeHtml($label) ?></p><strong><?= (int) $value ?></strong>
      </article>
    <?php endforeach; ?>
  </section>
  <section class="content-grid dashboard-grid">
    <article class="card">
      <h2>Visitor Activity Chart</h2>
      <div class="chart">
        <?php foreach ($dashboard['activity'] as $day): ?>
          <div class="chart-day"><span><?= (int) $day['visits'] ?></span>
            <div class="chart-bar" title="<?= escapeHtml($day['date']) ?>: <?= (int) $day['visits'] ?> visits"
              style="height:<?= max(3, (int) round($day['visits'] / $maxVisits * 100)) ?>%"></div>
            <small><?= escapeHtml($day['label']) ?></small>
          </div><?php endforeach; ?>
      </div>
    </article>
    <article class="card">
      <h2>Quick Actions</h2>
      <div class="quick-links"><a href="index.php?page=scanner">Scan New Entry <span>→</span></a><a
          href="index.php?page=students">Manage Students <span>→</span></a><a href="index.php?page=logs">View Visitor Logs
          <span>→</span></a></div>
    </article>
  </section>
  <section class="content-grid">
    <article class="card">
      <h2>Recent Library Visits</h2>
      <?php if ($dashboard['recent_visits']):
        foreach ($dashboard['recent_visits'] as $visit): ?>
          <div class="list-row">
            <div><strong><?= escapeHtml($visit['full_name']) ?></strong><small><?= escapeHtml($visit['student_number']) ?> ·
                <?= escapeHtml($visit['purpose']) ?></small></div><time><?= escapeHtml($visit['visit_date']) ?>
              <?= escapeHtml($visit['time_in']) ?></time>
          </div>
        <?php endforeach; else: ?>
        <p class="muted">No visits have been recorded yet.</p><?php endif; ?>
    </article>
    <article class="card">
      <h2>Latest Student Registrations</h2>
      <?php if ($dashboard['latest_students']):
        foreach ($dashboard['latest_students'] as $student): ?>
          <div class="list-row">
            <div><strong><?= escapeHtml($student['full_name']) ?></strong><small><?= escapeHtml($student['student_number']) ?>
                · <?= escapeHtml($student['program']) ?></small></div>
            <time><?= escapeHtml(date('M j, Y', strtotime($student['created_at']))) ?></time>
          </div>
        <?php endforeach; else: ?>
        <p class="muted">No student records found.</p><?php endif; ?>
    </article>
  </section>
<?php endif; ?>