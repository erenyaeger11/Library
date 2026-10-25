<?php require_once __DIR__ . '/_guard.php'; ?>
<div class="page-heading">
  <div>
    <p class="eyebrow">Library activity</p>
    <h1>Visitor Logs</h1>
  </div>
</div>
<section class="card table-card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Program</th>
          <th>Year Level</th>
          <th>Visit Date</th>
          <th>Time In</th>
          <th>Purpose</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= escapeHtml($log['student_number']) ?></td>
            <td><?= escapeHtml($log['full_name']) ?></td>
            <td><?= escapeHtml($log['program']) ?></td>
            <td><?= escapeHtml($log['year_level']) ?></td>
            <td><?= escapeHtml($log['visit_date']) ?></td>
            <td><?= escapeHtml($log['time_in']) ?></td>
            <td><?= escapeHtml($log['purpose']) ?></td>
          </tr><?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr>
            <td colspan="7" class="empty-state">No visitor logs have been recorded.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<!-- End: visit logs page -->