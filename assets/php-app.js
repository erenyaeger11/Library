(() => {
  const app = window.LibraryApp || {};

  document.querySelectorAll('.confirm-action').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Continue with this action?')) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('.edit-student').forEach((button) => {
    button.addEventListener('click', () => {
      const student = JSON.parse(button.dataset.student || '{}');
      const form = document.querySelector('#student-form form');
      if (!form) return;
      form.querySelector('[name="action"]').value = 'update_student';
      form.querySelector('[name="id"]')?.remove();
      const id = document.createElement('input');
      id.type = 'hidden';
      id.name = 'id';
      id.value = student.id;
      form.appendChild(id);
      for (const key of ['student_number', 'full_name', 'email', 'program', 'year_level']) {
        form.querySelector(`[name="${key}"]`).value = student[key] || '';
      }
      form.querySelector('.form-actions button').textContent = 'Save Changes';
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      form.querySelector('[name="full_name"]').focus();
    });
  });

  document.querySelectorAll('.generated-qr[data-qr-token]').forEach((container) => {
    const token = container.dataset.qrToken;
    if (typeof window.QRCode !== 'function') {
      container.textContent = 'QR library unavailable. Check your internet connection and reload.';
      return;
    }
    new window.QRCode(container, {
      text: token,
      width: 128,
      height: 128,
      correctLevel: window.QRCode.CorrectLevel.H
    });
    const downloadButton = container.closest('.qr-confirmation')?.querySelector('.download-qr');
    downloadButton?.addEventListener('click', () => {
      const image = container.querySelector('img');
      const canvas = container.querySelector('canvas');
      const dataUrl = image?.src || canvas?.toDataURL('image/png');
      if (!dataUrl) {
        window.alert('The QR code is not ready. Please wait and try again.');
        return;
      }
      const link = document.createElement('a');
      const studentNumber = container.closest('.qr-confirmation')?.querySelector('p')?.textContent?.split('·')[0]?.trim() || 'student';
      link.href = dataUrl;
      link.download = `${studentNumber}-library-qr.png`;
      link.click();
    });
  });

  if (!app.scannerPage) return;
  const message = document.getElementById('scan-message');
  const reader = document.getElementById('qr-reader');
  const cameraSelect = document.getElementById('camera-select');
  const purposeSelect = document.getElementById('scan-purpose');
  const restartButton = document.getElementById('restart-camera');
  const resultBox = document.getElementById('scan-result');
  const recentList = document.getElementById('recent-scans');
  let scanner;
  let busy = false;

  function setScanMessage(text, state = '') {
    message.textContent = text;
    message.className = `scan-message ${state}`;
  }

  async function handleQr(decodedText) {
    if (busy) return;
    busy = true;
    resultBox.hidden = true;
    setScanMessage('Checking QR code with the library database…');
    try {
      const response = await fetch(app.scanEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_identifier: decodedText, purpose: purposeSelect.value })
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to record entry.');
      const visit = payload.data;
      const student = visit.student;
      setScanMessage(payload.message || 'Access recorded successfully.', 'success');
      resultBox.innerHTML = '';
      const headline = document.createElement('strong');
      headline.textContent = `${student.full_name} · ${student.student_number}`;
      const details = document.createElement('div');
      details.textContent = `${student.program} · ${student.year_level} · Time in ${visit.time_in}`;
      resultBox.append(headline, details);
      resultBox.hidden = false;
      const recent = document.createElement('div');
      recent.className = 'recent-scan';
      const name = document.createElement('strong');
      name.textContent = student.full_name;
      const meta = document.createElement('span');
      meta.textContent = `${visit.time_in} · ${visit.purpose}`;
      recent.append(name, meta);
      recentList.prepend(recent);
      while (recentList.children.length > 5) recentList.lastElementChild.remove();
    } catch (error) {
      setScanMessage(error.message || 'Unable to validate this QR code.', 'error');
    } finally {
      window.setTimeout(() => { busy = false; }, 1800);
    }
  }

  async function startCamera(cameraId) {
    if (typeof window.Html5Qrcode !== 'function') {
      setScanMessage('Camera scanner library failed to load. Check your internet connection and reload.', 'error');
      return;
    }
    try {
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        throw new Error('Camera access requires HTTPS or localhost.');
      }
      if (scanner?.isScanning) await scanner.stop();
      if (scanner) await scanner.clear();
      scanner = new window.Html5Qrcode(reader.id, { verbose: false });
      setScanMessage('Starting laptop camera…');
      await scanner.start(cameraId, { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 }, handleQr, () => {});
      setScanMessage('Camera is live. Hold a student QR code in the frame.');
    } catch (error) {
      const detail = error?.name === 'NotAllowedError'
        ? 'Camera permission denied. Allow access in browser settings, then restart the camera.'
        : error.message || 'Could not start camera. Check browser permissions and webcam availability.';
      setScanMessage(detail, 'error');
    }
  }

  async function findCameras() {
    try {
      if (typeof window.Html5Qrcode !== 'function') {
        throw new Error('Camera scanner library failed to load. Check your internet connection.');
      }
      if (!window.isSecureContext) throw new Error('Camera access requires HTTPS or localhost.');
      setScanMessage('Finding laptop cameras…');
      const cameras = await window.Html5Qrcode.getCameras();
      if (!cameras.length) throw new Error('No camera was found. Connect or enable a webcam, then try again.');
      cameraSelect.replaceChildren(...cameras.map((camera, index) => {
        const option = document.createElement('option');
        option.value = camera.id;
        option.textContent = camera.label || `Camera ${index + 1}`;
        return option;
      }));
      cameraSelect.disabled = false;
      const preferred = cameras.find((camera) => /integrated|built.?in|webcam|front/i.test(camera.label)) || cameras[0];
      cameraSelect.value = preferred.id;
      await startCamera(preferred.id);
    } catch (error) {
      setScanMessage(error.message || 'Unable to find a camera.', 'error');
    }
  }

  cameraSelect.addEventListener('change', () => startCamera(cameraSelect.value));
  restartButton.addEventListener('click', findCameras);
  window.addEventListener('beforeunload', () => {
    if (scanner?.isScanning) scanner.stop().catch((error) => console.error('Unable to stop scanner:', error));
  });
  findCameras();
})();
