<?php require_once __DIR__ . '/_guard.php'; ?>
<div class="page-heading">
  <div>
    <p class="eyebrow">Student records</p>
    <h1>Student Management</h1>
  </div><a class="button primary" href="#student-form">Add New Student</a>
</div>
<?php if ($createdStudent): ?>
  <section class="card qr-confirmation">
    <div class="generated-qr" data-qr-token="<?= escapeHtml($createdStudent['qr_identifier']) ?>"></div>
    <div><strong>Student account created: <?= escapeHtml($createdStudent['full_name']) ?></strong>
      <p class="muted"><?= escapeHtml($createdStudent['student_number']) ?> · QR code is ready to download.</p><button
        type="button" class="button primary download-qr">Download QR code</button>
    </div><button type="button" class="button secondary dismiss-qr"
      onclick="this.closest('.qr-confirmation').remove()">Dismiss</button>
  </section>
<?php endif; ?>
<section class="card" id="student-form">
  <h2>Add or Edit Student</h2>
  <form method="post" class="form-grid">
    <input type="hidden" name="action" value="create_student"><input type="hidden" name="page" value="students"><input
      type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
    <?php foreach (['student_number' => 'Student ID number', 'full_name' => 'Full name', 'email' => 'Email address', 'program' => 'Course / program', 'year_level' => 'Year level'] as $field => $label): ?>
      <label><?= escapeHtml($label) ?><input name="<?= escapeHtml($field) ?>"
          type="<?= $field === 'email' ? 'email' : 'text' ?>" required></label>
    <?php endforeach; ?>
    <div class="form-actions"><button class="button primary" type="submit">Create student and QR code</button></div>
  </form>
</section>
<form method="get" class="search-row"><input type="hidden" name="page" value="students"><label>Search students<input
      name="q" value="<?= escapeHtml($query) ?>" placeholder="Search by ID, name, email, or program"></label><button
    class="button secondary" type="submit">Search</button></form>
<section class="card table-card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Course</th>
          <th>Year</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $student): ?>
          <tr>
            <td><?= escapeHtml($student['student_number']) ?></td>
            <td><?= escapeHtml($student['full_name']) ?></td>
            <td><?= escapeHtml($student['email']) ?></td>
            <td><?= escapeHtml($student['program']) ?></td>
            <td><?= escapeHtml($student['year_level']) ?></td>
            <td><span
                class="badge <?= $student['account_status'] === 'active' ? 'active' : 'inactive' ?>"><?= escapeHtml($student['account_status']) ?></span>
            </td>
            <td>
              <div class="action-row"><a href="index.php?page=students&view=<?= (int) $student['id'] ?>">View</a><button
                  type="button" class="link-button edit-student" data-id="<?= (int) $student['id'] ?>"
                  data-student="<?= escapeHtml(json_encode($student, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">Edit</button>
                <form method="post" class="confirm-action"
                  data-confirm="<?= escapeHtml(($student['account_status'] === 'active' ? 'Deactivate ' : 'Activate ') . $student['full_name'] . '?' . ($student['account_status'] === 'active' ? ' Their visit history will be kept.' : ' Generate a new QR code before they check in.')) ?>">
                  <input type="hidden" name="action" value="set_status"><input type="hidden" name="page"
                    value="students"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input
                    type="hidden" name="id" value="<?= (int) $student['id'] ?>"><input type="hidden" name="account_status"
                    value="<?= $student['account_status'] === 'active' ? 'inactive' : 'active' ?>"><button
                    class="link-button <?= $student['account_status'] === 'active' ? 'danger-text' : '' ?>"
                    type="submit"><?= $student['account_status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" class="confirm-action"
                  data-confirm="<?= escapeHtml('Permanently delete ' . $student['full_name'] . ' (' . $student['student_number'] . '), their QR code, and all visit history? This cannot be undone.') ?>">
                  <input type="hidden" name="action" value="delete_student"><input type="hidden" name="page"
                    value="students"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input
                    type="hidden" name="id" value="<?= (int) $student['id'] ?>"><button class="link-button danger-text"
                    type="submit">Delete</button></form>
              </div>
            </td>
          </tr><?php endforeach; ?>
        <?php if (!$students): ?>
          <tr>
            <td colspan="7" class="empty-state">No student records<?= $query !== '' ? ' match your search' : '' ?>.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php if ($studentProfile): ?>
  <section class="card profile-card">
    <div class="page-heading">
      <div>
        <p class="eyebrow">Student profile</p>
        <h2><?= escapeHtml($studentProfile['full_name']) ?></h2>
      </div><a class="button secondary" href="index.php?page=students">Close</a>
    </div>
    <dl class="profile-grid">
      <?php foreach (['Student ID' => $studentProfile['student_number'], 'Email' => $studentProfile['email'], 'Program' => $studentProfile['program'], 'Year level' => $studentProfile['year_level'], 'Account status' => $studentProfile['account_status'], 'QR status' => $studentProfile['qr_status'] ?: 'Not generated'] as $label => $value): ?>
        <div>
          <dt><?= escapeHtml($label) ?></dt>
          <dd><?= escapeHtml($value) ?></dd>
        </div><?php endforeach; ?>
    </dl>
    <h3>Visit history</h3><?php if ($studentProfile['visit_history']): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Time</th>
              <th>Purpose</th>
            </tr>
          </thead>
          <tbody><?php foreach ($studentProfile['visit_history'] as $visit): ?>
              <tr>
                <td><?= escapeHtml($visit['visit_date']) ?></td>
                <td><?= escapeHtml($visit['time_in']) ?></td>
                <td><?= escapeHtml($visit['purpose']) ?></td>
              </tr><?php endforeach; ?>
          </tbody>
        </table>
      </div><?php else: ?>
      <p class="muted">No visit history.</p><?php endif; ?>
  </section>
<?php endif; ?>
<!-- End: students page -->