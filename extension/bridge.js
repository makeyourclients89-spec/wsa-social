/**
 * WSA Social Chrome Extension - Webpage Bridge & Auto Detection
 * Injected into the WSA dashboard to sync server URL, report extension status,
 * and execute direct instructions from the dashboard panel.
 */
(() => {
  'use strict';

  const extVersion = '1.1.0';

  function isWsaDashboard() {
    return !!(
      document.querySelector('meta[name="wsa-social-app"]') ||
      document.querySelector('meta[name="wsa-active-client-id"]') ||
      window.location.search.includes('page=groups') ||
      window.location.pathname.includes('wsa-social') ||
      document.title.includes('WSA Social')
    );
  }

  function getBaseServerUrl() {
    return window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');
  }

  function getActiveClientId() {
    const meta = document.querySelector('meta[name="wsa-active-client-id"]');
    if (meta && meta.content) {
      const id = parseInt(meta.content, 10);
      if (!isNaN(id) && id > 0) return id;
    }
    // Fallback: check URL param client_id or workspace selector
    const urlParams = new URLSearchParams(window.location.search);
    const paramId = parseInt(urlParams.get('client_id'), 10);
    return (!isNaN(paramId) && paramId > 0) ? paramId : 2;
  }

  function syncWithBackground() {
    if (!isWsaDashboard()) return;

    try {
      document.documentElement.setAttribute('data-wsa-extension-installed', '1');
      document.documentElement.setAttribute('data-wsa-extension-version', extVersion);

      const serverUrl = getBaseServerUrl();
      const clientId = getActiveClientId();

      chrome.runtime.sendMessage({
        action: 'SYNC_WSA_PANEL',
        server_url: serverUrl,
        page_url: window.location.href,
        client_id: clientId,
        version: extVersion
      }, (res) => {
        if (chrome.runtime.lastError) {
          // Extension might be reloading or sleeping
          return;
        }
        if (res && res.success) {
          window.dispatchEvent(new CustomEvent('wsa-extension-synced', {
            detail: { version: extVersion, server_url: serverUrl, client_id: clientId }
          }));
        }
      });
    } catch (e) {
      // Ignore context invalidated errors during extension reload
    }
  }

  // Broadcast readiness event to the webpage
  function announceReady() {
    if (!isWsaDashboard()) return;
    document.documentElement.setAttribute('data-wsa-extension-installed', '1');
    document.documentElement.setAttribute('data-wsa-extension-version', extVersion);

    window.dispatchEvent(new CustomEvent('wsa-extension-ready', { 
      detail: { version: extVersion, status: 'active', server_url: getBaseServerUrl() } 
    }));
  }

  // Listen for manual trigger from WSA Social dashboard button
  window.addEventListener('wsa-trigger-share-now', (e) => {
    try {
      const clientId = e.detail?.client_id || getActiveClientId();
      const itemId = e.detail?.item_id || null;
      chrome.runtime.sendMessage({
        action: 'TRIGGER_NOW',
        client_id: clientId,
        item_id: itemId,
        server_url: getBaseServerUrl()
      }, (res) => {
        window.dispatchEvent(new CustomEvent('wsa-trigger-response', { detail: res }));
      });
    } catch (err) {
      console.warn('WSA Bridge Trigger Error:', err);
    }
  });

  // Listen for manual verification / ping events from the dashboard
  window.addEventListener('wsa-ping-extension', () => {
    syncWithBackground();
    announceReady();
    window.dispatchEvent(new CustomEvent('wsa-pong-extension', { 
      detail: { version: extVersion, status: 'active', server_url: getBaseServerUrl() } 
    }));
  });

  // Initial announcement & sync
  announceReady();
  syncWithBackground();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      announceReady();
      syncWithBackground();
    });
  }

  // Periodic heartbeat sync while the dashboard tab remains open (every 10s)
  setInterval(() => {
    syncWithBackground();
  }, 10000);
})();
