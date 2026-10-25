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
    // Turns the token into a QR image.
    new window.QRCode(container, {
      text: token,
      width: 256,
      height: 256,
      correctLevel: window.QRCode.CorrectLevel.H
    });
    const downloadButton = container.closest('.qr-confirmation')?.querySelector('.download-qr');
    downloadButton?.addEventListener('click', () => {
      const image = container.querySelector('img');
      const qrCanvas = container.querySelector('canvas');
      const qrSource = qrCanvas || image;
      if (!qrSource || (image && qrSource === image && !image.complete)) {
        window.alert('The QR code is not ready. Please wait and try again.');
        return;
      }
      const outputCanvas = document.createElement('canvas');
      outputCanvas.width = 284;
      outputCanvas.height = 284;
      const context = outputCanvas.getContext('2d');
      if (!context) {
        window.alert('The QR code could not be prepared for download. Please try again.');
        return;
      }
      context.fillStyle = '#fff';
      context.fillRect(0, 0, outputCanvas.width, outputCanvas.height);
      context.drawImage(qrSource, 14, 14, 256, 256);
      const link = document.createElement('a');
      const studentNumber = container.closest('.qr-confirmation')?.querySelector('p')?.textContent?.split('·')[0]?.trim() || 'student';
      link.href = outputCanvas.toDataURL('image/png');
      link.download = `${studentNumber}-library-qr.png`;
      link.click();
    }); // End: QR download
  }); // End: QR generator

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
      // Sends the scanned code to the server.
      const response = await fetch(app.scanEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_identifier: decodedText, purpose: purposeSelect.value })
      });
      let payload;
      try {
        payload = await response.json();
      } catch {
        throw new Error('The server returned an invalid response. Refresh the page and try again.');
      }
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
  } // End: handle scanned code

  async function startCamera(cameraId) {
    if (typeof window.Html5Qrcode !== 'function') {
      setScanMessage('Camera scanner library failed to load. Check your internet connection and reload.', 'error');
      return;
    }
    try {
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        throw new Error('Camera access requires HTTPS or localhost.');
      }
      // Starts the selected camera and reads QR codes.
      if (scanner?.isScanning) await scanner.stop();
      if (scanner) await scanner.clear();
      scanner = new window.Html5Qrcode(reader.id, {
        verbose: false,
        formatsToSupport: [window.Html5QrcodeSupportedFormats.QR_CODE]
      });
      setScanMessage('Starting camera…');
      await scanner.start(cameraId, {
        fps: 10,
        qrbox: (viewfinderWidth, viewfinderHeight) => {
          const size = Math.floor(Math.min(viewfinderWidth, viewfinderHeight, 300) * 0.8);
          return { width: size, height: size };
        }
      }, handleQr, () => {});
      setScanMessage('Camera is live. Hold a student QR code in the frame.');
    } catch (error) {
      const detail = error?.name === 'NotAllowedError'
        ? 'Camera permission denied. Allow camera access in browser settings, then restart the camera.'
        : error?.name === 'NotReadableError'
          ? 'The camera is busy in another app. Close other apps using it, then restart the camera.'
          : error?.name === 'OverconstrainedError'
            ? 'This camera does not support the requested settings. Select another camera and try again.'
            : error.message || 'Could not start camera. Check browser permissions and webcam availability.';
      setScanMessage(detail, 'error');
    }
  } // End: start camera

  async function findCameras() {
    try {
      if (typeof window.Html5Qrcode !== 'function') {
        throw new Error('Camera scanner library failed to load. Check your internet connection.');
      }
      if (!window.isSecureContext) throw new Error('Camera access requires HTTPS or localhost.');
      setScanMessage('Finding cameras…');
      const cameras = await window.Html5Qrcode.getCameras();
      if (!cameras.length) throw new Error('No camera was found. Connect or enable a webcam, then try again.');
      cameraSelect.replaceChildren(...cameras.map((camera, index) => {
        const option = document.createElement('option');
        option.value = camera.id;
        option.textContent = camera.label || `Camera ${index + 1}`;
        return option;
      }));
      cameraSelect.disabled = false;
      const preferred = cameras.find((camera) => /back|rear|environment/i.test(camera.label))
        || cameras.find((camera) => /integrated|built.?in|webcam|front/i.test(camera.label))
        || cameras[0];
      cameraSelect.value = preferred.id;
      await startCamera(preferred.id);
    } catch (error) {
      setScanMessage(error.message || 'Unable to find a camera.', 'error');
    }
  } // End: find cameras

  cameraSelect.addEventListener('change', () => startCamera(cameraSelect.value));
  restartButton.addEventListener('click', findCameras);
  window.addEventListener('beforeunload', () => {
    if (scanner?.isScanning) scanner.stop().catch((error) => console.error('Unable to stop scanner:', error));
  });
  findCameras();
})(); // End: app script
