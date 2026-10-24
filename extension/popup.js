/**
 * WSA Social Extension Popup Controller
 */

const SERVER_URL = 'http://localhost/wsa-social/';

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

  // Load state and server stats
  async function refreshData() {
    // 1. Get background state
    chrome.runtime.sendMessage({ action: 'GET_STATE' }, (state) => {
      if (!state) return;

      toggle.checked = state.autopilot_enabled;
      if (state.autopilot_enabled) {
        badge.className = 'badge badge-active';
        badge.innerText = 'ACTIVE';
      } else {
        badge.className = 'badge badge-paused';
        badge.innerText = 'PAUSED';
      }

      if (state.active_task) {
        taskBox.className = 'task-card';
        taskTitle.innerText = `⏳ Sharing: ${state.active_task.group_name}`;
        taskDesc.innerHTML = `As: <b>${state.active_task.sender_name}</b><br><small>${state.active_task.post_url}</small>`;
      } else {
        taskBox.className = 'task-card idle';
        taskTitle.innerText = 'Idle / Waiting for Next Due Task';
        taskDesc.innerText = 'Auto-Pilot runs queued items automatically every 2 minutes.';
      }

      // Render logs
      if (state.recent_logs && state.recent_logs.length > 0) {
        logsContainer.innerHTML = state.recent_logs
          .map((log) => `<div class="log-item">${escapeHtml(log)}</div>`)
          .join('');
      }
    });

    // 2. Fetch server stats
    try {
      const res = await fetch(`${SERVER_URL}index.php?page=api-group-queue-status`, { cache: 'no-cache' });
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
      console.warn('Could not contact WSA Social local server:', err);
    }
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

    chrome.runtime.sendMessage({ action: 'TRIGGER_NOW' }, (res) => {
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
  window.addEventListener('unload', () => clearInterval(timer));
});
