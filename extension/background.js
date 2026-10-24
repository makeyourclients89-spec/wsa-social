/**
 * WSA Social - Auto Facebook Group Share Background Service Worker
 * Coordinates queue polling, tab opening, and reporting to WSA Social Backend.
 */

const DEFAULT_SERVER_URL = 'http://localhost/wsa-social/';
let isProcessing = false;
let currentTabId = null;

// Initialize on install or startup
chrome.runtime.onInstalled.addListener(() => {
  chrome.storage.local.get(['autopilot_enabled', 'server_url', 'interval_minutes'], (data) => {
    if (data.autopilot_enabled === undefined) {
      chrome.storage.local.set({ autopilot_enabled: true });
    }
    if (!data.server_url) {
      chrome.storage.local.set({ server_url: DEFAULT_SERVER_URL });
    }
    if (!data.interval_minutes) {
      chrome.storage.local.set({ interval_minutes: 2 });
    }
  });

  // Set recurring alarm every 1 minute
  chrome.alarms.create('wsa_queue_poller', { periodInMinutes: 1 });
  logEvent('WSA Extension installed & alarm initialized');
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === 'wsa_queue_poller') {
    checkAndRunNext(false);
  }
});

// Helper for extension logging
function logEvent(msg) {
  const time = new Date().toLocaleTimeString();
  const entry = `[${time}] ${msg}`;
  console.log(entry);
  chrome.storage.local.get(['recent_logs'], (data) => {
    const logs = data.recent_logs || [];
    logs.unshift(entry);
    if (logs.length > 30) logs.pop();
    chrome.storage.local.set({ recent_logs: logs });
  });
}

// Check server for due task and execute
async function checkAndRunNext(ignoreTime = false) {
  if (isProcessing) {
    console.log('WSA Auto-Pilot: Already processing an item, skipping this tick.');
    return { success: false, reason: 'BUSY' };
  }

  const { autopilot_enabled, server_url } = await chrome.storage.local.get(['autopilot_enabled', 'server_url']);
  if (!autopilot_enabled && !ignoreTime) {
    return { success: false, reason: 'PAUSED' };
  }

  const baseUrl = server_url || DEFAULT_SERVER_URL;
  const endpoint = `${baseUrl.replace(/\/$/, '')}/index.php?page=api-group-queue-next${ignoreTime ? '&ignore_time=1' : ''}`;

  try {
    const res = await fetch(endpoint, { cache: 'no-cache' });
    const data = await res.json();

    if (!data.success || !data.found || !data.item) {
      logEvent('No queue items due right now.');
      return { success: false, reason: 'EMPTY' };
    }

    const item = data.item;
    isProcessing = true;
    logEvent(`Starting Auto-Share for #${item.id} -> ${item.group_name} (${item.sender_name})`);

    // Store active task details for content script
    await chrome.storage.local.set({ active_task: item });

    // Open target group in a new tab
    const tab = await chrome.tabs.create({ url: item.group_url, active: true });
    currentTabId = tab.id;

    // Safety timeout: if posting does not complete in 90 seconds, reset lock
    setTimeout(() => {
      if (isProcessing && currentTabId === tab.id) {
        logEvent(`⚠️ Task #${item.id} timed out after 90 seconds.`);
        isProcessing = false;
        currentTabId = null;
        chrome.storage.local.remove('active_task');
      }
    }, 90000);

    return { success: true, item };
  } catch (err) {
    console.error('WSA Queue Check Error:', err);
    logEvent(`Error checking queue: ${err.message}`);
    isProcessing = false;
    return { success: false, error: err.message };
  }
}

// Complete share on backend
async function reportShareComplete(result) {
  const { server_url } = await chrome.storage.local.get(['server_url']);
  const baseUrl = server_url || DEFAULT_SERVER_URL;
  const endpoint = `${baseUrl.replace(/\/$/, '')}/index.php?page=api-group-queue-complete`;

  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(result)
    });
    const data = await res.json();
    logEvent(`Server response for #${result.queue_id}: ${data.message || 'Updated'}`);
    return data;
  } catch (err) {
    console.error('Failed to report share complete to server:', err);
    logEvent(`Failed to update server for #${result.queue_id}: ${err.message}`);
  }
}

// Listen for messages from content scripts and popup
chrome.runtime.onMessage.addListener((req, sender, sendResponse) => {
  if (req.action === 'GET_ACTIVE_TASK') {
    chrome.storage.local.get(['active_task'], (data) => {
      sendResponse({ task: data.active_task || null });
    });
    return true;
  }

  if (req.action === 'TASK_COMPLETED') {
    const result = req.result;
    logEvent(`✅ Content script completed task #${result.queue_id} (Status: ${result.status})`);
    
    // Update local database
    reportShareComplete(result).then(() => {
      // Clear active task
      chrome.storage.local.remove('active_task');
      isProcessing = false;

      // Close the tab after 4 seconds so user can see completion
      if (sender.tab && sender.tab.id) {
        setTimeout(() => {
          chrome.tabs.remove(sender.tab.id).catch(() => {});
        }, 4000);
      }
      currentTabId = null;
    });

    sendResponse({ acknowledged: true });
    return true;
  }

  if (req.action === 'TRIGGER_NOW') {
    checkAndRunNext(true).then((res) => {
      sendResponse(res);
    });
    return true;
  }

  if (req.action === 'TOGGLE_AUTOPILOT') {
    chrome.storage.local.get(['autopilot_enabled'], (data) => {
      const newState = !data.autopilot_enabled;
      chrome.storage.local.set({ autopilot_enabled: newState }, () => {
        logEvent(`Auto-Pilot state changed to: ${newState ? 'ACTIVE' : 'PAUSED'}`);
        sendResponse({ autopilot_enabled: newState });
      });
    });
    return true;
  }

  if (req.action === 'GET_STATE') {
    chrome.storage.local.get(['autopilot_enabled', 'recent_logs', 'active_task'], (data) => {
      sendResponse({
        isProcessing,
        autopilot_enabled: !!data.autopilot_enabled,
        active_task: data.active_task || null,
        recent_logs: data.recent_logs || []
      });
    });
    return true;
  }
});
