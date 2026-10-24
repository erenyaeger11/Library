import { useEffect, useId, useRef, useState } from 'react';
import { Routes, Route, Navigate, useLocation, Link, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Html5Qrcode } from 'html5-qrcode';
import QRCode from 'qrcode';
import { BarChart3, QrCode, ShieldCheck, UserRound, LibraryBig, LogOut, Clock3 } from 'lucide-react';

const navItems = [
  { label: 'Dashboard', icon: BarChart3, to: '/dashboard' },
  { label: 'Student Management', icon: UserRound, to: '/students' },
  { label: 'QR Code Management', icon: QrCode, to: '/qr-codes' },
  { label: 'Library Entry Scanner', icon: ShieldCheck, to: '/scanner' },
  { label: 'Visitor Logs', icon: Clock3, to: '/logs' },
  { label: 'Reports and Analytics', icon: BarChart3, to: '/reports' },
  { label: 'Settings', icon: LibraryBig, to: '/settings' },
  { label: 'Logout', icon: LogOut, to: '/' }
];
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';

/**
 * Reads and validates a JSON response from the PHP API.
 * @param {Response} response Fetch response to parse.
 * @returns {Promise<Record<string, any>>} Decoded response payload.
 */
async function readApiResponse(response) {
  const contentType = response.headers.get('content-type') || '';
  const responseText = await response.text();

  if (!contentType.includes('application/json')) {
    throw new Error(
      'The API did not return JSON. Start Apache in XAMPP and make sure the PHP API is available at http://localhost/CDC101/api.'
    );
  }

  try {
    return JSON.parse(responseText);
  } catch {
    throw new Error('The API returned invalid JSON. Check the PHP error log for details.');
  }
}

/** Renders the application shell and routes admin users to protected pages. */
function App() {
  const location = useLocation();
  const [admin, setAdmin] = useState(null);
  const [authChecked, setAuthChecked] = useState(false);

  useEffect(() => {
    fetch(`${API_BASE_URL}/auth/me.php`, { credentials: 'include' })
      .then(async (response) => {
        const result = await readApiResponse(response);
        if (response.ok && result.success) setAdmin(result.data);
      })
      .catch((error) => {
        console.error('Unable to verify admin session:', error);
      })
      .finally(() => setAuthChecked(true));
  }, []);

  const pageVariants = {
    initial: { opacity: 0, y: 12 },
    animate: { opacity: 1, y: 0 },
    exit: { opacity: 0, y: -12 }
  };

  if (!authChecked) {
    return <div className="flex min-h-screen items-center justify-center text-slate-600">Checking administrator session...</div>;
  }

  if (!admin) {
    return (
      <Routes>
        <Route path="/" element={<LoginPage onLogin={setAdmin} />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    );
  }

  return (
    <div className="min-h-screen bg-slate-100 text-slate-800">
      <div className="flex min-h-screen">
        <aside className="w-72 bg-ssu-900 p-6 text-white shadow-lg">
          <div className="mb-8 flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-ssu-700 text-lg font-bold">SSU</div>
            <div>
              <p className="text-xs uppercase tracking-[0.25em] text-ssu-200">Library</p>
              <h1 className="text-xl font-semibold">QR Entry</h1>
            </div>
          </div>

          <nav className="space-y-2">
            {navItems.map(({ label, icon: Icon, to }) => (
              label === 'Logout' ? (
                <button
                  key={label}
                  type="button"
                  onClick={async () => {
                    try {
                      const response = await fetch(`${API_BASE_URL}/auth/logout.php`, {
                        method: 'POST',
                        credentials: 'include'
                      });
                      if (!response.ok) throw new Error('Logout request failed');
                      setAdmin(null);
                    } catch (error) {
                      console.error('Unable to log out:', error);
                    }
                  }}
                  className="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-200 transition hover:bg-ssu-800 hover:text-white"
                >
                  <Icon size={18} />
                  <span>{label}</span>
                </button>
              ) : (
                <Link
                  key={label}
                  to={to}
                  className="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-ssu-800 hover:text-white"
                >
                  <Icon size={18} />
                  <span>{label}</span>
                </Link>
              )
            ))}
          </nav>
        </aside>

        <main className="flex-1 p-6">
          <AnimatePresence mode="wait">
            <motion.div
              key={location.pathname}
              variants={pageVariants}
              initial="initial"
              animate="animate"
              exit="exit"
              transition={{ duration: 0.2 }}
              className="space-y-6"
            >
              <Routes>
                <Route path="/" element={<Navigate to="/dashboard" replace />} />
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/students" element={<StudentsPage />} />
                <Route path="/qr-codes" element={<QrCodesPage />} />
                <Route path="/scanner" element={<ScannerPage />} />
                <Route path="/logs" element={<LogsPage />} />
                <Route path="/reports" element={<ReportsPage />} />
                <Route path="/settings" element={<SettingsPage />} />
                <Route path="*" element={<Navigate to="/dashboard" replace />} />
              </Routes>
            </motion.div>
          </AnimatePresence>
        </main>
      </div>
    </div>
  );
}

/**
 * Renders the admin sign-in form and establishes an authenticated session.
 * @param {{ onLogin: (admin: object) => void }} props Login callback.
 */
function LoginPage({ onLogin }) {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  /** Authenticates the administrator with the PHP login endpoint. */
  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setSubmitting(true);

    try {
      const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ email, password })
      });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Sign in failed');
      }

      onLogin(result.data);
      navigate('/dashboard', { replace: true });
    } catch (requestError) {
      setError(requestError.message || 'Unable to sign in. Check the API connection.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-ssu-900 via-ssu-800 to-cyan-700 px-4">
      <motion.div initial={{ opacity: 0, scale: 0.98 }} animate={{ opacity: 1, scale: 1 }} className="w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div className="grid md:grid-cols-2">
          <div className="bg-ssu-900 p-8 text-white">
            <p className="mb-3 text-xs uppercase tracking-[0.35em] text-ssu-200">Samar State University</p>
            <h2 className="text-4xl font-bold">Library Access Portal</h2>
            <p className="mt-4 text-ssu-100">Secure student entry, QR verification, and visitor logging for the library.</p>
            <div className="mt-8 rounded-2xl bg-white/5 p-5 backdrop-blur-sm">
              <div className="mb-4 flex items-center gap-3">
                <div className="rounded-xl bg-ssu-700 p-2"><QrCode size={20} /></div>
                <div>
                  <p className="text-sm text-ssu-100">Quick verification</p>
                  <p className="font-semibold">Modern QR-based check-in</p>
                </div>
              </div>
              <p className="text-sm text-slate-200">Scan a student QR code to validate the account, log the visit, and prevent duplicate entry attempts.</p>
            </div>
          </div>

          <div className="p-8 md:p-10">
            <div className="mb-8">
              <p className="text-sm font-medium uppercase tracking-[0.2em] text-ssu-700">Administrator login</p>
              <h3 className="mt-2 text-3xl font-bold text-slate-800">Welcome back</h3>
            </div>

            <form className="space-y-5" onSubmit={handleSubmit}>
              <div>
                <label className="mb-2 block text-sm font-medium text-slate-700">Email address</label>
                <input type="email" required autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none ring-0 transition focus:border-ssu-400 focus:bg-white" placeholder="library.admin@ssu.edu.ph" />
              </div>

              <div>
                <label className="mb-2 block text-sm font-medium text-slate-700">Password</label>
                <input type="password" required autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none transition focus:border-ssu-400 focus:bg-white" placeholder="••••••••" />
              </div>

              {error && <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}

              <button type="submit" disabled={submitting} className="flex w-full items-center justify-center rounded-xl bg-ssu-700 px-4 py-3 font-semibold text-white transition hover:bg-ssu-800 disabled:cursor-wait disabled:opacity-60">
                {submitting ? 'Signing in...' : 'Sign in'}
              </button>
            </form>
          </div>
        </div>
      </motion.div>
    </div>
  );
}

/** Loads and displays live dashboard statistics and recent database records. */
function DashboardPage() {
  const [dashboard, setDashboard] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    /** Fetches the latest dashboard metrics from the backend. */
    const loadDashboard = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/reports/dashboard.php`, { credentials: 'include' });
        const result = await readApiResponse(response);
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load dashboard data');
        if (active) {
          setDashboard(result.data);
          setError('');
        }
      } catch (loadError) {
        if (active) setError(loadError.message || 'Unable to load dashboard data');
      }
    };

    loadDashboard();
    const refreshTimer = window.setInterval(loadDashboard, 15000);
    return () => {
      active = false;
      window.clearInterval(refreshTimer);
    };
  }, []);

  const stats = [
    { label: 'Total Registered Students', value: dashboard?.total_students },
    { label: "Today's Library Visits", value: dashboard?.today_visits },
    { label: 'Active Student Accounts', value: dashboard?.active_student_accounts },
    { label: "Today's Study Visits", value: dashboard?.study_visits_today },
    { label: "Today's Borrow Book Visits", value: dashboard?.borrow_book_visits_today }
  ];
  const maxVisits = Math.max(1, ...(dashboard?.activity || []).map((day) => day.visits));

  return (
    <div className="space-y-6">
      <header className="flex items-center justify-between">
        <div>
          <p className="text-sm uppercase tracking-[0.2em] text-slate-500">Overview</p>
          <h2 className="text-3xl font-bold text-slate-800">Admin Dashboard</h2>
        </div>
        <Link to="/students" className="rounded-xl bg-ssu-700 px-4 py-2 font-medium text-white shadow-md hover:bg-ssu-800">Manage Students</Link>
      </header>

      {error && <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">Dashboard data could not be refreshed: {error}</p>}
      <p className="text-xs text-slate-500">
        Live database data · Refreshes every 15 seconds
        {dashboard?.updated_at && ` · Updated ${new Date(dashboard.updated_at).toLocaleTimeString()}`}
      </p>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {stats.map((stat, index) => (
          <motion.div key={stat.label} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: index * 0.06 }} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft">
            <p className="text-sm text-slate-500">{stat.label}</p>
            <div className="mt-4 flex items-end justify-between">
              <h3 className="text-3xl font-bold text-slate-800">{stat.value ?? '—'}</h3>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft">
          <h3 className="mb-4 text-lg font-semibold text-slate-800">Visitor Activity Chart</h3>
          {dashboard?.activity ? (
            <div className="flex h-48 items-end gap-3">
              {dashboard.activity.map((day) => (
                <div key={day.date} className="flex h-full flex-1 flex-col items-center justify-end gap-2">
                  <span className="text-xs text-slate-600">{day.visits}</span>
                  <div
                    title={`${day.date}: ${day.visits} visits`}
                    className="w-full rounded-t-xl bg-gradient-to-t from-ssu-600 to-cyan-300"
                    style={{ height: `${Math.max(2, (day.visits / maxVisits) * 75)}%` }}
                  />
                  <span className="text-xs text-slate-500">{day.label}</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="py-16 text-center text-sm text-slate-500">{error ? 'Activity data unavailable.' : 'Loading activity data…'}</p>
          )}
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft">
          <h3 className="mb-4 text-lg font-semibold text-slate-800">Quick Actions</h3>
          <div className="space-y-3">
            {[['Scan New Entry', '/scanner'], ['Manage Students', '/students'], ['View Visitor Logs', '/logs']].map(([item, to]) => (
              <Link key={item} to={to} className="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left transition hover:border-ssu-300 hover:bg-slate-100">
                <span>{item}</span><span className="text-ssu-700">→</span>
              </Link>
            ))}
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft">
          <h3 className="mb-4 text-lg font-semibold text-slate-800">Recent Library Visits</h3>
          {dashboard?.recent_visits?.length ? dashboard.recent_visits.map((visit, index) => (
            <div key={`${visit.student_number}-${visit.visit_date}-${visit.time_in}-${index}`} className="flex justify-between gap-4 border-t border-slate-100 py-3 text-sm first:border-0">
              <div><p className="font-medium">{visit.full_name}</p><p className="text-slate-500">{visit.student_number} · {visit.purpose}</p></div>
              <span className="whitespace-nowrap text-slate-500">{visit.visit_date} {visit.time_in}</span>
            </div>
          )) : <p className="text-sm text-slate-500">{dashboard ? 'No visits have been recorded yet.' : 'Loading visits…'}</p>}
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft">
          <h3 className="mb-4 text-lg font-semibold text-slate-800">Latest Student Registrations</h3>
          {dashboard?.latest_students?.length ? dashboard.latest_students.map((student) => (
            <div key={student.student_number} className="flex justify-between gap-4 border-t border-slate-100 py-3 text-sm first:border-0">
              <div><p className="font-medium">{student.full_name}</p><p className="text-slate-500">{student.student_number} · {student.program}</p></div>
              <span className="whitespace-nowrap text-slate-500">{new Date(student.created_at).toLocaleDateString()}</span>
            </div>
          )) : <p className="text-sm text-slate-500">{dashboard ? 'No student records found.' : 'Loading students…'}</p>}
        </div>
      </div>
    </div>
  );
}

/** Displays student records and provides the add-student workflow. */
function StudentsPage() {
  const [students, setStudents] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [createdStudent, setCreatedStudent] = useState(null);
  const [editingStudent, setEditingStudent] = useState(null);
  const [viewingStudent, setViewingStudent] = useState(null);
  const [deletingStudentId, setDeletingStudentId] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [formData, setFormData] = useState({
    student_number: '',
    full_name: '',
    email: '',
    program: '',
    year_level: ''
  });

  /** Refreshes the student directory from the backend. */
  async function loadStudents() {
    setLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/students/list.php`, { credentials: 'include' });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load students');
      setStudents(result.data);
      setError('');
    } catch (loadError) {
      setError(loadError.message || 'Unable to load students');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadStudents();
  }, []);

  /** Creates a student or saves edits, then refreshes the directory. */
  async function handleSubmitStudent(event) {
    event.preventDefault();
    setError('');
    setSubmitting(true);

    if (editingStudent) {
      try {
        const response = await fetch(`${API_BASE_URL}/students/update.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ id: editingStudent.id, ...formData })
        });
        const result = await readApiResponse(response);
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update student');
        setShowForm(false);
        setEditingStudent(null);
        setFormData({ student_number: '', full_name: '', email: '', program: '', year_level: '' });
        await loadStudents();
        return;
      } catch (updateError) {
        setError(updateError.message || 'Unable to update student');
        setSubmitting(false);
        return;
      } finally {
        setSubmitting(false);
      }
    }

    try {
      const response = await fetch(`${API_BASE_URL}/students/create.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(formData)
      });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to create student');

      const { qr_identifier: qrIdentifier, ...student } = result.data;
      const qrDataUrl = await QRCode.toDataURL(qrIdentifier, { width: 320, margin: 2 });
      setCreatedStudent({
        ...student,
        qrDataUrl
      });
      setFormData({ student_number: '', full_name: '', email: '', program: '', year_level: '' });
      setShowForm(false);
      await loadStudents();
    } catch (createError) {
      setError(createError.message || 'Unable to create student');
    } finally {
      setSubmitting(false);
    }
  }

  /** Fetches a student's profile and visit history for the view dialog. */
  async function viewStudent(student) {
    setError('');
    try {
      const response = await fetch(`${API_BASE_URL}/students/show.php?id=${student.id}`, { credentials: 'include' });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load student profile');
      setViewingStudent(result.data);
    } catch (viewError) {
      setError(viewError.message || 'Unable to load student profile');
    }
  }

  /** Opens the student edit form with the current values filled in. */
  function editStudent(student) {
    setEditingStudent(student);
    setFormData({
      student_number: student.student_number,
      full_name: student.full_name,
      email: student.email,
      program: student.program,
      year_level: student.year_level
    });
    setShowForm(true);
    setError('');
  }

  /** Changes status, preserving visit history and requiring confirmation to deactivate. */
  async function setStudentStatus(student) {
    const nextStatus = student.account_status === 'active' ? 'inactive' : 'active';
    if (nextStatus === 'inactive' && !window.confirm(`Deactivate ${student.full_name}? Their visit history will be kept.`)) return;

    setError('');
    try {
      const response = await fetch(`${API_BASE_URL}/students/set-status.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id: student.id, account_status: nextStatus })
      });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to change student status');
      await loadStudents();
    } catch (statusError) {
      setError(statusError.message || 'Unable to change student status');
    }
  }

  /** Permanently deletes a student's account, QR code, and visit history. */
  async function deleteStudent(student) {
    const confirmed = window.confirm(
      `Permanently delete ${student.full_name} (${student.student_number})? This also deletes their QR code and all visit history. This cannot be undone.`
    );
    if (!confirmed) return;

    setError('');
    setDeletingStudentId(student.id);
    try {
      const response = await fetch(`${API_BASE_URL}/students/delete.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id: student.id })
      });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to delete student');
      if (viewingStudent?.id === student.id) setViewingStudent(null);
      await loadStudents();
    } catch (deleteError) {
      setError(deleteError.message || 'Unable to delete student');
    } finally {
      setDeletingStudentId(null);
    }
  }

  const filteredStudents = students.filter((student) => {
    const query = searchTerm.trim().toLowerCase();
    return !query || [student.student_number, student.full_name, student.email, student.program]
      .some((value) => value.toLowerCase().includes(query));
  });

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-sm uppercase tracking-[0.2em] text-slate-500">Student records</p>
          <h2 className="text-2xl font-bold text-slate-800">Student Management</h2>
        </div>
        <button type="button" onClick={() => {
          setShowForm((visible) => !visible);
          setEditingStudent(null);
          setFormData({ student_number: '', full_name: '', email: '', program: '', year_level: '' });
          setError('');
        }} className="rounded-xl bg-ssu-700 px-4 py-2 font-medium text-white">
          {showForm ? 'Cancel' : 'Add New Student'}
        </button>
      </div>

      {error && <p role="alert" className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}

      {createdStudent && (
        <div className="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
          <img src={createdStudent.qrDataUrl} alt={`QR code for ${createdStudent.full_name}`} className="h-28 w-28 bg-white p-1" />
          <div className="min-w-0 flex-1">
            <p className="font-semibold text-emerald-800">Student account created: {createdStudent.full_name}</p>
            <p className="text-sm text-emerald-700">
              {createdStudent.student_number} · QR code is ready to download.
            </p>
            <a href={createdStudent.qrDataUrl} download={`${createdStudent.student_number}-library-qr.png`} className="mt-2 inline-flex rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white">
              Download QR code
            </a>
          </div>
          <button type="button" onClick={() => setCreatedStudent(null)} aria-label="Dismiss QR code" className="rounded-lg px-3 py-2 text-emerald-800 hover:bg-emerald-100">Dismiss</button>
        </div>
      )}

      {showForm && (
        <form onSubmit={handleSubmitStudent} className="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
          <h3 className="mb-4 font-semibold">{editingStudent ? `Edit ${editingStudent.full_name}` : 'New student details'}</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            {[
              ['student_number', 'Student ID number', 'text'],
              ['full_name', 'Full name', 'text'],
              ['email', 'Email address', 'email'],
              ['program', 'Course / program', 'text'],
              ['year_level', 'Year level', 'text']
            ].map(([name, label, type]) => (
              <label key={name} className="block text-sm font-medium text-slate-700">
                {label}
                <input
                  name={name}
                  type={type}
                  required
                  value={formData[name]}
                  onChange={(event) => setFormData((current) => ({ ...current, [name]: event.target.value }))}
                  className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                />
              </label>
            ))}
          </div>
          <button type="submit" disabled={submitting} className="mt-4 rounded-xl bg-ssu-700 px-4 py-2 font-medium text-white disabled:opacity-60">
            {submitting ? 'Saving…' : editingStudent ? 'Save Changes' : 'Create student and QR code'}
          </button>
        </form>
      )}

      <label className="mb-4 block text-sm font-medium text-slate-700">
        Search students
        <input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} placeholder="Search by ID, name, email, or program" className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" />
      </label>

      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-slate-50 text-slate-600">
            <tr>
              <th className="px-4 py-3">Student ID</th>
              <th className="px-4 py-3">Full Name</th>
              <th className="px-4 py-3">Email</th>
              <th className="px-4 py-3">Course</th>
              <th className="px-4 py-3">Year</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredStudents.map((student) => (
              <tr key={student.id} className="border-t border-slate-200">
                <td className="px-4 py-3">{student.student_number}</td>
                <td className="px-4 py-3">{student.full_name}</td>
                <td className="px-4 py-3">{student.email}</td>
                <td className="px-4 py-3">{student.program}</td>
                <td className="px-4 py-3">{student.year_level}</td>
                <td className="px-4 py-3"><span className={`rounded-full px-2 py-1 text-xs font-semibold ${student.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'}`}>{student.account_status}</span></td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={() => viewStudent(student)} className="text-ssu-700">View</button>
                    <button type="button" onClick={() => editStudent(student)} className="text-ssu-700">Edit</button>
                    <button type="button" onClick={() => setStudentStatus(student)} className={student.account_status === 'active' ? 'text-red-700' : 'text-emerald-700'}>
                      {student.account_status === 'active' ? 'Deactivate' : 'Activate'}
                    </button>
                    <button type="button" onClick={() => deleteStudent(student)} disabled={deletingStudentId === student.id} className="text-red-700 disabled:opacity-50">
                      {deletingStudentId === student.id ? 'Deleting…' : 'Delete'}
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {!filteredStudents.length && !error && <tr><td colSpan="7" className="px-4 py-8 text-center text-slate-500">{loading ? 'Loading students…' : students.length ? 'No students match your search.' : 'No student records found.'}</td></tr>}
          </tbody>
        </table>
      </div>

      {viewingStudent && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" role="presentation" onClick={() => setViewingStudent(null)}>
          <section role="dialog" aria-modal="true" aria-labelledby="student-profile-title" onClick={(event) => event.stopPropagation()} className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-sm uppercase tracking-wider text-slate-500">Student profile</p>
                <h3 id="student-profile-title" className="text-2xl font-bold">{viewingStudent.full_name}</h3>
              </div>
              <button type="button" onClick={() => setViewingStudent(null)} className="rounded-lg border border-slate-200 px-3 py-1">Close</button>
            </div>
            <dl className="mt-5 grid gap-3 sm:grid-cols-2">
              {[
                ['Student ID', viewingStudent.student_number],
                ['Email', viewingStudent.email],
                ['Program', viewingStudent.program],
                ['Year level', viewingStudent.year_level],
                ['Account status', viewingStudent.account_status],
                ['QR status', viewingStudent.qr_status || 'Not generated']
              ].map(([label, value]) => <div key={label} className="rounded-lg bg-slate-50 p-3"><dt className="text-xs text-slate-500">{label}</dt><dd className="font-medium">{value}</dd></div>)}
            </dl>
            <h4 className="mt-6 font-semibold">Visit history</h4>
            {viewingStudent.visit_history?.length ? (
              <div className="mt-2 divide-y divide-slate-100">
                {viewingStudent.visit_history.map((visit, index) => <div key={`${visit.visit_date}-${visit.time_in}-${index}`} className="flex justify-between py-3 text-sm"><span>{visit.visit_date} · {visit.purpose}</span><span>{visit.time_in}</span></div>)}
              </div>
            ) : <p className="mt-2 text-sm text-slate-500">No visit history.</p>}
          </section>
        </div>
      )}
    </div>
  );
}

/** Lists active students and supports generating downloadable QR codes. */
function QrCodesPage() {
  const [students, setStudents] = useState([]);
  const [codes, setCodes] = useState({});
  const [loadingStudent, setLoadingStudent] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    fetch(`${API_BASE_URL}/students/list.php`, { credentials: 'include' })
      .then(async (response) => {
        const result = await readApiResponse(response);
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load students');
        setStudents(result.data);
      })
      .catch((loadError) => setError(loadError.message || 'Unable to load students'));
  }, []);

  /** Generates or replaces a student's QR identifier and image. */
  async function generateQr(student) {
    setError('');
    setLoadingStudent(student.id);
    try {
      const response = await fetch(`${API_BASE_URL}/students/regenerate-qr.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ student_id: student.id })
      });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to generate QR code');
      const dataUrl = await QRCode.toDataURL(result.data.qr_identifier, { width: 320, margin: 2 });
      setCodes((current) => ({ ...current, [student.id]: dataUrl }));
    } catch (generateError) {
      setError(generateError.message || 'Unable to generate QR code');
    } finally {
      setLoadingStudent(null);
    }
  }

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
      <h2 className="text-2xl font-bold text-slate-800">QR Code Management</h2>
      <p className="mt-2 text-sm text-slate-600">Generate a secure QR code for an active student. A regenerated code immediately revokes the previous code.</p>
      {error && <p role="alert" className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}
      <div className="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        {students.filter((student) => student.account_status === 'active').map((student) => (
          <div key={student.id} className="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center">
            {codes[student.id] ? (
              <img src={codes[student.id]} alt={`QR code for ${student.full_name}`} className="mx-auto mb-4 h-40 w-40 bg-white p-2" />
            ) : (
              <div className="mx-auto mb-4 flex h-40 w-40 items-center justify-center rounded-2xl bg-white text-ssu-700 shadow-sm ring-1 ring-slate-200">
                <QrCode size={52} />
              </div>
            )}
            <h3 className="font-semibold text-slate-800">{student.full_name}</h3>
            <p className="mt-1 text-sm text-slate-500">{student.student_number}</p>
            {codes[student.id] ? (
              <div className="mt-4 flex justify-center gap-2">
                <a
                  href={codes[student.id]}
                  download={`${student.student_number}-library-qr.png`}
                  className="inline-flex rounded-xl bg-ssu-700 px-4 py-2 text-sm font-medium text-white"
                >
                  Download PNG
                </a>
                <button
                  type="button"
                  disabled={loadingStudent === student.id}
                  onClick={() => generateQr(student)}
                  className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 disabled:opacity-60"
                >
                  Regenerate
                </button>
              </div>
            ) : (
              <button
                type="button"
                disabled={loadingStudent === student.id}
                onClick={() => generateQr(student)}
                className="mt-4 rounded-xl bg-ssu-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
              >
                {loadingStudent === student.id ? 'Generating…' : 'Generate QR'}
              </button>
            )}
          </div>
        ))}
        {!students.length && !error && <p className="text-sm text-slate-500">Loading students…</p>}
        {!students.length && error && <p className="text-sm text-slate-500">Student records are unavailable.</p>}
      </div>
    </div>
  );
}

/** Runs the camera scanner and submits decoded QR identifiers for validation. */
function ScannerPage() {
  const readerId = `qr-reader-${useId().replace(/:/g, '')}`;
  const [scanMessage, setScanMessage] = useState('Requesting laptop camera access…');
  const [scanState, setScanState] = useState('loading');
  const [purpose, setPurpose] = useState('STUDY');
  const [recentScans, setRecentScans] = useState([]);
  const [lastStudent, setLastStudent] = useState(null);
  const [cameras, setCameras] = useState([]);
  const [selectedCameraId, setSelectedCameraId] = useState('');
  const [cameraState, setCameraState] = useState('starting');
  const processingScan = useRef(false);
  const currentPurpose = useRef(purpose);
  const scannerRef = useRef(null);
  const readerRef = useRef(null);
  const activeRef = useRef(false);
  const cameraRequestRef = useRef(0);

  useEffect(() => {
    currentPurpose.current = purpose;
  }, [purpose]);

  /** Validates a decoded QR code and records its library visit. */
  const handleDecodedQr = async (decodedText) => {
    if (processingScan.current) return;
    processingScan.current = true;
    setScanState('loading');
    setScanMessage('Checking QR code with the library database…');
    setLastStudent(null);

    try {
      const response = await fetch(`${API_BASE_URL}/scanner/scan.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ qr_identifier: decodedText, purpose: currentPurpose.current })
      });
      const result = await readApiResponse(response);
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to record entry');
      if (!activeRef.current) return;

      const visit = {
        ...result.data.student,
        visit_date: result.data.visit_date,
        time_in: result.data.time_in,
        purpose: result.data.purpose
      };
      setLastStudent(result.data);
      setRecentScans((current) => [visit, ...current].slice(0, 5));
      setScanMessage(result.message);
      setScanState('success');
    } catch (scanError) {
      if (activeRef.current) {
        setScanMessage(scanError.message || 'Unable to validate this QR code');
        setScanState('error');
      }
    } finally {
      window.setTimeout(() => {
        processingScan.current = false;
      }, 1800);
    }
  };

  /** Starts the scanner on the selected laptop camera. */
  const startCamera = async (cameraId, requestId = ++cameraRequestRef.current) => {
    setCameraState('starting');
    setScanState('loading');
    setScanMessage('Starting laptop camera…');
    try {
      if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Camera access requires a secure page. Open this app on localhost or use HTTPS.');
      }
      if (!readerRef.current || !document.getElementById(readerId)) {
        throw new Error('The camera preview is not ready. Select Restart Camera and try again.');
      }

      if (scannerRef.current?.isScanning) {
        await scannerRef.current.stop();
      }
      if (scannerRef.current) {
        await scannerRef.current.clear();
      }
      if (!activeRef.current || requestId !== cameraRequestRef.current) return;

      const scanner = new Html5Qrcode(readerId, { verbose: false });
      scannerRef.current = scanner;
      await scanner.start(
        cameraId,
        { fps: 10, qrbox: { width: 260, height: 260 }, aspectRatio: 1 },
        handleDecodedQr,
        () => {}
      );

      if (!activeRef.current || requestId !== cameraRequestRef.current) {
        await scanner.stop();
        await scanner.clear();
        return;
      }

      if (activeRef.current) {
        setSelectedCameraId(cameraId);
        setCameraState('running');
        setScanState('idle');
        setScanMessage('Camera is live. Hold a student QR code in the frame.');
      }
    } catch (cameraError) {
      console.error('Unable to start laptop camera:', cameraError);
      if (activeRef.current && requestId === cameraRequestRef.current) {
        setCameraState('error');
        setScanState('error');
        const errorName = cameraError?.name || '';
        const errorMessage = typeof cameraError === 'string' ? cameraError : cameraError?.message;
        if (errorName === 'NotAllowedError' || errorName === 'PermissionDeniedError') {
          setScanMessage('Camera access denied. Allow camera permission in your browser, then select Restart Camera.');
        } else if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
          setScanMessage('No camera was found. Connect or enable a webcam, then restart the camera.');
        } else if (errorName === 'NotReadableError' || errorName === 'TrackStartError') {
          setScanMessage('The camera is busy or unavailable. Close other apps using the webcam and restart it.');
        } else {
          setScanMessage(errorMessage || 'Could not start the camera. Check browser permissions and try again.');
        }
      }
    }
  };

  /** Detects available cameras and starts the selected or preferred device. */
  const restartCamera = async () => {
    const requestId = ++cameraRequestRef.current;
    setCameraState('starting');
    setScanState('loading');
    setScanMessage('Finding laptop cameras…');
    try {
      if (!window.isSecureContext) {
        throw new Error(
          `Camera access is blocked at ${window.location.origin}. Open localhost on this laptop or use an HTTPS address for network testing.`
        );
      }
      const availableCameras = await Html5Qrcode.getCameras();
      if (!activeRef.current || requestId !== cameraRequestRef.current) return;
      if (!availableCameras.length) {
        throw new Error('No camera was found. Connect or enable a webcam, then try again.');
      }
      setCameras(availableCameras);
      const preferredCamera = availableCameras.find((camera) => /integrated|built.?in|webcam|front/i.test(camera.label));
      const cameraId = selectedCameraId && availableCameras.some((camera) => camera.id === selectedCameraId)
        ? selectedCameraId
        : (preferredCamera || availableCameras[0]).id;
      await startCamera(cameraId, requestId);
    } catch (cameraError) {
      console.error('Unable to enumerate laptop cameras:', cameraError);
      if (!activeRef.current || requestId !== cameraRequestRef.current) return;
      setCameraState('error');
      setScanState('error');
      setScanMessage(cameraError.name === 'NotAllowedError' || cameraError.name === 'PermissionDeniedError'
        ? 'Camera access denied. Allow camera permission in browser settings, then restart the camera.'
        : cameraError.message || 'Could not find a camera. Check that a webcam is connected.');
    }
  };

  useEffect(() => {
    activeRef.current = true;
    fetch(`${API_BASE_URL}/reports/visitor-logs.php`, { credentials: 'include' })
      .then(async (response) => {
        const result = await readApiResponse(response);
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load recent visits');
        if (activeRef.current) setRecentScans(result.data.slice(0, 5));
      })
      .catch((error) => {
        console.error('Unable to load recent scans:', error);
      });

    restartCamera();
    return () => {
      activeRef.current = false;
      cameraRequestRef.current += 1;
      const scanner = scannerRef.current;
      if (scanner?.isScanning) {
        scanner.stop()
          .then(() => scanner.clear())
          .catch((error) => console.error('Unable to stop camera scanner:', error));
      } else if (scanner) {
        scanner.clear().catch((error) => console.error('Unable to clear camera scanner:', error));
      }
    };
  }, []);

  return (
    <div className="grid gap-6 lg:grid-cols-[1.5fr_0.9fr]">
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-slate-500">Scan station</p>
            <h2 className="text-2xl font-bold text-slate-800">Library Entry Scanner</h2>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <label className="flex items-center gap-2 text-sm font-medium">
              Camera
              <select
                value={selectedCameraId}
                onChange={(event) => startCamera(event.target.value)}
                disabled={!cameras.length}
                className="max-w-48 rounded-lg border border-slate-200 bg-white px-3 py-2"
              >
                {cameras.map((camera, index) => (
                  <option key={camera.id} value={camera.id}>{camera.label || `Camera ${index + 1}`}</option>
                ))}
              </select>
            </label>
            <label className="flex items-center gap-2 text-sm font-medium">
              Purpose
              <select value={purpose} onChange={(event) => setPurpose(event.target.value)} className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <option value="STUDY">Study</option>
                <option value="BORROW_BOOK">Borrow book</option>
              </select>
            </label>
            <button type="button" onClick={restartCamera} className="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-medium">
              {cameraState === 'starting' ? 'Starting camera…' : 'Restart Camera'}
            </button>
          </div>
        </div>

        <div className="relative overflow-hidden rounded-2xl border-2 border-dashed border-ssu-300 bg-slate-100 p-4">
          <div id={readerId} ref={readerRef} className="mx-auto min-h-72 w-full max-w-3xl overflow-hidden rounded-2xl bg-slate-900" />
        </div>

        <div className="mt-4 flex items-center justify-between gap-3">
          <div role="status" className={`rounded-xl px-3 py-2 text-sm font-medium ${scanState === 'success' ? 'bg-emerald-100 text-emerald-700' : scanState === 'error' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700'}`}>
            {scanMessage}
          </div>
        </div>
        {lastStudent && <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
          <p className="font-semibold text-emerald-800">{lastStudent.student.full_name} · {lastStudent.student.student_number}</p>
          <p className="text-emerald-700">{lastStudent.student.program} · {lastStudent.student.year_level} · Time in {lastStudent.time_in}</p>
        </div>}
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
        <h3 className="text-lg font-semibold text-slate-800">Recent Successful Scans</h3>
        <div className="mt-4 space-y-3">
          {recentScans.map((visit, index) => (
            <div key={`${visit.student_number}-${visit.visit_date}-${visit.time_in}-${index}`} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <p className="font-medium text-slate-800">{visit.full_name}</p>
              <p className="text-sm text-slate-500">{visit.time_in} · {visit.purpose}</p>
            </div>
          ))}
          {!recentScans.length && <p className="text-sm text-slate-500">No visits recorded yet.</p>}
        </div>
      </div>
    </div>
  );
}

/** Loads and displays recent visitor log records from the backend. */
function LogsPage() {
  const [logs, setLogs] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    fetch(`${API_BASE_URL}/reports/visitor-logs.php`, { credentials: 'include' })
      .then(async (response) => {
        const result = await readApiResponse(response);
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load visitor logs');
        if (active) setLogs(result.data);
      })
      .catch((loadError) => {
        if (active) setError(loadError.message || 'Unable to load visitor logs');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => { active = false; };
  }, []);

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
      <h2 className="text-2xl font-bold text-slate-800">Visitor Logs</h2>
      {error && <p role="alert" className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}
      <div className="mt-4 overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-slate-50 text-slate-600">
            <tr>
              <th className="px-4 py-3">Student ID</th>
              <th className="px-4 py-3">Student Name</th>
              <th className="px-4 py-3">Program</th>
              <th className="px-4 py-3">Year Level</th>
              <th className="px-4 py-3">Visit Date</th>
              <th className="px-4 py-3">Time In</th>
              <th className="px-4 py-3">Purpose</th>
            </tr>
          </thead>
          <tbody>
            {logs.map((log, index) => (
              <tr key={`${log.student_number}-${log.visit_date}-${log.time_in}-${index}`} className="border-t border-slate-200">
                <td className="px-4 py-3">{log.student_number}</td>
                <td className="px-4 py-3">{log.full_name}</td>
                <td className="px-4 py-3">{log.program}</td>
                <td className="px-4 py-3">{log.year_level}</td>
                <td className="px-4 py-3">{log.visit_date}</td>
                <td className="px-4 py-3">{log.time_in}</td>
                <td className="px-4 py-3">{log.purpose}</td>
              </tr>
            ))}
            {!logs.length && !error && <tr><td colSpan="7" className="px-4 py-8 text-center text-slate-500">{loading ? 'Loading visitor records…' : 'No visitor logs have been recorded.'}</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/** Displays the reports and analytics section. */
function ReportsPage() {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
      <h2 className="text-2xl font-bold text-slate-800">Reports and Analytics</h2>
      <p className="mt-2 text-slate-600">Daily, monthly, and quarterly visitor statistics will appear here.</p>
    </div>
  );
}

/** Displays library system configuration settings. */
function SettingsPage() {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
      <h2 className="text-2xl font-bold text-slate-800">Settings</h2>
      <p className="mt-2 text-slate-600">Configure duplicate check-in rules, default visit purpose, and notification preferences.</p>
    </div>
  );
}

export default App;
