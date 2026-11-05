/**
 * TrackED v2.0 — app.js
 * SPA Navigation + Schedule Manager + Irregular Students + Live Feed
 */
"use strict";

const App = {
    currentSection: 'dashboard',
    liveInterval: null,
    autoRefresh: true,
    allStudents: [],
    allSchedules: [],
    reportData: [],
    deleteTargetId: null,
    deleteTargetName: null,
    regHistory: [],
    todayDay: new Date().toLocaleDateString('en-US', { weekday: 'long' }),
    scheduleListLevel: 'College',
    todayScheduleLevel: '',
    liveLevel: '',
    clusterDivision: 'College',
    clusterEventId: '',
    clusterDashboard: null,
    reportMode: 'movement',
    dashboardLevel: '',
    movementPolicy: null,
    musterData: {session:null,members:[],recent_sessions:[]},
    auditData: [],
    healthInterval: null,
};

/* ═══════════════════════════
   INIT
═══════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    initNav();
    initClock();
    initSidebarToggle();
    initAcademicArchitecture();
    initDashboardAcademicFilters();
    initScheduleLevelTabs();
    initRegistrationForm();
    initPhotoUpload();
    initModals();
    initReports();
    initRecordsFilters();
    initScheduleForm();
    initMovementPolicy();
    initSubjectForm();
    initSettingsForm();
    initRetentionSettings();
    initSchedulePanelSync();
    initIdGenerator();
    initClusterManager();
    initSystemHealth();
    initAuditTrail();
    initEmergencyMuster();

    // Initial loads
    fetchLiveScans();
    fetchRejectedScans();
    fetchMovementDashboard();
    loadMovementPolicy();
    loadDashboardEventStatus();
    checkConnection();
    startAutoRefresh();

    // Demo: simulate invalid RFID after 10s
});

/* ═══════════════════════════
   NAVIGATION
═══════════════════════════ */
function initNav() {
    const titles = {
        dashboard:'Dashboard', registration:'Student Registration',
        records:'Student Records', reports:'Monitoring Reports',
        schedules:'Campus Policy', idgenerator:'ID Generator', clusters:'Intramurals Clusters', settings:'Settings',
        muster:'Emergency Muster', health:'System Health', audit:'Audit Trail',
    };
    document.querySelectorAll('.nav-item[data-section]').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            const sec = item.dataset.section;
            if (sec === App.currentSection) return;
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            item.classList.add('active');
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.getElementById(`section-${sec}`)?.classList.add('active');
            document.getElementById('page-title').textContent = titles[sec] || sec;
            App.currentSection = sec;
            onSectionEnter(sec);
            if (window.innerWidth <= 768) document.body.classList.remove('sidebar-open');
        });
    });
}

function onSectionEnter(sec) {
    if (sec === 'dashboard')  { fetchLiveScans(); fetchRejectedScans(); fetchMovementDashboard(); loadMovementPolicy(); loadDashboardEventStatus(); }
    if (sec === 'records')    { fetchStudents(); }
    if (sec === 'schedules')  { loadMovementPolicy(); }
    if (sec === 'idgenerator') { loadIdGeneratorData(); }
    if (sec === 'clusters')   { loadIntramuralEvents(); }
    if (sec === 'muster')     { loadMuster(); }
    if (sec === 'health')     { loadSystemHealth(); }
    if (sec === 'audit')      { loadAuditTrail(); }
    if (sec === 'settings')   { loadSettingsValues(); loadRetentionStatus(); }
    if (sec === 'registration') {
        setTimeout(() => document.getElementById('rfid_uid')?.focus(), 100);
    }
}

/* ═══════════════════════════
   CLOCK
═══════════════════════════ */
function initClock() {
    const clockEl = document.getElementById('topbar-clock');
    const dateEl  = document.getElementById('topbar-date');
    function tick() {
        const n = new Date();
        const h = (n.getHours()%12||12).toString().padStart(2,'0');
        const m = n.getMinutes().toString().padStart(2,'0');
        const s = n.getSeconds().toString().padStart(2,'0');
        const ap = n.getHours()>=12?'PM':'AM';
        if (clockEl) clockEl.textContent = `${h}:${m}:${s} ${ap}`;
        if (dateEl) {
            const days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            const months=['January','February','March','April','May','June','July','August','September','October','November','December'];
            dateEl.textContent = `${days[n.getDay()]}, ${months[n.getMonth()]} ${n.getDate()}, ${n.getFullYear()}`;
        }
    }
    tick(); setInterval(tick, 1000);
}

/* ═══════════════════════════
   SIDEBAR TOGGLE
═══════════════════════════ */
function initSidebarToggle() {
    document.getElementById('menu-toggle')?.addEventListener('click', () => {
        if (window.innerWidth <= 768) document.body.classList.toggle('sidebar-open');
        else document.body.classList.toggle('sidebar-collapsed');
    });
    document.body.addEventListener('click', e => {
        if (window.innerWidth <= 768 && document.body.classList.contains('sidebar-open'))
            if (!e.target.closest('.sidebar') && !e.target.closest('#menu-toggle'))
                document.body.classList.remove('sidebar-open');
    });
}

/* ═══════════════════════════
   LIVE SCAN FEED
═══════════════════════════ */
function fetchLiveScans() {
    const tbody = document.getElementById('live-tbody');
    const badge = document.getElementById('last-refresh');
    const btn   = document.getElementById('refresh-btn');
    if (btn) btn.classList.add('spinning');

    fetch(`index.php?action=get_live_scans&level=${encodeURIComponent(App.liveLevel || '')}`)
        .then(r => r.json())
        .then(data => {
            renderLiveTable(data, tbody);
            if (badge) badge.textContent = `Updated ${fmtTime(new Date())}`;
        })
        .catch(() => {
            if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="table-empty"><p>Could not load live feed.</p></td></tr>`;
        })
        .finally(() => { if (btn) btn.classList.remove('spinning'); });
}

function movementBadge(type) {
    const t=(type||'').toUpperCase();
    if(t==='IN') return `<span class="movement-badge movement-in"><i class="ph-fill ph-sign-in"></i> IN</span>`;
    if(t==='OUT') return `<span class="movement-badge movement-out"><i class="ph-fill ph-sign-out"></i> OUT</span>`;
    return `<span class="movement-badge">${esc(t||'—')}</span>`;
}
function movementContextBadge(context, reason='') {
    const c=context||'NORMAL';
    if(c==='EARLY_EXIT') return `<span class="context-badge context-early" title="${esc(reason||'Authorized early exit')}"><i class="ph-fill ph-warning-circle"></i> Early Exit</span>`;
    if(c==='AUTHORIZED_REENTRY') return `<span class="context-badge context-reentry"><i class="ph-fill ph-arrow-u-up-left"></i> Re-entry</span>`;
    if(c==='EVENT') return `<span class="context-badge context-event"><i class="ph-fill ph-trophy"></i> Event</span>`;
    return `<span class="context-badge context-normal"><i class="ph-fill ph-shield-check"></i> Normal</span>`;
}
function renderLiveTable(scans, tbody) {
    if (!tbody) return;
    if (!scans?.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty"><i class="ph ph-arrows-left-right" style="font-size:2rem;opacity:.3;"></i><p>No movement logs yet.</p></td></tr>`;
        return;
    }
    tbody.innerHTML = '';
    scans.forEach((r, i) => {
        const dt=new Date(r.verified_at);
        const tr=document.createElement('tr');
        if(i===0) tr.className='new-row';
        tr.innerHTML=`
            <td><span style="color:var(--text-muted);font-size:.8rem">${i+1}</span></td>
            <td><div class="student-cell"><div class="student-avatar">${r.photo_path?`<img src="${esc(r.photo_path)}" alt="">`:initials(r.full_name)}</div><div><div class="student-name">${esc(r.full_name)}</div><div class="student-meta">${esc(r.student_id_no||'')}</div></div></div></td>
            <td><div class="student-name">${esc(academicPrimary(r))}</div><div class="student-meta">${esc(shortLevel(r.education_level||'College'))} • ${esc(academicSecondary(r))}</div></td>
            <td>${esc(r.section||'—')}</td>
            <td><span class="scan-time">${dt.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}, ${fmtTime(dt)}</span></td>
            <td>${movementBadge(r.movement_type)}</td>
            <td>${movementContextBadge(r.movement_context,r.authorization_reason)}${r.authorization_reason?`<div class="movement-reason">${esc(r.authorization_reason)}</div>`:''}</td>`;
        tbody.appendChild(tr);
    });
}

function fetchRejectedScans() {
    const tbody = document.getElementById('rejected-tbody');
    const btn = document.getElementById('refresh-rejected-btn');
    if (btn) btn.classList.add('spinning');

    fetch('index.php?action=get_rejected_scans')
        .then(r => r.json())
        .then(data => {
            renderRejectedTable(data, tbody);
            const count = Array.isArray(data)
                ? data.filter(x => {
                    const d = new Date(x.rejected_at);
                    const n = new Date();
                    return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth() && d.getDate() === n.getDate();
                }).length
                : 0;
            const stat = document.getElementById('stat-rejected');
            if (stat) stat.textContent = count;
        })
        .catch(() => {
            if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="table-empty"><p>Could not load denied attempts.</p></td></tr>`;
        })
        .finally(() => { if (btn) btn.classList.remove('spinning'); });
}

function renderRejectedTable(rows, tbody) {
    if (!tbody) return;
    if (!rows?.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty"><i class="ph ph-shield-check" style="font-size:2rem;opacity:.3;"></i><p>No denied attempts recorded.</p></td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    rows.forEach((r, i) => {
        const dt = new Date(r.rejected_at);
        const attempts = parseInt(r.attempts_today || 0, 10);
        const locked = Number(r.scan_enabled ?? 1) !== 1;
        const securityCount = parseInt(r.denied_attempt_count || 0, 10);
        const repeated = locked || attempts >= 3;
        const tr = document.createElement('tr');
        if (repeated) tr.classList.add('denied-repeat-row');
        tr.innerHTML = `
            <td><span style="color:var(--text-muted);font-size:.8rem;">${i+1}</span></td>
            <td><div class="student-cell">
                <div class="student-avatar">${r.photo_path?`<img src="${esc(r.photo_path)}" alt="">`:initials(r.full_name||'?')}</div>
                <div><div class="student-name">${esc(r.full_name||'Unknown Student')}</div><div class="student-meta">${esc(r.student_id_no||'No student number')}</div></div>
            </div></td>
            <td><span class="scan-time">${esc(r.rfid_uid||'—')}</span></td>
            <td><div class="denied-movement-stack">${movementBadge(r.scan_type)}${movementContextBadge(r.movement_context||'NORMAL')}</div></td>
            <td><span class="denied-reason">${esc(r.rejection_reason||'—')}</span></td>
            <td><span class="scan-time">${dt.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}, ${fmtTime(dt)}</span></td>
            <td>${locked
                ? `<span class="attempt-badge danger"><i class="ph-fill ph-lock-key"></i> LOCKED ${securityCount || 3}/3</span>`
                : repeated
                    ? `<span class="attempt-badge danger"><i class="ph-fill ph-warning"></i> ${attempts} Repeated</span>`
                    : `<span class="attempt-badge">${attempts}</span>`}
            </td>`;
        tbody.appendChild(tr);
    });
}

function startAutoRefresh() {
    App.liveInterval = setInterval(() => {
        if (App.currentSection === 'dashboard' && App.autoRefresh) { fetchLiveScans(); fetchRejectedScans(); fetchMovementDashboard(); }
    }, 5000);
    document.getElementById('refresh-btn')?.addEventListener('click', () => { fetchLiveScans(); fetchRejectedScans(); fetchMovementDashboard(); });
    document.getElementById('refresh-rejected-btn')?.addEventListener('click', fetchRejectedScans);
    document.getElementById('auto-refresh-toggle')?.addEventListener('change', function() {
        App.autoRefresh = this.checked;
        showToast(this.checked ? 'Auto-refresh ON' : 'Auto-refresh OFF', '', this.checked ? 'success' : 'info');
    });
}

/* ═══════════════════════════
   TODAY'S ACTIVE SCHEDULES
═══════════════════════════ */
function fetchTodaySchedules() {
    fetch('index.php?action=get_schedules')
        .then(r => r.json())
        .then(data => { App.allSchedules = data; renderTodaySchedules(data); })
        .catch(() => {});
}

function renderTodaySchedules(schedules) {
    const el = document.getElementById('today-schedules-list');
    if (!el) return;

    const today = App.todayDay;

    if (!schedules.length) {
        el.innerHTML = `<p class="today-no-sched">No schedules configured yet. Go to <strong>Schedule Manager</strong> to set them up.</p>`;
        return;
    }

    // Only show schedules active today and matching the selected education level.
    const active = schedules.filter(s => Array.isArray(s.days) && s.days.includes(today) && (!App.todayScheduleLevel || (s.education_level || 'College') === App.todayScheduleLevel));

    if (!active.length) {
        el.innerHTML = `
            <div class="today-no-sched" style="width:100%;padding:1.25rem 1.5rem;">
                <i class="ph ph-coffee" style="font-size:1.5rem;opacity:.4;display:block;margin-bottom:.4rem;"></i>
                No classes scheduled for <strong>${App.todayDay}</strong>.
                ${App.todayDay === 'Sunday' || App.todayDay === 'Saturday' ? 'Enjoy the weekend! 😊' : 'Check Schedule Manager to configure.'}
            </div>`;
        return;
    }

    el.innerHTML = '';
    active.forEach(s => {
        const chip = document.createElement('div');
        chip.className = 'today-sched-chip active-today';
        chip.innerHTML = `
            <span class="chip-level">${esc(shortLevel(s.education_level || 'College'))}</span>
            <span class="chip-course">${esc(schedulePrimary(s))}</span>
            <span class="chip-year">${esc(scheduleSecondary(s))}</span>
            <div style="display:flex;align-items:center;gap:.35rem;margin-top:.1rem;width:100%;">
                <span class="chip-time">⏰ ${fmtScheduleTime(s.time_in_start,'06:00')}–${fmtScheduleTime(s.late_cutoff,'08:30')}</span>
                <span style="font-size:.68rem;color:var(--text-muted);">entry</span>
            </div>
            <span style="font-size:.68rem;font-weight:600;color:var(--success);margin-top:.1rem;">● Active Today</span>
            <button class="sched-edit-btn sched-subjects-btn chip-subjects-btn" data-id="${s.id}" data-course="${esc(schedulePrimary(s))}" data-year="${esc(s.year_level)}">
                <i class="ph ph-books"></i> View Subjects
            </button>`;
        chip.querySelector('.chip-subjects-btn').addEventListener('click', () => {
            openSubjectsModal(s.id, schedulePrimary(s), s.year_level, true);
        });
        el.appendChild(chip);
    });
}

function initDashboardAcademicFilters() {
    const dashLevel=document.getElementById('dashboard-level-filter');
    if(dashLevel){
        App.dashboardLevel=dashLevel.value||'';
        dashLevel.addEventListener('change',()=>{App.dashboardLevel=dashLevel.value||'';fetchMovementDashboard();});
    }
    const liveLevel=document.getElementById('live-level-filter');
    if(liveLevel){
        App.liveLevel=liveLevel.value||'';
        liveLevel.addEventListener('change',()=>{App.liveLevel=liveLevel.value||'';fetchLiveScans();});
    }
}
function fetchMovementDashboard(){
    const level=App.dashboardLevel||'';
    fetch(`index.php?action=get_movement_dashboard&level=${encodeURIComponent(level)}`)
      .then(r=>r.json()).then(d=>{
        const set=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=Number(v||0).toLocaleString();};
        set('stat-total',d.total); set('stat-inside',d.inside); set('stat-entries',d.entries); set('stat-exits',d.exits); set('stat-early-exits',d.early_exits); set('stat-rejected',d.rejected);
      }).catch(()=>{});
}
function loadDashboardEventStatus(){
    fetch('index.php?action=get_intramural_override_status').then(r=>r.json()).then(d=>{
        const title=document.getElementById('dashboard-event-title');
        const copy=document.getElementById('dashboard-event-status');
        const badge=document.getElementById('dashboard-event-badge');
        if(!title||!copy||!badge)return;
        const active=!!(d.active||d.is_active||d.event);
        if(active){
            const ev=d.event||d;
            title.textContent=ev.name||'Intramurals Mode Active';
            copy.textContent='Event attendance windows are active. Permanent physical IN/OUT monitoring continues independently.';
            badge.textContent='ACTIVE EVENT'; badge.classList.add('active');
        }else{
            title.textContent='Normal Campus Mode';
            copy.textContent='No time-bound event attendance override is active. Physical movement monitoring remains live.';
            badge.textContent='STANDBY'; badge.classList.remove('active');
        }
    }).catch(()=>{});
}

/* ═══════════════════════════
   CONNECTION STATUS
═══════════════════════════ */
function checkConnection() {
    const dot = document.getElementById('status-dot');
    const txt = document.getElementById('status-text');
    fetch('index.php?action=get_live_scans')
        .then(r => {
            if (r.ok) { dot?.classList.add('connected'); if(txt) txt.textContent='Database Connected'; }
            else throw new Error();
        })
        .catch(() => { dot?.classList.add('error'); if(txt) txt.textContent='Connection Error'; });
}

/* ═══════════════════════════
   ACADEMIC ARCHITECTURE
═══════════════════════════ */
const ACADEMIC_YEARS = {
    'College': ['1st Year','2nd Year','3rd Year','4th Year'],
    'Senior High School': ['Grade 11','Grade 12'],
    'Junior High School': ['Grade 7','Grade 8','Grade 9','Grade 10']
};

function selectedAcademicLevel(prefix) {
    return document.querySelector(`#${prefix}-level-picker input[name="education_level"]:checked`)?.value || 'College';
}

function setYearOptions(select, level, current='') {
    if (!select) return;
    const years = ACADEMIC_YEARS[level] || [];
    select.innerHTML = `<option value="">${level==='College'?'Select Year Level':'Select Grade Level'}</option>` + years.map(y=>`<option value="${y}">${y}</option>`).join('');
    if (current && years.includes(current)) select.value = current;
}

function syncAcademicFields(prefix) {
    const level = selectedAcademicLevel(prefix);
    const college = document.getElementById(`${prefix}-course-group`);
    const track = document.getElementById(`${prefix}-track-group`);
    const strand = document.getElementById(`${prefix}-strand-group`);
    const year = document.getElementById(`${prefix}-year`);
    const yearLabel = document.getElementById(`${prefix}-year-label`);

    if (college) college.style.display = level === 'College' ? '' : 'none';
    if (track) track.style.display = level === 'Senior High School' ? '' : 'none';
    if (strand) strand.style.display = level === 'Senior High School' ? '' : 'none';
    if (yearLabel) yearLabel.innerHTML = `<i class="ph ph-graduation-cap"></i> ${level==='College'?'Year Level':'Grade Level'} <span class="field-required">*</span>`;
    setYearOptions(year, level, year?.value || '');

    if (level !== 'College') {
        const c = document.getElementById(`${prefix}-course`); if (c) c.value='';
    }
    if (level !== 'Senior High School') {
        const t = document.getElementById(`${prefix}-track`); if (t) t.value='';
        const s = document.getElementById(`${prefix}-strand`); if (s) s.value='';
    }
}

function academicIdentity(obj) {
    const level = obj.education_level || 'College';
    if (level === 'Senior High School') return [obj.strand || obj.track || 'SHS', obj.year_level, obj.section ? `Sec. ${obj.section}` : ''].filter(Boolean).join(' · ');
    if (level === 'Junior High School') return [obj.year_level || 'JHS', obj.section ? `Sec. ${obj.section}` : ''].filter(Boolean).join(' · ');
    return [obj.course || 'College', obj.year_level, obj.section ? `Sec. ${obj.section}` : ''].filter(Boolean).join(' · ');
}

function shortLevel(level) {
    if (level === 'Senior High School') return 'Senior High';
    if (level === 'Junior High School') return 'Junior High';
    return 'College';
}

function academicPrimary(obj) {
    const level = obj.education_level || 'College';
    if (level === 'Senior High School') return obj.strand || obj.track || 'Senior High';
    if (level === 'Junior High School') return 'Junior High School';
    return obj.course || 'College';
}

function academicSecondary(obj) {
    const level = obj.education_level || 'College';
    if (level === 'Senior High School') return [obj.track, obj.year_level, obj.section ? `Sec. ${obj.section}` : ''].filter(Boolean).join(' · ');
    if (level === 'Junior High School') return [obj.year_level, obj.section ? `Sec. ${obj.section}` : ''].filter(Boolean).join(' · ');
    return [obj.year_level, obj.section ? `Sec. ${obj.section}` : ''].filter(Boolean).join(' · ');
}

function schedulePrimary(obj) {
    const level = obj.education_level || 'College';
    if (level === 'Senior High School') return obj.strand || obj.track || 'Senior High';
    if (level === 'Junior High School') return 'Junior High';
    return obj.course || 'College';
}

function scheduleSecondary(obj) {
    const level = obj.education_level || 'College';
    const scope = obj.section_scope ? `Sec. ${obj.section_scope}` : 'All Sections';
    if (level === 'Senior High School') return [obj.track, obj.year_level, scope].filter(Boolean).join(' · ');
    return [obj.year_level, scope].filter(Boolean).join(' · ');
}

function scheduleMatchesStudent(s, level, course, track, strand, year, section='') {
    if ((s.education_level || 'College') !== level || s.year_level !== year) return false;
    if (level === 'College' && s.course !== course) return false;
    if (level === 'Senior High School' && (s.track !== track || s.strand !== strand)) return false;
    const scope = s.section_scope || '';
    return !scope || scope === section;
}

function initAcademicArchitecture() {
    ['reg','sched'].forEach(prefix => {
        document.querySelectorAll(`#${prefix}-level-picker input[name="education_level"]`).forEach(r => r.addEventListener('change', () => syncAcademicFields(prefix)));
        syncAcademicFields(prefix);
    });
}

/* ═══════════════════════════
   REGISTRATION FORM
═══════════════════════════ */
function clearSignaturePreview() {
    const input=document.getElementById('student_signature'); if(input) input.value='';
    const img=document.getElementById('signature-preview-img'); if(img){img.removeAttribute('src');img.style.display='none';}
    const ph=document.getElementById('signature-preview-placeholder'); if(ph) ph.style.display='';
}

function initRegistrationForm() {
    const form      = document.getElementById('reg-form');
    const rfidInput = document.getElementById('rfid_uid');
    const clearBtn  = document.getElementById('clear-form-btn');
    const mobile    = document.getElementById('parent_mobile');
    const submitBtn = document.getElementById('reg-submit-btn');
    const irrChk    = document.getElementById('is_irregular_chk');
    const courseEl  = document.getElementById('reg-course');
    const yearEl    = document.getElementById('reg-year');
    const trackEl   = document.getElementById('reg-track');
    const strandEl  = document.getElementById('reg-strand');
    const sectionEl = document.querySelector('#reg-form [name="section"]');

    // RFID flash on input
    rfidInput?.addEventListener('input', () => {
        const ind = document.getElementById('rfid-indicator');
        if (ind) { ind.style.color = '#16a34a'; setTimeout(() => ind.style.color = '', 600); }
    });
    rfidInput?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); document.querySelector('[name="student_id_no"]')?.focus(); }
    });

    // Mobile format
    mobile?.addEventListener('focus', () => { if (!mobile.value) mobile.value = '+63'; });
    mobile?.addEventListener('input', () => {
        let v = mobile.value.replace(/[^\d+]/g,'');
        if (!v.startsWith('+63')) v = '+63' + v.replace(/\D/g,'');
        if (v.length > 13) v = v.slice(0,13);
        mobile.value = v;
    });


    // Schedule preview based on the selected academic hierarchy
    function updateSchedPreview() {
        const level = selectedAcademicLevel('reg');
        const c = courseEl?.value || '';
        const t = trackEl?.value || '';
        const st = strandEl?.value || '';
        const y = yearEl?.value || '';
        const sec = sectionEl?.value?.trim() || '';
        if (!y || (level==='College' && !c) || (level==='Senior High School' && (!t || !st))) return;
        const box = document.getElementById('schedule-preview-box');
        const content = document.getElementById('sched-preview-content');
        if (!box || !content) return;

        const matches = App.allSchedules.filter(s => scheduleMatchesStudent(s, level, c, t, st, y, sec));
        const found = matches.sort((a,b)=>(b.section_scope?1:0)-(a.section_scope?1:0))[0];
        box.style.display = 'block';
        const label = level==='College' ? `${c} ${y}` : level==='Senior High School' ? `${st} ${y}` : y;
        if (found) {
            const days = found.days || [];
            content.innerHTML = `
                <div class="preview-academic-tag"><i class="ph-fill ph-graduation-cap"></i>${esc(level)} · ${esc(label)}${found.section_scope?` · Sec. ${esc(found.section_scope)}`:''}</div>
                <div class="preview-days">${days.map(d=>`<span class="preview-day-tag">${d}</span>`).join('')}</div>
                <div class="preview-times">✅ On Time: before <strong>${fmtScheduleTime(found.ontime_cutoff,'07:30')}</strong> &nbsp;|&nbsp; ⚠️ Entry until <strong>${fmtScheduleTime(found.late_cutoff,'08:30')}</strong></div>`;
        } else {
            content.innerHTML = `<p class="preview-no-sched">No schedule configured for <strong>${esc(label)}</strong> yet.</p>`;
        }
    }

    [courseEl,yearEl,trackEl,strandEl,sectionEl].forEach(el => el?.addEventListener('change', () => {
        if (!App.allSchedules.length) fetch('index.php?action=get_schedules').then(r=>r.json()).then(d=>{App.allSchedules=d;updateSchedPreview();});
        else updateSchedPreview();
    }));
    document.querySelectorAll('#reg-level-picker input[name="education_level"]').forEach(el => el.addEventListener('change', updateSchedPreview));

    // Clear
    clearBtn?.addEventListener('click', () => {
        form?.reset();
        clearPhotoPreview(); clearSignaturePreview();
        if (irrBox) irrBox.style.display = 'none';
        document.getElementById('schedule-preview-box').style.display = 'none';
        rfidInput?.focus();
        showToast('Form Cleared','','info');
    });

    // Submit
    form?.addEventListener('submit', async e => {
        e.preventDefault();
        if (!validateRegForm()) return;
        setLoading(submitBtn, true, 'Registering...');
        try {
            const fd = new FormData(form);
            const photo = document.getElementById('student_photo');
            if (photo?.files[0]) fd.set('student_photo', photo.files[0]);
            const signature = document.getElementById('student_signature');
            if (signature?.files[0]) fd.set('student_signature', signature.files[0]);
            const res  = await fetch('index.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
            const data = await res.json();
            if (data.status === 'success') {
                showToast('Registered!', data.message, 'success');
                addRegHistory(fd.get('full_name'), fd.get('course') || fd.get('strand') || fd.get('education_level'), fd.get('year_level'));
                form.reset(); clearPhotoPreview(); clearSignaturePreview();
                if (irrBox) irrBox.style.display = 'none';
                document.getElementById('schedule-preview-box').style.display = 'none';
                rfidInput?.focus();
            } else if (data.status === 'error') {
                showToast('Failed', data.message, 'error');
            } else {
                showToast('Notice', data.message, 'info');
            }
        } catch { showToast('Network Error','Check connection.','error'); }
        finally  { setLoading(submitBtn, false, '<i class="ph ph-user-plus"></i> Register Student'); }
    });
}

function validateRegForm() {
    const v = id => document.getElementById(id)?.value?.trim();
    const q = n  => document.querySelector(`[name="${n}"]`)?.value?.trim();
    if (!v('rfid_uid'))        { showToast('RFID Required','Scan the card first.','warning'); return false; }
    if (!q('student_id_no'))   { showToast('Student ID Required','','warning'); return false; }
    if (!q('full_name'))       { showToast('Name Required','','warning'); return false; }
    const level = document.querySelector('#reg-level-picker input[name="education_level"]:checked')?.value || 'College';
    if (level === 'College' && !q('course')) { showToast('Course Required','Select a college course.','warning'); return false; }
    if (level === 'Senior High School' && (!q('track') || !q('strand'))) { showToast('Track / Strand Required','Select the SHS track and strand.','warning'); return false; }
    if (!q('year_level'))      { showToast(level==='College'?'Year Level Required':'Grade Level Required','Select the academic level.','warning'); return false; }
    if (!q('parent_mobile'))   { showToast('Mobile Required','','warning'); return false; }
    const mob = q('parent_mobile');
    if (mob && !/^\+63\d{10}$/.test(mob)) { showToast('Invalid Mobile','Must be +63 + 10 digits.','warning'); return false; }
    return true;
}

function addRegHistory(name, course, year) {
    App.regHistory.unshift({ name, course, year, time: new Date() });
    if (App.regHistory.length > 5) App.regHistory.pop();
    const el = document.getElementById('reg-history-list');
    if (!el) return;
    el.innerHTML = '';
    App.regHistory.forEach(h => {
        const d = document.createElement('div');
        d.className = 'history-item';
        d.innerHTML = `
            <div class="history-avatar">${initials(h.name)}</div>
            <div>
                <div class="name">${esc(h.name)}</div>
                <div class="time">${esc(h.course)} ${esc(h.year)} · ${fmtTime(h.time)}</div>
            </div>`;
        el.appendChild(d);
    });
}

/* ═══════════════════════════
   PHOTO UPLOAD
═══════════════════════════ */
function initPhotoUpload() {
    const input  = document.getElementById('student_photo');
    const circle = document.getElementById('photo-preview-circle');
    const zone   = document.getElementById('photo-drop-zone');

    input?.addEventListener('change',  e => { if (e.target.files[0]) previewPhoto(e.target.files[0]); });
    circle?.addEventListener('click',  () => input?.click());
    zone?.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragging'); });
    zone?.addEventListener('dragleave',() => zone.classList.remove('dragging'));
    zone?.addEventListener('drop',     e => {
        e.preventDefault(); zone.classList.remove('dragging');
        const f = e.dataTransfer.files[0];
        if (f?.type.startsWith('image/')) {
            previewPhoto(f);
            const dt = new DataTransfer(); dt.items.add(f);
            if (input) input.files = dt.files;
        } else showToast('Invalid File','Use JPG or PNG.','warning');
    });
}

function previewPhoto(file) {
    if (file.size > 2*1024*1024) { showToast('Too Large','Max 2MB.','warning'); return; }
    const r = new FileReader();
    r.onload = e => {
        const img = document.getElementById('photo-preview-img');
        const ph  = document.getElementById('photo-placeholder');
        if (img) { img.src = e.target.result; img.style.display = 'block'; }
        if (ph)  ph.style.display = 'none';
    };
    r.readAsDataURL(file);
}

function clearPhotoPreview() {
    const img = document.getElementById('photo-preview-img');
    const ph  = document.getElementById('photo-placeholder');
    const inp = document.getElementById('student_photo');
    if (img) { img.src=''; img.style.display='none'; }
    if (ph)  ph.style.display = '';
    if (inp) inp.value = '';
}

/* ═══════════════════════════
   STUDENT RECORDS
═══════════════════════════ */
function syncRecordsAcademicFilters() {
    const level = document.getElementById('records-level')?.value || '';
    const group = document.getElementById('filter-group');
    const year = document.getElementById('filter-year');
    if (group) {
        if (level === 'College') group.innerHTML = `<option value="">All Courses</option>` + COURSES.map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');
        else if (level === 'Senior High School') group.innerHTML = `<option value="">All Strands</option>` + SHS_STRANDS.map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');
        else group.innerHTML = `<option value="">All Academic Groups</option>`;
        group.disabled = !['College','Senior High School'].includes(level);
    }
    if (year) {
        let years = level ? (ACADEMIC_YEARS[level] || []) : [...ACADEMIC_YEARS.College,...ACADEMIC_YEARS['Senior High School'],...ACADEMIC_YEARS['Junior High School']];
        year.innerHTML = `<option value="">${level==='College'?'All Year Levels':level?'All Grade Levels':'All Years / Grades'}</option>` + [...new Set(years)].map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');
    }
}

function fetchStudents() {
    const tbody = document.getElementById('records-tbody');
    const badge = document.getElementById('records-count');
    if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</td></tr>`;
    const s=document.getElementById('records-search')?.value.trim()||'';
    const level=document.getElementById('records-level')?.value||'';
    const group=document.getElementById('filter-group')?.value||'';
    const fy=document.getElementById('filter-year')?.value||'';
    const fi=document.getElementById('filter-irregular')?.value??'-1';
    const url=`index.php?action=get_students&s=${encodeURIComponent(s)}&level=${encodeURIComponent(level)}&group=${encodeURIComponent(group)}&year=${encodeURIComponent(fy)}&irreg=${fi}`;
    fetch(url).then(r=>r.json()).then(data=>{App.allStudents=data;renderRecordsTable(data,tbody,badge);}).catch(()=>{if(tbody)tbody.innerHTML=`<tr><td colspan="9" class="table-empty"><p>Failed to load.</p></td></tr>`;});
}

function renderRecordsTable(students, tbody, badge) {
    if (!tbody) return;
    if (badge) badge.textContent=`${students.length} student${students.length!==1?'s':''}`;
    if (!students.length) { tbody.innerHTML=`<tr><td colspan="9" class="table-empty"><i class="ph ph-users" style="font-size:2rem;opacity:.3;"></i><p>No students found.</p></td></tr>`; return; }
    tbody.innerHTML='';
    students.forEach(st=>{
        const inside=st.campus_state==='INSIDE';
        const last=st.last_movement_at?new Date(st.last_movement_at):null;
        const tr=document.createElement('tr');
        tr.innerHTML=`<td><div class="student-avatar" style="width:38px;height:38px;">${st.photo_path?`<img src="${esc(st.photo_path)}" alt="">`:initials(st.full_name)}</div></td>
        <td><div class="student-name">${esc(st.full_name)}</div><div class="student-meta">${esc(st.student_id_no||'')} · ${esc(st.parent_mobile||'')}</div></td>
        <td><span class="academic-level-badge level-${(st.education_level||'College').toLowerCase().replace(/[^a-z]+/g,'-')}">${esc(shortLevel(st.education_level||'College'))}</span></td>
        <td><div class="student-name">${esc(academicPrimary(st))}</div><div class="student-meta">${esc(academicSecondary(st))}</div></td>
        <td><span class="presence-badge ${inside?'inside':'outside'}"><i class="ph-fill ${inside?'ph-map-pin-area':'ph-sign-out'}"></i>${inside?'INSIDE CAMPUS':'OUTSIDE'}</span></td>
        <td>${Number(st.scan_enabled ?? 1)===1
            ? `<span class="scan-access-badge enabled"><i class="ph-fill ph-lock-key-open"></i> ENABLED</span><div class="student-meta">Denied ${parseInt(st.denied_attempt_count||0,10)}/3</div>`
            : `<span class="scan-access-badge locked"><i class="ph-fill ph-lock-key"></i> LOCKED</span><div class="student-meta scan-lock-copy">${parseInt(st.denied_attempt_count||3,10)}/3 denied • SPMO action required</div>`}</td>
        <td><div class="movement-count-cell"><strong>${parseInt(st.movements_today||0,10)}</strong><span>movement${parseInt(st.movements_today||0,10)===1?'':'s'} today</span></div></td>
        <td>${last?`<div>${movementBadge(st.last_movement)}</div><div class="student-meta movement-last-time">${last.toLocaleDateString('en-PH',{month:'short',day:'numeric'})} • ${fmtTime(last)}</div>${st.last_movement_context==='EARLY_EXIT'?`<div class="student-meta early-note">${esc(st.last_authorization_reason||'Authorized early exit')}</div>`:''}`:'<span class="student-meta">No movement recorded</span>'}</td>
        <td><div class="action-btns">${Number(st.scan_enabled ?? 1)!==1?`<button class="action-btn unlock-scan" title="SPMO: Enable IN/OUT scanning" data-id="${st.id}" data-name="${esc(st.full_name)}"><i class="ph ph-lock-key-open"></i></button>`:''}<button class="action-btn history" title="Movement History" data-id="${st.id}"><i class="ph ph-clock-counter-clockwise"></i></button><button class="action-btn edit" title="Edit" data-student='${JSON.stringify(st).replace(/'/g,"&#39;")}'><i class="ph ph-pencil-simple"></i></button><button class="action-btn delete" title="Delete" data-id="${st.id}" data-name="${esc(st.full_name)}"><i class="ph ph-trash"></i></button></div></td>`;
        tbody.appendChild(tr);
    });
    tbody.querySelectorAll('.action-btn.unlock-scan').forEach(btn=>btn.addEventListener('click',()=>reenableStudentScanning(btn.dataset.id,btn.dataset.name)));
    tbody.querySelectorAll('.action-btn.history').forEach(btn=>btn.addEventListener('click',()=>openMovementHistory(btn.dataset.id)));
    tbody.querySelectorAll('.action-btn.edit').forEach(btn=>btn.addEventListener('click',()=>openEditModal(JSON.parse(btn.dataset.student))));
    tbody.querySelectorAll('.action-btn.delete').forEach(btn=>btn.addEventListener('click',()=>openDeleteModal(btn.dataset.id,btn.dataset.name)));
}
function reenableStudentScanning(studentId, studentName){
    const name = studentName || 'this student';
    if(!confirm(`Re-enable campus IN/OUT scanning for ${name}?\n\nThis will reset the denied verification counter to 0/3. It will NOT change the student's current campus IN/OUT state.`)) return;
    const fd=new FormData();fd.append('action','reenable_student_scanning');fd.append('student_id',studentId);
    fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        showToast(data.status==='success'?'Scan Access Enabled':'Unable to Enable',data.message||'',data.status==='success'?'success':'error');
        if(data.status==='success'){fetchStudents();fetchRejectedScans();}
    }).catch(()=>showToast('Network Error','Could not update scan access.','error'));
}

function openMovementHistory(studentId){
    const st=(App.allStudents||[]).find(x=>String(x.id)===String(studentId));
    if(!st)return;
    document.getElementById('movement-history-name').textContent=st.full_name||'Movement History';
    document.getElementById('movement-history-profile').textContent=`${shortLevel(st.education_level||'College')} • ${academicPrimary(st)} • ${academicSecondary(st)}`;
    const summary=document.getElementById('movement-history-summary');
    if(summary) summary.innerHTML=`<span class="presence-badge ${st.campus_state==='INSIDE'?'inside':'outside'}">${st.campus_state==='INSIDE'?'INSIDE CAMPUS':'OUTSIDE'}</span><span>${parseInt(st.movements_today||0,10)} movement(s) today</span>${Number(st.scan_enabled??1)!==1?'<span class="scan-access-badge locked"><i class="ph-fill ph-lock-key"></i> RFID IN/OUT LOCKED — SPMO</span>':''}`;
    const list=document.getElementById('movement-history-list');
    if(list)list.innerHTML='<div class="loader-row"><div class="loader-dots"><span></span><span></span><span></span></div> Loading movement history...</div>';
    openModal('movement-history-modal');
    fetch(`index.php?action=get_student_movements&student_id=${encodeURIComponent(studentId)}&limit=200`).then(r=>r.json()).then(rows=>{
        if(!list)return;
        if(!rows.length){list.innerHTML='<div class="movement-history-empty"><i class="ph ph-clock-counter-clockwise"></i><p>No movement records yet.</p></div>';return;}
        const noOutCount=rows.filter(r=>r.record_kind==='EXCEPTION'&&r.movement_context==='NO_OUT').length;
        if(summary&&noOutCount){summary.insertAdjacentHTML('beforeend',`<span class="history-exception-count"><i class="ph-fill ph-warning-circle"></i> ${noOutCount} NO OUT exception${noOutCount===1?'':'s'}</span>`);}
        list.innerHTML=rows.map(r=>{if(r.record_kind==='EXCEPTION'){const d=new Date(`${r.exception_date}T00:00:00`),last=r.verified_at?new Date(r.verified_at):null;return `<div class="movement-timeline-item exception"><div class="movement-timeline-marker"><i class="ph-fill ph-warning-octagon"></i></div><div class="movement-timeline-body"><div class="movement-timeline-top"><span class="no-out-badge"><i class="ph-fill ph-warning-octagon"></i> NO OUT RECORDED</span> ${exceptionStatusBadge(r)}<time>${d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}</time></div><p>Last verified movement was <strong>IN${last?` at ${fmtTime(last)}`:''}</strong>. No exit time was fabricated.</p>${r.resolution_note?`<small>${esc(r.resolution_note)}</small>`:'<small>System end-of-day monitoring exception.</small>'}</div></div>`;}const d=new Date(r.verified_at);return `<div class="movement-timeline-item ${r.movement_type==='IN'?'in':'out'}"><div class="movement-timeline-marker"><i class="ph-fill ${r.movement_type==='IN'?'ph-sign-in':'ph-sign-out'}"></i></div><div class="movement-timeline-body"><div class="movement-timeline-top">${movementBadge(r.movement_type)} ${movementContextBadge(r.movement_context,r.authorization_reason)}<time>${d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})} • ${fmtTime(d)}</time></div>${r.authorization_reason?`<p><strong>Authorization:</strong> ${esc(r.authorization_reason)}</p>`:''}<small>Verified by ${esc(r.verified_by||'Guard Kiosk')}</small></div></div>`}).join('');
    }).catch(()=>{if(list)list.innerHTML='<div class="table-empty"><p>Could not load movement history.</p></div>';});
}

function initRecordsFilters() {
    let debounce;
    document.getElementById('records-search')?.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(fetchStudents,400);});
    document.getElementById('records-level')?.addEventListener('change',()=>{syncRecordsAcademicFilters();fetchStudents();});
    ['filter-group','filter-year','filter-irregular'].forEach(id=>document.getElementById(id)?.addEventListener('change',fetchStudents));
    syncRecordsAcademicFilters();
}

/* Keep the two Schedule Manager panels visually equal on desktop.
   The editor determines the height; the right-side list scrolls internally. */
function syncSchedulePanelHeights() {
    const editor = document.querySelector('.sched-editor-panel');
    const list   = document.querySelector('.sched-list-panel');
    if (!editor || !list) return;

    if (window.innerWidth <= 1200) {
        list.style.height = 'auto';
        return;
    }

    list.style.height = 'auto';
    const editorHeight = Math.ceil(editor.getBoundingClientRect().height);
    if (editorHeight > 0) list.style.height = `${editorHeight}px`;
}

function initSchedulePanelSync() {
    window.addEventListener('resize', syncSchedulePanelHeights);

    const editor = document.querySelector('.sched-editor-panel');
    if ('ResizeObserver' in window && editor) {
        const ro = new ResizeObserver(() => syncSchedulePanelHeights());
        ro.observe(editor);
    }

    requestAnimationFrame(syncSchedulePanelHeights);
}

/* ═══════════════════════════
   SCHEDULE MANAGER
═══════════════════════════ */
function fetchSchedules() {
    const el = document.getElementById('schedules-list-container');
    if (el) el.innerHTML = `<div class="loader-row"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</div>`;

    fetch('index.php?action=get_schedules')
        .then(r => r.json())
        .then(data => {
            App.allSchedules = data;
            renderSelectedScheduleLevel();
            requestAnimationFrame(syncSchedulePanelHeights);
        })
        .catch(() => { if (el) el.innerHTML = `<p style="padding:1rem;color:var(--text-muted)">Failed to load schedules.</p>`; });
}

function initScheduleLevelTabs() {
    document.querySelectorAll('#schedule-level-tabs .schedule-level-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            App.scheduleListLevel = btn.dataset.level || 'College';
            document.querySelectorAll('#schedule-level-tabs .schedule-level-tab').forEach(b => b.classList.toggle('active', b === btn));
            renderSelectedScheduleLevel();
        });
    });
}

function renderSelectedScheduleLevel() {
    const container = document.getElementById('schedules-list-container');
    if (!container) return;
    const rows = (App.allSchedules || []).filter(s => (s.education_level || 'College') === App.scheduleListLevel);
    const count = document.getElementById('schedule-level-count');
    if (count) count.textContent = rows.length;
    renderSchedulesList(rows, container);
}

function renderSchedulesList(schedules, container) {
    if (!container) return;
    if (!schedules.length) {
        container.innerHTML = `<p style="padding:1.25rem;color:var(--text-muted);font-size:.85rem;text-align:center;">No schedules for this education level yet. Use the form on the left to create one.</p>`;
        return;
    }
    container.innerHTML = '';
    schedules.forEach(s => {
        const days = s.days || [];
        const daysHtml = days.map(d => `<span class="sched-day-tag${d===App.todayDay?' today-active':''}">${d.substring(0,3)}</span>`).join('');
        const lunch = String(s.lunch_enabled ?? '0') === '1';
        const card = document.createElement('div');
        card.className = 'sched-card';
        card.innerHTML = `
            <div class="sched-card-left">
                <div class="sched-course-label"><span class="sched-level-mini">${esc(shortLevel(s.education_level || 'College'))}</span><span class="sched-course-badge">${esc(schedulePrimary(s))}</span>${esc(s.year_level)}${s.section_scope?`<span class="sched-section-chip">Sec. ${esc(s.section_scope)}</span>`:`<span class="sched-section-chip all">All Sections</span>`}</div>
                <div class="sched-days-row">${daysHtml||'<span style="font-size:.75rem;color:var(--text-muted)">No days set</span>'}</div>
                <div style="font-size:.7rem;color:var(--text-muted);margin-top:.45rem;line-height:1.55;">
                    IN ${fmtScheduleTime(s.time_in_start,'06:00')} → ${fmtScheduleTime(s.late_cutoff,'08:30')} · On-time ≤ ${fmtScheduleTime(s.ontime_cutoff,'07:30')}<br>
                    ${lunch ? `Lunch OUT ${fmtScheduleTime(s.lunch_out_start,'11:30')}–${fmtScheduleTime(s.lunch_out_end,'12:15')} · IN ${fmtScheduleTime(s.lunch_in_start,'12:15')}–${fmtScheduleTime(s.lunch_in_end,'13:15')}<br>` : 'Lunch break disabled<br>'}
                    Final OUT ${fmtScheduleTime(s.time_out_start || s.timeout_cutoff,'15:00')}–${fmtScheduleTime(s.time_out_end,'18:00')}
                </div>
            </div>
            <div class="sched-card-right">
                <div class="sched-times">🟢 ${fmtScheduleTime(s.ontime_cutoff,'07:30')}<br>🟡 ${fmtScheduleTime(s.late_cutoff,'08:30')}<br>${lunch?'🍱 Lunch ON':'➖ No Lunch'}<br>🌙 ${fmtScheduleTime(s.time_out_start || s.timeout_cutoff,'15:00')}</div>
                <div style="display:flex;flex-direction:column;gap:.4rem;">
                    <button class="sched-edit-btn" data-course="${esc(schedulePrimary(s))}" data-year="${esc(s.year_level)}" data-days='${JSON.stringify(days)}'>Edit</button>
                    <button class="sched-edit-btn sched-subjects-btn" data-id="${s.id}" data-course="${esc(s.course)}" data-year="${esc(s.year_level)}"><i class="ph ph-books"></i> Subjects</button>
                </div>
            </div>`;
        card.querySelector('.sched-edit-btn:not(.sched-subjects-btn)').addEventListener('click', () => loadScheduleIntoForm(s));
        card.querySelector('.sched-subjects-btn').addEventListener('click', e => openSubjectsModal(e.currentTarget.dataset.id, e.currentTarget.dataset.course, e.currentTarget.dataset.year, false));
        container.appendChild(card);
    });
}

function fmtScheduleTime(v, fallback='--') {
    if (v == null || v === '') return fallback;
    const str = String(v).trim();
    const m = str.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
    if (!m) return fallback;
    const h24 = Number(m[1]);
    const min = m[2];
    if (!Number.isFinite(h24) || h24 < 0 || h24 > 23) return fallback;
    const ap = h24 >= 12 ? 'PM' : 'AM';
    const h12 = (h24 % 12) || 12;
    return `${h12.toString().padStart(2,'0')}:${min} ${ap}`;
}

function fmtScheduleInput(v, fallback='') {
    if (v == null || v === '') return fallback;
    const str = String(v).trim();
    const m = str.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (!m) return fallback;
    return `${m[1].padStart(2,'0')}:${m[2]}`;
}

function loadScheduleIntoForm(s) {
    const level = s.education_level || 'College';
    const radio = document.querySelector(`#sched-level-picker input[value="${CSS.escape(level)}"]`); if (radio) radio.checked = true;
    syncAcademicFields('sched');
    document.getElementById('sched-course').value = s.course || '';
    document.getElementById('sched-track').value = s.track || '';
    document.getElementById('sched-strand').value = s.strand || '';
    setYearOptions(document.getElementById('sched-year'), level, s.year_level || '');
    document.getElementById('sched-section-scope').value = s.section_scope || '';
    document.getElementById('sched-in-start').value = fmtScheduleInput(s.time_in_start,'06:00');
    document.getElementById('sched-ontime').value = fmtScheduleInput(s.ontime_cutoff,'07:30');
    document.getElementById('sched-late').value = fmtScheduleInput(s.late_cutoff,'08:30');
    const lunch = String(s.lunch_enabled ?? '0') === '1';
    document.getElementById('sched-lunch-enabled').checked = lunch;
    document.getElementById('sched-lunch-out-start').value = fmtScheduleInput(s.lunch_out_start,'11:30');
    document.getElementById('sched-lunch-out-end').value = fmtScheduleInput(s.lunch_out_end,'12:15');
    document.getElementById('sched-lunch-in-start').value = fmtScheduleInput(s.lunch_in_start,'12:15');
    document.getElementById('sched-lunch-in-end').value = fmtScheduleInput(s.lunch_in_end,'13:15');
    document.getElementById('sched-out-start').value = fmtScheduleInput(s.time_out_start || s.timeout_cutoff,'15:00');
    document.getElementById('sched-out-end').value = fmtScheduleInput(s.time_out_end,'18:00');
    document.getElementById('sched-lunch-fields').style.display = lunch ? '' : 'none';
    const days = Array.isArray(s.days) ? s.days : [];
    document.querySelectorAll('#sched-day-picker input[type=checkbox]').forEach(cb => cb.checked = days.includes(cb.value));
    document.getElementById('sched-course').scrollIntoView({ behavior:'smooth', block:'center' });
    showToast('Schedule Loaded', `Editing ${schedulePrimary(s)} ${s.year_level}`, 'info');
}

/* ═══════════════════════════
   SUBJECTS PER SCHEDULE
═══════════════════════════ */
function openSubjectsModal(scheduleId, course, year, readOnly) {
    document.getElementById('subj-schedule-id').value = scheduleId;
    document.getElementById('subjects-modal-title').textContent = `${course} — ${year}`;
    resetSubjectForm();
    const form = document.getElementById('subject-form');
    if (form) form.style.display = readOnly ? 'none' : '';
    document.querySelectorAll('#subjects-tbody .subj-edit-btn, #subjects-tbody .subj-delete-btn').forEach(b => b.style.display = readOnly ? 'none' : '');
    fetchSubjects(scheduleId, readOnly);
    openModal('subjects-modal');
}

function fetchSubjects(scheduleId, readOnly) {
    const tbody = document.getElementById('subjects-tbody');
    tbody.innerHTML = `<tr><td colspan="7" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</td></tr>`;
    fetch(`index.php?action=get_subjects&schedule_id=${scheduleId}`)
        .then(r => r.json())
        .then(data => renderSubjectsTable(data, readOnly))
        .catch(() => { tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Failed to load subjects.</td></tr>`; });
}

function renderSubjectsTable(subjects, readOnly) {
    const tbody = document.getElementById('subjects-tbody');
    const cols = readOnly ? 6 : 7;

    // Hide or show the Action column header depending on readOnly
    const actionTh = tbody.closest('table')?.querySelector('thead tr th:last-child');
    if (actionTh) actionTh.style.display = readOnly ? 'none' : '';

    if (!subjects.length) {
        tbody.innerHTML = `<tr><td colspan="${cols}" class="table-empty">No subjects added yet.</td></tr>`;
        return;
    }
    tbody.innerHTML = '';
    subjects.forEach(s => {
        const tr = document.createElement('tr');
        const time = (s.time_start && s.time_end)
            ? `${s.time_start.substring(0,5)} - ${s.time_end.substring(0,5)}`
            : (s.time_start ? s.time_start.substring(0,5) : '—');
        tr.innerHTML = `
            <td>${esc(s.subject_name)}</td>
            <td>${esc(s.subject_code||'—')}</td>
            <td>${esc(s.instructor||'—')}</td>
            <td>${esc(s.room||'—')}</td>
            <td>${esc(s.day||'—')}</td>
            <td>${time}</td>
            ${readOnly ? '' : `<td style="display:flex;gap:.4rem;">
                <button class="btn-icon subj-edit-btn" title="Edit"><i class="ph ph-pencil-simple"></i></button>
                <button class="btn-icon subj-delete-btn" title="Delete"><i class="ph ph-trash"></i></button>
            </td>`}`;
        if (!readOnly) {
            tr.querySelector('.subj-edit-btn').addEventListener('click', () => fillSubjectForm(s));
            tr.querySelector('.subj-delete-btn').addEventListener('click', () => deleteSubject(s.id, document.getElementById('subj-schedule-id').value));
        }
        tbody.appendChild(tr);
    });
}

function fillSubjectForm(s) {
    document.getElementById('subj-id').value          = s.id;
    document.getElementById('subj-name').value        = s.subject_name || '';
    document.getElementById('subj-code').value        = s.subject_code || '';
    document.getElementById('subj-instructor').value  = s.instructor || '';
    document.getElementById('subj-room').value        = s.room || '';
    document.getElementById('subj-day').value         = s.day || '';
    document.getElementById('subj-time-start').value  = s.time_start ? s.time_start.substring(0,5) : '';
    document.getElementById('subj-time-end').value    = s.time_end   ? s.time_end.substring(0,5)   : '';
    document.getElementById('subj-name').scrollIntoView({ behavior:'smooth', block:'center' });
}

function resetSubjectForm() {
    document.getElementById('subject-form')?.reset();
    document.getElementById('subj-id').value = '0';
}

function deleteSubject(id, scheduleId) {
    const fd = new FormData();
    fd.append('action','delete_subject');
    fd.append('id', id);
    fetch('index.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.status==='success'?'Deleted':'Notice', data.message, data.status);
            fetchSubjects(scheduleId);
        })
        .catch(() => showToast('Error','Could not delete subject.','error'));
}

function initSubjectForm() {
    document.getElementById('subjects-modal-close')?.addEventListener('click', () => closeModal('subjects-modal'));
    document.getElementById('subjects-modal')?.addEventListener('click', e => { if (e.target.id==='subjects-modal') closeModal('subjects-modal'); });
    document.getElementById('subj-clear-btn')?.addEventListener('click', resetSubjectForm);

    document.getElementById('subject-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const scheduleId = document.getElementById('subj-schedule-id').value;
        const saveBtn    = document.getElementById('subj-save-btn');
        const name = document.getElementById('subj-name').value.trim();
        if (!name) { showToast('Required','Subject name is required.','warning'); return; }

        setLoading(saveBtn, true, 'Saving...');
        try {
            const fd = new FormData();
            fd.append('action','save_subject');
            fd.append('schedule_id', scheduleId);
            fd.append('id', document.getElementById('subj-id').value);
            fd.append('subject_name', name);
            fd.append('subject_code', document.getElementById('subj-code').value.trim());
            fd.append('instructor',   document.getElementById('subj-instructor').value.trim());
            fd.append('room',         document.getElementById('subj-room').value.trim());
            fd.append('day',          document.getElementById('subj-day').value);
            fd.append('time_start',   document.getElementById('subj-time-start').value);
            fd.append('time_end',     document.getElementById('subj-time-end').value);

            const res  = await fetch('index.php', { method:'POST', body: fd });
            const data = await res.json();
            showToast(data.status==='success'?'Saved!':'Notice', data.message, data.status);
            if (data.status==='success'||data.status==='info') {
                resetSubjectForm();
                fetchSubjects(scheduleId);
            }
        } catch { showToast('Error','Could not save subject.','error'); }
        finally  { setLoading(saveBtn, false, '<i class="ph ph-floppy-disk"></i> Save Subject'); }
    });
}

function initScheduleForm() {
    const form = document.getElementById('sched-form');
    const clearBtn = document.getElementById('sched-clear-btn');
    const reloadBtn = document.getElementById('reload-schedules-btn');
    const saveBtn = document.getElementById('sched-save-btn');
    const lunchChk = document.getElementById('sched-lunch-enabled');
    const lunchFields = document.getElementById('sched-lunch-fields');

    const syncLunchVisibility = () => { if (lunchFields) lunchFields.style.display = lunchChk?.checked ? '' : 'none'; };
    lunchChk?.addEventListener('change', syncLunchVisibility);
    syncLunchVisibility();

    clearBtn?.addEventListener('click', () => {
        form?.reset();
        document.querySelector('#sched-level-picker input[value="College"]').checked = true; syncAcademicFields('sched');
        document.querySelectorAll('#sched-day-picker input').forEach(c => c.checked = false);
        syncLunchVisibility();
    });

    reloadBtn?.addEventListener('click', () => fetchSchedules());

    form?.addEventListener('submit', async e => {
        e.preventDefault();
        const level = selectedAcademicLevel('sched');
        const course = document.getElementById('sched-course')?.value || '';
        const track = document.getElementById('sched-track')?.value || '';
        const strand = document.getElementById('sched-strand')?.value || '';
        const year = document.getElementById('sched-year')?.value || '';
        const sectionScope = document.getElementById('sched-section-scope')?.value?.trim() || '';
        const days = [...document.querySelectorAll('#sched-day-picker input:checked')].map(c => c.value);
        const tin = document.getElementById('sched-in-start')?.value || '06:00';
        const ontime = document.getElementById('sched-ontime')?.value || '07:30';
        const late = document.getElementById('sched-late')?.value || '08:30';
        const lunchEnabled = !!lunchChk?.checked;
        const los = document.getElementById('sched-lunch-out-start')?.value || '11:30';
        const loe = document.getElementById('sched-lunch-out-end')?.value || '12:15';
        const lis = document.getElementById('sched-lunch-in-start')?.value || '12:15';
        const lie = document.getElementById('sched-lunch-in-end')?.value || '13:15';
        const tos = document.getElementById('sched-out-start')?.value || '15:00';
        const toe = document.getElementById('sched-out-end')?.value || '18:00';

        if (!year || (level==='College' && !course) || (level==='Senior High School' && (!track || !strand))) { showToast('Required','Complete the academic classification.','warning'); return; }
        if (!days.length) { showToast('Select Days','Pick at least one class day.','warning'); return; }
        if (!(tin <= ontime && ontime < late)) { showToast('Invalid Entry Times','Use: Time-IN Start ≤ On-Time Cutoff < Late/Entry Cutoff.','warning'); return; }
        if (!(tos < toe)) { showToast('Invalid Time-OUT','Time-OUT Start must be before Time-OUT End.','warning'); return; }
        if (lunchEnabled && !(late < los && los < loe && loe <= lis && lis < lie && lie < tos)) {
            showToast('Invalid Lunch Times','Use: Entry cutoff < Lunch OUT window ≤ Lunch IN window < final Time-OUT.','warning'); return;
        }

        setLoading(saveBtn, true, 'Saving...');
        try {
            const fd = new FormData();
            fd.append('action','save_schedule');
            fd.append('education_level',level); fd.append('course',course); fd.append('track',track); fd.append('strand',strand); fd.append('year_level',year); fd.append('section_scope',sectionScope);
            fd.append('time_in_start',tin+':00'); fd.append('ontime_cutoff',ontime+':00'); fd.append('late_cutoff',late+':00');
            fd.append('lunch_enabled',lunchEnabled?'1':'0');
            fd.append('lunch_out_start',los+':00'); fd.append('lunch_out_end',loe+':00');
            fd.append('lunch_in_start',lis+':00'); fd.append('lunch_in_end',lie+':00');
            fd.append('time_out_start',tos+':00'); fd.append('time_out_end',toe+':00');
            days.forEach(d => fd.append('days[]',d));
            const res = await fetch('index.php',{method:'POST',body:fd});
            const data = await res.json();
            showToast(data.status==='success'?'Saved!':'Notice',data.message,data.status);
            if (data.status==='success'||data.status==='info') { fetchSchedules(); fetchTodaySchedules(); form.reset(); document.querySelectorAll('#sched-day-picker input').forEach(c=>c.checked=false); syncLunchVisibility(); }
        } catch { showToast('Error','Could not save schedule.','error'); }
        finally { setLoading(saveBtn,false,'<i class="ph ph-floppy-disk"></i> Save Schedule'); }
    });
}

/* ═══════════════════════════
   MODALS
═══════════════════════════ */
function initModals() {
    // Edit
    document.getElementById('modal-close')?.addEventListener('click',  () => closeModal('edit-modal'));
    document.getElementById('modal-cancel')?.addEventListener('click', () => closeModal('edit-modal'));
    document.getElementById('edit-modal')?.addEventListener('click', e => { if (e.target.id==='edit-modal') closeModal('edit-modal'); });

    document.getElementById('edit-level')?.addEventListener('change', () => syncEditAcademicFields());

    document.getElementById('edit-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const saveBtn = e.target.querySelector('[type="submit"]');
        setLoading(saveBtn, true, 'Saving...');
        try {
            const fd = new FormData();
            fd.append('action', 'update_student');
            fd.append('student_id',     document.getElementById('edit-id').value);
            fd.append('student_id_no',  document.getElementById('edit-idno').value);
            fd.append('full_name',      document.getElementById('edit-name').value);
            fd.append('education_level',document.getElementById('edit-level').value);
            fd.append('course',         document.getElementById('edit-course').value);
            fd.append('track',          document.getElementById('edit-track').value);
            fd.append('strand',         document.getElementById('edit-strand').value);
            fd.append('year_level',     document.getElementById('edit-year').value);
            fd.append('section',        document.getElementById('edit-section').value);
            fd.append('parent_mobile',  document.getElementById('edit-mobile').value);
            fd.append('guardian_name', document.getElementById('edit-guardian-name')?.value || '');
            fd.append('guardian_address', document.getElementById('edit-guardian-address')?.value || '');
            const sigFile=document.getElementById('edit-student-signature')?.files?.[0]; if(sigFile) fd.append('student_signature',sigFile);
            const irrChk = document.getElementById('edit-is-irregular');
            fd.append('is_irregular', irrChk?.checked ? '1' : '0');
            const res  = await fetch('index.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd });
            const data = await res.json();
            if (data.status === 'success' || data.status === 'info') {
                showToast('Profile Updated', data.message || 'Changes saved successfully.', 'success');
                closeModal('edit-modal');
                fetchStudents();
            } else {
                showToast('Error', data.message || 'Could not save changes.', 'error');
            }
        } catch { showToast('Network Error', 'Check connection.', 'error'); }
        finally  { setLoading(saveBtn, false, '<i class="ph ph-floppy-disk"></i> Save Changes'); }
    });

    // Delete
    document.getElementById('delete-modal-close')?.addEventListener('click', () => closeModal('delete-modal'));
    document.getElementById('delete-cancel')?.addEventListener('click',      () => closeModal('delete-modal'));
    document.getElementById('delete-modal')?.addEventListener('click', e => { if (e.target.id==='delete-modal') closeModal('delete-modal'); });

    document.getElementById('delete-confirm')?.addEventListener('click', async () => {
        if (!App.deleteTargetId) return;
        const btn = document.getElementById('delete-confirm');
        setLoading(btn, true, 'Deleting...');
        try {
            const fd = new FormData();
            fd.append('action','delete'); fd.append('student_id', App.deleteTargetId);
            await fetch('index.php', { method:'POST', body: fd });
            showToast('Deleted', `${App.deleteTargetName} removed.`, 'success');
            closeModal('delete-modal');
            fetchStudents();
        } catch { showToast('Error','Could not delete.','error'); }
        finally  { setLoading(btn, false, '<i class="ph ph-trash"></i> Yes, Delete'); }
    });

    // Invalid RFID
    document.getElementById('rfid-alert-close')?.addEventListener('click', () => {
        closeModal('invalid-rfid-modal');
        document.querySelector('.nav-item[data-section="registration"]')?.click();
    });
}

function syncEditAcademicFields(currentYear='') {
    const level=document.getElementById('edit-level')?.value||'College';
    document.getElementById('edit-course-group').style.display=level==='College'?'':'none';
    document.getElementById('edit-track-group').style.display=level==='Senior High School'?'':'none';
    document.getElementById('edit-strand-group').style.display=level==='Senior High School'?'':'none';
    const label=document.getElementById('edit-year-label'); if(label) label.textContent=level==='College'?'Year Level *':'Grade Level *';
    setYearOptions(document.getElementById('edit-year'),level,currentYear||document.getElementById('edit-year')?.value||'');
    if(level!=='College') document.getElementById('edit-course').value='';
    if(level!=='Senior High School'){document.getElementById('edit-track').value='';document.getElementById('edit-strand').value='';}
}

function openEditModal(s) {
    document.getElementById('edit-id').value=s.id; document.getElementById('edit-rfid').value=s.rfid_uid; document.getElementById('edit-idno').value=s.student_id_no||''; document.getElementById('edit-name').value=s.full_name;
    const level=s.education_level||'College'; document.getElementById('edit-level').value=level; syncEditAcademicFields(s.year_level||'');
    if(level==='College') document.getElementById('edit-course').value=s.course||'';
    if(level==='Senior High School'){document.getElementById('edit-track').value=s.track||'';document.getElementById('edit-strand').value=s.strand||'';}
    document.getElementById('edit-section').value=s.section||''; document.getElementById('edit-mobile').value=s.parent_mobile||'';
    const egn=document.getElementById('edit-guardian-name'); if(egn) egn.value=s.guardian_name||'';
    const ega=document.getElementById('edit-guardian-address'); if(ega) ega.value=s.guardian_address||'';
    const esig=document.getElementById('edit-signature-preview-img'), esph=document.getElementById('edit-signature-preview-placeholder');
    const esrc=resolveStudentPhoto(s.signature_path); if(esig){esig.src=esrc;esig.style.display=esrc?'block':'none';} if(esph)esph.style.display=esrc?'none':'';
    const esinput=document.getElementById('edit-student-signature'); if(esinput)esinput.value='';
    const irrChk=document.getElementById('edit-is-irregular'); if(irrChk) irrChk.checked=s.is_irregular==1; openModal('edit-modal');
}

function openDeleteModal(id, name) {
    App.deleteTargetId = id; App.deleteTargetName = name;
    document.getElementById('delete-student-name').textContent = name;
    openModal('delete-modal');
}

function openModal(id)  { document.getElementById(id)?.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); document.body.style.overflow = ''; }

/* ═══════════════════════════
   MONITORING REPORTS
═══════════════════════════ */
function initReports() {
    document.querySelectorAll('.report-mode-btn').forEach(btn=>btn.addEventListener('click',()=>setReportMode(btn.dataset.mode||'movement')));
    const level=document.getElementById('report-level');
    level?.addEventListener('change',()=>{syncReportAcademicFilters();loadReportClusters();});
    syncReportAcademicFilters(); loadReportClusterEvents();
    document.getElementById('report-cluster-event')?.addEventListener('change',()=>{
        loadReportClusters();
        const sel=document.getElementById('report-cluster-event');
        const id=Number(sel?.value||0);
        if(id && Array.isArray(App.intramuralEvents)){
            const ev=App.intramuralEvents.find(e=>Number(e.id)===id);
            if(ev){
                const from=document.getElementById('report-from'),to=document.getElementById('report-to');
                if(from && ev.start_date)from.value=ev.start_date;
                if(to && ev.end_date)to.value=ev.end_date;
                if(App.reportMode==='event')fetchReport();
            }
        }
    });
    document.getElementById('generate-report-btn')?.addEventListener('click',fetchReport);
    document.getElementById('export-csv-btn')?.addEventListener('click',exportXLSX);
    document.getElementById('reset-report-btn')?.addEventListener('click',resetReportFilters);
    document.getElementById('report-search')?.addEventListener('keydown',e=>{if(e.key==='Enter')fetchReport();});
    document.getElementById('movement-history-close')?.addEventListener('click',()=>closeModal('movement-history-modal'));
    document.getElementById('movement-history-modal')?.addEventListener('click',e=>{if(e.target.id==='movement-history-modal')closeModal('movement-history-modal');});
    setReportMode('movement');
}
function setReportMode(mode){
    App.reportMode=['movement','exception','event'].includes(mode)?mode:'movement';
    document.querySelectorAll('.report-mode-btn').forEach(b=>{const active=b.dataset.mode===App.reportMode;b.classList.toggle('active',active);b.setAttribute('aria-selected',active?'true':'false');});
    document.querySelectorAll('.report-movement-only').forEach(e=>e.style.display=App.reportMode==='movement'?'':'none');
    document.querySelectorAll('.report-exception-only').forEach(e=>e.style.display=App.reportMode==='exception'?'':'none');
    document.querySelectorAll('.report-event-only').forEach(e=>e.style.display=App.reportMode==='event'?'':'none');
    document.querySelectorAll('.report-cluster-filter').forEach(e=>e.style.display=App.reportMode==='exception'?'none':'');
    const title=document.getElementById('report-scope-title'),copy=document.getElementById('report-scope-copy'),result=document.getElementById('report-results-title'),sub=document.getElementById('report-results-subtitle'),thead=document.getElementById('report-thead');
    if(App.reportMode==='movement'){
        if(title)title.textContent='Movement Investigation'; if(copy)copy.textContent='Search exact verified physical IN/OUT history by date, student, academic group, context, event, or cluster.'; if(result)result.textContent='Campus Movement Logs'; if(sub)sub.textContent='Actual verified campus movement only — no inferred or fabricated OUT records.';
        if(thead)thead.innerHTML='<tr><th>#</th><th>Student</th><th>Academic Group</th><th>Date / Time</th><th>Movement</th><th>Context</th><th>Authorization / Verification</th><th>Event</th></tr>';
    }else if(App.reportMode==='exception'){
        if(title)title.textContent='Daily Monitoring Exceptions'; if(copy)copy.textContent='Review completed days where the student entered campus but no verified OUT was recorded.'; if(result)result.textContent='Daily Exception Records'; if(sub)sub.textContent='NO OUT is an anomaly record only. The system never invents an exit time.';
        if(thead)thead.innerHTML='<tr><th>#</th><th>Student</th><th>Academic Group</th><th>Date</th><th>Exception</th><th>Last Recorded Movement</th><th>Status</th><th>Resolution Note</th></tr>';
    }else{
        if(title)title.textContent='Event Attendance'; if(copy)copy.textContent='Review time-bound Intramurals attendance separately from permanent campus movement logs.'; if(result)result.textContent='Event Attendance Results'; if(sub)sub.textContent='Event attendance uses the selected Intramurals schedule and roster.';
        if(thead)thead.innerHTML='<tr><th>#</th><th>Student</th><th>Academic Group</th><th>Date</th><th>Time-IN</th><th>Time-OUT</th><th>Status</th><th>Type</th></tr>';
    }
    App.reportData=[]; const body=document.getElementById('report-tbody'); if(body)body.innerHTML='<tr><td colspan="8" class="table-empty"><p>Select filters and click Generate Report.</p></td></tr>';
    const badge=document.getElementById('report-count');if(badge)badge.textContent='0 records';
    const sum=document.getElementById('report-summary-row'); if(sum)sum.style.display='none';
}
function resetReportFilters(){
    const set=(id,v='')=>{const e=document.getElementById(id);if(e)e.value=v;};
    set('report-search');set('report-level');set('report-group');set('report-year');set('report-section');set('report-movement');set('report-context');set('report-cluster-event');set('report-cluster');set('report-status');set('report-exception-status');
    const today=new Date(),from=new Date();from.setDate(today.getDate()-7);const iso=d=>{const p=n=>String(n).padStart(2,'0');return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;};set('report-from',iso(from));set('report-to',iso(today));
    syncReportAcademicFilters();loadReportClusters();setReportMode(App.reportMode||'movement');showToast('Filters Reset','Report filters returned to the default 7-day view.','success');
}
function syncReportAcademicFilters() {
    const level=document.getElementById('report-level')?.value||'';
    const groupWrap=document.getElementById('report-group-wrap'), group=document.getElementById('report-group'), groupLabel=document.getElementById('report-group-label'), year=document.getElementById('report-year'), yearLabel=document.getElementById('report-year-label');
    if(group){
        if(level===''){group.innerHTML='<option value="">All Academic Groups</option>';if(groupWrap)groupWrap.style.display='none';}
        else if(level==='College'){group.innerHTML='<option value="">All Courses</option>'+COURSES.map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');if(groupLabel)groupLabel.innerHTML='<i class="ph ph-book-open"></i> Course / Program';if(groupWrap)groupWrap.style.display='';}
        else if(level==='Senior High School'){group.innerHTML='<option value="">All Strands</option>'+SHS_STRANDS.map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');if(groupLabel)groupLabel.innerHTML='<i class="ph ph-path"></i> Strand';if(groupWrap)groupWrap.style.display='';}
        else{group.innerHTML='<option value="">All Junior High</option>';if(groupWrap)groupWrap.style.display='none';}
    }
    if(year){let years=level?(ACADEMIC_YEARS[level]||[]):[...ACADEMIC_YEARS.College,...ACADEMIC_YEARS['Senior High School'],...ACADEMIC_YEARS['Junior High School']];year.innerHTML=`<option value="">${level==='College'?'All Year Levels':level?'All Grade Levels':'All Years / Grades'}</option>`+[...new Set(years)].map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');}
    if(yearLabel)yearLabel.innerHTML=`<i class="ph ph-graduation-cap"></i> ${level==='College'?'Year Level':level?'Grade Level':'Year / Grade'}`;
}
function fetchReport(){
    const from=document.getElementById('report-from')?.value,to=document.getElementById('report-to')?.value;
    if(!from||!to){showToast('Dates Required','Select both From and To dates.','warning');return;} if(new Date(from)>new Date(to)){showToast('Invalid Range','Start date must be before the end date.','warning');return;}
    const eventFilter=document.getElementById('report-cluster-event')?.value||'';
    const eventStatus=document.getElementById('report-status')?.value||'';
    if(App.reportMode==='event' && eventStatus==='Absent' && !eventFilter){showToast('Select an Event','Event absence is computed from a specific Intramurals event date range and roster.','warning');return;}
    const action=App.reportMode==='event'?'get_event_report':App.reportMode==='exception'?'get_exception_report':'get_report';
    const params=new URLSearchParams({action,date_from:from,date_to:to,search:document.getElementById('report-search')?.value?.trim()||'',level:document.getElementById('report-level')?.value||'',group:document.getElementById('report-group')?.value||'',year:document.getElementById('report-year')?.value||'',section:document.getElementById('report-section')?.value?.trim()||''});
    if(App.reportMode==='movement'){params.set('movement',document.getElementById('report-movement')?.value||'');params.set('context',document.getElementById('report-context')?.value||'');params.set('cluster_event',eventFilter);params.set('cluster_id',document.getElementById('report-cluster')?.value||'');}
    else if(App.reportMode==='exception'){params.set('exception_status',document.getElementById('report-exception-status')?.value||'');}
    else{params.set('status',eventStatus);params.set('irreg','');params.set('cluster_event',eventFilter);params.set('cluster_id',document.getElementById('report-cluster')?.value||'');}
    const tbody=document.getElementById('report-tbody'),badge=document.getElementById('report-count'),btn=document.getElementById('generate-report-btn');
    setLoading(btn,true,'Generating...'); if(tbody)tbody.innerHTML='<tr><td colspan="8" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Preparing report…</td></tr>';
    fetch(`index.php?${params}`).then(r=>r.json()).then(data=>{App.reportData=Array.isArray(data)?data:[];renderReportTable(App.reportData,tbody,badge);updateReportSummary(App.reportData);}).catch(()=>{if(tbody)tbody.innerHTML='<tr><td colspan="8" class="table-empty"><p>Failed to load this report. Check the database connection and try again.</p></td></tr>';}).finally(()=>setLoading(btn,false,'<i class="ph ph-magnifying-glass"></i> Generate Report'));
}
function exceptionStatusBadge(r){return Number(r.is_resolved)===1?'<span class="exception-status-badge resolved"><i class="ph-fill ph-check-circle"></i> Resolved</span>':'<span class="exception-status-badge open"><i class="ph-fill ph-warning-circle"></i> Open</span>';}
function renderReportTable(records,tbody,badge){
    if(!tbody)return;if(badge)badge.textContent=`${records.length} record${records.length!==1?'s':''}`;if(!records.length){tbody.innerHTML='<tr><td colspan="8" class="table-empty"><i class="ph ph-magnifying-glass" style="font-size:1.8rem;opacity:.3"></i><p>No records match the selected filters.</p></td></tr>';return;}tbody.innerHTML='';
    if(App.reportMode==='movement'){
        records.forEach((r,i)=>{const dt=new Date(r.verified_at);const tr=document.createElement('tr');tr.innerHTML=`<td class="row-number">${i+1}</td><td><div class="student-name">${esc(r.full_name)}</div><div class="student-meta">${esc(r.student_id_no||'')}</div></td><td><div class="student-name">${esc(academicPrimary(r))}</div><div class="student-meta">${esc(shortLevel(r.education_level||'College'))} • ${esc(academicSecondary(r))}</div></td><td><span class="scan-time">${dt.toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'})}<br>${fmtTime(dt)}</span></td><td>${movementBadge(r.movement_type)}</td><td>${movementContextBadge(r.movement_context,r.authorization_reason)}</td><td>${r.authorization_reason?`<div class="authorization-copy"><strong>${esc(r.authorization_reason)}</strong><small>${esc(r.verified_by||'Guard Kiosk')}</small></div>`:`<span class="student-meta">Verified by ${esc(r.verified_by||'Guard Kiosk')}</span>`}</td><td>${r.event_id?`<span class="context-badge context-event">Event #${esc(r.event_id)}</span>`:'<span class="student-meta">—</span>'}</td>`;tbody.appendChild(tr);});
    }else if(App.reportMode==='exception'){
        records.forEach((r,i)=>{const last=r.last_movement_at?new Date(r.last_movement_at):null;const tr=document.createElement('tr');tr.className=Number(r.is_resolved)===1?'exception-row-resolved':'exception-row-open';tr.innerHTML=`<td class="row-number">${i+1}</td><td><div class="student-name">${esc(r.full_name)}</div><div class="student-meta">${esc(r.student_id_no||'')}</div></td><td><div class="student-name">${esc(academicPrimary(r))}</div><div class="student-meta">${esc(shortLevel(r.education_level||'College'))} • ${esc(academicSecondary(r))}</div></td><td><span class="scan-time">${new Date(`${r.exception_date}T00:00:00`).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'})}</span></td><td><span class="no-out-badge"><i class="ph-fill ph-warning-octagon"></i> NO OUT RECORDED</span></td><td>${last?`<div class="last-movement-exception"><span>${movementBadge('IN')}</span><strong>${fmtTime(last)}</strong><small>Last verified movement</small></div>`:'<span class="student-meta">Movement unavailable</span>'}</td><td>${exceptionStatusBadge(r)}</td><td>${r.resolution_note?`<div class="exception-note">${esc(r.resolution_note)}</div>`:'<span class="student-meta">No fabricated OUT time. Review if needed.</span>'}</td>`;tbody.appendChild(tr);});
    }else{
        records.forEach((r,i)=>{const absent=r.status==='Absent',dt=r.scan_time?new Date(r.scan_time):null,tout=r.time_out?fmtTime(new Date(r.time_out)):'—';const tr=document.createElement('tr');tr.innerHTML=`<td class="row-number">${i+1}</td><td><div class="student-name">${esc(r.full_name)}</div><div class="student-meta">${esc(r.student_id_no||'')}</div></td><td><div class="student-name">${esc(academicPrimary(r))}</div><div class="student-meta">${esc(academicSecondary(r))}</div></td><td>${dt?dt.toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}):'—'}</td><td>${dt&&!absent?fmtTime(dt):'—'}</td><td>${tout}</td><td>${statusBadge(r.status)}</td><td>${r.is_irregular==1?'<span class="badge badge-irregular">Irregular</span>':'<span class="badge badge-regular">Regular</span>'}</td>`;tbody.appendChild(tr);});
    }
}
function updateReportSummary(records){
    const row=document.getElementById('report-summary-row');if(row)row.style.display=records.length?'grid':'none';const set=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=v;};set('rpt-total',records.length);
    if(App.reportMode==='movement'){set('rpt-label-a','IN');set('rpt-label-b','OUT');set('rpt-label-c','Early Exit');set('rpt-label-d','Re-entry');set('rpt-ontime',records.filter(r=>r.movement_type==='IN').length);set('rpt-late',records.filter(r=>r.movement_type==='OUT').length);set('rpt-absent',records.filter(r=>r.movement_context==='EARLY_EXIT').length);set('rpt-irreg-count',records.filter(r=>r.movement_context==='AUTHORIZED_REENTRY').length);}
    else if(App.reportMode==='exception'){const open=records.filter(r=>Number(r.is_resolved)!==1),resolved=records.filter(r=>Number(r.is_resolved)===1);set('rpt-label-a','Open NO OUT');set('rpt-label-b','Resolved');set('rpt-label-c','Students');set('rpt-label-d','Affected Days');set('rpt-ontime',open.length);set('rpt-late',resolved.length);set('rpt-absent',new Set(records.map(r=>r.student_id_no||r.rfid_uid)).size);set('rpt-irreg-count',new Set(records.map(r=>r.exception_date)).size);}
    else{set('rpt-label-a','On Time');set('rpt-label-b','Late');set('rpt-label-c','Absent');set('rpt-label-d','Irregular');set('rpt-ontime',records.filter(r=>r.status==='On Time').length);set('rpt-late',records.filter(r=>r.status==='Late').length);set('rpt-absent',records.filter(r=>r.status==='Absent').length);set('rpt-irreg-count',records.filter(r=>r.is_irregular==1).length);}
}
function exportXLSX(){
    if(!App.reportData?.length){showToast('No Data','Generate a report first.','warning');return;}
    const from=document.getElementById('report-from')?.value||'',to=document.getElementById('report-to')?.value||'',level=document.getElementById('report-level')?.value||'All Levels';
    let rows,title,sheet,kind;
    if(App.reportMode==='movement'){
        kind='Movement_Log';title='Campus Movement Investigation Report';sheet='Movement Logs';rows=App.reportData.map((r,i)=>({No:i+1,'Student No.':r.student_id_no||'','Student Name':r.full_name||'','Education Level':r.education_level||'','Academic Group':academicPrimary(r),'Year / Grade':r.year_level||'','Section':r.section||'','Date':new Date(r.verified_at).toLocaleDateString('en-CA'),'Time':fmtTime(new Date(r.verified_at)),'Movement':r.movement_type||'','Context':r.movement_context||'NORMAL','Authorization Reason':r.authorization_reason||'','Verified By':r.verified_by||'Guard Kiosk','Event ID':r.event_id||''}));
    }else if(App.reportMode==='exception'){
        kind='Daily_Exceptions';title='Daily Monitoring Exception Report';sheet='Daily Exceptions';rows=App.reportData.map((r,i)=>({No:i+1,'Student No.':r.student_id_no||'','Student Name':r.full_name||'','Education Level':r.education_level||'','Academic Group':academicPrimary(r),'Year / Grade':r.year_level||'','Section':r.section||'','Exception Date':r.exception_date||'','Exception':'NO OUT RECORDED','Last Movement':'IN','Last Movement Time':r.last_movement_at?fmtTime(new Date(r.last_movement_at)):'','Status':Number(r.is_resolved)===1?'Resolved':'Open','Resolution Note':r.resolution_note||'','Detected At':r.detected_at||''}));
    }else{
        kind='Event_Attendance';title='Time-Bound Event Attendance Report';sheet='Event Attendance';rows=App.reportData.map((r,i)=>({No:i+1,'Student No.':r.student_id_no||'','Student Name':r.full_name||'','Education Level':r.education_level||'','Academic Group':academicPrimary(r),'Year / Grade':r.year_level||'','Section':r.section||'','Date':r.scan_time?new Date(r.scan_time).toLocaleDateString('en-CA'):'','Time IN':r.scan_time?fmtTime(new Date(r.scan_time)):'','Time OUT':r.time_out?fmtTime(new Date(r.time_out)):'','Status':r.status||''}));
    }
    const filename=`TrackED_${kind}_${from}_to_${to}.xlsx`;
    const build=()=>{const XLSX=window.XLSX,wb=XLSX.utils.book_new();const meta=[['TrackED Student Monitoring System'],[title],[`Date Range: ${from} to ${to}`],[`Education Level: ${level}`],App.reportMode==='exception'?['Important: NO OUT is an exception record; no exit time is fabricated.']:[],[]].filter(x=>x.length);const metaSheet=XLSX.utils.aoa_to_sheet(meta);XLSX.utils.sheet_add_json(metaSheet,rows,{origin:`A${meta.length+1}`});XLSX.utils.book_append_sheet(wb,metaSheet,sheet);XLSX.writeFile(wb,filename);showToast('Export Complete',filename,'success');};
    if(window.XLSX){build();return;}
    const fallback=()=>exportExcelCompatible(rows,filename.replace(/\.xlsx$/i,'.xls'));
    if(navigator.onLine===false){fallback();return;}
    showToast('Loading...','Preparing Excel export…','info');
    const sc=document.createElement('script');sc.src='https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';sc.onload=build;sc.onerror=fallback;document.head.appendChild(sc);
}
function exportExcelCompatible(rows,filename){
    const cols=Object.keys(rows[0]||{}); if(!cols.length)return;
    const cell=v=>String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const html=`<html><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>${cols.map(c=>`<th>${cell(c)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${cols.map(c=>`<td>${cell(r[c])}</td>`).join('')}</tr>`).join('')}</tbody></table></body></html>`;
    const blob=new Blob([html],{type:'application/vnd.ms-excel;charset=utf-8'}); const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=filename;document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove();},1000);showToast('Export Complete',`${filename} (offline-compatible Excel format)`,'success');
}

/* ═══════════════════════════
   CAMPUS MOVEMENT POLICY
═══════════════════════════ */
function initMovementPolicy(){
    document.getElementById('movement-policy-form')?.addEventListener('submit',saveMovementPolicy);
    document.getElementById('policy-hs-controlled-exit')?.addEventListener('change',syncHighSchoolControlledExitUI);
}
function syncHighSchoolControlledExitUI(){
    const master=document.getElementById('policy-hs-controlled-exit');
    if(!master)return;
    const enabled=master.checked;
    const panel=master.closest('.policy-editor-card');
    panel?.classList.toggle('policy-disabled',!enabled);
    const masterWrap=master.closest('.policy-master-toggle');
    masterWrap?.classList.toggle('is-on',enabled);
    master.setAttribute('aria-checked', enabled ? 'true' : 'false');
    const label=document.getElementById('policy-hs-controlled-label');
    if(label)label.textContent=enabled?'CONTROLLED EXIT ON':'CONTROLLED EXIT OFF';
    ['policy-hs-dismissal','policy-early-reason','policy-reentry','policy-sms-early'].forEach(id=>{
        const el=document.getElementById(id);if(el)el.disabled=!enabled;
    });
}
function loadMovementPolicy(){
    fetch('index.php?action=get_movement_policy').then(r=>r.json()).then(d=>{
        App.movementPolicy=d;
        const time=(d.hs_dismissal_start||'15:30:00').substring(0,5);
        const val=(id,v)=>{const e=document.getElementById(id);if(e)e.value=v;}; const chk=(id,v)=>{const e=document.getElementById(id);if(e)e.checked=String(v)==='1'||v===1||v===true;};
        chk('policy-hs-controlled-exit',d.hs_controlled_exit_enabled===undefined?1:d.hs_controlled_exit_enabled);val('policy-hs-dismissal',time);val('policy-cooldown',d.same_card_cooldown_seconds||4);chk('policy-early-reason',d.hs_early_exit_requires_reason);chk('policy-reentry',d.hs_allow_reentry);chk('policy-sms-movement',d.sms_every_movement);chk('policy-sms-early',d.sms_early_exit);syncHighSchoolControlledExitUI();
        const dash=document.getElementById('dashboard-hs-dismissal');if(dash)dash.textContent=fmtPolicyTime(time);
    }).catch(()=>{});
}
function fmtPolicyTime(v){if(!v)return'--:--';const [h,m]=v.split(':').map(Number);const ap=h>=12?'PM':'AM';return `${h%12||12}:${String(m||0).padStart(2,'0')} ${ap}`;}
async function saveMovementPolicy(e){
    e?.preventDefault(); const btn=document.getElementById('save-movement-policy');setLoading(btn,true,'Saving...');
    try{const fd=new FormData();fd.append('action','save_movement_policy');if(document.getElementById('policy-hs-controlled-exit')?.checked)fd.append('hs_controlled_exit_enabled','1');fd.append('hs_dismissal_start',(document.getElementById('policy-hs-dismissal')?.value||'15:30')+':00');if(document.getElementById('policy-early-reason')?.checked)fd.append('hs_early_exit_requires_reason','1');if(document.getElementById('policy-reentry')?.checked)fd.append('hs_allow_reentry','1');if(document.getElementById('policy-sms-movement')?.checked)fd.append('sms_every_movement','1');if(document.getElementById('policy-sms-early')?.checked)fd.append('sms_early_exit','1');fd.append('same_card_cooldown_seconds',document.getElementById('policy-cooldown')?.value||'4');const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();showToast(d.status==='success'?'Policy Saved':'Error',d.message||'',d.status);if(d.status==='success'){loadMovementPolicy();fetchMovementDashboard();}}catch{showToast('Error','Could not save campus policy.','error');}finally{setLoading(btn,false,'<i class="ph ph-floppy-disk"></i> Save Campus Policy');}
}

/* ═══════════════════════════
   SETTINGS
═══════════════════════════ */
function loadSettingsValues() {
    fetch('index.php?action=get_config')
        .then(r => r.json())
        .then(data => {
            ['school_name'].forEach(key => {
                const el = document.querySelector(`[name="${key}"]`);
                if (el && data[key] !== undefined) el.value = data[key];
            });
            const smsChk = document.getElementById('sms_enabled_chk');
            if (smsChk) smsChk.checked = data['sms_enabled'] === '1';
        })
        .catch(() => {});
}

function initSettingsForm() {
    document.getElementById('save-config-btn')?.addEventListener('click', async function() {
        const btn = this;
        setLoading(btn, true, 'Saving...');
        try {
            const fd = new FormData();
            fd.append('action','save_config');
            ['school_name'].forEach(key => {
                const el = document.querySelector(`[name="${key}"]`);
                if (el) fd.append(key, el.value.trim());
            });
            fd.append('sms_enabled', document.getElementById('sms_enabled_chk')?.checked ? '1' : '0');
            const res  = await fetch('index.php', { method:'POST', body: fd });
            const data = await res.json();
            showToast(
                data.status === 'success' ? 'Settings Saved!' : 'Notice',
                data.message || 'Configuration updated.',
                data.status
            );
        } catch { showToast('Error','Could not save settings.','error'); }
        finally  { setLoading(btn, false, '<i class="ph ph-floppy-disk"></i> Save All Changes'); }
    });
}


function initRetentionSettings() {
    document.getElementById('save-retention-policy')?.addEventListener('click', saveRetentionPolicy);
    document.getElementById('run-backup-now')?.addEventListener('click', runRetentionBackupNow);
    document.getElementById('add-retention-hold')?.addEventListener('click', addRetentionHold);
}

async function loadRetentionStatus() {
    try {
        const r=await fetch('index.php?action=get_retention_status'); const d=await r.json();
        if(d.status!=='success') return;
        const cfg=d.config||{};
        const set=(id,v)=>{const e=document.getElementById(id);if(e)e.value=v??''};
        const chk=(id,v)=>{const e=document.getElementById(id);if(e)e.checked=String(v)==='1'};
        set('retention-days',cfg.log_retention_days||30);set('retention-archive-months',cfg.archive_retention_months||12);
        chk('retention-daily-backup',cfg.daily_backup_enabled);chk('retention-monthly-archive',cfg.monthly_archive_enabled);chk('retention-auto-purge',cfg.auto_purge_enabled);
        const live=document.getElementById('retention-live-count');if(live)live.textContent=Number(d.live_log_count||0).toLocaleString();
        const oldest=document.getElementById('retention-oldest');if(oldest)oldest.textContent='Oldest live record: '+(d.oldest_live_log?formatRetentionDate(d.oldest_live_log):'None');
        const days=document.getElementById('retention-archive-days');if(days)days.textContent=Number(d.archive_days||0).toLocaleString();
        const path=document.getElementById('retention-backup-path');if(path)path.textContent=d.backup_path||'C:\\xampp\\tracked_backups';
        const last=d.last_run||null, st=document.getElementById('retention-last-status'), tm=document.getElementById('retention-last-time'), badge=document.getElementById('retention-state-badge');
        if(last){if(st){st.textContent=last.status==='SUCCESS'?'Verified':last.status;st.className=last.status==='SUCCESS'?'retention-good':'retention-bad';}if(tm)tm.textContent=formatRetentionDate(last.completed_at||last.started_at)+(last.purged_records?` • ${last.purged_records} purged`:'');if(badge)badge.classList.toggle('warn',last.status!=='SUCCESS');}
        renderRetentionHolds(d.holds||[]);
    } catch(e) { /* settings remains usable if maintenance migration is not installed yet */ }
}
function formatRetentionDate(v){if(!v)return '—';const d=new Date(String(v).replace(' ','T'));return Number.isNaN(d.getTime())?String(v):d.toLocaleString('en-PH',{year:'numeric',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});}
function renderRetentionHolds(holds){const box=document.getElementById('retention-hold-list');if(!box)return;if(!holds.length){box.innerHTML='<div class="retention-empty"><i class="ph ph-shield-check"></i> No protected incident ranges.</div>';return;}box.innerHTML=holds.map(h=>`<div class="retention-hold-item"><div class="retention-hold-lock"><i class="ph-fill ph-lock-key"></i></div><div><strong>${esc(h.case_name)}</strong><span>${esc(h.date_from)} → ${esc(h.date_to)}</span>${h.note?`<small>${esc(h.note)}</small>`:''}</div><button type="button" class="btn-icon retention-release" data-id="${h.id}" title="Release hold"><i class="ph ph-lock-key-open"></i></button></div>`).join('');box.querySelectorAll('.retention-release').forEach(b=>b.addEventListener('click',()=>releaseRetentionHold(b.dataset.id)));}
async function saveRetentionPolicy(){const btn=document.getElementById('save-retention-policy');setLoading(btn,true,'Saving...');try{const fd=new FormData();fd.append('action','save_retention_config');fd.append('log_retention_days',document.getElementById('retention-days')?.value||30);fd.append('archive_retention_months',document.getElementById('retention-archive-months')?.value||12);if(document.getElementById('retention-daily-backup')?.checked)fd.append('daily_backup_enabled','1');if(document.getElementById('retention-monthly-archive')?.checked)fd.append('monthly_archive_enabled','1');if(document.getElementById('retention-auto-purge')?.checked)fd.append('auto_purge_enabled','1');const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();showToast(d.status==='success'?'Retention Policy Saved':'Error',d.message||'',d.status);if(d.status==='success')loadRetentionStatus();}catch{showToast('Error','Could not save retention policy.','error');}finally{setLoading(btn,false,'<i class="ph ph-floppy-disk"></i> Save Retention Policy');}}
async function runRetentionBackupNow(){const btn=document.getElementById('run-backup-now');setLoading(btn,true,'Backing up...');try{const fd=new FormData();fd.append('action','run_retention_now');const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();showToast(d.status==='success'?'Verified Backup Complete':'Backup Failed',d.message||'',d.status,d.status==='success'?6500:8000);loadRetentionStatus();}catch{showToast('Backup Failed','Could not run maintenance. Check the server/PHP logs.','error');}finally{setLoading(btn,false,'<i class="ph ph-cloud-arrow-up"></i> Backup Now');}}
async function addRetentionHold(){const name=document.getElementById('hold-case-name')?.value.trim()||'',from=document.getElementById('hold-date-from')?.value||'',to=document.getElementById('hold-date-to')?.value||'',note=document.getElementById('hold-note')?.value.trim()||'';if(!name||!from||!to)return showToast('Incomplete Hold','Enter a case name and date range.','warning');const btn=document.getElementById('add-retention-hold');setLoading(btn,true,'Protecting...');try{const fd=new FormData();fd.append('action','add_retention_hold');fd.append('case_name',name);fd.append('date_from',from);fd.append('date_to',to);fd.append('note',note);const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();showToast(d.status==='success'?'Logs Protected':'Error',d.message||'',d.status);if(d.status==='success'){['hold-case-name','hold-date-from','hold-date-to','hold-note'].forEach(id=>{const e=document.getElementById(id);if(e)e.value=''});loadRetentionStatus();}}catch{showToast('Error','Could not protect this date range.','error');}finally{setLoading(btn,false,'<i class="ph ph-lock-simple"></i> Protect Date Range');}}
async function releaseRetentionHold(id){const fd=new FormData();fd.append('action','delete_retention_hold');fd.append('id',id);try{const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();showToast(d.status==='success'?'Hold Released':'Error',d.message||'',d.status);loadRetentionStatus();}catch{showToast('Error','Could not release hold.','error');}}


/* Student signature upload previews */
function bindSignaturePreview(inputId, imgId, placeholderId) {
    const input=document.getElementById(inputId), img=document.getElementById(imgId), ph=document.getElementById(placeholderId);
    if(!input||!img)return;
    input.addEventListener('change',()=>{
        const file=input.files?.[0];
        if(!file){img.removeAttribute('src');img.style.display='none';if(ph)ph.style.display='';return;}
        if(!/^image\/(png|jpeg|webp)$/i.test(file.type)){showToast('Invalid Signature','Use PNG, JPG, or WEBP for the student signature.','warning');input.value='';return;}
        const reader=new FileReader();reader.onload=()=>{img.src=reader.result;img.style.display='block';if(ph)ph.style.display='none';};reader.readAsDataURL(file);
    });
}

document.addEventListener('DOMContentLoaded',()=>{
    bindSignaturePreview('student_signature','signature-preview-img','signature-preview-placeholder');
    bindSignaturePreview('edit-student-signature','edit-signature-preview-img','edit-signature-preview-placeholder');
});

/* ═══════════════════════════
   AI-ASSISTED ID GENERATOR
═══════════════════════════ */
const IDGen = {
    students: [],
    templates: [],
    selected: new Set(),
    frontDataUrl: '',
    backDataUrl: '',
    currentTemplate: null,
    layout: {
        photo:         {x:26.5, y:21.5, w:47.05, h:29.67},
        full_name:     {x:16,   y:55.5, w:68, h:7.5},
        student_id_no: {x:22,   y:64.0, w:56, h:5.5},
        course_year:   {x:15,   y:70.0, w:70, h:5.0},
        section:       {x:27,   y:80.5, w:46, h:4.8},
        signature:     {x:25,   y:73.8, w:50, h:7.0},
    }
};

function initIdGenerator() {
    document.getElementById('idtpl-front')?.addEventListener('change', e => previewTemplateFile(e.target.files?.[0], 'front'));
    document.getElementById('idtpl-save')?.addEventListener('click', saveIdTemplate);
    document.getElementById('idtpl-reset-layout')?.addEventListener('click', () => {
        IDGen.layout = {
            photo:{x:26.5,y:21.5,w:47.05,h:29.67}, full_name:{x:16,y:55.5,w:68,h:7.5},
            student_id_no:{x:22,y:64,w:56,h:5.5}, course_year:{x:15,y:70,w:70,h:5}, section:{x:27,y:82.0,w:46,h:4.8}, signature:{x:25,y:73.8,w:50,h:7.0}
        };
        applyIdLayout();
        showToast('Layout Reset','Field positions returned to the default smart layout.','info');
    });
    document.getElementById('idgen-preview-select')?.addEventListener('change', updateIdPreviewStudent);
    document.getElementById('idgen-level')?.addEventListener('change', () => { syncIdGenAcademicFilters(); renderIdStudentList(); });
    ['idgen-group','idgen-year'].forEach(id => document.getElementById(id)?.addEventListener('change', renderIdStudentList));
    document.getElementById('idgen-search')?.addEventListener('input', renderIdStudentList);
    document.getElementById('idgen-check-all')?.addEventListener('change', e => setVisibleIdSelection(e.target.checked));
    document.getElementById('idgen-generate')?.addEventListener('click', generateIdPrintSheet);
    document.querySelectorAll('.idgen-side-tab').forEach(btn=>btn.addEventListener('click',()=>setIdPreviewSide(btn.dataset.idSide||'front')));
    initIdFieldDragging();
}

function syncIdGenAcademicFilters() {
    const level=document.getElementById('idgen-level')?.value||'';
    const group=document.getElementById('idgen-group'), wrap=document.getElementById('idgen-group-wrap'), gl=document.getElementById('idgen-group-label');
    const year=document.getElementById('idgen-year'), yl=document.getElementById('idgen-year-label');
    if(group){
        if(level==='College'){group.innerHTML='<option value="">All Courses</option>'+COURSES.map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join(''); if(gl)gl.textContent='Course / Program'; if(wrap)wrap.style.display='';}
        else if(level==='Senior High School'){group.innerHTML='<option value="">All Strands</option>'+SHS_STRANDS.map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join(''); if(gl)gl.textContent='Strand'; if(wrap)wrap.style.display='';}
        else {group.innerHTML='<option value="">All Academic Groups</option>'; if(wrap)wrap.style.display='none';}
    }
    if(year){let years=level?(ACADEMIC_YEARS[level]||[]):[...ACADEMIC_YEARS.College,...ACADEMIC_YEARS['Senior High School'],...ACADEMIC_YEARS['Junior High School']];year.innerHTML=`<option value="">${level==='College'?'All Year Levels':level?'All Grade Levels':'All Years / Grades'}</option>`+[...new Set(years)].map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');}
    if(yl)yl.textContent=level==='College'?'Year Level':level?'Grade Level':'Year / Grade';
}

function idDepartmentLabel(s){const l=s?.education_level||'College';return l==='Senior High School'?'SENIOR HIGH SCHOOL':l==='Junior High School'?'JUNIOR HIGH SCHOOL':'COLLEGE DEPARTMENT';}
function idSubtitle(s){const l=s?.education_level||'College';return l==='Senior High School'?'OFFICIAL SENIOR HIGH ID':l==='Junior High School'?'OFFICIAL JUNIOR HIGH ID':'OFFICIAL COLLEGE ID';}
function idAcademicLine(s){const l=s?.education_level||'College';if(l==='Senior High School')return [s.strand,s.year_level].filter(Boolean).join(' - ');if(l==='Junior High School')return s.year_level||'Junior High School';return [s.course,s.year_level].filter(Boolean).join(' - ');}

async function loadIdGeneratorData() {
    await Promise.all([loadIdTemplates(), loadIdStudents()]);
    syncIdGenAcademicFilters();
    renderIdStudentList();
    updateIdPreviewStudent();
}

async function loadIdTemplates() {
    try {
        const r = await fetch('index.php?action=get_id_templates');
        IDGen.templates = await r.json();
        const active = IDGen.templates.find(t => String(t.is_active) === '1') || IDGen.templates[0];
        if (!active) return;
        IDGen.currentTemplate = active;
        document.getElementById('idtpl-id').value = active.id || 0;
        document.getElementById('idtpl-name').value = active.template_name || 'Official Student ID';
        document.getElementById('idtpl-existing-front').value = active.front_image || '';
        document.getElementById('idtpl-existing-back').value = active.back_image || '';
        if (active.config && active.config.layout) IDGen.layout = {...IDGen.layout, ...active.config.layout};
        // Migrate the older compact signature box to the roomier official layout.
        const sigCfg = IDGen.layout.signature;
        if (sigCfg && Number(sigCfg.w) <= 38.5 && Number(sigCfg.h) <= 5.1) {
            IDGen.layout.signature = {x:25, y:73.8, w:50, h:7.0};
            if (IDGen.layout.section && Number(IDGen.layout.section.y) < 81.5) {
                IDGen.layout.section = {...IDGen.layout.section, y:82.0};
            }
        }
        const bg = document.getElementById('id-card-bg');
        if (bg) bg.src = (active.front_image && active.front_image !== '__builtin_portrait__') ? active.front_image : '';
        document.getElementById('id-default-template')?.classList.toggle('hidden-template', !!(active.front_image && active.front_image !== '__builtin_portrait__'));
        applyIdLayout();
    } catch (e) {
        console.warn('ID template load failed', e);
    }
}

async function loadIdStudents() {
    try {
        const r = await fetch('index.php?action=get_students');
        IDGen.students = await r.json();
        const sel = document.getElementById('idgen-preview-select');
        if (sel) {
            sel.innerHTML = IDGen.students.length
                ? IDGen.students.map(s => `<option value="${s.id}">${esc(s.full_name)} — ${esc(s.student_id_no||'No ID No.')}</option>`).join('')
                : '<option value="">No active students</option>';
        }
        renderIdStudentList();
    } catch {
        showToast('ID Generator','Could not load student records.','error');
    }
}

function previewTemplateFile(file, side) {
    if (!file) return;
    if (!/^image\/(png|jpeg)$/.test(file.type)) {
        showToast('Invalid Template','Please upload a JPG or PNG image.','error'); return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        if (side === 'front') {
            IDGen.frontDataUrl = reader.result;
            const bg = document.getElementById('id-card-bg');
            if (bg) bg.src = reader.result;
            document.getElementById('id-default-template')?.classList.add('hidden-template');
        } else {
            IDGen.backDataUrl = reader.result;
            const backBg=document.getElementById('id-card-back-bg');
            if(backBg){backBg.src=reader.result;backBg.style.display='block';}
            document.getElementById('id-back-built')?.classList.add('custom-back-active');
            setIdPreviewSide('back');
        }
    };
    reader.readAsDataURL(file);
}

function initIdFieldDragging() {
    document.querySelectorAll('#id-card-stage .id-field').forEach(el => {
        let active=false, sx=0, sy=0, ox=0, oy=0;
        el.addEventListener('pointerdown', e => {
            active=true; el.setPointerCapture(e.pointerId); sx=e.clientX; sy=e.clientY;
            const field=el.dataset.field, cfg=IDGen.layout[field] || {x:0,y:0}; ox=cfg.x; oy=cfg.y;
            el.classList.add('dragging'); e.preventDefault();
        });
        el.addEventListener('pointermove', e => {
            if (!active) return;
            const stage=document.getElementById('id-card-stage'); if (!stage) return;
            const rect=stage.getBoundingClientRect(), field=el.dataset.field, cfg=IDGen.layout[field];
            cfg.x=Math.max(0, Math.min(100-cfg.w, ox + ((e.clientX-sx)/rect.width)*100));
            cfg.y=Math.max(0, Math.min(100-cfg.h, oy + ((e.clientY-sy)/rect.height)*100));
            applyIdLayout();
        });
        const end=e=>{active=false;el.classList.remove('dragging');};
        el.addEventListener('pointerup',end); el.addEventListener('pointercancel',end);
    });
}

function applyIdLayout() {
    document.querySelectorAll('#id-card-stage .id-field').forEach(el => {
        const c=IDGen.layout[el.dataset.field]; if (!c) return;
        el.style.left=c.x+'%'; el.style.top=c.y+'%'; el.style.width=c.w+'%'; el.style.height=c.h+'%';
    });
    requestAnimationFrame(() => document.querySelectorAll('#id-card-stage .id-field-text').forEach(autoFitElementText));
}

function autoFitElementText(el) {
    if (!el) return;
    let size=18; el.style.fontSize=size+'px';
    while (size>8 && (el.scrollWidth>el.clientWidth || el.scrollHeight>el.clientHeight)) {
        size-=0.5; el.style.fontSize=size+'px';
    }
}

function resolveStudentPhoto(path) {
    path = String(path || '').trim();
    if (!path) return '';
    if (/^https?:\/\//i.test(path) || path.startsWith('data:')) return path;
    return path.replace(/^\/+/, '');
}

function setIdPreviewSide(side='front') {
    const isBack=side==='back';
    const front=document.querySelector('#section-idgenerator .id-card-stage-wrap:not(.id-back-stage-wrap)');
    const back=document.getElementById('id-back-stage-wrap');
    if(front) front.style.display=isBack?'none':'';
    if(back) back.style.display=isBack?'':'none';
    document.querySelectorAll('.idgen-side-tab').forEach(b=>b.classList.toggle('active',(b.dataset.idSide||'front')===side));
}

function emergencyName(s){return String(s?.guardian_name||'').trim()||'PARENT / GUARDIAN';}
function emergencyMobile(s){return String(s?.parent_mobile||'').trim()||'NO CONTACT NUMBER';}
function emergencyAddress(s){return String(s?.guardian_address||'').trim()||'ADDRESS NOT PROVIDED';}
function currentSchoolYearStart(date=new Date()){
    const year=date.getFullYear();
    return date.getMonth()<5 ? year-1 : year;
}
function validationSchoolYears(count=4){
    const start=currentSchoolYearStart();
    return Array.from({length:count},(_,i)=>`${start+i}-${start+i+1}`);
}
function currentSchoolYearLabel(){
    const start=currentSchoolYearStart();
    return `S.Y. ${start}–${start+1}`;
}

function updateIdPreviewStudent() {
    const id=document.getElementById('idgen-preview-select')?.value;
    const s=IDGen.students.find(x=>String(x.id)===String(id))||IDGen.students[0]; if(!s)return;
    document.getElementById('id-preview-name').textContent=s.full_name||'STUDENT NAME';
    document.getElementById('id-preview-no').textContent=s.student_id_no||'NO STUDENT NUMBER';
    document.getElementById('id-preview-course').textContent=idAcademicLine(s)||'ACADEMIC PROFILE';
    document.getElementById('id-preview-section').textContent=`Section ${s.section||'—'}`;
    const side=document.getElementById('idtpl-side-label'); if(side)side.textContent=idDepartmentLabel(s);
    const sub=document.getElementById('idtpl-level-subtitle'); if(sub)sub.textContent=idSubtitle(s);
    const img=document.getElementById('id-preview-photo'),src=resolveStudentPhoto(s.photo_path);if(img){img.src=src;img.style.display=src?'block':'none';}
    const sig=document.getElementById('id-preview-signature'),sigsrc=resolveStudentPhoto(s.signature_path);if(sig){sig.src=sigsrc;sig.style.display=sigsrc?'block':'none';const wrap=sig.closest('.id-field-signature');wrap?.classList.toggle('has-signature',!!sigsrc);}
    const g=document.getElementById('id-back-guardian'); if(g) g.textContent=emergencyName(s);
    const m=document.getElementById('id-back-mobile'); if(m) m.textContent=emergencyMobile(s);
    const a=document.getElementById('id-back-address'); if(a) a.textContent=emergencyAddress(s);
    const sy=document.getElementById('idtpl-school-year'); if(sy) sy.textContent=currentSchoolYearLabel();
    validationSchoolYears().forEach((year,i)=>{const el=document.getElementById(`id-back-year-${i+1}`);if(el)el.textContent=year;});
    applyIdLayout();
}

async function saveIdTemplate() {
    const btn=document.getElementById('idtpl-save');
    const frontFile=document.getElementById('idtpl-front')?.files?.[0];
    const existingFront=document.getElementById('idtpl-existing-front')?.value || '';
    
    const fd=new FormData();
    fd.append('action','save_id_template');
    fd.append('template_id',document.getElementById('idtpl-id')?.value||'0');
    fd.append('template_name',document.getElementById('idtpl-name')?.value.trim()||'Official Student ID');
    fd.append('existing_front',existingFront || '__builtin_portrait__');
    fd.append('existing_back','');
    fd.append('config_json',JSON.stringify({version:2,card:'CR80-portrait',photo:'1x1-inch',layout:IDGen.layout}));
    if (frontFile) fd.append('front_template',frontFile);
    setLoading(btn,true,'Saving...');
    try {
        const r=await fetch('index.php',{method:'POST',body:fd}); const d=await r.json();
        showToast(d.status==='success'?'Template Saved':'Template Error',d.message||'',d.status);
        if (d.status==='success') {
            document.getElementById('idtpl-id').value=d.id||0;
            document.getElementById('idtpl-existing-front').value=d.front_image||existingFront;
            document.getElementById('idtpl-existing-back').value=d.back_image||'';
            await loadIdTemplates();
        }
    } catch { showToast('Template Error','Could not save the ID template.','error'); }
    finally { setLoading(btn,false,'<i class="ph ph-floppy-disk"></i> Save Template & Layout'); }
}

function getFilteredIdStudents() {
    const level=document.getElementById('idgen-level')?.value||'', group=document.getElementById('idgen-group')?.value||'', y=document.getElementById('idgen-year')?.value||'', q=(document.getElementById('idgen-search')?.value||'').toLowerCase().trim();
    return IDGen.students.filter(s=>{const l=s.education_level||'College';const groupOk=!group||(level==='College'?s.course===group:level==='Senior High School'?s.strand===group:true);return(!level||l===level)&&groupOk&&(!y||s.year_level===y)&&(!q||String(s.full_name||'').toLowerCase().includes(q)||String(s.student_id_no||'').toLowerCase().includes(q));});
}

function renderIdStudentList() {
    const tbody=document.getElementById('idgen-tbody');if(!tbody)return;const rows=getFilteredIdStudents();
    if(!rows.length){tbody.innerHTML='<tr><td colspan="6" class="empty-state">No matching active students.</td></tr>';updateIdSelectedCount();updateVisibleIdSelectionState();return;}
    tbody.innerHTML=rows.map(s=>{const src=resolveStudentPhoto(s.photo_path);return `<tr><td><label class="idgen-row-check-wrap" title="Select ${esc(s.full_name)}"><input type="checkbox" class="idgen-row-check" data-id="${s.id}" aria-label="Select ${esc(s.full_name)}" ${IDGen.selected.has(String(s.id))?'checked':''}><span class="idgen-row-check-visual" aria-hidden="true"></span></label></td><td>${src?`<img src="${esc(src)}" class="table-avatar-img">`:`<div class="table-avatar">${initials(s.full_name)}</div>`}</td><td><div class="student-name">${esc(s.full_name)}</div><div class="student-meta">${esc(s.student_id_no||'No student number')}</div></td><td><span class="academic-level-badge">${esc(shortLevel(s.education_level||'College'))}</span></td><td><div class="student-name">${esc(academicPrimary(s))}</div><div class="student-meta">${esc(academicSecondary(s))}</div></td><td>${esc(s.section||'—')}</td></tr>`;}).join('');
    tbody.querySelectorAll('.idgen-row-check').forEach(ch=>ch.addEventListener('change',()=>{ch.checked?IDGen.selected.add(ch.dataset.id):IDGen.selected.delete(ch.dataset.id);updateIdSelectedCount();updateVisibleIdSelectionState();}));updateIdSelectedCount();updateVisibleIdSelectionState();
}

function setVisibleIdSelection(on) {
    getFilteredIdStudents().forEach(s=> on ? IDGen.selected.add(String(s.id)) : IDGen.selected.delete(String(s.id)));
    renderIdStudentList();
}

function updateVisibleIdSelectionState() {
    const visible = getFilteredIdStudents();
    const selectedVisible = visible.filter(s => IDGen.selected.has(String(s.id))).length;
    const all = document.getElementById('idgen-check-all');
    if (all) {
        all.checked = visible.length > 0 && selectedVisible === visible.length;
        all.indeterminate = selectedVisible > 0 && selectedVisible < visible.length;
    }
}

function updateIdSelectedCount(){ const el=document.getElementById('idgen-selected-count'); if(el) el.textContent=`${IDGen.selected.size} selected`; }

function getIdTemplateSource() {
    return IDGen.frontDataUrl || IDGen.currentTemplate?.front_image || document.getElementById('idtpl-existing-front')?.value || '__builtin_portrait__';
}
function getIdBackSource() { return ''; }

function generateIdPrintSheet() {
    const front=getIdTemplateSource();
    const students=IDGen.students.filter(s=>IDGen.selected.has(String(s.id)));
    if (!students.length) { showToast('No Students Selected','Select at least one student to generate IDs.','warning'); return; }
    const win=window.open('','_blank'); if(!win){showToast('Pop-up Blocked','Allow pop-ups for TrackED to generate the PVC print sheet.','warning');return;}
    const layout=JSON.parse(JSON.stringify(IDGen.layout));
    const abs=u=>{try{return new URL(u,window.location.href).href}catch{return u}};
    const builtin = !front || front === '__builtin_portrait__';
    const frontUrl=builtin?'':abs(front);
    const fixedTemplate = s => `<div class="built"><div class="topline"></div><div class="head"><img src="${abs('logo.png')}"><div><b>STUDENT IDENTIFICATION CARD</b><span>${esc(idSubtitle(s))}</span></div></div><div class="sy">${esc(currentSchoolYearLabel())}</div><div class="side">${esc(idDepartmentLabel(s))}</div><div class="photoframe"></div><div class="bottom"></div></div>`;
    const backBuilt = s => {const yrs=validationSchoolYears();return `<div class="backbuilt validationback"><img class="watermark validationwatermark" src="${abs('logo.png')}"><div class="backschool"><b>SOUTHWESTERN INSTITUTE OF BUSINESS AND TECHNOLOGY, INC.</b><small>PINAMALAYAN, ORIENTAL MINDORO</small></div><div class="emergencybox"><div class="inforow"><span>IN CASE OF EMERGENCY</span><b>${esc(emergencyName(s))} / ${esc(emergencyMobile(s))}</b></div><div class="inforow"><span>ADDRESS</span><b>${esc(emergencyAddress(s))}</b></div></div><div class="rules"><span>• THIS CARD IS NON-TRANSFERABLE AND VALID ONLY FOR SEMESTER LAST VALIDATED.</span><span>• IT MUST BE WORN AT ALL TIMES WHILE INSIDE SCHOOL PREMISES.</span><span>• ANY VISIBLE TAMPERING WILL INVALIDATE THIS CARD.</span><span>• IF LOST, FINDER IS REQUESTED TO SURRENDER THIS TO THE REGISTRAR OFFICE.</span></div><div class="validationgrid"><div class="vh">School<br>Year</div><div class="vh">1st<br>Semester</div><div class="vh">2nd<br>Semester</div><div class="vh">Validation</div>${yrs.map(y=>`<div class="vy">${esc(y)}</div><div></div><div></div><div></div>`).join('')}</div><div class="registrar validationregistrar"><i></i><b>DANICA CLAIRE P. LAT, LPT, CRS, CEPL</b><small>ASSISTANT COLLEGE REGISTRAR</small></div></div>`};
    const card=(s,side='front')=>{
        if(side==='back') return `<div class="idcard back">${backBuilt(s)}</div>`;
        const photo=resolveStudentPhoto(s.photo_path), sig=resolveStudentPhoto(s.signature_path), course=idAcademicLine(s);
        const f=(key,inner,cls='txt')=>{const c=layout[key];return `<div class="fld ${cls}" style="left:${c.x}%;top:${c.y}%;width:${c.w}%;height:${c.h}%">${inner}</div>`};
        const bg=builtin?'':`style="background-image:url('${frontUrl.replace(/'/g,'%27')}')"`;
        return `<div class="idcard" ${bg}>${builtin?fixedTemplate(s):''}${f('photo',photo?`<img src="${abs(photo)}">`:'','photo')}${f('full_name',esc(s.full_name||''))}${f('student_id_no',esc(s.student_id_no||''))}${f('course_year',esc(course))}${f('section',esc('Section '+(s.section||'—')))}${f('signature',`${sig?`<img src="${abs(sig)}">`:''}<span>${sig?'':'NO SIGNATURE'}</span><small>Student Signature</small>`,'signature')}</div>`;
    };
    const pairs=[];
    for(let i=0;i<students.length;i+=2) pairs.push(students.slice(i,i+2));
    const pages=pairs.map((pair,idx)=>{
        const slotsFront=pair.map(s=>`<div class="slot">${card(s,'front')}</div>`).join('')+(pair.length<2?'<div class="slot empty"></div>':'');
        const slotsBack=pair.map(s=>`<div class="slot">${card(s,'back')}</div>`).join('')+(pair.length<2?'<div class="slot empty"></div>':'');
        return `<section class="print-page front-page"><div class="page-label">PAIR ${idx+1} · FRONT</div><div class="pairrow">${slotsFront}</div></section><section class="print-page back-page"><div class="page-label">PAIR ${idx+1} · BACK · DUPLEX LONG-EDGE</div><div class="pairrow">${slotsBack}</div></section>`;
    }).join('');
    win.document.write(`<!doctype html><html><head><title>TrackED - PVC ID 2-Up Front & Back</title><style>
    @page{size:A4 portrait;margin:0}*{box-sizing:border-box}html,body{margin:0;padding:0;background:#eef2f7;font-family:Arial,sans-serif;color:#111}.toolbar{position:sticky;top:0;z-index:99;background:#111827;color:#fff;padding:10px 16px;display:flex;align-items:center;justify-content:center;gap:16px;font-size:12px}.toolbar button{border:0;border-radius:8px;background:#fff;color:#111827;padding:8px 18px;font-weight:800;cursor:pointer}.print-page{width:210mm;height:297mm;background:#fff;position:relative;display:flex;align-items:center;justify-content:center;break-after:page;page-break-after:always}.pairrow{display:grid;grid-template-columns:53.98mm 53.98mm;gap:14mm;align-items:center;justify-content:center}.slot{width:53.98mm;height:85.6mm;position:relative}.slot:before,.slot:after{content:'';position:absolute;inset:-2mm;border:.15mm dashed #aab3c0;pointer-events:none}.slot:after{inset:-3.5mm;border:0;background:linear-gradient(#555,#555) left top/5mm .15mm no-repeat,linear-gradient(#555,#555) left top/.15mm 5mm no-repeat,linear-gradient(#555,#555) right top/5mm .15mm no-repeat,linear-gradient(#555,#555) right top/.15mm 5mm no-repeat,linear-gradient(#555,#555) left bottom/5mm .15mm no-repeat,linear-gradient(#555,#555) left bottom/.15mm 5mm no-repeat,linear-gradient(#555,#555) right bottom/5mm .15mm no-repeat,linear-gradient(#555,#555) right bottom/.15mm 5mm no-repeat}.slot.empty:before,.slot.empty:after{display:none}.page-label{position:absolute;top:8mm;left:0;right:0;text-align:center;font-size:2.7mm;letter-spacing:.25mm;color:#64748b;font-weight:700}.idcard{width:53.98mm;height:85.6mm;position:relative;background-size:100% 100%;background-repeat:no-repeat;overflow:hidden;background:#fff;border-radius:2mm;box-shadow:inset 0 0 0 .12mm #d7dce3}.built{position:absolute;inset:0;background:linear-gradient(160deg,#fff 0 76%,#eaf4ff 76%);overflow:hidden}.topline{position:absolute;left:0;right:0;top:0;height:1.8mm;background:#0879c9}.head{position:absolute;left:4mm;right:7mm;top:5mm;height:10mm;display:flex;align-items:center;gap:2mm}.head img{width:9mm;height:9mm;object-fit:contain}.head div{display:flex;flex-direction:column;line-height:1.05}.head b{font-size:2.5mm;letter-spacing:.12mm}.head span{font-size:1.6mm;color:#64748b;letter-spacing:.22mm;margin-top:.8mm}.side{position:absolute;right:0;top:0;width:5.5mm;height:85.6mm;background:#0879c9;color:white;writing-mode:vertical-rl;transform:rotate(180deg);display:flex;align-items:center;justify-content:center;font-size:2.15mm;font-weight:900;letter-spacing:.32mm}.sy{position:absolute;left:1.5mm;top:26mm;height:27mm;writing-mode:vertical-rl;transform:rotate(180deg);font-size:2mm;font-weight:800;letter-spacing:.2mm}.photoframe{position:absolute;left:14.3mm;top:18.4mm;width:25.4mm;height:25.4mm;border:.55mm solid #0879c9;background:#eef6fc}.bottom{position:absolute;left:-5mm;right:4mm;bottom:-3mm;height:16mm;background:linear-gradient(150deg,#d9efff 0 48%,#0879c9 48% 58%,#cfe8f8 58%);clip-path:polygon(0 42%,100% 0,100% 100%,0 100%)}.fld{position:absolute;display:flex;align-items:center;justify-content:center;text-align:center;overflow:hidden;z-index:3}.fld.txt{font-weight:700;line-height:1.05;padding:.3mm;font-size:3.2mm;white-space:nowrap}.fld.photo img{width:100%;height:100%;object-fit:cover}.fld.signature{flex-direction:column;border-bottom:.2mm solid #334155}.fld.signature img{max-width:98%;max-height:88%;object-fit:contain}.fld.signature span{font-size:1.45mm;color:#94a3b8;font-weight:700}.fld.signature small{font-size:1.4mm;color:#64748b;line-height:1}.backbuilt{position:absolute;inset:0;background:#f9faf9;overflow:hidden}.backdark{position:absolute;z-index:1;left:0;right:0;top:0;height:31mm;background:linear-gradient(155deg,#07111b,#111827);color:#fff;display:flex;flex-direction:column;align-items:center;text-align:center;padding:6mm 3mm 0}.backdark b{font-size:2.7mm;line-height:1.05;letter-spacing:.15mm}.backdark small{font-size:1.25mm;margin-top:1.2mm;letter-spacing:.1mm}.backwave{position:absolute;z-index:3;left:-5mm;right:-5mm;top:24mm;height:15mm;background:#f9faf9;border-radius:50% 50% 0 0/70% 70% 0 0;transform:rotate(3deg)}.watermark{position:absolute;z-index:1;left:7mm;top:34mm;width:40mm;height:40mm;object-fit:contain;opacity:.065;filter:grayscale(1);}.backcontent{position:absolute;z-index:4;left:4mm;right:4mm;top:34mm;bottom:3mm;text-align:center;display:flex;flex-direction:column;align-items:center}.wear{font-family:Georgia,serif;font-size:1.85mm;text-decoration:underline;margin-bottom:2mm}.return{font-family:Georgia,serif;font-size:1.65mm}.office{font-size:1.95mm;letter-spacing:.05mm;margin-top:1.2mm}.officecontact{font-size:1.55mm;margin-top:.8mm}.emergency{margin-top:5mm;display:flex;flex-direction:column;align-items:center;max-width:44mm}.emergency span{font-family:Georgia,serif;font-size:1.85mm;margin-bottom:1.5mm}.emergency b{font-size:2.55mm;text-transform:uppercase;line-height:1.05}.emergency strong{font-size:2.7mm;letter-spacing:.12mm;margin-top:.6mm}.emergency small{font-size:1.65mm;font-weight:700;text-transform:uppercase;line-height:1.15;margin-top:.8mm}.registrar{margin-top:auto;width:42mm;display:flex;flex-direction:column;align-items:center}.regsig{font-family:"Segoe Script","Brush Script MT",cursive;font-size:3.2mm;line-height:1;margin-bottom:-.2mm;transform:rotate(-4deg)}.registrar i{display:block;width:28mm;border-top:.22mm solid #334155}.registrar b{font-size:1.75mm;margin-top:1mm;letter-spacing:.03mm}.registrar small{font-size:1.4mm;margin-top:.5mm;letter-spacing:.08mm}
    .validationback{background:#fff;color:#111;padding:2.3mm 2.5mm 2.2mm;display:flex;flex-direction:column;align-items:stretch}.validationwatermark{left:7mm!important;top:28mm!important;width:40mm!important;height:40mm!important;opacity:.045!important}.backschool{text-align:center;position:relative;z-index:3;border-bottom:.22mm solid #111;padding:0 1mm 1.6mm}.backschool b{display:block;font-size:1.9mm;line-height:1.08;letter-spacing:.05mm}.backschool small{display:block;font-size:1.2mm;margin-top:.7mm}.emergencybox{position:relative;z-index:3;border:.22mm solid #111;border-top:0}.inforow{min-height:8.2mm;padding:1mm 1.5mm;border-bottom:.18mm solid #111;text-align:left;display:flex;flex-direction:column;justify-content:center}.inforow:last-child{border-bottom:0}.inforow span{font-size:1.35mm;font-weight:800;letter-spacing:.02mm}.inforow b{font-size:2mm;text-align:center;line-height:1.05;margin-top:.5mm}.rules{position:relative;z-index:3;display:flex;flex-direction:column;gap:1mm;margin:2.4mm .5mm 2mm;font-size:1.15mm;line-height:1.15}.validationgrid{position:relative;z-index:3;display:grid;grid-template-columns:12mm repeat(3,1fr);grid-template-rows:8mm repeat(4,6.6mm);border-left:.22mm solid #111;border-top:.22mm solid #111}.validationgrid>div{border-right:.22mm solid #111;border-bottom:.22mm solid #111;display:flex;align-items:center;justify-content:center;text-align:center;font-size:1.45mm}.validationgrid .vh{background:#111;color:#fff;font-size:1.5mm;font-weight:800;line-height:1.05}.validationgrid .vy{background:#111;color:#fff;font-weight:800;font-size:1.4mm}.validationregistrar{position:relative;z-index:3;margin-top:auto;width:100%;padding-bottom:.5mm}.validationregistrar i{width:28mm;margin:0 auto 1mm}.validationregistrar b{font-size:1.65mm!important;margin-top:0!important;line-height:1.08}.validationregistrar small{font-size:1.25mm!important;margin-top:.65mm!important;letter-spacing:.04mm}.validationregistrar .regsig{display:none}.backdark,.backwave,.backcontent{display:none!important}
    @media print{html,body{background:#fff}.toolbar,.page-label{display:none}.print-page{break-after:page;page-break-after:always}.idcard{box-shadow:none}.slot:before{border-color:#bbb}.slot:after{opacity:.65}}
    </style></head><body><div class="toolbar"><button onclick="window.print()">Print PVC Sheet</button><span>${students.length} ID(s) · 2 per A4 page · CR80 53.98 × 85.60 mm · Fixed Emergency Back · 100% Actual Size · Duplex: Flip on long edge</span></div>${pages}<script>document.querySelectorAll('.fld.txt').forEach(el=>{let s=14;el.style.fontSize=s+'px';while(s>7&&(el.scrollWidth>el.clientWidth||el.scrollHeight>el.clientHeight)){s-=.5;el.style.fontSize=s+'px'}})<\/script></body></html>`);
    win.document.close();
}


/* Safe Settings: operational health only — no secret values rendered */
async function refreshSafeOperationalHealth() {
    try {
        const res = await fetch('index.php?action=get_sms_status', {cache:'no-store'});
        const data = await res.json();
        const pending = document.getElementById('sms-pending');
        const sent = document.getElementById('sms-sent');
        const failed = document.getElementById('sms-failed');
        if (pending) pending.textContent = Number(data.pending || 0);
        if (sent) sent.textContent = Number(data.sent || 0);
        if (failed) failed.textContent = Number(data.failed || 0);
    } catch (_) {}
}
document.getElementById('reload-sms-btn')?.addEventListener('click', refreshSafeOperationalHealth);
refreshSafeOperationalHealth();


/* ═══════════════════════════
   INTRAMURALS CLUSTER MANAGER
═══════════════════════════ */
function initClusterManager() {
    document.querySelectorAll('#cluster-division-tabs .cluster-division-tab').forEach(btn => btn.addEventListener('click', () => {
        App.clusterDivision = btn.dataset.division || 'College';
        document.querySelectorAll('#cluster-division-tabs .cluster-division-tab').forEach(b => b.classList.toggle('active', b===btn));
        loadClusterDashboard();
    }));
    document.getElementById('cluster-new-event')?.addEventListener('click', () => { resetIntramuralEventForm(); openModal('cluster-event-modal'); });
    document.getElementById('cluster-delete-event')?.addEventListener('click', openDeleteIntramuralEventModal);
    document.getElementById('cluster-delete-event-close')?.addEventListener('click', () => closeModal('cluster-delete-event-modal'));
    document.getElementById('cluster-delete-event-cancel')?.addEventListener('click', () => closeModal('cluster-delete-event-modal'));
    document.getElementById('cluster-delete-event-modal')?.addEventListener('click', e => { if (e.target.id==='cluster-delete-event-modal') closeModal('cluster-delete-event-modal'); });
    document.getElementById('cluster-delete-event-confirm')?.addEventListener('click', deleteIntramuralEvent);
    document.querySelectorAll('[data-cluster-modal-close]').forEach(x => x.addEventListener('click',()=>closeModal('cluster-event-modal')));
    document.getElementById('cluster-event-form')?.addEventListener('submit', saveIntramuralEvent);
    document.getElementById('cluster-event-select')?.addEventListener('change', e => { App.clusterEventId=e.target.value; syncDeleteEventButton(); loadClusterDashboard(); syncClusterEventHeading(); });
    document.getElementById('cluster-add-name')?.addEventListener('click', () => addClusterNameInput(''));
    document.getElementById('cluster-save-names')?.addEventListener('click', saveClusterNames);
    document.getElementById('cluster-generate')?.addEventListener('click', generateClusterAssignments);
    document.getElementById('cluster-member-search')?.addEventListener('input', renderClusterRosters);
    document.getElementById('cluster-print')?.addEventListener('click', printClusterRosters);
    ['college-event-lunch','hs-event-lunch'].forEach(id=>document.getElementById(id)?.addEventListener('change',syncEventLunchVisibility));
    const overrideToggle=document.getElementById('cluster-event-override');
    if(overrideToggle){
        const syncOverrideToggle=()=>{
            const label=overrideToggle.closest('.event-override-toggle');
            label?.classList.toggle('enabled',overrideToggle.checked);
            label?.setAttribute('aria-checked',overrideToggle.checked?'true':'false');
        };
        overrideToggle.addEventListener('change',syncOverrideToggle);
        syncOverrideToggle();
    }
}

function loadIntramuralEvents() {
    fetch('index.php?action=get_intramural_events').then(r=>r.json()).then(data=>{
        if(data.status==='migration_required'){
            showToast('Intramurals Setup','Run sql_intramurals_cluster_migration.sql in tracked_db first.','warning');
            return;
        }
        const sel=document.getElementById('cluster-event-select'); if(!sel)return;
        const events=data.events||[]; App.intramuralEvents=events;
        sel.innerHTML=events.length?events.map(e=>`<option value="${e.id}" ${e.is_active==1?'data-active="1"':''}>${esc(e.name)} • ${esc(e.school_year)}</option>`).join(''):'<option value="">No event yet</option>';
        const active=events.find(e=>String(e.is_active)==='1')||events[0];
        if(active){App.clusterEventId=String(active.id);sel.value=App.clusterEventId;} else App.clusterEventId='';
        syncDeleteEventButton(); syncClusterEventHeading(events); loadClusterDashboard(); loadReportClusterEvents(events); refreshEventOverrideStatus();
    }).catch(()=>showToast('Error','Could not load Intramurals events.','error'));
}

function syncDeleteEventButton(){
    const btn=document.getElementById('cluster-delete-event');
    if(btn) btn.disabled=!App.clusterEventId;
}
function openDeleteIntramuralEventModal(){
    if(!App.clusterEventId) return showToast('No Event Selected','Select an Intramurals event first.','warning');
    const sel=document.getElementById('cluster-event-select');
    const label=sel?.selectedOptions?.[0]?.textContent||'Selected Intramurals event';
    const name=document.getElementById('cluster-delete-event-name'); if(name) name.textContent=label;
    openModal('cluster-delete-event-modal');
}
function deleteIntramuralEvent(){
    if(!App.clusterEventId) return;
    const btn=document.getElementById('cluster-delete-event-confirm');
    const original=btn?.innerHTML||'';
    if(btn){btn.disabled=true;btn.innerHTML='<i class="ph ph-spinner ph-spin"></i> Deleting...';}
    const fd=new FormData();fd.append('action','delete_intramural_event');fd.append('event_id',App.clusterEventId);
    fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.status==='success'){
            closeModal('cluster-delete-event-modal');
            App.clusterEventId=''; App.clusterDashboard={clusters:[]};
            showToast('Event Deleted',d.message||'Intramurals event removed.','success');
            loadIntramuralEvents();
        }else showToast('Delete Failed',d.message||'Could not delete event.','error');
    }).catch(()=>showToast('Delete Failed','Could not reach the server.','error')).finally(()=>{if(btn){btn.disabled=false;btn.innerHTML=original;}});
}

function syncClusterEventHeading(events=null){
    const sel=document.getElementById('cluster-event-select'); const opt=sel?.selectedOptions?.[0];
    document.getElementById('cluster-event-title').textContent=opt&&sel.value?opt.textContent:'Create or select an Intramurals event';
    document.getElementById('cluster-event-subtitle').textContent=App.clusterDivision==='College'?'College pool is isolated from all High School assignments.':'Senior High and Junior High share the High School pool; College students are excluded.';
}

function evVal(id){return document.getElementById(id)?.value||'';} function evCheck(id){return document.getElementById(id)?.checked?1:0;}
function resetIntramuralEventForm(){document.getElementById('cluster-event-form')?.reset(); const id=document.getElementById('cluster-event-id');if(id)id.value=''; const ov=document.getElementById('cluster-event-override');if(ov){ov.checked=true;ov.dispatchEvent(new Event('change',{bubbles:true}));} syncEventLunchVisibility();}
function syncEventLunchVisibility(){[['college-event-lunch','college-event-lunch-times'],['hs-event-lunch','hs-event-lunch-times']].forEach(([c,b])=>{const box=document.getElementById(b);if(box)box.classList.toggle('enabled',!!document.getElementById(c)?.checked);});}
function saveIntramuralEvent(e){
    e.preventDefault(); const fd=new FormData(); const add=(k,v)=>fd.append(k,v);
    add('action','save_intramural_event');add('id',evVal('cluster-event-id'));add('name',evVal('cluster-event-name'));add('school_year',evVal('cluster-event-school-year'));add('start_date',evVal('cluster-event-start'));add('end_date',evVal('cluster-event-end'));add('override_enabled',evCheck('cluster-event-override'));
    [['college','college-event'],['hs','hs-event']].forEach(([p,id])=>{add(`${p}_time_in_start`,evVal(`${id}-time-in`));add(`${p}_ontime_cutoff`,evVal(`${id}-ontime`));add(`${p}_late_cutoff`,evVal(`${id}-late`));add(`${p}_lunch_enabled`,evCheck(`${id}-lunch`));add(`${p}_lunch_out_start`,evVal(`${id}-lunch-out-start`));add(`${p}_lunch_out_end`,evVal(`${id}-lunch-out-end`));add(`${p}_lunch_in_start`,evVal(`${id}-lunch-in-start`));add(`${p}_lunch_in_end`,evVal(`${id}-lunch-in-end`));add(`${p}_time_out_start`,evVal(`${id}-out-start`));add(`${p}_time_out_end`,evVal(`${id}-out-end`));});
    fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status==='success'){closeModal('cluster-event-modal');resetIntramuralEventForm();showToast('Event Schedule Saved',d.message,'success');loadIntramuralEvents();}else showToast('Could not save',d.message||'Unknown error','error');});
}
function refreshEventOverrideStatus(){fetch('index.php?action=get_intramural_override_status').then(r=>r.json()).then(d=>{const title=document.getElementById('event-override-status-title'),copy=document.getElementById('event-override-status-copy'),pill=document.getElementById('event-mode-pill'),box=document.getElementById('event-override-status');if(!title||!copy||!pill)return;if(d.active&&d.event){const e=d.event;title.textContent=`${e.name} event attendance is active`;copy.textContent=`${e.start_date} to ${e.end_date} • Event times qualify Intramurals attendance while permanent campus IN/OUT monitoring continues independently.`;pill.textContent='EVENT MODE';box?.classList.add('active');}else{title.textContent='Normal campus monitoring active';copy.textContent='No time-bound event attendance window is active today. Permanent IN/OUT movement logging remains available.';pill.textContent='NORMAL MODE';box?.classList.remove('active');}}).catch(()=>{});}

function loadClusterDashboard(){
    syncClusterEventHeading(); if(!App.clusterEventId){renderClusterSetup([]);renderClusterRosters();return;}
    fetch(`index.php?action=get_cluster_dashboard&event_id=${encodeURIComponent(App.clusterEventId)}&division=${encodeURIComponent(App.clusterDivision)}`).then(r=>r.json()).then(d=>{
        if(d.status!=='success')return; App.clusterDashboard=d;
        document.getElementById('cluster-pool-count').textContent=d.pool_count||0;document.getElementById('cluster-assigned-count').textContent=d.assigned_count||0;document.getElementById('cluster-unassigned-count').textContent=d.unassigned_count||0;document.getElementById('cluster-count').textContent=(d.clusters||[]).length;
        renderClusterSetup(d.clusters||[]); renderClusterHistory(d.history||[]); renderClusterRosters();
    });
}

function renderClusterSetup(clusters){const list=document.getElementById('cluster-name-list');if(!list)return;list.innerHTML='';if(!clusters.length){['Cluster Alpha','Cluster Beta','Cluster Gamma','Cluster Delta'].forEach(addClusterNameInput);}else clusters.forEach(c=>addClusterNameInput(c.name));}
function addClusterNameInput(name=''){const list=document.getElementById('cluster-name-list');if(!list)return;const row=document.createElement('div');row.className='cluster-name-row';row.innerHTML=`<span class="cluster-name-index">${list.children.length+1}</span><input class="form-input cluster-name-input" value="${esc(name)}" placeholder="Cluster name"><button class="btn-icon cluster-remove-name" title="Remove"><i class="ph ph-trash"></i></button>`;row.querySelector('.cluster-remove-name').addEventListener('click',()=>{if(list.children.length<=2){showToast('Minimum 2 clusters','','warning');return;}row.remove();[...list.children].forEach((r,i)=>r.querySelector('.cluster-name-index').textContent=i+1);});list.appendChild(row);}
function saveClusterNames(){if(!App.clusterEventId){showToast('Create an Event First','','warning');return;}const names=[...document.querySelectorAll('.cluster-name-input')].map(x=>x.value.trim()).filter(Boolean);const fd=new FormData();fd.append('action','save_clusters');fd.append('event_id',App.clusterEventId);fd.append('division',App.clusterDivision);fd.append('names',JSON.stringify(names));fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{showToast(d.status==='success'?'Clusters Saved':'Error',d.message||'',d.status==='success'?'success':'error');if(d.status==='success')loadClusterDashboard();});}
function generateClusterAssignments(){if(!App.clusterEventId)return showToast('Create an Event First','','warning');if(!confirm(`Randomly assign ALL active ${App.clusterDivision} students into the configured clusters? Existing assignments for this division will be replaced.`))return;const fd=new FormData();fd.append('action','generate_cluster_assignments');fd.append('event_id',App.clusterEventId);fd.append('division',App.clusterDivision);fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{showToast(d.status==='success'?'Randomization Complete':'Generation Failed',d.message||'',d.status==='success'?'success':'error');if(d.status==='success')loadClusterDashboard();});}
function renderClusterHistory(history){const box=document.getElementById('cluster-history');if(!box)return;box.innerHTML=history.length?history.map(h=>`<div class="cluster-history-item"><i class="ph-fill ph-shuffle-angular"></i><div><strong>${h.student_count} students • ${h.cluster_count} clusters</strong><span>${esc(h.generated_at)}</span></div></div>`).join(''):'<div class="cluster-empty-mini">No generation history yet.</div>';}
function academicMini(s){const l=s.education_level||'College';if(l==='College')return `${s.course||'College'} • ${s.year_level||''}`;if(l==='Senior High School')return `${s.strand||s.track||'SHS'} • ${s.year_level||''}`;return `${s.year_level||'Junior High'}${s.section?' • Sec. '+s.section:''}`;}
function renderClusterRosters(){const grid=document.getElementById('cluster-roster-grid');if(!grid)return;const clusters=App.clusterDashboard?.clusters||[];const q=(document.getElementById('cluster-member-search')?.value||'').toLowerCase();if(!clusters.length){grid.innerHTML=`<div class="cluster-empty-state"><i class="ph ph-trophy"></i><strong>No clusters configured</strong><span>Add cluster names above, then generate balanced assignments.</span></div>`;return;}grid.innerHTML=clusters.map((c,i)=>{const members=(c.members||[]).filter(m=>!q||`${m.full_name} ${m.student_id_no} ${academicMini(m)}`.toLowerCase().includes(q));return `<article class="cluster-roster-card"><div class="cluster-roster-head"><div class="cluster-avatar">${i+1}</div><div><span>CLUSTER ${String(i+1).padStart(2,'0')}</span><h4>${esc(c.name)}</h4></div><strong>${members.length}</strong></div><div class="cluster-member-list">${members.length?members.map(m=>`<div class="cluster-member-row"><div class="cluster-member-photo">${m.photo_path?`<img src="${esc(m.photo_path)}">`:`<i class="ph-fill ph-user"></i>`}</div><div class="cluster-member-copy"><strong>${esc(m.full_name)}</strong><span>${esc(m.student_id_no)} • ${esc(academicMini(m))}</span></div><select class="cluster-move-select themed-select" data-student="${m.id}">${clusters.map(x=>`<option value="${x.id}" ${x.id==c.id?'selected':''}>${esc(x.name)}</option>`).join('')}</select></div>`).join(''):'<div class="cluster-no-members">No matching members</div>'}</div></article>`;}).join('');grid.querySelectorAll('.cluster-move-select').forEach(sel=>sel.addEventListener('change',()=>moveClusterMember(sel.dataset.student,sel.value)));}
function moveClusterMember(student,cluster){const fd=new FormData();fd.append('action','move_cluster_member');fd.append('student_id',student);fd.append('cluster_id',cluster);fd.append('event_id',App.clusterEventId);fd.append('division',App.clusterDivision);fetch('index.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status==='success'){showToast('Member Moved','Manual adjustment saved.','success');loadClusterDashboard();}else showToast('Move Failed','','error');});}
function printClusterRosters(){const clusters=App.clusterDashboard?.clusters||[];if(!clusters.length)return showToast('No Rosters to Print','','warning');const w=window.open('','_blank');if(!w)return;w.document.write(`<html><head><title>Intramurals Rosters</title><style>body{font-family:Arial;padding:28px;color:#111}h1{margin:0 0 4px}.meta{color:#555;margin-bottom:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}.card{border:1px solid #ccc;border-radius:12px;padding:16px;break-inside:avoid}.card h2{font-size:18px;margin:0 0 10px}.card ol{margin:0;padding-left:22px}.card li{padding:4px 0;font-size:12px}@media print{body{padding:0}.grid{gap:12px}}</style></head><body><h1>TrackED Intramurals Cluster Rosters</h1><div class="meta">${esc(document.getElementById('cluster-event-select')?.selectedOptions?.[0]?.textContent||'')} • ${esc(App.clusterDivision)}</div><div class="grid">${clusters.map(c=>`<div class="card"><h2>${esc(c.name)} (${(c.members||[]).length})</h2><ol>${(c.members||[]).map(m=>`<li>${esc(m.full_name)} — ${esc(m.student_id_no)} — ${esc(academicMini(m))}</li>`).join('')}</ol></div>`).join('')}</div><script>window.onload=()=>window.print()<\/script></body></html>`);w.document.close();}

function loadReportClusterEvents(prefetched=null){const fill=events=>{const sel=document.getElementById('report-cluster-event');if(!sel)return;const cur=sel.value;sel.innerHTML='<option value="">All / None</option>'+events.map(e=>`<option value="${e.id}">${esc(e.name)} • ${esc(e.school_year)}</option>`).join('');if([...sel.options].some(o=>o.value===cur))sel.value=cur;loadReportClusters();};if(prefetched)fill(prefetched);else fetch('index.php?action=get_intramural_events').then(r=>r.json()).then(d=>{if(d.status==='success')fill(d.events||[])}).catch(()=>{});}
function loadReportClusters(){const event=document.getElementById('report-cluster-event')?.value||'';const sel=document.getElementById('report-cluster');if(!sel)return;const level=document.getElementById('report-level')?.value||'';sel.disabled=true;if(!event){sel.innerHTML='<option value="">Select an event first</option>';return;}if(!level){sel.innerHTML='<option value="">Select education level first</option>';return;}const division=level==='College'?'College':'High School';sel.innerHTML='<option value="">Loading clusters…</option>';fetch(`index.php?action=get_cluster_dashboard&event_id=${encodeURIComponent(event)}&division=${encodeURIComponent(division)}`).then(r=>r.json()).then(d=>{sel.disabled=false;if(d.status==='success')sel.innerHTML='<option value="">All Clusters</option>'+(d.clusters||[]).map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('');else sel.innerHTML='<option value="">All Clusters</option>';}).catch(()=>{sel.disabled=false;sel.innerHTML='<option value="">All Clusters</option>';});}


/* ═══════════════════════════
   CORE UI UTILITIES
   These are intentionally kept in the main bundle because nearly every
   interactive module (records, reports, ID generator, clusters, settings)
   depends on them.
═══════════════════════════ */
function showToast(title, message, type='info', duration=4000) {
    const icons = { success:'ph-check-circle', error:'ph-x-circle', warning:'ph-warning', info:'ph-info' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<div class="toast-icon"><i class="ph-fill ${icons[type]||icons.info}"></i></div><div class="toast-content"><div class="toast-title">${esc(title)}</div><div class="toast-msg">${esc(message)}</div></div><button class="toast-close" type="button"><i class="ph ph-x"></i></button>`;
    document.getElementById('toast-container')?.appendChild(t);
    const dismissToast = () => {
        clearTimeout(Number(t.dataset.timer || 0));
        t.classList.add('removing');
        setTimeout(() => t.remove(), 260);
    };
    t.querySelector('.toast-close')?.addEventListener('click', dismissToast);
    t.dataset.timer = String(setTimeout(dismissToast, duration));
}
function statusBadge(status) {
    if (!status || status === 'Absent') return `<span class="badge badge-muted"><i class="ph ph-x-circle"></i> Absent</span>`;
    if (status==='On Time') return `<span class="badge badge-success"><i class="ph ph-check-circle"></i> On Time</span>`;
    if (status==='Late') return `<span class="badge badge-warning"><i class="ph ph-clock-countdown"></i> Late</span>`;
    return `<span class="badge badge-muted">${esc(status)}</span>`;
}
function initials(name) {
    if (!name) return '??';
    const p = String(name).trim().split(/\s+/);
    return (p.length>=2 ? (p[0][0]||'')+(p[p.length-1][0]||'') : String(name).substring(0,2)).toUpperCase();
}
function fmtTime(d) {
    if (!(d instanceof Date)) d = new Date(d);
    if (Number.isNaN(d.getTime())) return '--:--';
    const h=(d.getHours()%12||12).toString().padStart(2,'0');
    const m=d.getMinutes().toString().padStart(2,'0');
    const s=d.getSeconds().toString().padStart(2,'0');
    const ap=d.getHours()>=12?'PM':'AM';
    return `${h}:${m}:${s} ${ap}`;
}
function esc(s) {
    if (s==null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function setLoading(btn, on, label) {
    if (!btn) return;
    btn.disabled = !!on;
    btn.innerHTML = on ? `<div class="loader-dots" style="gap:3px"><span></span><span></span><span></span></div> ${label}` : label;
}

// ── DARK / LIGHT MODE ─────────────────────────────────────
(function initThemeToggle(){
    const html=document.documentElement;
    const btn=document.getElementById('theme-toggle');
    const icon=document.getElementById('theme-icon');
    const label=document.getElementById('theme-label');
    const STORAGE='tracked_theme';
    function applyTheme(theme){
        const next=theme==='dark'?'dark':'light';
        html.setAttribute('data-theme',next);
        try{ localStorage.setItem(STORAGE,next); }catch(e){}
        if(icon) icon.className=next==='dark'?'ph ph-sun':'ph ph-moon';
        if(label) label.textContent=next==='dark'?'Light':'Dark';
        btn?.setAttribute('aria-label',next==='dark'?'Switch to light theme':'Switch to dark theme');
        btn?.setAttribute('title',next==='dark'?'Switch to Light Mode':'Switch to Dark Mode');
    }
    let saved='light';
    try{ saved=localStorage.getItem(STORAGE)||html.getAttribute('data-theme')||'light'; }catch(e){}
    applyTheme(saved);
    btn?.addEventListener('click',()=>applyTheme(html.getAttribute('data-theme')==='dark'?'light':'dark'));
})();

window.TrackED = Object.assign(window.TrackED || {}, { showToast, fetchLiveScans, fetchStudents });


/* ═══════════════════════════
   OPERATIONS — SYSTEM HEALTH
═══════════════════════════ */
function opsDateTime(v){
    if(!v)return '—'; const d=new Date(String(v).replace(' ','T')); if(Number.isNaN(d.getTime()))return String(v);
    return `${d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})} • ${fmtTime(d)}`;
}
function opsRelative(v){
    if(!v)return 'Never'; const d=new Date(String(v).replace(' ','T')); const sec=Math.max(0,Math.floor((Date.now()-d.getTime())/1000));
    if(sec<10)return 'just now'; if(sec<60)return `${sec}s ago`; const m=Math.floor(sec/60); if(m<60)return `${m}m ago`; const h=Math.floor(m/60); if(h<24)return `${h}h ago`; return `${Math.floor(h/24)}d ago`;
}
function humanBytes(n){n=Number(n||0);if(!n)return '—';const u=['B','KB','MB','GB','TB'];let i=0;while(n>=1024&&i<u.length-1){n/=1024;i++;}return `${n.toFixed(i<2?0:1)} ${u[i]}`;}
function setHealthState(id,state){const el=document.getElementById(id);if(!el)return;el.classList.remove('health-ok','health-warn','health-bad','health-neutral');el.classList.add(`health-${state}`);}
async function loadSystemHealth(){
    const stamp=document.getElementById('health-last-refresh'); if(stamp)stamp.textContent='Refreshing...';
    try{
        const r=await fetch('index.php?action=get_system_health',{cache:'no-store'});const d=await r.json();
        const set=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=v};
        const dbOk=d.database?.status==='ONLINE';set('health-db-status',dbOk?'ONLINE':'OFFLINE');set('health-db-detail',dbOk?`${d.database.latency_ms??'—'} ms query`:(d.database?.message||'Unavailable'));setHealthState('health-db-card',dbOk?'ok':'bad');
        const opi=d.orangepi||{};const opiOk=opi.status==='ONLINE';set('health-opi-status',opi.status||'UNKNOWN');set('health-opi-detail',opi.last_seen?`Seen ${opsRelative(opi.last_seen)}${opi.server_latency_ms!=null?` • ${opi.server_latency_ms} ms DB`:''}`:'No heartbeat yet');setHealthState('health-opi-card',opiOk?'ok':(opi.status==='UNKNOWN'?'neutral':'bad'));
        const q=Number(opi.unsynced_movements||0)+Number(opi.unsynced_rejections||0)+Number(opi.pending_event_queue||0);set('health-queue-status',q===0?'CLEAR':`${q} PENDING`);set('health-queue-detail',`${Number(opi.unsynced_movements||0)} movement • ${Number(opi.unsynced_rejections||0)} denied • ${Number(opi.pending_event_queue||0)} event`);setHealthState('health-queue-card',q===0?'ok':q<10?'warn':'bad');
        const sms=d.sms||{};const failed=Number(sms.failed||0),pending=Number(sms.pending||0);set('health-sms-status',failed?`${failed} FAILED`:pending?`${pending} PENDING`:'QUEUE CLEAR');set('health-sms-detail',sms.latest_sent?`Last sent ${opsRelative(sms.latest_sent)}`:`${Number(sms.sent||0)} sent total`);setHealthState('health-sms-card',failed?'bad':pending>10?'warn':'ok');
        const b=d.backup||{};set('health-backup-status',b.status||'NOT CONFIGURED');set('health-backup-detail',b.completed_at?`Last run ${opsRelative(b.completed_at)}`:(b.message||'No backup run yet'));setHealthState('health-backup-card',b.status==='SUCCESS'?'ok':b.status==='FAILED'?'bad':'warn');
        const rf=d.rfid_activity||{};set('health-rfid-status',rf.last_activity?'ACTIVITY RECORDED':'IDLE');set('health-rfid-detail',rf.last_activity?`${rf.kind||'RFID'} • ${opsRelative(rf.last_activity)}`:'No scan activity recorded');setHealthState('health-rfid-card',rf.last_activity?'ok':'neutral');
        set('health-db-latency',d.database?.latency_ms!=null?`${d.database.latency_ms} ms local • ${opi.server_latency_ms!=null?opi.server_latency_ms+' ms Orange Pi→DB':'remote —'}`:'—');set('health-opi-seen',opi.last_seen?`${opsDateTime(opi.last_seen)} (${opsRelative(opi.last_seen)})`:'Never');set('health-opi-success',opi.last_sync_success?opsDateTime(opi.last_sync_success):'Never');set('health-opi-error',opi.last_sync_error||'None');set('health-opi-version',opi.version||'—');set('health-storage',`${humanBytes(d.storage?.free_bytes)} free of ${humanBytes(d.storage?.total_bytes)}`);
        if(stamp)stamp.textContent=`Updated ${new Date().toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit',second:'2-digit'})}`;
        const guide=document.getElementById('health-guidance');if(guide){const items=[];if(!d.operations_ready)items.push(['warning','Run sql_operations_upgrade.sql to enable the full Operations module.']);if(!opiOk)items.push(['warning','Orange Pi heartbeat is stale or missing. Check tracked-sync.service and network access to the Admin PC.']);if(q>0)items.push(['info',`${q} local item(s) are waiting to synchronize. Unsynced records remain protected from local cleanup.`]);if(failed)items.push(['warning',`${failed} SMS message(s) are marked failed. Review the SMS queue/modem before assuming notifications were delivered.`]);if(b.status==='FAILED')items.push(['warning','The latest verified backup run failed. Auto-purge remains protected by the backup verification rules.']);if(!items.length)items.push(['check-circle','Core health indicators are normal. Continue routine monitoring and verified backups.']);guide.innerHTML=items.map(([icon,txt])=>`<div class="health-guidance-item"><i class="ph ph-${icon}"></i><p>${esc(txt)}</p></div>`).join('');}
    }catch(e){if(stamp)stamp.textContent='Health fetch failed';['health-db-card','health-opi-card'].forEach(id=>setHealthState(id,'bad'));}
}
function initSystemHealth(){
    document.getElementById('health-refresh-btn')?.addEventListener('click',loadSystemHealth);
    if(App.healthInterval)clearInterval(App.healthInterval);App.healthInterval=setInterval(()=>{if(App.currentSection==='health')loadSystemHealth();},15000);
}

/* ═══════════════════════════
   OPERATIONS — AUDIT TRAIL
═══════════════════════════ */
async function loadAuditTrail(){
    const tbody=document.getElementById('audit-tbody');if(!tbody)return;tbody.innerHTML='<tr><td colspan="7" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Loading audit trail...</td></tr>';
    const qs=new URLSearchParams({action:'get_audit_log',category:document.getElementById('audit-category')?.value||'',s:document.getElementById('audit-search')?.value.trim()||'',date_from:document.getElementById('audit-date-from')?.value||'',date_to:document.getElementById('audit-date-to')?.value||'',limit:'300'});
    try{const r=await fetch(`index.php?${qs.toString()}`,{cache:'no-store'});const d=await r.json();App.auditData=d.logs||[];renderAuditTrail(App.auditData);}catch{tbody.innerHTML='<tr><td colspan="7" class="table-empty"><p>Could not load audit trail.</p></td></tr>';}
}
function auditBadge(cat){const c=String(cat||'SYSTEM').toUpperCase();return `<span class="audit-category audit-${c.toLowerCase()}">${esc(c)}</span>`;}
function renderAuditTrail(rows){const tbody=document.getElementById('audit-tbody'),count=document.getElementById('audit-count');if(count)count.textContent=`${rows.length} entr${rows.length===1?'y':'ies'}`;if(!tbody)return;if(!rows.length){tbody.innerHTML='<tr><td colspan="7" class="table-empty"><p>No audit entries match the selected filters.</p></td></tr>';return;}tbody.innerHTML=rows.map(r=>{let meta='';try{const m=JSON.parse(r.metadata_json||'{}');const useful=Object.entries(m).filter(([,v])=>v!==''&&v!==null&&typeof v!=='object').slice(0,3);if(useful.length)meta=`<div class="audit-meta">${useful.map(([k,v])=>`<span>${esc(k.replaceAll('_',' '))}: <b>${esc(v)}</b></span>`).join('')}</div>`;}catch{}return `<tr><td><div class="audit-time">${opsDateTime(r.created_at)}</div></td><td>${auditBadge(r.category)}</td><td><code class="audit-action">${esc(r.action_type||'')}</code></td><td><div class="student-name">${esc(r.description||'')}</div>${meta}</td><td><div>${esc(r.entity_type||'—')}</div><small>${esc(r.entity_id||'')}</small></td><td><div class="student-name">${esc(r.performed_by||'System')}</div></td><td><span class="audit-source">${esc(r.source||'ADMIN')}</span><small>${esc(r.ip_address||'')}</small></td></tr>`}).join('');}
function initAuditTrail(){let t;document.getElementById('audit-refresh-btn')?.addEventListener('click',loadAuditTrail);document.getElementById('audit-category')?.addEventListener('change',loadAuditTrail);['audit-date-from','audit-date-to'].forEach(id=>document.getElementById(id)?.addEventListener('change',loadAuditTrail));document.getElementById('audit-search')?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(loadAuditTrail,350);});}

/* ═══════════════════════════
   OPERATIONS — EMERGENCY MUSTER
═══════════════════════════ */
async function loadMuster(sessionId=''){
    const qs=new URLSearchParams({action:'get_muster'});if(sessionId)qs.set('session_id',sessionId);
    try{const r=await fetch(`index.php?${qs.toString()}`,{cache:'no-store'});const d=await r.json();App.musterData=d;renderMuster();}catch{showToast('Muster Error','Could not load emergency muster data.','error');}
}
function musterStatusBadge(s){const v=String(s||'UNCONFIRMED');const labels={UNCONFIRMED:'UNCONFIRMED',SAFE:'SAFE',NOT_FOUND:'NOT FOUND',EXCUSED:'EXCUSED'};const icons={UNCONFIRMED:'question',SAFE:'check-circle',NOT_FOUND:'warning',EXCUSED:'minus-circle'};return `<span class="muster-status status-${v.toLowerCase()}"><i class="ph-fill ph-${icons[v]||'question'}"></i>${labels[v]||v}</span>`;}
function renderMuster(){
    const d=App.musterData||{},session=d.session,members=d.members||[],active=session?.status==='ACTIVE',anyActive=(d.recent_sessions||[]).some(x=>x.status==='ACTIVE');const start=document.getElementById('muster-start-panel'),shell=document.getElementById('muster-active-shell');if(start)start.style.display=(active||anyActive)?'none':'';if(shell)shell.style.display=session?'':'none';
    if(session){const title=document.getElementById('muster-session-title'),meta=document.getElementById('muster-session-meta'),pill=document.querySelector('.muster-live-pill'),close=document.getElementById('close-muster-btn');if(title)title.textContent=session.title||'Emergency Muster';if(meta)meta.textContent=`Started ${opsDateTime(session.started_at)} by ${session.started_by||'SPMO'}${session.closed_at?` • Closed ${opsDateTime(session.closed_at)}`:''}${session.notes?` • ${session.notes}`:''}`;if(pill){pill.innerHTML=active?'<span></span> MUSTER ACTIVE':'<i class="ph-fill ph-check-circle"></i> MUSTER CLOSED';pill.classList.toggle('closed',!active);}if(close)close.style.display=active?'':'none';}
    const counts={SAFE:0,UNCONFIRMED:0,NOT_FOUND:0,EXCUSED:0};members.forEach(x=>counts[x.accounted_status]=(counts[x.accounted_status]||0)+1);const set=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=v};set('muster-total',members.length);set('muster-safe',counts.SAFE||0);set('muster-unconfirmed',counts.UNCONFIRMED||0);set('muster-notfound',counts.NOT_FOUND||0);set('muster-excused',counts.EXCUSED||0);
    renderMusterRows();renderMusterHistory(d.recent_sessions||[]);
}
function filteredMusterMembers(){const q=(document.getElementById('muster-search')?.value||'').toLowerCase().trim(),lvl=document.getElementById('muster-level-filter')?.value||'',st=document.getElementById('muster-status-filter')?.value||'';return (App.musterData?.members||[]).filter(x=>(!q||`${x.full_name_snapshot} ${x.student_id_no_snapshot} ${x.academic_group_snapshot} ${x.section_snapshot}`.toLowerCase().includes(q))&&(!lvl||x.education_level_snapshot===lvl)&&(!st||x.accounted_status===st));}
function renderMusterRows(){const tbody=document.getElementById('muster-tbody'),count=document.getElementById('muster-count');if(!tbody)return;const rows=filteredMusterMembers(),active=App.musterData?.session?.status==='ACTIVE';if(count)count.textContent=`${rows.length} student${rows.length!==1?'s':''}`;if(!rows.length){tbody.innerHTML='<tr><td colspan="9" class="table-empty"><p>No students match this muster view.</p></td></tr>';return;}tbody.innerHTML=rows.map((m,i)=>`<tr class="muster-row ${m.accounted_status==='NOT_FOUND'?'critical':''}"><td class="row-number">${i+1}</td><td><div class="student-name">${esc(m.full_name_snapshot)}</div><div class="student-meta">${esc(m.student_id_no_snapshot)}</div></td><td><span class="academic-level-badge">${esc(shortLevel(m.education_level_snapshot))}</span></td><td>${esc(m.academic_group_snapshot||'—')}</td><td>${esc(m.year_level_snapshot||'—')}</td><td>${esc(m.section_snapshot||'—')}</td><td>${musterStatusBadge(m.accounted_status)}${m.accounted_at?`<div class="student-meta">${opsRelative(m.accounted_at)}</div>`:''}</td><td><div class="muster-actions">${active?`<button class="muster-action safe" data-member="${m.id}" data-status="SAFE" title="Mark Safe"><i class="ph-fill ph-check"></i></button><button class="muster-action missing" data-member="${m.id}" data-status="NOT_FOUND" title="Mark Not Found"><i class="ph-fill ph-warning"></i></button><button class="muster-action excused" data-member="${m.id}" data-status="EXCUSED" title="Mark Excused"><i class="ph-fill ph-minus"></i></button><button class="muster-action reset" data-member="${m.id}" data-status="UNCONFIRMED" title="Reset"><i class="ph ph-arrow-counter-clockwise"></i></button>`:'<span class="student-meta">Read-only</span>'}</div></td><td><div class="muster-note-wrap"><input class="form-input muster-note-input" data-member="${m.id}" value="${esc(m.note||'')}" placeholder="Optional note" ${active?'':'disabled'}>${active?`<button class="muster-note-save" data-member="${m.id}" title="Save note"><i class="ph ph-floppy-disk"></i></button>`:''}</div></td></tr>`).join('');tbody.querySelectorAll('.muster-action').forEach(b=>b.addEventListener('click',()=>updateMusterMember(b.dataset.member,b.dataset.status)));tbody.querySelectorAll('.muster-note-save').forEach(b=>b.addEventListener('click',()=>{const m=(App.musterData.members||[]).find(x=>String(x.id)===String(b.dataset.member));updateMusterMember(b.dataset.member,m?.accounted_status||'UNCONFIRMED');}));}
async function updateMusterMember(id,status){const note=document.querySelector(`.muster-note-input[data-member="${String(id)}"]`)?.value||'';const fd=new FormData();fd.append('action','update_muster_member');fd.append('member_id',id);fd.append('status',status);fd.append('note',note);try{const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();if(d.status==='success')loadMuster(App.musterData?.session?.id||'');else showToast('Muster Update',d.message||'Could not update student.','error');}catch{showToast('Network Error','Could not update muster status.','error');}}
async function startMuster(){if(!confirm('Start an EMERGENCY MUSTER now?\n\nTrackED will freeze a snapshot of every student currently marked INSIDE campus.'))return;const btn=document.getElementById('start-muster-btn');setLoading(btn,true,'Starting...');const fd=new FormData();fd.append('action','start_muster');fd.append('title',document.getElementById('muster-title')?.value.trim()||'');fd.append('notes',document.getElementById('muster-notes')?.value.trim()||'');try{const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();showToast(d.status==='success'?'Emergency Muster Active':'Could Not Start',d.message||'',d.status);if(d.status==='success')loadMuster(d.session_id);}catch{showToast('Network Error','Could not start emergency muster.','error');}finally{setLoading(btn,false,'<i class="ph-fill ph-siren"></i> Start Emergency Muster');}}
async function closeMuster(){const sid=App.musterData?.session?.id;if(!sid)return;if(!confirm('Close this emergency muster?\n\nThe roster and all accounted statuses will remain preserved for review.'))return;const fd=new FormData();fd.append('action','close_muster');fd.append('session_id',sid);try{const r=await fetch('index.php',{method:'POST',body:fd});const d=await r.json();showToast(d.status==='success'?'Muster Closed':'Unable to Close',d.message||'',d.status);if(d.status==='success')loadMuster(sid);}catch{showToast('Network Error','Could not close muster.','error');}}
function renderMusterHistory(rows){const box=document.getElementById('muster-session-history');if(!box)return;if(!rows.length){box.innerHTML='<p class="student-meta">No previous sessions.</p>';return;}box.innerHTML=rows.map(x=>`<button type="button" class="muster-history-item ${String(App.musterData?.session?.id)===String(x.id)?'active':''}" data-session="${x.id}"><span><strong>${esc(x.title)}</strong><small>${opsDateTime(x.started_at)} • ${x.total_snapshot} student snapshot</small></span><span class="muster-history-status ${x.status.toLowerCase()}">${esc(x.status)}</span></button>`).join('');box.querySelectorAll('[data-session]').forEach(b=>b.addEventListener('click',()=>loadMuster(b.dataset.session)));}
function initEmergencyMuster(){document.getElementById('start-muster-btn')?.addEventListener('click',startMuster);document.getElementById('close-muster-btn')?.addEventListener('click',closeMuster);['muster-search','muster-level-filter','muster-status-filter'].forEach(id=>document.getElementById(id)?.addEventListener(id==='muster-search'?'input':'change',renderMusterRows));document.getElementById('muster-export-btn')?.addEventListener('click',()=>{const sid=App.musterData?.session?.id;if(!sid)return showToast('No Muster','Start or select a muster session first.','warning');window.location.href=`index.php?action=export_muster_csv&session_id=${encodeURIComponent(sid)}`;});document.getElementById('muster-print-btn')?.addEventListener('click',()=>{if(!App.musterData?.session)return showToast('No Muster','Start or select a muster session first.','warning');document.body.classList.add('print-muster');window.print();setTimeout(()=>document.body.classList.remove('print-muster'),500);});}
