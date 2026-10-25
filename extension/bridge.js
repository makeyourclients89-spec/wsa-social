/**
 * WSA Social Chrome Extension - Webpage Bridge & Auto Detection
 * Injected into the WSA dashboard to report extension presence and active version.
 */
(() => {
  try {
    const extVersion = '1.0.0';
    document.documentElement.setAttribute('data-wsa-extension-installed', '1');
    document.documentElement.setAttribute('data-wsa-extension-version', extVersion);

    // Broadcast instant readiness event to the webpage
    window.dispatchEvent(new CustomEvent('wsa-extension-ready', { 
      detail: { version: extVersion, status: 'active' } 
    }));

    // Listen for manual verification / ping events from the dashboard
    window.addEventListener('wsa-ping-extension', () => {
      window.dispatchEvent(new CustomEvent('wsa-pong-extension', { 
        detail: { version: extVersion, status: 'active' } 
      }));
    });
  } catch (e) {
    console.error('WSA Bridge Error:', e);
  }
})();
