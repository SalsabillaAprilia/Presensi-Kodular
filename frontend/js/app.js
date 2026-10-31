// ========================================================
// Smart Attendance – Mobile Client (Guru & Pegawai)
// Geofencing GPS + Pop-Up Kamera Selfie Real-Time
// ========================================================

const API_BASE = '../api';

// ---- State Aplikasi ----
let currentUser = null;
let currentLat = null;
let currentLng = null;
let currentJarak = null;
let isWithinRadius = false;
let activeTipe = 'masuk'; // 'masuk' atau 'pulang'
let snapshotBase64 = null;
let kameraStream = null;

let map = null;
let markerUser = null;
let markerSekolah = null;
let circleRadius = null;
let jamTimer = null;

// Pengaturan default (akan ditimpa oleh data dari database)
let SEKOLAH_LAT = -6.2088;
let SEKOLAH_LNG = 106.8456;
let MAX_RADIUS = 150;

// ========================================================
// 1. INISIALISASI & LIFECYCLE
// ========================================================
document.addEventListener('DOMContentLoaded', async () => {
  // Ambil pengaturan sekolah & geofencing dari server
  await muatPengaturanSekolah();

  // Cek validitas sesi login (sinkronisasi Server PHP & Local Storage)
  await periksaSesiLogin();

  // Jalankan jam digital real-time
  mulaiJamDigital();
});

async function periksaSesiLogin() {
  try {
    // 1. Validasi sesi aktif langsung ke backend PHP
    const res = await fetchJSON(`${API_BASE}/check_session.php`);
    if (res && res.status === 'success' && res.data && res.data.logged_in && res.data.user) {
      currentUser = res.data.user;
      localStorage.setItem('absensi_pegawai_user', JSON.stringify(currentUser));
      sessionStorage.setItem('absensi_pegawai_user', JSON.stringify(currentUser));
      tampilkanDashboard();
      return;
    }
  } catch (err) {
    console.warn('Verifikasi sesi server via jaringan tertunda:', err);
  }

  // 2. Fallback: Cek data tersimpan di sessionStorage / localStorage
  const savedUser = sessionStorage.getItem('absensi_pegawai_user') || localStorage.getItem('absensi_pegawai_user');
  if (savedUser) {
    try {
      currentUser = JSON.parse(savedUser);
      tampilkanDashboard();
      return;
    } catch (_) {
      // Data rusak
    }
  }

  // 3. Jika tidak ada sesi valid
  currentUser = null;
  localStorage.removeItem('absensi_pegawai_user');
  sessionStorage.removeItem('absensi_pegawai_user');
  tampilkanHalaman('page-login');
}

async function muatPengaturanSekolah() {
  try {
    const res = await fetchJSON(`${API_BASE}/settings.php`);
    if (res.status === 'success' && res.data) {
      if (res.data.kampus_lat?.value) SEKOLAH_LAT = parseFloat(res.data.kampus_lat.value);
      if (res.data.kampus_lng?.value) SEKOLAH_LNG = parseFloat(res.data.kampus_lng.value);
      if (res.data.max_radius?.value) MAX_RADIUS = parseInt(res.data.max_radius.value);
    }
  } catch (e) {
    console.warn('Gagal memuat setting sekolah, memakai nilai default:', e);
  }
}

function tampilkanHalaman(pageId) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById(pageId);
  if (target) target.classList.add('active');
}

// ========================================================
// 2. JAM DIGITAL REAL-TIME
// ========================================================
function mulaiJamDigital() {
  updateTampilanJam();
  if (jamTimer) clearInterval(jamTimer);
  jamTimer = setInterval(updateTampilanJam, 1000);
}

function updateTampilanJam() {
  const now = new Date();
  const elTgl = document.getElementById('jam-tanggal');
  const elWkt = document.getElementById('jam-waktu');

  if (elTgl) {
    elTgl.textContent = now.toLocaleDateString('id-ID', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    });
  }
  if (elWkt) {
    elWkt.textContent = now.toLocaleTimeString('id-ID', { hour12: false }) + ' WIB';
  }
}

// ========================================================
// 3. AUTHENTICATION (LOGIN & LOGOUT)
// ========================================================
function togglePassword() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

async function doLogin() {
  const nipEl = document.getElementById('nip');
  const passEl = document.getElementById('password');
  const alertEl = document.getElementById('login-alert');
  const btnText = document.getElementById('login-btn-text');
  const submitBtn = document.getElementById('btn-login-submit');

  hideAlert(alertEl);

  const nip = nipEl.value.trim();
  const password = passEl.value.trim();

  if (!nip || !password) {
    showAlert(alertEl, 'NIP dan Kata Sandi wajib diisi', 'error');
    return;
  }

  btnText.innerHTML = '<span class="spinner"></span> Memverifikasi…';
  submitBtn.disabled = true;

  try {
    const res = await postJSON(`${API_BASE}/login.php`, { nip, password });
    if (res.status === 'success' && res.data) {
      currentUser = res.data;
      localStorage.setItem('absensi_pegawai_user', JSON.stringify(currentUser));
      sessionStorage.setItem('absensi_pegawai_user', JSON.stringify(currentUser));
      tampilkanDashboard();
    } else {
      showAlert(alertEl, res.message || 'NIP atau kata sandi tidak cocok', 'error');
    }
  } catch (e) {
    showAlert(alertEl, 'Gagal menghubungi server: ' + e.message, 'error');
  } finally {
    btnText.textContent = 'Masuk';
    submitBtn.disabled = false;
  }
}

async function doLogout() {
  if (!confirm('Apakah Anda yakin ingin keluar dari sistem presensi?')) return;
  tutupModalKamera();

  try {
    await postJSON(`${API_BASE}/logout.php`, {});
  } catch (err) {
    console.warn('Gagal memproses logout server:', err);
  }

  localStorage.removeItem('absensi_pegawai_user');
  sessionStorage.removeItem('absensi_pegawai_user');
  currentUser = null;
  tampilkanHalaman('page-login');
  const nipEl = document.getElementById('nip');
  const passEl = document.getElementById('password');
  if (nipEl) nipEl.value = '';
  if (passEl) passEl.value = '';
}

// ========================================================
// 4. DASHBOARD PEGAWAI & KONTROL TAB
// ========================================================
function tampilkanDashboard() {
  tampilkanHalaman('page-home');

  // Render Profil Topbar
  document.getElementById('user-nama').textContent = currentUser.nama || 'Pegawai Sekolah';
  const roleLabel = currentUser.role === 'admin' ? 'Administrator TU' : (currentUser.jabatan || 'Guru / Tenaga Pendidik');
  document.getElementById('user-meta').textContent = `${currentUser.nip} • ${roleLabel}`;

  const avatarSvg = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
    </svg>
  `;
  document.getElementById('user-avatar-icon').innerHTML = avatarSvg;

  // Set default tab
  switchTab('presensi');

  // Inisialisasi Peta & Deteksi Lokasi GPS
  initMap();
  refreshLokasi();

  // Cek Status Presensi Hari Ini
  cekStatusPresensiHariIni();
}

function switchTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(ctn => ctn.classList.remove('active'));

  if (tabId === 'presensi') {
    document.getElementById('btn-tab-presensi').classList.add('active');
    document.getElementById('tab-presensi').classList.add('active');
    if (map) setTimeout(() => map.invalidateSize(), 200);
  } else if (tabId === 'riwayat') {
    document.getElementById('btn-tab-riwayat').classList.add('active');
    document.getElementById('tab-riwayat').classList.add('active');
    loadRiwayatPresensi();
  }
}

// ========================================================
// 5. STATUS PRESENSI HARI INI & ALUR BERTAHAP
// ========================================================
async function cekStatusPresensiHariIni() {
  if (!currentUser) return;

  const badgeEl = document.getElementById('presensi-status-badge');
  const wrapMasuk = document.getElementById('action-masuk-wrap');
  const wrapPulang = document.getElementById('action-pulang-wrap');
  const wrapSelesai = document.getElementById('action-selesai-wrap');
  const wrapIzin = document.getElementById('action-izin-info-wrap');

  try {
    const res = await fetchJSON(`${API_BASE}/absensi.php?user_id=${currentUser.user_id}`);
    if (res.status === 'success' && res.data) {
      const statusHarian = res.data.status_hari_ini;
      const detail = res.data.detail_hari_ini;

      // Sembunyikan semua tombol aksi dulu
      wrapMasuk.style.display = 'none';
      wrapPulang.style.display = 'none';
      wrapSelesai.style.display = 'none';
      if (wrapIzin) wrapIzin.style.display = 'none';

      if (statusHarian === 'belum_masuk') {
        badgeEl.className = 'status-badge status-badge-idle';
        badgeEl.innerHTML = '<span class="status-dot"></span><span>Belum Absen Masuk</span>';
        wrapMasuk.style.display = 'block';
        evaluasiTombolAksi();
      }
      else if (statusHarian === 'izin' || statusHarian === 'sakit') {
        const isSakit = statusHarian === 'sakit';
        const labelText = isSakit ? 'Sakit' : 'Izin';
        badgeEl.className = 'status-badge ' + (isSakit ? 'status-badge-terlambat' : 'status-badge-idle');
        badgeEl.innerHTML = `<span class="status-dot"></span><span>${labelText}</span>`;

        const titleEl = document.getElementById('izin-info-title');
        const descEl = document.getElementById('izin-info-text');
        if (titleEl) titleEl.textContent = `Keterangan ${labelText} Tercatat`;
        if (descEl) {
          const jamMsk = detail && detail.jam_masuk ? detail.jam_masuk.substr(0, 5) : '–';
          const ket = detail && detail.keterangan ? detail.keterangan : '–';
          descEl.textContent = `Status: ${labelText.toUpperCase()} (Tercatat pukul ${jamMsk} WIB). Keterangan: "${ket}".`;
        }
        if (wrapIzin) wrapIzin.style.display = 'block';
      }
      else if (statusHarian === 'sudah_masuk') {
        const isHadir = detail && detail.status === 'hadir';
        const jamMsk = detail ? detail.jam_masuk.substr(0, 5) : '–';

        badgeEl.className = 'status-badge ' + (isHadir ? 'status-badge-hadir' : 'status-badge-terlambat');
        badgeEl.innerHTML = `<span class="status-dot"></span><span>${isHadir ? 'Hadir' : 'Terlambat'} (Masuk: ${jamMsk} WIB)</span>`;

        document.getElementById('detail-jam-masuk-text').textContent =
          `Tercatat pukul ${jamMsk} WIB dengan status: ${detail.status.toUpperCase()} (Jarak: ${detail.jarak_masuk ?? 0}m).`;

        // HANYA MUNCULKAN TOMBOL PULANG!
        wrapPulang.style.display = 'block';
        evaluasiTombolAksi();
      }
      else if (statusHarian === 'sudah_pulang') {
        const jamMsk = detail ? detail.jam_masuk.substr(0, 5) : '–';
        const jamPlg = detail ? detail.jam_pulang.substr(0, 5) : '–';

        badgeEl.className = 'status-badge status-badge-pulang';
        badgeEl.innerHTML = `<span class="status-dot"></span><span>Selesai (Masuk ${jamMsk} • Pulang ${jamPlg})</span>`;

        document.getElementById('detail-selesai-text').textContent =
          `Presensi Masuk (${jamMsk} WIB) dan Presensi Pulang (${jamPlg} WIB) telah sukses tercatat. Terima kasih atas dedikasi Anda hari ini!`;

        wrapSelesai.style.display = 'block';
      }
    }
  } catch (e) {
    console.error('Gagal mengecek status presensi:', e);
  }
}

function evaluasiTombolAksi() {
  const btnMasuk = document.getElementById('btn-trigger-masuk');
  const btnPulang = document.getElementById('btn-trigger-pulang');

  if (btnMasuk) {
    btnMasuk.disabled = !isWithinRadius;
  }
  if (btnPulang) {
    btnPulang.disabled = !isWithinRadius;
  }
}

// ========================================================
// 6. GEOFENCING GPS & PETA LEAFLET
// ========================================================
function initMap() {
  if (map) return;

  map = L.map('map', { zoomControl: false }).setView([SEKOLAH_LAT, SEKOLAH_LNG], 16);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19
  }).addTo(map);

  // Marker Titik Sekolah
  markerSekolah = L.marker([SEKOLAH_LAT, SEKOLAH_LNG], {
    icon: L.divIcon({
      className: '',
      html: `
        <div style="width:36px; height:36px; background:#0a192f; border:2px solid #d4af37; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#d4af37; box-shadow:0 4px 14px rgba(0,0,0,0.35)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 21h18M3 10h18M5 10v11M19 10v11M12 3l9 7H3l9-7zM12 14v4M9 14v4M15 14v4"/>
          </svg>
        </div>
      `,
      iconSize: [36, 36],
      iconAnchor: [18, 18]
    })
  }).addTo(map).bindPopup('<b>Lokasi Sekolah (SMK Global Nusantara)</b>');

  // Lingkaran Radius Sekolah
  circleRadius = L.circle([SEKOLAH_LAT, SEKOLAH_LNG], {
    radius: MAX_RADIUS,
    color: '#2563eb',
    fillColor: '#3b82f6',
    fillOpacity: 0.15,
    weight: 2
  }).addTo(map);
}

function refreshLokasi() {
  const coordText = document.getElementById('gps-koordinat-text');
  const jarakText = document.getElementById('gps-jarak-text');
  const statusBox = document.getElementById('gps-status-box');
  const statusIcn = document.getElementById('gps-status-icon');
  const statusTxt = document.getElementById('gps-status-text');

  coordText.textContent = 'Mencari GPS…';
  jarakText.textContent = '–';
  statusBox.className = 'gps-status-box gps-out-radius';
  statusIcn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
  statusTxt.textContent = 'Meminta izin & membaca koordinat GPS…';

  if (!navigator.geolocation) {
    statusTxt.textContent = 'Browser ini tidak mendukung fitur Geolocation/GPS.';
    coordText.textContent = 'Tidak didukung';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    pos => {
      currentLat = pos.coords.latitude;
      currentLng = pos.coords.longitude;

      currentJarak = hitungJarakHaversine(currentLat, currentLng, SEKOLAH_LAT, SEKOLAH_LNG);
      isWithinRadius = currentJarak <= MAX_RADIUS;

      coordText.textContent = `${currentLat.toFixed(6)}, ${currentLng.toFixed(6)}`;
      jarakText.textContent = `${Math.round(currentJarak)} m (Maks: ${MAX_RADIUS} m)`;

      if (isWithinRadius) {
        statusBox.className = 'gps-status-box gps-in-radius';
        statusIcn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        statusTxt.textContent = `Dalam Radius Sekolah (Jarak: ${Math.round(currentJarak)} meter)`;
      } else {
        statusBox.className = 'gps-status-box gps-out-radius';
        statusIcn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        statusTxt.textContent = `Di Luar Radius Sekolah (Jarak: ${Math.round(currentJarak)}m / Maks ${MAX_RADIUS}m)`;
      }

      // Update marker user di peta
      updateMapUserPosition(currentLat, currentLng);
      evaluasiTombolAksi();
    },
    err => {
      let pesan = 'Gagal mendeteksi lokasi.';
      if (err.code === 1) pesan = 'Izin lokasi GPS ditolak. Silakan aktifkan izin lokasi di browser.';
      if (err.code === 2) pesan = 'Sinyal GPS tidak tersedia saat ini.';
      if (err.code === 3) pesan = 'Waktu permintaan GPS habis (timeout).';

      statusBox.className = 'gps-status-box gps-out-radius';
      statusIcn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
      statusTxt.textContent = pesan;
      coordText.textContent = 'Error';
    },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
  );
}

function updateMapUserPosition(lat, lng) {
  if (!map) return;

  if (markerUser) {
    markerUser.setLatLng([lat, lng]);
  } else {
    markerUser = L.marker([lat, lng], {
      icon: L.divIcon({
        className: '',
        html: `
          <div style="width:34px; height:34px; background:#2563eb; border:2.5px solid #ffffff; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#ffffff; box-shadow:0 4px 14px rgba(37,99,235,0.45)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
        `,
        iconSize: [34, 34],
        iconAnchor: [17, 17]
      })
    }).addTo(map).bindPopup('<b>Posisi Anda Saat Ini</b>');
  }

  const bounds = L.latLngBounds([[lat, lng], [SEKOLAH_LAT, SEKOLAH_LNG]]);
  map.fitBounds(bounds, { padding: [35, 35] });
}

function hitungJarakHaversine(lat1, lon1, lat2, lon2) {
  const R = 6371000; // Radius bumi dalam meter
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

// ========================================================
// 7. MODAL FOTO POP-UP (KAMERA SELFIE)
// ========================================================
async function bukaModalKamera(tipe) {
  const alertEl = document.getElementById('presensi-alert');
  hideAlert(alertEl);

  if (!isWithinRadius) {
    showAlert(alertEl, `Anda berada di luar area sekolah (${Math.round(currentJarak)}m). Tidak dapat melakukan absen.`, 'error');
    return;
  }

  activeTipe = tipe;
  snapshotBase64 = null;

  const modalTitle = document.getElementById('modal-kamera-title');
  modalTitle.textContent = tipe === 'masuk' ? 'Foto Presensi Masuk' : 'Foto Presensi Pulang';

  // Tampilkan modal overlay
  document.getElementById('modal-kamera').classList.add('active');

  // Reset tampilan kontrol kamera
  document.getElementById('kamera-video').style.display = 'block';
  document.getElementById('kamera-snapshot').style.display = 'none';
  document.getElementById('kamera-controls-live').style.display = 'flex';
  document.getElementById('kamera-controls-preview').style.display = 'none';
  const badgeLbl = document.getElementById('camera-badge-label');
  if (badgeLbl) badgeLbl.textContent = 'Kamera Live';
  hideAlert(document.getElementById('modal-camera-alert'));

  // Request stream kamera depan
  await aktifkanKameraDepan();
}

async function aktifkanKameraDepan() {
  const alertModal = document.getElementById('modal-camera-alert');
  hideAlert(alertModal);

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    showAlert(alertModal, 'Browser ini tidak mendukung akses kamera langsung (MediaDevices). Pastikan menggunakan HTTPS atau Localhost.', 'error');
    return;
  }

  try {
    kameraStream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: 'user',
        width: { ideal: 640 },
        height: { ideal: 480 }
      },
      audio: false
    });
    const video = document.getElementById('kamera-video');
    video.srcObject = kameraStream;
  } catch (err) {
    let msg = 'Gagal mengakses kamera: ' + err.message;
    if (err.name === 'NotAllowedError') msg = 'Izin kamera ditolak. Berikan izin kamera di pengaturan browser Anda.';
    if (err.name === 'NotFoundError') msg = 'Kamera depan tidak ditemukan pada perangkat ini.';
    showAlert(alertModal, msg, 'error');
  }
}

function jepretFoto() {
  const video = document.getElementById('kamera-video');
  const snapshot = document.getElementById('kamera-snapshot');
  const canvas = document.getElementById('kamera-canvas');

  canvas.width = video.videoWidth || 640;
  canvas.height = video.videoHeight || 480;

  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  snapshotBase64 = canvas.toDataURL('image/jpeg', 0.85);

  // Tampilkan snapshot preview
  snapshot.src = snapshotBase64;
  snapshot.style.display = 'block';
  video.style.display = 'none';

  // Ganti kontrol tombol
  document.getElementById('kamera-controls-live').style.display = 'none';
  document.getElementById('kamera-controls-preview').style.display = 'flex';
  const badgeLbl = document.getElementById('camera-badge-label');
  if (badgeLbl) badgeLbl.textContent = 'Hasil Foto';
}

function ulangiFoto() {
  snapshotBase64 = null;
  document.getElementById('kamera-snapshot').style.display = 'none';
  document.getElementById('kamera-video').style.display = 'block';

  document.getElementById('kamera-controls-live').style.display = 'flex';
  document.getElementById('kamera-controls-preview').style.display = 'none';
  const badgeLbl = document.getElementById('camera-badge-label');
  if (badgeLbl) badgeLbl.textContent = 'Kamera Live';
}

function tutupModalKamera() {
  if (kameraStream) {
    kameraStream.getTracks().forEach(track => track.stop());
    kameraStream = null;
  }
  document.getElementById('modal-kamera').classList.remove('active');
}

// ========================================================
// 8. PENGIRIMAN PRESENSI (ABSEN MASUK & PULANG)
// ========================================================
async function kirimPresensi() {
  const alertModal = document.getElementById('modal-camera-alert');
  const btnSubmit = document.getElementById('btn-submit-absensi');
  const btnText = document.getElementById('btn-submit-text');

  if (!snapshotBase64) {
    showAlert(alertModal, 'Foto selfie belum diambil.', 'error');
    return;
  }

  btnSubmit.disabled = true;
  btnText.innerHTML = '<span class="spinner"></span> Mengirim…';

  try {
    const payload = {
      user_id: currentUser.user_id,
      tipe: activeTipe,
      latitude: currentLat,
      longitude: currentLng,
      foto: snapshotBase64
    };

    const res = await postJSON(`${API_BASE}/absensi.php`, payload);

    if (res.status === 'success') {
      tutupModalKamera();
      const alertEl = document.getElementById('presensi-alert');
      showAlert(alertEl, `${res.message} (Pukul ${res.data.jam} WIB)`, 'success');

      // Sinkronisasi ulang status hari ini
      await cekStatusPresensiHariIni();
    } else {
      showAlert(alertModal, res.message || 'Gagal menyimpan presensi', 'error');
    }
  } catch (err) {
    showAlert(alertModal, 'Terjadi kesalahan: ' + err.message, 'error');
  } finally {
    btnSubmit.disabled = false;
    btnText.textContent = 'Kirim Presensi';
  }
}

// ========================================================
// 8.5. PENGAJUAN IZIN / SAKIT MANDIRI (BEBAS GEOFENCING)
// ========================================================
let selectedTipeIzin = 'sakit';

function bukaModalIzin() {
  selectedTipeIzin = 'sakit';
  pilihTipeIzin('sakit');
  const ketEl = document.getElementById('keterangan-izin');
  if (ketEl) ketEl.value = '';
  hideAlert(document.getElementById('izin-alert'));
  const modal = document.getElementById('modal-izin');
  if (modal) modal.classList.add('active');
}

function tutupModalIzin() {
  const modal = document.getElementById('modal-izin');
  if (modal) modal.classList.remove('active');
}

function pilihTipeIzin(tipe) {
  selectedTipeIzin = tipe;
  const radioSakit = document.getElementById('radio-sakit');
  const radioIzin = document.getElementById('radio-izin');
  const lblSakit = document.getElementById('label-radio-sakit');
  const lblIzin = document.getElementById('label-radio-izin');

  if (tipe === 'sakit') {
    if (radioSakit) radioSakit.checked = true;
    if (lblSakit) lblSakit.classList.add('active');
    if (lblIzin) lblIzin.classList.remove('active');
  } else {
    if (radioIzin) radioIzin.checked = true;
    if (lblIzin) lblIzin.classList.add('active');
    if (lblSakit) lblSakit.classList.remove('active');
  }
}

async function kirimPengajuanIzin() {
  const alertEl = document.getElementById('izin-alert');
  const ketEl = document.getElementById('keterangan-izin');
  const submitBtn = document.getElementById('btn-submit-izin');
  const btnText = document.getElementById('btn-submit-izin-text');

  hideAlert(alertEl);

  const keterangan = ketEl ? ketEl.value.trim() : '';
  if (!keterangan) {
    showAlert(alertEl, 'Mohon cantumkan alasan / keterangan pengajuan ' + selectedTipeIzin + '.', 'error');
    if (ketEl) ketEl.focus();
    return;
  }

  if (submitBtn) submitBtn.disabled = true;
  if (btnText) btnText.innerHTML = '<span class="spinner"></span> Mengirim…';

  try {
    const payload = {
      user_id: currentUser ? currentUser.user_id : null,
      tipe: selectedTipeIzin,
      keterangan: keterangan
    };

    const res = await postJSON(`${API_BASE}/absensi.php`, payload);
    if (res.status === 'success') {
      tutupModalIzin();
      showAlert(document.getElementById('presensi-alert'), res.message || 'Pengajuan berhasil dikirimkan.', 'success');
      await cekStatusPresensiHariIni();
      await loadRiwayatPresensi();
    } else {
      showAlert(alertEl, res.message || 'Gagal mengirim pengajuan.', 'error');
    }
  } catch (err) {
    showAlert(alertEl, 'Terjadi kesalahan: ' + err.message, 'error');
  } finally {
    if (submitBtn) submitBtn.disabled = false;
    if (btnText) btnText.textContent = 'Kirim Pengajuan';
  }
}

// ========================================================
// 9. RIWAYAT PRESENSI & MODAL ZOOM FOTO
// ========================================================
async function loadRiwayatPresensi() {
  if (!currentUser) return;

  const container = document.getElementById('riwayat-container');
  container.innerHTML = '<div class="empty-state"><span class="spinner" style="border-color:#94a3b8; border-top-color:#2563eb"></span> Memuat riwayat…</div>';

  try {
    const res = await fetchJSON(`${API_BASE}/absensi.php?user_id=${currentUser.user_id}`);
    if (res.status === 'success' && res.data) {
      const ringkasan = res.data.ringkasan;
      const riwayat = res.data.riwayat;

      // Update ringkasan
      document.getElementById('sum-hadir').textContent = ringkasan.hadir || 0;
      document.getElementById('sum-terlambat').textContent = ringkasan.terlambat || 0;
      document.getElementById('sum-total').textContent = (ringkasan.hadir || 0) + (ringkasan.terlambat || 0) + (ringkasan.izin || 0) + (ringkasan.sakit || 0);

      if (!riwayat || riwayat.length === 0) {
        container.innerHTML = '<div class="empty-state">Belum ada riwayat kehadiran tercatat.</div>';
        return;
      }

      container.innerHTML = riwayat.map(item => {
        const tglFormatted = new Date(item.tanggal).toLocaleDateString('id-ID', {
          weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
        });

        const st = (item.status || 'hadir').toLowerCase();
        let badgeCls = 'status-badge-hadir';
        let statusLabel = 'Hadir';

        if (st === 'terlambat') {
          badgeCls = 'status-badge-terlambat';
          statusLabel = 'Terlambat';
        } else if (st === 'izin') {
          badgeCls = 'status-badge-idle';
          statusLabel = 'Izin';
        } else if (st === 'sakit') {
          badgeCls = 'status-badge-terlambat';
          statusLabel = 'Sakit';
        }

        const fotoMasukThumb = item.foto_masuk
          ? `<img class="riwayat-thumb" src="../${item.foto_masuk}" alt="Masuk" onclick="zoomFoto('../${item.foto_masuk}', 'Foto Absen Masuk - ${tglFormatted}')" />`
          : `<div class="riwayat-thumb-ph">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </div>`;

        const fotoPulangThumb = item.foto_pulang
          ? `<img class="riwayat-thumb" src="../${item.foto_pulang}" alt="Pulang" onclick="zoomFoto('../${item.foto_pulang}', 'Foto Absen Pulang - ${tglFormatted}')" />`
          : `<div class="riwayat-thumb-ph">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </div>`;

        const jamMsk = item.jam_masuk ? item.jam_masuk.substr(0, 5) : '–';
        const jamPlg = item.jam_pulang ? item.jam_pulang.substr(0, 5) : '–';

        return `
          <div class="riwayat-card-item">
            <div class="riwayat-header">
              <span class="riwayat-date" style="display:inline-flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted)"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>${tglFormatted}</span>
              </span>
              <span class="status-badge ${badgeCls}" style="padding:3px 10px; font-size:11px;">
                ${statusLabel}
              </span>
            </div>
            
            <div class="riwayat-body-grid">
              <div class="riwayat-subcol">
                ${fotoMasukThumb}
                <div class="riwayat-subcol-info">
                  <div class="lbl">Masuk</div>
                  <div class="val">${jamMsk} WIB</div>
                  <div style="color:#64748b; font-size:10px;">${item.jarak_masuk ?? 0}m</div>
                </div>
              </div>

              <div class="riwayat-subcol">
                ${fotoPulangThumb}
                <div class="riwayat-subcol-info">
                  <div class="lbl">Pulang</div>
                  <div class="val">${jamPlg} WIB</div>
                  <div style="color:#64748b; font-size:10px;">${item.jarak_pulang ? item.jarak_pulang + 'm' : '–'}</div>
                </div>
              </div>
            </div>
            ${item.keterangan ? `<div style="margin-top:8px; padding-top:8px; border-top:1px dashed #e2e8f0; font-size:11px; color:#475569;"><span style="font-weight:700; color:#334155;">Keterangan:</span> ${item.keterangan}</div>` : ''}
          </div>
        `;
      }).join('');
    }
  } catch (err) {
    container.innerHTML = `<div class="empty-state" style="color:#dc2626">Gagal memuat: ${err.message}</div>`;
  }
}

function zoomFoto(src, title) {
  document.getElementById('zoom-foto-img').src = src;
  document.getElementById('zoom-foto-title').textContent = title || 'Foto Presensi';
  document.getElementById('modal-zoom-foto').classList.add('active');
}

function tutupModalZoom() {
  document.getElementById('modal-zoom-foto').classList.remove('active');
}

// ========================================================
// 10. AJAX HELPERS & ALERT
// ========================================================
async function postJSON(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

async function fetchJSON(url) {
  const res = await fetch(url, {
    credentials: 'same-origin'
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

function showAlert(el, msg, type) {
  if (!el) return;
  el.textContent = msg;
  el.className = 'alert alert-' + type + ' show';
}

function hideAlert(el) {
  if (!el) return;
  el.className = 'alert';
  el.textContent = '';
}
