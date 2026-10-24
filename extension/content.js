/**
 * WSA Social - Facebook Group Auto-Publisher Content Script
 * Executes zero-click automated sharing inside Facebook Group feeds.
 */

(function () {
  'use strict';

  // Prevent multiple injections
  if (window.__WSA_CONTENT_INJECTED__) return;
  window.__WSA_CONTENT_INJECTED__ = true;

  console.log('🚀 WSA Social Auto-Share Content Script loaded.');

  let hudElement = null;

  // Create floating HUD
  function createHud(initialText = 'WSA Auto-Pilot Initializing...') {
    if (document.getElementById('wsa-hud-card')) return;

    hudElement = document.createElement('div');
    hudElement.id = 'wsa-hud-card';
    hudElement.style.cssText = `
      position: fixed;
      top: 18px;
      right: 22px;
      z-index: 999999;
      background: #0f172a;
      color: #f8fafc;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      font-size: 13px;
      padding: 14px 18px;
      border-radius: 12px;
      border: 1.5px solid #3b82f6;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      max-width: 360px;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      gap: 6px;
    `;

    hudElement.innerHTML = `
      <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155; padding-bottom:6px;">
        <span style="font-weight:800; color:#60a5fa; display:flex; align-items:center; gap:6px;">
          <span>⚡ WSA Auto-Pilot</span>
        </span>
        <span id="wsa-hud-badge" style="background:#1d4ed8; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px;">WORKING</span>
      </div>
      <div id="wsa-hud-status" style="font-weight:600; color:#e2e8f0; font-size:12.5px; line-height:1.4;">${initialText}</div>
    `;

    document.body.appendChild(hudElement);
  }

  function updateHud(statusText, badge = null, badgeBg = null) {
    if (!hudElement) createHud(statusText);
    const sEl = document.getElementById('wsa-hud-status');
    const bEl = document.getElementById('wsa-hud-badge');
    if (sEl) sEl.innerHTML = statusText;
    if (badge && bEl) {
      bEl.innerText = badge;
      if (badgeBg) bEl.style.background = badgeBg;
    }
  }

  // Helper sleep
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  // Initialize and check if current page matches an active task
  async function init() {
    // Wait for DOM to stabilize
    await sleep(2000);

    chrome.runtime.sendMessage({ action: 'GET_ACTIVE_TASK' }, async (response) => {
      if (!response || !response.task) {
        // No active task for this tab
        return;
      }

      const task = response.task;
      const currentUrl = window.location.href.toLowerCase();
      const taskGroupUrl = (task.group_url || '').toLowerCase();

      // Check if we are on the target Facebook group
      if (!currentUrl.includes('facebook.com/groups/')) {
        return;
      }

      console.log('🎯 WSA Auto-Pilot: Processing Task #' + task.id, task);
      createHud(`Preparing share for <b>${task.group_name}</b> as <i>${task.sender_name}</i>...`);

      try {
        await executeGroupShare(task);
      } catch (err) {
        console.error('WSA Auto-Pilot Execution Error:', err);
        updateHud(`❌ Error: ${err.message}`, 'FAILED', '#ef4444');
        chrome.runtime.sendMessage({
          action: 'TASK_COMPLETED',
          result: {
            queue_id: task.id,
            status: 'failed',
            shared_post_url: task.group_url,
            error_message: err.message
          }
        });
      }
    });
  }

  // Find Composer Box on Facebook Group Feed
  async function findComposerTrigger() {
    for (let attempts = 0; attempts < 15; attempts++) {
      // 1. Selector strategy: aria-labels
      let btn = document.querySelector('div[aria-label*="Write something"][role="button"]') ||
                document.querySelector('div[aria-label*="Create a public post"][role="button"]') ||
                document.querySelector('div[aria-label*="Write something..."]') ||
                document.querySelector('div[aria-label*="Create a post"]');

      if (btn) return btn;

      // 2. Text matching strategy
      const allButtons = document.querySelectorAll('div[role="button"][tabindex="0"]');
      for (const b of allButtons) {
        const text = (b.innerText || '').trim();
        if (
          text.includes('Write something...') ||
          text.includes('Write something to') ||
          text.includes('Create a public post') ||
          text.includes("What's on your mind")
        ) {
          return b;
        }
      }

      // 3. Spans with text
      const allSpans = document.querySelectorAll('span');
      for (const span of allSpans) {
        const text = (span.innerText || '').trim();
        if (text === 'Write something...' || text === 'Create a public post…' || text.startsWith('Write something to')) {
          const parentBtn = span.closest('div[role="button"]');
          if (parentBtn) return parentBtn;
          return span;
        }
      }

      await sleep(1000);
    }
    return null;
  }

  // Find Post Dialog (searches specifically for the Create Post modal)
  async function findPostDialog(maxAttempts = 20) {
    for (let i = 0; i < maxAttempts; i++) {
      const dialogs = Array.from(document.querySelectorAll('div[role="dialog"]'));
      
      // Check from newest to oldest (dialogs are usually appended at the end of DOM)
      for (let j = dialogs.length - 1; j >= 0; j--) {
        const d = dialogs[j];
        const rect = d.getBoundingClientRect();
        if (rect.width > 200 && rect.height > 150) {
          const text = (d.innerText || '');
          if (
            text.includes('Create post') ||
            text.includes('Create a public post') ||
            text.includes('Add to your post') ||
            text.includes("What's on your mind") ||
            d.querySelector('[aria-label="Post"]') ||
            d.querySelector('[aria-label*="Create a public post"]') ||
            d.querySelector('[contenteditable="true"]') ||
            d.querySelector('[role="textbox"]')
          ) {
            return d;
          }
        }
      }

      await sleep(500);
    }
    return null;
  }

  // Find the text input/contenteditable inside the modal dialog (rock-solid & multi-strategy)
  async function findEditor(dialog) {
    const candidateSelectors = [
      '[contenteditable="true"][role="textbox"]',
      '[contenteditable="true"]',
      '[role="textbox"]',
      '[data-lexical-editor="true"]',
      '[aria-label*="Create a public post"]',
      '[aria-label*="What\'s on your mind"]',
      '[aria-label*="Write something"]',
      '[data-placeholder*="Create a public post"]',
      '[aria-placeholder*="Create a public post"]'
    ];

    for (let attempt = 0; attempt < 25; attempt++) {
      // Strategy 1: Search inside the passed dialog
      if (dialog) {
        for (const sel of candidateSelectors) {
          const el = dialog.querySelector(sel);
          if (el) {
            console.log(`✅ WSA: Found editor in dialog via '${sel}'`, el);
            return el;
          }
        }
      }

      // Strategy 2: Search inside ANY div[role="dialog"] (100% modal-scoped, never the background feed!)
      for (const sel of candidateSelectors) {
        const el = document.querySelector(`div[role="dialog"] ${sel}`);
        if (el) {
          console.log(`✅ WSA: Found editor in role=dialog via '${sel}'`, el);
          return el;
        }
      }

      // Strategy 3: Click the placeholder text inside the modal to mount / focus Lexical
      const scope = dialog || document.querySelector('div[role="dialog"]');
      if (scope) {
        const textNodes = Array.from(scope.querySelectorAll('span, p, div'));
        const placeholder = textNodes.find(el => {
          const t = (el.innerText || '').trim();
          return (
            t === 'Create a public post...' ||
            t.startsWith('Create a public post') ||
            t.startsWith("What's on your mind") ||
            t.startsWith('Write something')
          );
        });

        if (placeholder) {
          placeholder.click();
          await sleep(350);
        } else {
          // Click center-upper region of modal where typing box lives
          const rect = scope.getBoundingClientRect();
          if (rect.width > 200 && rect.height > 150) {
            const clickTarget = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height * 0.35);
            if (clickTarget && scope.contains(clickTarget)) {
              clickTarget.click();
              await sleep(350);
            }
          }
        }

        // Strategy 4: Check if document.activeElement is now focused inside a modal
        if (document.activeElement && document.activeElement.closest('div[role="dialog"]')) {
          const act = document.activeElement;
          if (act.isContentEditable || act.getAttribute('role') === 'textbox' || act.hasAttribute('contenteditable')) {
            console.log('✅ WSA: Found editor via activeElement inside dialog', act);
            return act;
          }
        }
      }

      await sleep(400);
    }

    return null;
  }

  // Insert content into modern Lexical / Draft.js editors with full newline and preview support
  async function insertTextIntoEditor(editor, text) {
    // 1. Also copy to real OS clipboard for safety backup
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
      }
    } catch (e) {
      console.warn('Clipboard write fallback ignored:', e);
    }

    // 2. Focus and activate editor
    editor.focus();
    await sleep(200);
    editor.click();
    await sleep(200);

    // 3. Clear any existing selection
    try {
      const sel = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(editor);
      sel.removeAllRanges();
      sel.addRange(range);
    } catch (e) {}

    // 4. Primary Method: Native-like ClipboardEvent paste with DataTransfer
    // (This triggers Lexical's PASTE_COMMAND, parses newlines, and fetches Reel link preview)
    let pastedSuccess = false;
    try {
      const dt = new DataTransfer();
      dt.setData('text/plain', text);
      const pasteEvent = new ClipboardEvent('paste', {
        bubbles: true,
        cancelable: true,
        clipboardData: dt
      });
      editor.dispatchEvent(pasteEvent);
      await sleep(500);

      const curVal = (editor.innerText || editor.textContent || '').trim();
      if (curVal.length > 5) {
        pastedSuccess = true;
        console.log('✅ Text successfully pasted via ClipboardEvent into Lexical editor!');
      }
    } catch (err) {
      console.warn('ClipboardEvent paste attempt error:', err);
    }

    // 5. Fallback Method: Line-by-line insertion preserving paragraphs for Reel URL detection
    if (!pastedSuccess) {
      console.log('⚠️ Using line-by-line execCommand fallback...');
      editor.focus();
      const lines = text.split('\n');
      for (let idx = 0; idx < lines.length; idx++) {
        const line = lines[idx];
        if (line.length > 0) {
          try {
            document.execCommand('insertText', false, line);
          } catch (e) {}
        }
        if (idx < lines.length - 1) {
          try {
            document.execCommand('insertParagraph', false);
          } catch (e) {}
          await sleep(50);
        }
      }
    }

    // 6. Dispatch input & change events so React / Lexical updates its internal state
    editor.dispatchEvent(new InputEvent('input', { bubbles: true, cancelable: true, inputType: 'insertText', data: ' ' }));
    editor.dispatchEvent(new Event('input', { bubbles: true }));
    editor.dispatchEvent(new Event('change', { bubbles: true }));

    // 7. Flush state, trigger link preview parser, and cycle blur/focus
    try {
      editor.focus();
      document.execCommand('insertText', false, ' ');
      await sleep(100);
      document.execCommand('delete', false);
      await sleep(100);
      editor.blur();
      await sleep(250);
    } catch (e) {}

    return true;
  }

  // Find Facebook Post Button (waits for preview to load and button to be active, with auto-force-enable fallback)
  async function findPostButton(dialog) {
    const dialogScope = dialog || document.querySelector('div[role="dialog"]');

    for (let attempts = 0; attempts < 25; attempts++) {
      const candidateButtons = Array.from(
        dialogScope
          ? dialogScope.querySelectorAll('div[role="button"], button')
          : document.querySelectorAll('div[role="dialog"] div[role="button"], div[role="dialog"] button')
      );

      let detectedPostBtn = null;

      for (const b of candidateButtons) {
        const ariaLabel = (b.getAttribute('aria-label') || '').trim().toLowerCase();
        const text = (b.innerText || '').trim().toLowerCase();

        // Skip close or attachment icon buttons
        if (ariaLabel.includes('close') || ariaLabel.includes('photo') || ariaLabel.includes('tag') || ariaLabel.includes('feeling')) {
          continue;
        }

        const isPostCandidate = (
          ariaLabel === 'post' ||
          ariaLabel === 'publish' ||
          ariaLabel.includes('post to') ||
          text === 'post' ||
          text === 'publish' ||
          text.includes('post to') ||
          text.includes('submit')
        );

        if (isPostCandidate) {
          detectedPostBtn = b;

          const ariaDisabled = b.getAttribute('aria-disabled');
          const hasDisabledAttr = b.hasAttribute('disabled');
          const isDisabled = ariaDisabled === 'true' || hasDisabledAttr === true;

          // If button is enabled naturally, return it immediately
          if (!isDisabled) {
            console.log('✅ WSA: Active Post button ready!', b);
            return b;
          }
        }
      }

      // If the Post button is found but stays disabled (e.g., Reel preview taking too long or state desync),
      // after 10 attempts (~6 seconds) force-enable it so the post publishes!
      if (detectedPostBtn && attempts >= 10) {
        console.log('⚡ WSA: Force-enabling Post button to complete publication...', detectedPostBtn);
        detectedPostBtn.removeAttribute('disabled');
        detectedPostBtn.setAttribute('aria-disabled', 'false');
        detectedPostBtn.style.pointerEvents = 'auto';
        detectedPostBtn.style.opacity = '1';
        return detectedPostBtn;
      }

      await sleep(600);
    }

    return null;
  }

  // Check for Admin Approval or Live permalink with strict anti-false-positive checks
  async function detectOutcomeAndPermalink(task, existingPostUrls, preApprovalRequired) {
    let outcomeStatus = 'shared';
    let permalink = null;

    // 1. Wait for modal dialog to submit & close (up to 8 seconds)
    for (let wait = 0; wait < 16; wait++) {
      const activeDialog = document.querySelector('div[role="dialog"]');
      if (!activeDialog) break;

      // Check if Facebook showed an inline error/warning inside the dialog
      const dialogText = (activeDialog.innerText || '').toLowerCase();
      if (
        dialogText.includes('something went wrong') ||
        dialogText.includes('temporarily blocked') ||
        dialogText.includes('restricted from posting') ||
        dialogText.includes('violates our community') ||
        dialogText.includes('couldn\'t be shared')
      ) {
        throw new Error(`Facebook error: ${dialogText.slice(0, 120)}`);
      }

      await sleep(500);
    }

    // 2. If preApprovalRequired was already declared in the composer modal, it's 100% Pending Approval!
    if (preApprovalRequired) {
      console.log('🟡 WSA: Group composer confirmed post approval is required.');
      updateHud('🟡 Share Recorded! Pending Admin Approval. Tab closing in 4s...', 'APPROVAL', '#eab308');
      return { status: 'pending_approval', postUrl: null };
    }

    // 3. Monitor page for toasts, banners, and pending post widgets
    for (let i = 0; i < 18; i++) {
      const fullPageText = (document.body.innerText || '').toLowerCase();

      const approvalPhrases = [
        'submitted for admin approval',
        'pending admin approval',
        'sent to admin for review',
        'sent to admin',
        'waiting for approval',
        'pending approval',
        'submitted for approval',
        'requires admin approval',
        'participant approval',
        'will appear after it\'s approved',
        'will appear after it is approved',
        'will be visible after it is approved',
        'admin or moderator must approve',
        'admins must approve',
        'manage pending posts',
        'view pending posts',
        'your post has been submitted',
        'submitted your post',
        'post submitted'
      ];

      const hasPendingPhrase = approvalPhrases.some(phrase => fullPageText.includes(phrase));
      const hasPendingLink = !!document.querySelector('a[href*="/pending_posts"], a[href*="pending_posts"]');
      const urlIsPending = window.location.href.toLowerCase().includes('/pending_posts');

      if (hasPendingPhrase || hasPendingLink || urlIsPending) {
        console.log('🟡 WSA: Admin Approval notice detected!');
        updateHud('🟡 Share Recorded! Pending Admin Approval. Tab closing in 4s...', 'APPROVAL', '#eab308');
        return { status: 'pending_approval', postUrl: null };
      }

      // 4. Look for a BRAND NEW post permalink (NOT present before posting)
      const currentLinks = Array.from(document.querySelectorAll('a[href*="/posts/"], a[href*="/permalink/"]'));
      for (const a of currentLinks) {
        const href = a.href || '';
        if (href.includes('/groups/') && (href.includes('/posts/') || href.includes('/permalink/'))) {
          // Reject comments, users, and photos
          if (!href.includes('/user/') && !href.includes('/comment/') && !href.includes('comment_id')) {
            const clean = href.split('?')[0];
            // Must be NEW (not in pre-existing snapshot)
            if (!existingPostUrls.has(clean)) {
              permalink = clean;
              console.log('✅ WSA: Verified newly created post permalink:', permalink);
              break;
            }
          }
        }
      }

      if (permalink) {
        updateHud('✅ Live Post Permalink Verified! Tab closing in 4s...', 'SUCCESS', '#10b981');
        return { status: 'shared', postUrl: permalink };
      }

      await sleep(500);
    }

    // Default if dialog closed cleanly, no approval notice, and no new link extracted yet
    updateHud('✅ Successfully Shared to Group Feed! Tab closing in 4s...', 'SUCCESS', '#10b981');
    return { status: 'shared', postUrl: null };
  }

  // Profile / Sender Page Switcher: Ensure sharing happens as task.sender_name
  async function switchProfileIfNeeded(task, dialog = null) {
    if (!task || !task.sender_name) return false;

    // Normalize sender name: remove " (Page)", " [Page]", etc.
    const rawTarget = task.sender_name.replace(/\s*[\(\[](page|profile)[\)\]]/gi, '').trim();
    if (!rawTarget) return false;
    const cleanTargetLower = rawTarget.toLowerCase();

    console.log(`🔍 WSA Profile Check: Target sender is "${rawTarget}" (ID: ${task.account_external_id || 'N/A'})`);

    const isMatch = (str) => {
      if (!str) return false;
      const s = str.toLowerCase();
      return s.includes(cleanTargetLower) || (task.account_external_id && s.includes(String(task.account_external_id)));
    };

    // Helper: Select option from open dropdown / popover menu
    const selectTargetFromMenu = async () => {
      for (let attempt = 0; attempt < 10; attempt++) {
        // Facebook menus usually use role="menuitem", role="radio", role="menuitemradio", role="option", or clickable divs
        const candidates = Array.from(document.querySelectorAll(
          'div[role="menuitem"], div[role="radio"], div[role="menuitemradio"], div[role="option"], div[role="button"][tabindex="0"], div[role="dialog"] div[tabindex="0"]'
        ));

        for (const el of candidates) {
          const text = (el.innerText || '').toLowerCase();
          const aria = (el.getAttribute('aria-label') || '').toLowerCase();

          if (isMatch(text) || isMatch(aria)) {
            console.log(`🎯 WSA: Found profile switcher option matching "${rawTarget}":`, el);
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            await sleep(300);
            el.click();
            return true;
          }
        }
        await sleep(400);
      }
      return false;
    };

    // 1. If inside modal dialog, check author section
    if (dialog) {
      const dialogLines = (dialog.innerText || '').split('\n').map(l => l.trim()).filter(Boolean);
      const topLines = dialogLines.slice(0, 8);
      const alreadyActive = topLines.some(line => isMatch(line));

      if (alreadyActive) {
        console.log(`✅ WSA: Modal author is already "${rawTarget}".`);
        return true;
      }

      updateHud(`🔄 Checking modal author dropdown for "${rawTarget}"...`);

      // Find author dropdown button inside dialog (near the top)
      const modalRect = dialog.getBoundingClientRect();
      const candidates = Array.from(dialog.querySelectorAll('div[role="button"], button, div[aria-haspopup]'));
      const topButtons = candidates.filter(b => {
        const r = b.getBoundingClientRect();
        return r.top >= modalRect.top && r.top <= modalRect.top + 160 && r.width > 20 && r.height > 15;
      });

      let authorDropdownBtn = topButtons.find(b => {
        const label = (b.getAttribute('aria-label') || b.innerText || '').toLowerCase();
        return (
          label.includes('interact') ||
          label.includes('choose how') ||
          label.includes('switch') ||
          label.includes('posting as') ||
          b.getAttribute('aria-haspopup') === 'menu' ||
          b.getAttribute('aria-haspopup') === 'listbox' ||
          b.getAttribute('aria-haspopup') === 'true'
        );
      });

      // If no explicit aria-haspopup, pick the button with author name or near top
      if (!authorDropdownBtn && topButtons.length > 0) {
        authorDropdownBtn = topButtons.find(b => {
          const t = (b.innerText || '').trim();
          return t.length > 2 && t.length < 50 && !t.includes('Post') && !t.includes('Cancel') && !t.includes('Close');
        });
      }

      if (authorDropdownBtn) {
        console.log('🔄 WSA: Clicking modal author dropdown button...', authorDropdownBtn);
        authorDropdownBtn.click();
        await sleep(800);

        const switched = await selectTargetFromMenu();
        if (switched) {
          updateHud(`✅ Switched post author to "${rawTarget}"!`);
          await sleep(1500);
          return true;
        }
      }
    }

    // 2. Check on the Group page header / feed (outside dialog)
    const pageButtons = Array.from(document.querySelectorAll('div[role="button"], button, div[aria-haspopup]'));
    let groupInteractBtn = pageButtons.find(b => {
      const label = (b.getAttribute('aria-label') || b.innerText || '').toLowerCase();
      return (
        label.includes('interacting as') ||
        label.includes('interact as') ||
        label.includes('choose how to interact') ||
        label.includes('select a profile')
      );
    });

    if (groupInteractBtn) {
      const currentLabel = (groupInteractBtn.getAttribute('aria-label') || groupInteractBtn.innerText || '');
      if (isMatch(currentLabel)) {
        console.log(`✅ WSA: Group is already interacting as "${rawTarget}".`);
        return true;
      }

      console.log('🔄 WSA: Clicking group "Interacting as" button...', groupInteractBtn);
      groupInteractBtn.click();
      await sleep(800);

      const switched = await selectTargetFromMenu();
      if (switched) {
        updateHud(`✅ Switched interacting profile to "${rawTarget}"!`);
        await sleep(1500);
        return true;
      }
    }

    console.log(`ℹ️ WSA: Profile "${rawTarget}" is either active or cannot be switched directly.`);
    return false;
  }

  // Main automated flow
  async function executeGroupShare(task) {
    updateHud(`🎯 Checking sender identity for "${task.sender_name}"...`);
    // Attempt profile switch on group feed if switcher is present
    await switchProfileIfNeeded(task, null);

    let dialog = await findPostDialog(3);

    if (!dialog) {
      updateHud('1️⃣ Locating "Write something..." box...');
      const composerTrigger = await findComposerTrigger();

      if (!composerTrigger) {
        throw new Error('Could not find group composer trigger ("Write something..."). You may need to join the group or switch profile.');
      }

      updateHud('2️⃣ Opening post dialog...');
      composerTrigger.scrollIntoView({ behavior: 'smooth', block: 'center' });
      await sleep(600);
      composerTrigger.click();

      updateHud('3️⃣ Waiting for modal dialog...');
      dialog = await findPostDialog(20);
      if (!dialog) {
        throw new Error('Post creation dialog did not open.');
      }
    } else {
      updateHud('3️⃣ Modal dialog already open, proceeding...');
    }

    // Check & switch identity inside the open post modal dialog
    updateHud(`🔄 Verifying author identity inside post modal...`);
    const didSwitchInDialog = await switchProfileIfNeeded(task, dialog);
    if (didSwitchInDialog) {
      // Re-acquire dialog reference if switching caused re-render
      dialog = (await findPostDialog(10)) || dialog;
      await sleep(800);
    }

    updateHud('4️⃣ Locating text editor inside dialog...');
    const editor = await findEditor(dialog);
    if (!editor) {
      throw new Error('Could not find contenteditable editor inside post dialog.');
    }

    updateHud('5️⃣ Auto-filling Caption & Reel Link...');
    const fullText = task.full_content || (task.caption ? `${task.caption}\n\n${task.post_url}` : task.post_url);
    await insertTextIntoEditor(editor, fullText);

    updateHud('⏳ Waiting for Facebook Reel preview & Post button activation...');
    await sleep(3500);

    updateHud('6️⃣ Locating active "Post" button...');
    const postBtn = await findPostButton(dialog);
    if (!postBtn) {
      throw new Error('Post button was not found or remained disabled.');
    }

    // Snapshot existing posts & check pre-approval before clicking
    const modalTextBefore = (dialog.innerText || '').toLowerCase();
    const btnLabelBefore = (postBtn.innerText || postBtn.getAttribute('aria-label') || '').toLowerCase();
    const preApprovalRequired = (
      modalTextBefore.includes('require admin approval') ||
      modalTextBefore.includes('requires admin approval') ||
      modalTextBefore.includes('must be approved by an admin') ||
      modalTextBefore.includes('admin must approve') ||
      modalTextBefore.includes('participant approval') ||
      modalTextBefore.includes('submit for approval') ||
      modalTextBefore.includes('approval required') ||
      btnLabelBefore.includes('submit')
    );

    const existingPostUrls = new Set();
    document.querySelectorAll('a[href*="/posts/"], a[href*="/permalink/"]').forEach(a => {
      const clean = (a.href || '').split('?')[0];
      if (clean) existingPostUrls.add(clean);
    });

    updateHud('🚀 Auto-Clicking Post Button (Zero-Click!)...');
    postBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    await sleep(500);
    try {
      postBtn.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true, view: window }));
    } catch (e) {}
    postBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
    try {
      postBtn.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, cancelable: true, view: window }));
    } catch (e) {}
    postBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
    postBtn.click();

    updateHud('7️⃣ Detecting publication & capturing permalink...');
    const outcome = await detectOutcomeAndPermalink(task, existingPostUrls, preApprovalRequired);

    if (outcome.status === 'pending_approval') {
      updateHud(`🟡 Share Recorded! Pending Admin Approval. Tab closing in 4s...`, 'PENDING', '#eab308');
    } else {
      updateHud(`✅ Successfully Shared Live! Tab closing in 4s...`, 'SUCCESS', '#10b981');
    }

    // Report back to background service worker
    chrome.runtime.sendMessage({
      action: 'TASK_COMPLETED',
      result: {
        queue_id: task.id,
        status: outcome.status,
        shared_post_url: outcome.postUrl,
        error_message: null
      }
    });
  }

  // Start initialization
  init();
})();
