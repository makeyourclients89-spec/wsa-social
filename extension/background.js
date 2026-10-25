/**
 * WSA Social - Auto Facebook Group Share Background Service Worker
 * Coordinates queue polling, tab opening, and reporting to WSA Social Backend.
 * Version 1.1.0: Supports Dynamic Panel URL Auto-Sync, Human Timing & Client Scoping.
 */

const DEFAULT_SERVER_URL = 'http://localhost/wsa-social/';
let isProcessing = false;
let currentTabId = null;

// Initialize on install or startup
chrome.runtime.onInstalled.addListener(() => {
  chrome.storage.local.get(['autopilot_enabled', 'server_url', 'interval_minutes', 'active_client_id'], (data) => {
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
  logEvent('WSA Extension v1.1.0 installed & alarm initialized');
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
    if (logs.length > 35) logs.pop();
    chrome.storage.local.set({ recent_logs: logs });
  });
}

// Check server for due task and execute
async function checkAndRunNext(ignoreTime = false, forcedClientId = null, forcedItemId = null) {
  if (isProcessing) {
    console.log('WSA Auto-Pilot: Already processing an item, skipping this tick.');
    return { success: false, reason: 'BUSY' };
  }

  const { autopilot_enabled, server_url, active_client_id } = await chrome.storage.local.get([
    'autopilot_enabled',
    'server_url',
    'active_client_id'
  ]);

  if (!autopilot_enabled && !ignoreTime) {
    return { success: false, reason: 'PAUSED' };
  }

  const baseUrl = server_url || DEFAULT_SERVER_URL;
  const targetClient = forcedClientId || active_client_id || 2;

  const params = new URLSearchParams();
  params.append('page', 'api-group-queue-next');
  if (ignoreTime) params.append('ignore_time', '1');
  if (targetClient) params.append('client_id', targetClient);
  if (forcedItemId) params.append('item_id', forcedItemId);

  const endpoint = `${baseUrl.replace(/\/$/, '')}/index.php?${params.toString()}`;

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

    // Safety timeout: 180 seconds to comfortably allow human timing simulation (15-50s)
    setTimeout(() => {
      if (isProcessing && currentTabId === tab.id) {
        logEvent(`⚠️ Task #${item.id} timed out after 180 seconds.`);
        isProcessing = false;
        currentTabId = null;
        chrome.storage.local.remove('active_task');
      }
    }, 180000);

    return { success: true, item };
  } catch (err) {
    console.error('WSA Queue Check Error:', err);
    logEvent(`Error contacting ${baseUrl}: ${err.message}`);
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

// Listen for messages from content scripts, bridge, and popup
chrome.runtime.onMessage.addListener((req, sender, sendResponse) => {
  // Sync URL and client from active WSA Dashboard tab
  if (req.action === 'SYNC_WSA_PANEL') {
    const newUrl = req.server_url;
    const clientId = req.client_id || null;
    chrome.storage.local.set({
      server_url: newUrl,
      active_client_id: clientId,
      last_panel_seen: Date.now()
    }, () => {
      logEvent(`🔗 Synced with active dashboard: ${newUrl}`);
      sendResponse({ success: true, synced_url: newUrl });
    });
    return true;
  }

  if (req.action === 'GET_ACTIVE_TASK') {
    chrome.storage.local.get(['active_task'], (data) => {
      sendResponse({ task: data.active_task || null });
    });
    return true;
  }

  if (req.action === 'TASK_COMPLETED') {
    const result = req.result;
    logEvent(`✅ Content script completed task #${result.queue_id} (Status: ${result.status})`);

    // Update server database
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
    if (req.server_url) {
      chrome.storage.local.set({ server_url: req.server_url });
    }
    checkAndRunNext(true, req.client_id, req.item_id).then((res) => {
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

  if (req.action === 'UPDATE_SERVER_URL') {
    const newUrl = (req.server_url || '').trim();
    if (newUrl) {
      chrome.storage.local.set({ server_url: newUrl }, () => {
        logEvent(`Server URL updated manually to: ${newUrl}`);
        sendResponse({ success: true });
      });
    } else {
      sendResponse({ success: false, error: 'Empty URL' });
    }
    return true;
  }

  if (req.action === 'GET_STATE') {
    chrome.storage.local.get(['autopilot_enabled', 'recent_logs', 'active_task', 'server_url', 'active_client_id'], (data) => {
      sendResponse({
        isProcessing,
        autopilot_enabled: !!data.autopilot_enabled,
        active_task: data.active_task || null,
        recent_logs: data.recent_logs || [],
        server_url: data.server_url || DEFAULT_SERVER_URL,
        active_client_id: data.active_client_id || 2
      });
    });
    return true;
  }
});
