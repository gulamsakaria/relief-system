// Enforcement always happens server-side; this button stays enabled regardless
function toggleBtnLoading(btn, isLoading, loadingLabel) {
  if (!btn) return;
  if (isLoading) {
    btn.dataset.originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner"></span>${escapeHtml(loadingLabel || btn.dataset.originalHtml)}`;
  } else {
    btn.disabled = false;
    if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
  }
}

let nidDebounceTimer = null;
let pendingFamilyPayload = null; // holds registration data while the fuzzy-match modal is open
let resolvedAreaId = null; // set by updateZoneStatus() once a covered upazila is picked

const I18N = {
  bn: {
    not_found: 'এই NID-তে কোনো পরিবার নিবন্ধিত পাওয়া যায়নি — আগে "পরিবার নিবন্ধন" পেজ থেকে নিবন্ধন করুন।',
    family_members_suffix: 'জন',
    family_members_label: 'পরিবার সদস্য',
    duplicate_warning: 'Duplicate Warning',
    duplicate_msg: (ngo, date) => `এই পরিবার ইতিমধ্যে <b>${ngo}</b> থেকে ${date} তারিখে এই ক্যাটাগরির ত্রাণ পেয়েছে। ৭ দিন পার না হলে database নিজেই block করে দেবে।`,
    eligible: '✔ Eligible — এই পরিবার এই ক্যাটাগরিতে ত্রাণ পাওয়ার যোগ্য।',
    blocked_suffix: '— duplicate_log-এ লেখা হয়েছে।',
    reg_required: 'NID ও নাম আবশ্যক।',
    member_fields_required: 'প্রত্যেক সদস্যের নাম ও NID/জন্ম নিবন্ধন নম্বর আবশ্যক।',
    fuzzy_intro: (name) => `"<b>${name}</b>" নামের সাথে কাছাকাছি মিল আছে এমন নাম ইতিমধ্যে নিবন্ধিত:`,
    fuzzy_outro: 'এটা কি ভিন্ন পরিবার, নাকি একই পরিবার ভুল বানানে আবার নিবন্ধন করা হচ্ছে?',
    edit_distance: 'edit distance',
    member_no: 'সদস্য',
    name_placeholder: 'নাম',
    number_placeholder: 'নম্বর',
    birth_certificate: 'জন্ম নিবন্ধন',
    zone_matched: (areaName) => `✅ এই উপজেলা "<b>${areaName}</b>" ত্রাণ অঞ্চলের অন্তর্ভুক্ত — Fairness Dashboard-এ ট্র্যাক হবে।`,
    zone_not_covered: 'ℹ️ এই উপজেলায় এখনো কোনো নির্দিষ্ট ত্রাণ অঞ্চল ট্র্যাক করা হচ্ছে না — নিবন্ধন করা যাবে, শুধু Fairness Dashboard-এ দেখানো হবে না।',
    select_upazila_required: 'বিভাগ, জেলা ও উপজেলা নির্বাচন করুন।',
    point_fields_required: 'পয়েন্টের নাম ও একটা এলাকা (quick-pick বা বিভাগ/জেলা/উপজেলা) নির্বাচন করুন।',
    matched_via_member: (name) => `ℹ️ এই NID পরিবার প্রধানের নয় — সদস্য <b>${name}</b>-এর, তবু একই পরিবার হিসেবে শনাক্ত হয়েছে।`,
    network_error: 'নেটওয়ার্ক সমস্যা হয়েছে — ইন্টারনেট সংযোগ চেক করে আবার চেষ্টা করুন।',
  },
  en: {
    not_found: 'No family is registered under this NID — please register on the "Family Registration" page first.',
    family_members_suffix: '',
    family_members_label: 'Family members',
    duplicate_warning: 'Duplicate Warning',
    duplicate_msg: (ngo, date) => `This family already received relief in this category from <b>${ngo}</b> on ${date}. The database will automatically block another entry until 7 days have passed.`,
    eligible: '✔ Eligible — this family is eligible to receive relief in this category.',
    blocked_suffix: '— logged to duplicate_log.',
    reg_required: 'NID and name are required.',
    member_fields_required: "Each member's name and NID/Birth Certificate number are required.",
    fuzzy_intro: (name) => `A name close to "<b>${name}</b>" is already registered:`,
    fuzzy_outro: 'Is this a different family, or the same family being registered again with a spelling mistake?',
    edit_distance: 'edit distance',
    member_no: 'Member',
    name_placeholder: 'Name',
    number_placeholder: 'Number',
    birth_certificate: 'Birth Certificate',
    zone_matched: (areaName) => `✅ This upazila falls under the "<b>${areaName}</b>" relief zone — it'll be tracked on the Fairness Dashboard.`,
    zone_not_covered: "ℹ️ This upazila isn't in an actively-tracked relief zone yet — registration will still work, it just won't appear on the Fairness Dashboard.",
    select_upazila_required: 'Please select division, district, and upazila.',
    point_fields_required: 'Please enter a point name and pick a location (quick-pick or division/district/upazila).',
    matched_via_member: (name) => `ℹ️ This NID doesn't belong to the family head — it belongs to member <b>${name}</b>, but was still matched to the same family.`,
    network_error: 'A network error occurred — check your connection and try again.',
  },
};
function jt(key) {
  const lang = (typeof CUR_LANG !== 'undefined') ? CUR_LANG : 'bn';
  return I18N[lang][key];
}

document.addEventListener('DOMContentLoaded', () => {
  animateFillBars();
  animateKpiCounters();
  if (typeof RELIEF_AREAS !== 'undefined') initReliefMap(RELIEF_AREAS, MAP_I18N);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeFuzzyModal();
  });

  const nidInput = document.getElementById('distNid');
  if (nidInput) {
    nidInput.addEventListener('input', () => {
      clearTimeout(nidDebounceTimer);
      nidDebounceTimer = setTimeout(checkFamilyAndDuplicate, 400);
    });
    const itemSelect = document.getElementById('distItem');
    if (itemSelect) itemSelect.addEventListener('change', checkFamilyAndDuplicate);
  }

  if (document.getElementById('stockIndicatorBox')) {
    const distItemSel = document.getElementById('distItem');
    const distNgoSel = document.getElementById('distNgo');
    if (distItemSel) distItemSel.addEventListener('change', updateStockIndicator);
    if (distNgoSel) distNgoSel.addEventListener('change', updateStockIndicator);
    updateStockIndicator();
  }

  const sizeInput = document.getElementById('regSize');
  if (sizeInput) {
    sizeInput.addEventListener('input', () => renderMemberRows(sizeInput.value));
    renderMemberRows(sizeInput.value);
  }

  const divisionSelect = document.getElementById('regDivision');
  if (divisionSelect) {
    divisionSelect.addEventListener('change', () => populateDistricts(divisionSelect.value, 'regDistrict', 'regUpazila'));
    document.getElementById('regDistrict').addEventListener('change', (e) => populateUpazilas(e.target.value, 'regUpazila'));
    document.getElementById('regUpazila').addEventListener('change', (e) => updateZoneStatus(e.target.value));
  }

  const ngoLogoInput = document.getElementById('ngoLogoInput');
  if (ngoLogoInput) {
    ngoLogoInput.addEventListener('change', () => {
      const file = ngoLogoInput.files[0];
      if (file) document.getElementById('ngoLogoPreview').src = URL.createObjectURL(file);
    });
  }

  const pointDivisionSelect = document.getElementById('pointDivision');
  if (pointDivisionSelect) {
    pointDivisionSelect.addEventListener('change', () => populateDistricts(pointDivisionSelect.value, 'pointDistrict', 'pointUpazila'));
    document.getElementById('pointDistrict').addEventListener('change', (e) => populateUpazilas(e.target.value, 'pointUpazila'));

    document.getElementById('togglePointForm').addEventListener('click', (e) => {
      e.preventDefault();
      const form = document.getElementById('newPointForm');
      form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });
    document.getElementById('quickPickZone').addEventListener('change', (e) => {
      if (e.target.value) {
        ['pointDivision', 'pointDistrict', 'pointUpazila'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('pointDistrict').disabled = true;
        document.getElementById('pointUpazila').disabled = true;
      }
    });
    ['pointDivision', 'pointDistrict', 'pointUpazila'].forEach(id => {
      document.getElementById(id).addEventListener('change', (e) => {
        if (e.target.value) document.getElementById('quickPickZone').value = '';
      });
    });
  }
});

/* ---------- Dashboard: animated fill bars + KPI counters + Leaflet map ---------- */

// Set width one frame later so the CSS transition animates
function animateFillBars() {
  const bars = document.querySelectorAll('.gauge-fill[data-width], .bar-fill[data-width]');
  if (!bars.length) return;
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      bars.forEach(el => { el.style.width = el.dataset.width + '%'; });
    });
  });
}

function animateKpiCounters() {
  document.querySelectorAll('.kpi .n[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count, 10) || 0;
    const duration = 900;
    const start = performance.now();
    function tick(now) {
      const p = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(eased * target).toLocaleString();
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
}

// Renders the relief-zone map (dashboard.php only)
function initReliefMap(areas, i18n) {
  const el = document.getElementById('reliefMap');
  if (!el || typeof L === 'undefined') return;

  const map = L.map('reliefMap').setView([23.9, 90.4], 6.6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 17,
  }).addTo(map);

  areas.forEach(a => {
    const color = a.ratio < 0.5 ? '#C0392B' : (a.ratio < 1 ? '#B9770E' : '#1E8F5F');
    const radius = 9 + Math.min(a.population / 4000, 16);
    L.circleMarker([a.lat, a.lng], {
      radius, color: '#fff', weight: 2, fillColor: color, fillOpacity: 0.85,
    })
      .addTo(map)
      .bindPopup(
        `<b>${escapeHtml(a.name)}</b><br>` +
        `${i18n.population}: ${a.population.toLocaleString()}<br>` +
        `${i18n.received}: ${a.received}<br>` +
        `${i18n.fairness}: ${a.ratio.toFixed(2)}×`
      );
  });
}

function updateZoneStatus(upazilaId) {
  const statusBox = document.getElementById('regZoneStatus');
  resolvedAreaId = null;

  if (!upazilaId) {
    statusBox.style.display = 'none';
    return;
  }

  const match = AREA_BY_UPAZILA[upazilaId];
  statusBox.style.display = 'block';
  if (match) {
    resolvedAreaId = match.area_id;
    statusBox.innerHTML = jt('zone_matched')(escapeHtml(match.area_name));
  } else {
    statusBox.innerHTML = jt('zone_not_covered');
  }
}

/* ---------------- Division/District/Upazila cascading select ---------------- */

function populateDistricts(divisionId, districtSelectId, upazilaSelectId) {
  const districtSelect = document.getElementById(districtSelectId);
  const upazilaSelect = document.getElementById(upazilaSelectId);
  const districtPlaceholder = districtSelect.options[0].textContent;
  const upazilaPlaceholder = upazilaSelect.options[0].textContent;

  districtSelect.innerHTML = `<option value="">${districtPlaceholder}</option>`;
  upazilaSelect.innerHTML = `<option value="">${upazilaPlaceholder}</option>`;

  if (!divisionId) {
    districtSelect.disabled = true;
    upazilaSelect.disabled = true;
    return;
  }

  BD_DISTRICTS
    .filter(d => d.division_id === parseInt(divisionId, 10))
    .forEach(d => {
      const opt = document.createElement('option');
      opt.value = d.id;
      opt.textContent = d.name;
      districtSelect.appendChild(opt);
    });
  districtSelect.disabled = false;
  upazilaSelect.disabled = true;
}

function populateUpazilas(districtId, upazilaSelectId) {
  const upazilaSelect = document.getElementById(upazilaSelectId);
  const placeholderText = upazilaSelect.options[0].textContent;

  upazilaSelect.innerHTML = '';
  const placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.textContent = placeholderText;
  upazilaSelect.appendChild(placeholder);

  if (!districtId) {
    upazilaSelect.disabled = true;
    return;
  }

  BD_UPAZILAS
    .filter(u => u.district_id === parseInt(districtId, 10))
    .forEach(u => {
      const opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = u.name;
      upazilaSelect.appendChild(opt);
    });
  upazilaSelect.disabled = false;
}

/* ---------------- Distribution page ---------------- */

function currentDistNgoId() {
  const ngoSelect = document.getElementById('distNgo');
  const ngoFixed = document.getElementById('distNgoFixed');
  return ngoSelect ? ngoSelect.value : (ngoFixed ? ngoFixed.dataset.ngoId : null);
}

// Greys out relief-item options the selected NGO has no stock of
function updateItemAvailability() {
  const itemSelect = document.getElementById('distItem');
  if (!itemSelect || typeof STOCK_MAP === 'undefined') return;

  const ngoId = currentDistNgoId();
  const ngoStock = (ngoId && STOCK_MAP[ngoId]) || {};

  Array.from(itemSelect.options).forEach(opt => {
    const qty = ngoStock[opt.value] !== undefined ? parseFloat(ngoStock[opt.value]) : 0;
    const available = qty > 0;
    opt.disabled = !available;
    opt.textContent = available ? opt.dataset.label : `${opt.dataset.label} — ${NO_STOCK_SUFFIX}`;
  });

  if (itemSelect.selectedOptions[0] && itemSelect.selectedOptions[0].disabled) {
    const firstAvailable = Array.from(itemSelect.options).find(o => !o.disabled);
    if (firstAvailable) itemSelect.value = firstAvailable.value;
  }
}

// Informational only — real block happens server-side.
// Units measured by weight/volume (kg, liter) turn red below STOCK_MIN_THRESHOLD,
// with a pulse animation so low stock is hard to miss.
const STOCK_THRESHOLD_UNITS = ['kg', 'liter'];

function updateStockIndicator() {
  const box = document.getElementById('stockIndicatorBox');
  if (!box || typeof STOCK_MAP === 'undefined') return;

  updateItemAvailability();

  const itemSelect = document.getElementById('distItem');
  if (!itemSelect) return;
  const itemId = itemSelect.value;
  const unit = itemSelect.selectedOptions[0] ? itemSelect.selectedOptions[0].dataset.unit : '';

  const ngoId = currentDistNgoId();
  if (!ngoId) { box.innerHTML = ''; return; }

  const ngoStock = STOCK_MAP[ngoId] || {};
  const qty = ngoStock[itemId] !== undefined ? parseFloat(ngoStock[itemId]) : 0;
  const qtyLabel = qty % 1 === 0 ? qty.toString() : qty.toFixed(2);
  const minRequired = STOCK_THRESHOLD_UNITS.includes(unit) ? STOCK_MIN_THRESHOLD : 0;

  if (qty <= 0) {
    box.innerHTML = `<div class="alert red show stock-low-pulse">${STOCK_MSG_NONE}</div>`;
  } else if (qty < minRequired) {
    box.innerHTML = `<div class="alert red show stock-low-pulse">${STOCK_MSG_LOW.replace('%s', qtyLabel).replace('%s', escapeHtml(unit)).replace('%s', minRequired)}</div>`;
  } else {
    box.innerHTML = `<div class="alert green show">${STOCK_MSG_AVAILABLE.replace('%s', qtyLabel).replace('%s', escapeHtml(unit))}</div>`;
  }
}

async function checkFamilyAndDuplicate() {
  const nid = document.getElementById('distNid').value.trim();
  const box = document.getElementById('familyLookupBox');
  const alertBox = document.getElementById('distAlert');
  const submitBtn = document.getElementById('distSubmitBtn');

  alertBox.className = 'alert';
  alertBox.textContent = '';
  box.innerHTML = '';
  submitBtn.disabled = true;

  if (nid.length < 4) return;

  const itemId = document.getElementById('distItem').value;
  const body = new URLSearchParams({ nid, item_id: itemId });

  let data;
  try {
    const res = await fetch('api/check_duplicate.php', { method: 'POST', body });
    data = await res.json();
  } catch (err) {
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('network_error');
    return;
  }

  if (data.status === 'invalid') return;

  if (data.status === 'not_found') {
    box.innerHTML = `<div class="alert amber show">${jt('not_found')}</div>`;
    return;
  }

  const memberNote = data.matched_name
    ? `<div class="meta">${jt('matched_via_member')(escapeHtml(data.matched_name))}</div>`
    : '';
  box.innerHTML = `<div class="family-card">
      <div>
        <div class="name">${escapeHtml(data.head_name)}</div>
        <div class="meta">${escapeHtml(data.area_name || '—')} · ${jt('family_members_label')}: ${data.family_size} ${jt('family_members_suffix')}</div>
        ${memberNote}
      </div>
      <div style="font-size:20px;">✅</div>
    </div>`;

  if (data.status === 'duplicate') {
    alertBox.className = 'alert red show';
    alertBox.innerHTML = `⚠ <b>${jt('duplicate_warning')}</b> — ${jt('duplicate_msg')(escapeHtml(data.last_ngo), data.last_date)}`;
  } else {
    alertBox.className = 'alert green show';
    alertBox.textContent = jt('eligible');
  }
  submitBtn.disabled = false;
}

async function submitDistribution() {
  const submitBtn = document.getElementById('distSubmitBtn');
  const alertBox = document.getElementById('distAlert');
  toggleBtnLoading(submitBtn, true);

  const nid = document.getElementById('distNid').value.trim();
  const itemId = document.getElementById('distItem').value;
  const pointId = document.getElementById('distPoint').value;
  const qty = document.getElementById('distQty').value;
  const ngoSelect = document.getElementById('distNgo');

  const body = new URLSearchParams({ nid, item_id: itemId, point_id: pointId, quantity: qty, csrf_token: CSRF_TOKEN });
  if (ngoSelect && ngoSelect.tagName === 'SELECT') body.append('ngo_id', ngoSelect.value);

  let data;
  try {
    const res = await fetch('api/save_distribution.php', { method: 'POST', body });
    data = await res.json();
  } catch (err) {
    toggleBtnLoading(submitBtn, false);
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('network_error');
    return;
  }
  toggleBtnLoading(submitBtn, false);

  if (data.status === 'success') {
    alertBox.className = 'alert green show';
    alertBox.textContent = '✔ ' + data.message;
  } else if (data.status === 'blocked') {
    alertBox.className = 'alert red show';
    alertBox.textContent = '🚫 ' + data.message + ' ' + jt('blocked_suffix');
  } else {
    alertBox.className = 'alert red show';
    alertBox.textContent = '✖ ' + data.message;
  }
  setTimeout(() => location.reload(), 1600);
}

async function submitNewPoint() {
  const alertBox = document.getElementById('newPointAlert');
  const name = document.getElementById('newPointName').value.trim();
  const quickPick = document.getElementById('quickPickZone').value;
  const upazilaId = quickPick || document.getElementById('pointUpazila').value;

  if (!name || !upazilaId) {
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('point_fields_required');
    return;
  }

  const submitBtn = document.getElementById('savePointBtn');
  toggleBtnLoading(submitBtn, true);
  const body = new URLSearchParams({ point_name: name, upazila_id: upazilaId, csrf_token: CSRF_TOKEN });

  let data;
  try {
    const res = await fetch('api/save_distribution_point.php', { method: 'POST', body });
    data = await res.json();
  } catch (err) {
    toggleBtnLoading(submitBtn, false);
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('network_error');
    return;
  }
  toggleBtnLoading(submitBtn, false);

  if (data.status === 'success') {
    const select = document.getElementById('distPoint');
    const opt = document.createElement('option');
    opt.value = data.point_id;
    opt.textContent = data.point_name;
    select.appendChild(opt);
    select.value = data.point_id;

    alertBox.className = 'alert green show';
    alertBox.textContent = '✔ ' + data.message;
    document.getElementById('newPointName').value = '';
    document.getElementById('quickPickZone').value = '';
    setTimeout(() => {
      document.getElementById('newPointForm').style.display = 'none';
      alertBox.className = 'alert';
      alertBox.textContent = '';
    }, 1600);
  } else {
    alertBox.className = 'alert red show';
    alertBox.textContent = '✖ ' + data.message;
  }
}

/* ---------------- Registration page ---------------- */

// Renders input rows for every member besides the head
function renderMemberRows(size) {
  const container = document.getElementById('memberRows');
  if (!container) return;

  const n = Math.max(1, Math.min(20, parseInt(size, 10) || 1));
  const otherCount = n - 1;

  // Preserve values already typed in
  const existing = getMembersFromRows();

  container.innerHTML = '';
  for (let i = 0; i < otherCount; i++) {
    const prev = existing[i] || { name: '', id_type: 'NID', id_number: '' };
    const row = document.createElement('div');
    row.className = 'member-row';
    row.innerHTML = `
      <div class="member-no">${jt('member_no')} ${i + 2}</div>
      <div class="field"><input type="text" class="memName" placeholder="${jt('name_placeholder')}" value="${escapeHtml(prev.name)}"></div>
      <div class="field">
        <select class="memIdType">
          <option value="NID" ${prev.id_type === 'NID' ? 'selected' : ''}>NID</option>
          <option value="Birth Certificate" ${prev.id_type === 'Birth Certificate' ? 'selected' : ''}>${jt('birth_certificate')}</option>
        </select>
      </div>
      <div class="field"><input type="text" class="memIdNum" placeholder="${jt('number_placeholder')}" value="${escapeHtml(prev.id_number)}"></div>
    `;
    container.appendChild(row);
  }
}

function getMembersFromRows() {
  const container = document.getElementById('memberRows');
  if (!container) return [];
  return Array.from(container.querySelectorAll('.member-row')).map(row => ({
    name: row.querySelector('.memName').value.trim(),
    id_type: row.querySelector('.memIdType').value,
    id_number: row.querySelector('.memIdNum').value.trim(),
  }));
}

async function submitRegistration() {
  const nid = document.getElementById('regNid').value.trim();
  const idTypeEl = document.getElementById('regIdType');
  const idType = idTypeEl ? idTypeEl.value : 'NID';
  const name = document.getElementById('regName').value.trim();
  const phone = document.getElementById('regPhone').value.trim();
  const size = document.getElementById('regSize').value;
  const upazilaId = document.getElementById('regUpazila').value;
  const alertBox = document.getElementById('regAlert');

  if (!nid || !name) {
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('reg_required');
    return;
  }

  if (!upazilaId) {
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('select_upazila_required');
    return;
  }

  const members = getMembersFromRows();
  if (members.some(m => !m.name || !m.id_number)) {
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('member_fields_required');
    return;
  }

  pendingFamilyPayload = { nid, id_type: idType, name, phone, size, upazila_id: upazilaId, members: JSON.stringify(members) };
  await doSaveFamily(false);
}

async function doSaveFamily(force) {
  const alertBox = document.getElementById('regAlert');
  const submitBtn = document.getElementById('regSubmitBtn');
  const p = pendingFamilyPayload;
  toggleBtnLoading(submitBtn, true);
  const body = new URLSearchParams({ ...p, force: force ? '1' : '0', csrf_token: CSRF_TOKEN });

  let data;
  try {
    const res = await fetch('api/save_family.php', { method: 'POST', body });
    data = await res.json();
  } catch (err) {
    toggleBtnLoading(submitBtn, false);
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('network_error');
    return;
  }
  toggleBtnLoading(submitBtn, false);

  if (data.status === 'fuzzy_warning') {
    document.getElementById('fuzzyModalBody').innerHTML =
      jt('fuzzy_intro')(escapeHtml(p.name)) + '<br><br>' +
      data.matches.map(m => `• <b>${escapeHtml(m.name)}</b> (${escapeHtml(m.area_name || '—')}) — ${jt('edit_distance')}: ${m.distance}`).join('<br>') +
      `<br><br>${jt('fuzzy_outro')}`;
    document.getElementById('fuzzyModal').classList.add('show');
    return;
  }

  closeFuzzyModal();
  if (data.status === 'success') {
    alertBox.className = 'alert green show';
    alertBox.textContent = '✔ ' + data.message;
    setTimeout(() => location.reload(), 1400);
  } else {
    alertBox.className = 'alert red show';
    alertBox.textContent = '✖ ' + data.message;
  }
}

/* ---------------- NGO profile page ---------------- */

async function saveNgoProfile() {
  const btn = document.getElementById('profileSaveBtn');
  const alertBox = document.getElementById('profileAlert');
  const phone = document.getElementById('profilePhone').value.trim();
  const logoInput = document.getElementById('ngoLogoInput');
  toggleBtnLoading(btn, true);

  const formData = new FormData();
  formData.append('phone', phone);
  formData.append('csrf_token', CSRF_TOKEN);
  if (logoInput && logoInput.files[0]) formData.append('logo', logoInput.files[0]);

  let data;
  try {
    const res = await fetch('api/save_ngo_profile.php', { method: 'POST', body: formData });
    data = await res.json();
  } catch (err) {
    toggleBtnLoading(btn, false);
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('network_error');
    return;
  }
  toggleBtnLoading(btn, false);

  if (data.status === 'success') {
    alertBox.className = 'alert green show';
    alertBox.textContent = '✔ ' + data.message;
    if (data.logo_path) document.getElementById('ngoLogoPreview').src = data.logo_path + '?t=' + Date.now();
  } else {
    alertBox.className = 'alert red show';
    alertBox.textContent = '✖ ' + data.message;
  }
}

function confirmRegistration() { doSaveFamily(true); }
function closeFuzzyModal() { document.getElementById('fuzzyModal')?.classList.remove('show'); }

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}
