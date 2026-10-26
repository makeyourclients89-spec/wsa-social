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

// ============================================================
// Multi-Profile Auto-Like & Auto-Comment Queue Controller
// ============================================================
async function runNextLikeQueueItem() {
  const { like_queue } = await chrome.storage.local.get(['like_queue']);
  if (!like_queue || !like_queue.profiles) return;

  const { post_url, post_snippet, profiles, current_index, enable_like, enable_comment, delay_between_accounts_sec } = like_queue;

  if (current_index >= profiles.length) {
    logEvent(`🎉 Multi-Profile Auto-Boost complete! All ${profiles.length} connected accounts have finished.`);
    chrome.storage.local.remove(['like_queue', 'active_task']);
    isProcessing = false;
    return;
  }

  const profile = profiles[current_index];
  isProcessing = true;
  logEvent(`👉 [${current_index + 1}/${profiles.length}] Opening post to like/comment as "${profile.label}"`);

  const task = {
    type: 'AUTO_LIKE',
    post_url: post_url,
    post_snippet: post_snippet || '',
    profile_name: profile.label,
    profile_id: profile.id,
    enable_like: enable_like !== false,
    enable_comment: enable_comment !== false,
    index: current_index + 1,
    total: profiles.length
  };

  await chrome.storage.local.set({ active_task: task });

  const tab = await chrome.tabs.create({ url: post_url, active: true });
  currentTabId = tab.id;

  // 120s safety timeout per account
  setTimeout(() => {
    if (currentTabId === tab.id) {
      logEvent(`⚠️ Auto-Boost timeout for "${profile.label}". Advancing to next account.`);
      chrome.tabs.remove(tab.id).catch(() => {});
      currentTabId = null;
      advanceLikeQueue();
    }
  }, 120000);
}

function advanceLikeQueue() {
  chrome.storage.local.get(['like_queue'], (data) => {
    if (!data.like_queue) {
      isProcessing = false;
      return;
    }
    const queue = data.like_queue;
    queue.current_index++;
    const delaySec = Math.max(5, parseInt(queue.delay_between_accounts_sec || 25, 10));

    if (queue.current_index < queue.profiles.length) {
      logEvent(`⏳ Waiting ${delaySec}s before opening next profile [${queue.current_index + 1}/${queue.profiles.length}]...`);
    }

    chrome.storage.local.set({ like_queue: queue }, () => {
      setTimeout(() => {
        runNextLikeQueueItem();
      }, delaySec * 1000); // Configured delay between accounts!
    });
  });
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

      // Close the tab ONLY if status is 'shared' or 'pending_approval'
      // If status is 'failed', KEEP tab open so user can inspect or complete manually!
      if ((result.status === 'shared' || result.status === 'pending_approval') && sender.tab && sender.tab.id) {
        logEvent(`Closing tab #${sender.tab.id} in 4 seconds after successful record.`);
        setTimeout(() => {
          chrome.tabs.remove(sender.tab.id).catch(() => {});
        }, 4000);
      } else {
        logEvent(`Tab #${sender?.tab?.id} kept open (task status is "${result.status}").`);
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

  if (req.action === 'START_AUTO_LIKE') {
    const postUrl = req.post_url;
    const profiles = req.profiles || [];
    if (!postUrl || profiles.length === 0) {
      sendResponse({ success: false, error: 'Missing post URL or profiles' });
      return true;
    }

    const delaySec = Math.max(5, parseInt(req.delay_between_accounts_sec || 25, 10));
    const enableLike = req.enable_like !== false;
    const enableComment = req.enable_comment !== false;
    const postSnippet = req.post_snippet || '';

    logEvent(`🚀 Starting Auto-Boost for ${profiles.length} profiles (Delay: ${delaySec}s) on: ${postUrl}`);

    const likeQueue = {
      post_url: postUrl,
      post_id: req.post_id || null,
      post_snippet: postSnippet,
      profiles: profiles,
      delay_between_accounts_sec: delaySec,
      enable_like: enableLike,
      enable_comment: enableComment,
      current_index: 0
    };

    chrome.storage.local.set({ like_queue: likeQueue }, () => {
      runNextLikeQueueItem();
    });

    sendResponse({ success: true, count: profiles.length });
    return true;
  }

  if (req.action === 'LIKE_TASK_COMPLETED') {
    const result = req.result;
    logEvent(`✅ Auto-Boost finished for "${result.profile_name}" (Liked: ${result.already_liked ? 'Already' : 'Yes'}, Commented: ${result.commented ? 'Yes' : 'No'})`);

    // Close tab after completing session
    if (sender.tab && sender.tab.id) {
      chrome.tabs.remove(sender.tab.id).catch(() => {});
    }
    currentTabId = null;

    advanceLikeQueue();

    sendResponse({ acknowledged: true });
    return true;
  }
    currentTabId = null;

    advanceLikeQueue();

    sendResponse({ acknowledged: true });
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
