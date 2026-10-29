/**
 * WSA Social Extension Popup Controller
 * Version 1.1.0: Dynamic server URL auto-sync, client isolation, and realtime status.
 */

document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('toggle-autopilot');
  const badge = document.getElementById('status-badge');
  const btnRunNow = document.getElementById('btn-run-now');
  const taskBox = document.getElementById('active-task-box');
  const taskTitle = document.getElementById('task-title');
  const taskDesc = document.getElementById('task-desc');
  const statQueued = document.getElementById('stat-queued');
  const statShared = document.getElementById('stat-shared');
  const statPending = document.getElementById('stat-pending');
  const serverTime = document.getElementById('server-time');
  const logsContainer = document.getElementById('logs-container');
  const labelServerUrl = document.getElementById('label-server-url');
  const btnOpenDashboard = document.getElementById('btn-open-dashboard');
  const btnEditServer = document.getElementById('btn-edit-server');
  const boxEditServer = document.getElementById('box-edit-server');
  const inputServerUrl = document.getElementById('input-server-url');
  const btnSaveServer = document.getElementById('btn-save-server');
  const btnCancelServer = document.getElementById('btn-cancel-server');

  let activeServerUrl = 'http://localhost/wsa-social/';
  let activeClientId = 2;

  // Edit server controls
  if (btnEditServer) {
    btnEditServer.addEventListener('click', () => {
      boxEditServer.style.display = boxEditServer.style.display === 'none' ? 'block' : 'none';
      inputServerUrl.value = activeServerUrl;
    });
  }

  if (btnCancelServer) {
    btnCancelServer.addEventListener('click', () => {
      boxEditServer.style.display = 'none';
    });
  }

  if (btnSaveServer) {
    btnSaveServer.addEventListener('click', () => {
      let val = (inputServerUrl.value || '').trim();
      if (!val) return;
      if (!val.endsWith('/')) val += '/';
      chrome.runtime.sendMessage({ action: 'UPDATE_SERVER_URL', server_url: val }, (res) => {
        boxEditServer.style.display = 'none';
        refreshData();
      });
    });
  }

  // Load state and server stats
  async function refreshData() {
    // 1. Get background state
    chrome.runtime.sendMessage({ action: 'GET_STATE' }, async (state) => {
      if (!state) return;

      if (state.server_url) {
        activeServerUrl = state.server_url;
      }
      if (state.active_client_id) {
        activeClientId = state.active_client_id;
      }

      // Update server URL label and dashboard button
      if (labelServerUrl) {
        try {
          const u = new URL(activeServerUrl);
          labelServerUrl.innerText = u.host;
          labelServerUrl.title = activeServerUrl;
        } catch (e) {
          labelServerUrl.innerText = activeServerUrl;
        }
      }

      if (btnOpenDashboard) {
        btnOpenDashboard.href = `${activeServerUrl.replace(/\/$/, '')}/index.php?page=groups&tab=queue`;
      }

      toggle.checked = state.autopilot_enabled;
      if (state.autopilot_enabled) {
        badge.className = 'badge badge-active';
        badge.innerText = 'ACTIVE';
      } else {
        badge.className = 'badge badge-paused';
        badge.innerText = 'PAUSED';
      }

      // Update timing summary
      const timingSummaryEl = document.getElementById('popup-timings-summary');
      if (timingSummaryEl) {
        chrome.storage.local.get(['timing_settings'], (data) => {
          const t = data.timing_settings || {};
          const sMin = t.boost_scroll_min_sec || 20;
          const sMax = t.boost_scroll_max_sec || 30;
          const cMin = t.boost_comment_min_sec || 30;
          const cMax = t.boost_comment_max_sec || 45;
          const shM = t.group_share_interval_mins || 2;
          timingSummaryEl.innerText = `${sMin}-${sMax}s scroll • ${cMin}-${cMax}s comment • ${shM}m share`;
        });
      }

      if (state.active_task) {
        taskBox.className = 'task-card';
        taskTitle.innerText = `⏳ Sharing: ${state.active_task.group_name}`;
        taskDesc.innerHTML = `As: <b>${state.active_task.sender_name}</b><br><small>${state.active_task.post_url}</small>`;
      } else {
        taskBox.className = 'task-card idle';
        taskTitle.innerText = 'Idle / Waiting for Next Due Task';
        taskDesc.innerText = 'Auto-Pilot runs queued items automatically using human anti-bot cadence.';
      }

      // Render logs
      if (state.recent_logs && state.recent_logs.length > 0) {
        logsContainer.innerHTML = state.recent_logs
          .map((log) => `<div class="log-item">${escapeHtml(log)}</div>`)
          .join('');
      }

      // 2. Fetch server stats from the ACTIVE connected URL
      try {
        const statsUrl = `${activeServerUrl.replace(/\/$/, '')}/index.php?page=api-group-queue-status&client_id=${activeClientId}`;
        const res = await fetch(statsUrl, { cache: 'no-cache' });
        const data = await res.json();
        if (data.success) {
          statQueued.innerText = data.queued_count ?? 0;
          statShared.innerText = data.today_shared_count ?? data.shared_count ?? 0;
          statPending.innerText = data.pending_count ?? 0;
          if (data.server_time) {
            const t = data.server_time.split(' ')[1] || data.server_time;
            serverTime.innerText = `IST ${t}`;
          }
        }
      } catch (err) {
        console.warn('Could not contact active WSA server at', activeServerUrl, err);
      }
    });
  }

  // Toggle Auto-Pilot
  toggle.addEventListener('change', () => {
    chrome.runtime.sendMessage({ action: 'TOGGLE_AUTOPILOT' }, (res) => {
      refreshData();
    });
  });

  // Run Now button
  btnRunNow.addEventListener('click', () => {
    btnRunNow.disabled = true;
    btnRunNow.innerText = '⏳ Starting Next Share...';
    btnRunNow.style.opacity = '0.7';

    chrome.runtime.sendMessage({ action: 'TRIGGER_NOW', client_id: activeClientId, server_url: activeServerUrl }, (res) => {
      setTimeout(() => {
        btnRunNow.disabled = false;
        btnRunNow.innerText = '⚡ Run Next Share Now (Zero-Click)';
        btnRunNow.style.opacity = '1';
        refreshData();
      }, 1500);
    });
  });

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // Initial load & interval refresh
  refreshData();
  const timer = setInterval(refreshData, 3000);
});
