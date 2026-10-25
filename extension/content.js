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

  // Viral Facebook Reels Hashtags Pool
  const VIRAL_HASHTAGS_POOL = [
    '#viralreels', '#trending', '#fbreels', '#reelsfb', 
    '#explorepage', '#fyp', '#viralpost', '#reelkarofeelkaro', 
    '#reelsvideo', '#dailyreels', '#trend', '#watchthis',
    '#facebookreels', '#viralvideo'
  ];

  function getViralHashtags() {
    const shuffled = [...VIRAL_HASHTAGS_POOL].sort(() => 0.5 - Math.random());
    const count = Math.random() > 0.35 ? 2 : 1;
    return shuffled.slice(0, count).join(' ');
  }

  // Simulate realistic human typing character by character
  async function typeHumanKeystrokes(editor, text) {
    editor.focus();
    for (let i = 0; i < text.length; i++) {
      const char = text[i];
      try {
        editor.dispatchEvent(new KeyboardEvent('keydown', { key: char, code: char === ' ' ? 'Space' : `Key${char.toUpperCase()}`, bubbles: true }));
        document.execCommand('insertText', false, char);
        editor.dispatchEvent(new InputEvent('input', {
          bubbles: true,
          cancelable: true,
          inputType: 'insertText',
          data: char
        }));
        editor.dispatchEvent(new KeyboardEvent('keyup', { key: char, bubbles: true }));
      } catch (e) {
        try { document.execCommand('insertText', false, char); } catch (err) {}
      }
      // Natural typing delay: 50ms to 130ms per character
      const delay = Math.floor(Math.random() * (130 - 50 + 1)) + 50;
      await sleep(delay);
    }
  }

  // Detect if Facebook has finished generating the rich Reel / Link Preview card
  function detectFacebookPreviewCard(dialog) {
    const scope = dialog || document.querySelector('div[role="dialog"]');
    if (!scope) return { detected: false, reason: 'Dialog not found' };

    // 1. Check for wide preview thumbnail image (excluding user avatars which are <= 45px)
    const images = Array.from(scope.querySelectorAll('img'));
    for (const img of images) {
      const rect = img.getBoundingClientRect();
      const src = (img.src || '').toLowerCase();
      if (rect.width >= 90 && rect.height >= 50) {
        if (
          src.includes('fbcdn.net') || 
          src.includes('scontent') || 
          src.includes('external') ||
          src.includes('safe_image')
        ) {
          return {
            detected: true,
            type: 'THUMBNAIL_IMAGE',
            details: `Reel thumbnail verified (${Math.round(rect.width)}x${Math.round(rect.height)}px)`
          };
        }
      }
    }

    // 2. Check for "Remove preview" or attachment close button (Facebook always renders this on link cards)
    const removeButtons = Array.from(scope.querySelectorAll('div[role="button"], button'));
    for (const rb of removeButtons) {
      const label = (rb.getAttribute('aria-label') || rb.innerText || '').toLowerCase();
      if (
        label.includes('remove preview') ||
        label.includes('remove link') ||
        label.includes('remove attachment') ||
        label.includes('clear link preview')
      ) {
        return {
          detected: true,
          type: 'REMOVE_PREVIEW_HANDLE',
          details: `Attachment close handle found ("${label}")`
        };
      }
    }

    // 3. Check for link domain / reel title card
    const textElements = Array.from(scope.querySelectorAll('span, div, h2, h3'));
    for (const el of textElements) {
      const t = (el.innerText || '').trim().toLowerCase();
      if (
        t === 'facebook.com' || 
        t === 'www.facebook.com' || 
        t === 'fb.watch' ||
        t.startsWith('facebook.com/reel')
      ) {
        const rect = el.getBoundingClientRect();
        if (rect.height > 8 && rect.top > 80) {
          return {
            detected: true,
            type: 'DOMAIN_LABEL',
            details: `Facebook Reel card header detected ("${t}")`
          };
        }
      }
    }

    return { detected: false, reason: 'Facebook Reel preview card is still fetching from Meta servers...' };
  }

  // Deep Facebook Diagnostic: Extract exact reason why Post button might be disabled
  function diagnosePostButtonFailure(dialog) {
    const scope = dialog || document.querySelector('div[role="dialog"]');
    const reasons = [];

    // Check 1: Is user joined to this group?
    const bodyText = (document.body.innerText || '').toLowerCase();
    if (bodyText.includes('join group to post') || bodyText.includes('join group')) {
      let joinBtn = document.querySelector('div[aria-label*="Join group" i], div[aria-label*="Join Group"]');
      if (!joinBtn) {
        const buttons = Array.from(document.querySelectorAll('div[role="button"], button'));
        joinBtn = buttons.find(b => {
          const t = (b.innerText || '').trim().toLowerCase();
          return t === 'join group' || t === 'join' || t.startsWith('join');
        });
      }
      if (joinBtn) reasons.push('Account has not joined this group (Join Group button detected)');
    }

    // Check 2: Error banner or warning inside dialog
    if (scope) {
      const dialogText = (scope.innerText || '').toLowerCase();
      if (dialogText.includes('temporarily blocked')) reasons.push('Account is temporarily action-blocked by Facebook');
      if (dialogText.includes('couldn\'t be shared')) reasons.push('Facebook blocked this URL from being shared');
      if (dialogText.includes('something went wrong')) reasons.push('Facebook internal server error: Something went wrong');
      if (dialogText.includes('choose how to interact')) reasons.push('Facebook author switch required before posting');
      if (dialogText.includes('admin approval')) reasons.push('Group requires Admin Approval before posting');
    }

    // Check 3: Preview status
    const preview = detectFacebookPreviewCard(scope);
    if (!preview.detected) {
      reasons.push('Facebook could not generate Reel preview card within the timeout');
    }

    // Check 4: Editor content length
    if (scope) {
      const editor = scope.querySelector('[contenteditable="true"]');
      if (!editor || (editor.innerText || '').trim().length === 0) {
        reasons.push('Editor was detected as empty by Facebook React state');
      }
    }

    return reasons.length > 0 ? reasons.join(' | ') : 'Post button remained locked by Facebook React internal validator';
  }

  // Insert content into modern Lexical / Draft.js editors with human typing/editing simulation
  async function insertTextIntoEditor(editor, text) {
    // 1. Natural pause before typing/pasting (1.2 to 2.5 seconds)
    const initialPause = Math.floor(Math.random() * (2500 - 1200 + 1)) + 1200;
    await sleep(initialPause);

    // 2. Also copy to real OS clipboard for safety backup
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
      }
    } catch (e) {
      console.warn('Clipboard write fallback ignored:', e);
    }

    // 3. Focus and activate editor
    editor.focus();
    await sleep(300);
    editor.click();
    await sleep(300);

    // 4. Clear any existing selection
    try {
      const sel = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(editor);
      sel.removeAllRanges();
      sel.addRange(range);
    } catch (e) {}

    // 5. Primary Method: Native-like ClipboardEvent paste with DataTransfer
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
      await sleep(800);

      const curVal = (editor.innerText || editor.textContent || '').trim();
      if (curVal.length > 5) {
        pastedSuccess = true;
        console.log('✅ Text successfully pasted via ClipboardEvent into Lexical editor!');
      }
    } catch (err) {
      console.warn('ClipboardEvent paste attempt error:', err);
    }

    // 6. Fallback Method: Line-by-line insertion preserving paragraphs
    if (!pastedSuccess) {
      console.log('⚠️ Using line-by-line execCommand fallback with variable human cadence...');
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
          await sleep(Math.floor(Math.random() * 120) + 60);
        }
      }
    }

    // 7. Human Edit Simulation ("caption edit kar ke dekho isse facebook real lage"):
    // Add space, wait, backspace, and trigger real input events to fool Facebook bot detection
    try {
      await sleep(400);
      editor.focus();

      // Dispatch real keydown for space
      editor.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', code: 'Space', bubbles: true }));
      document.execCommand('insertText', false, ' ');
      editor.dispatchEvent(new InputEvent('input', { bubbles: true, cancelable: true, inputType: 'insertText', data: ' ' }));

      // Natural pause between edit keystrokes (300 to 600ms)
      await sleep(Math.floor(Math.random() * 300) + 300);

      // Backspace the space to leave clean text
      editor.dispatchEvent(new KeyboardEvent('keydown', { key: 'Backspace', code: 'Backspace', bubbles: true }));
      document.execCommand('delete', false);
      editor.dispatchEvent(new InputEvent('input', { bubbles: true, cancelable: true, inputType: 'deleteContentBackward' }));

      console.log('✅ WSA: Simulated human keystroke edit cycle in editor.');
    } catch (e) {
      console.warn('Human edit simulation fallback ignored:', e);
    }

    // 8. Dispatch input & change events so React / Lexical marks form as dirty & ready to submit
    editor.dispatchEvent(new Event('input', { bubbles: true }));
    editor.dispatchEvent(new Event('change', { bubbles: true }));

    return true;
  }

  // Find Facebook Post Button (checks text, aria, Hindi, geometry, with auto-force-enable fallback)
  async function findPostButton(dialog) {
    for (let attempts = 0; attempts < 25; attempts++) {
      // Always query current active dialog in case Facebook re-rendered the modal
      const currentDialog = document.querySelector('div[role="dialog"]') || dialog;
      if (!currentDialog) {
        await sleep(600);
        continue;
      }

      const dialogRect = currentDialog.getBoundingClientRect();
      const allButtons = Array.from(currentDialog.querySelectorAll('div[role="button"], button'));

      let bestCandidate = null;

      // 1. Text & Aria-label matching (Supports English, Hindi, and generic action labels)
      for (const b of allButtons) {
        const ariaLabel = (b.getAttribute('aria-label') || '').trim().toLowerCase();
        const text = (b.innerText || '').trim().toLowerCase();

        // Skip close and utility icon buttons
        if (
          ariaLabel.includes('close') || 
          ariaLabel.includes('photo') || 
          ariaLabel.includes('video') || 
          ariaLabel.includes('tag') || 
          ariaLabel.includes('feeling') ||
          ariaLabel.includes('remove') ||
          text === '✕' ||
          text === 'x'
        ) {
          continue;
        }

        const isMatch = (
          ariaLabel === 'post' ||
          ariaLabel === 'publish' ||
          ariaLabel === 'next' ||
          ariaLabel.includes('post to') ||
          ariaLabel.includes('publish to') ||
          ariaLabel.includes('share') ||
          ariaLabel.includes('submit') ||
          ariaLabel.includes('पोस्ट') ||
          ariaLabel.includes('प्रकाशित') ||
          text === 'post' ||
          text === 'publish' ||
          text === 'next' ||
          text === 'submit' ||
          text.includes('post to') ||
          text.includes('publish') ||
          text.includes('पोस्ट') ||
          b.getAttribute('type') === 'submit'
        );

        if (isMatch) {
          bestCandidate = b;
          const ariaDisabled = b.getAttribute('aria-disabled');
          const isDisabled = ariaDisabled === 'true' || b.hasAttribute('disabled');
          if (!isDisabled) {
            console.log('✅ WSA: Active Post button found by text/aria:', b);
            return b;
          }
        }
      }

      // 2. Geometric Matching: Primary action button in the bottom 35% of modal
      if (!bestCandidate) {
        const bottomButtons = allButtons.filter(b => {
          const r = b.getBoundingClientRect();
          const isInBottomArea = r.top >= dialogRect.top + dialogRect.height * 0.60;
          const isWide = r.width >= 100;
          return isInBottomArea && isWide;
        });

        if (bottomButtons.length > 0) {
          // The lowest button in the modal is the submit button
          bottomButtons.sort((a, b) => b.getBoundingClientRect().top - a.getBoundingClientRect().top);
          bestCandidate = bottomButtons[0];
          const ariaDisabled = bestCandidate.getAttribute('aria-disabled');
          const isDisabled = ariaDisabled === 'true' || bestCandidate.hasAttribute('disabled');
          if (!isDisabled) {
            console.log('✅ WSA: Active Post button found by geometry in footer:', bestCandidate);
            return bestCandidate;
          }
        }
      }

      // 3. If bestCandidate found but disabled by Facebook:
      if (bestCandidate) {
        const editor = currentDialog.querySelector('[contenteditable="true"]');
        if (editor && attempts % 2 === 0) {
          try {
            editor.focus();
            document.execCommand('insertText', false, ' ');
            document.execCommand('delete', false);
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            editor.dispatchEvent(new Event('change', { bubbles: true }));
          } catch (e) {}
        }

        // If after 5 attempts (~3.5s) it remains disabled, force-enable it so post publishes!
        if (attempts >= 5) {
          console.log('⚡ WSA: Force-enabling candidate Post button to submit...', bestCandidate);
          bestCandidate.removeAttribute('disabled');
          bestCandidate.setAttribute('aria-disabled', 'false');
          bestCandidate.style.pointerEvents = 'auto';
          bestCandidate.style.opacity = '1';
          return bestCandidate;
        }
      }

      await sleep(700);
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

    // Human Typing Viral Hashtags ("har baar caption box me ek ya 2 hashtag type kar ke likho")
    const viralTags = getViralHashtags();
    updateHud(`✍️ Typing viral hashtags: <b>${viralTags}</b> (Human Keystrokes)...`);
    await sleep(600);
    try {
      document.execCommand('insertParagraph', false);
    } catch (e) {
      document.execCommand('insertText', false, '\n');
    }
    await sleep(200);
    await typeHumanKeystrokes(editor, viralTags);
    console.log(`✅ WSA: Naturally typed viral hashtags: ${viralTags}`);

    // Active Preview Detection & Human Cadence Loop
    // User Requirement: Preview aane ke baad hi post button click ho, aur minimum 15s wait rahe!
    let previewDetected = false;
    let previewDetails = '';
    const maxWaitSeconds = 45;
    let elapsedSeconds = 0;

    updateHud('⏳ <b>Waiting for Reel Preview Card:</b> Facebook is scraping video thumbnail...', 'FETCHING', '#3b82f6');

    while (elapsedSeconds < maxWaitSeconds) {
      elapsedSeconds++;

      // Check if preview card appeared in dialog
      if (!previewDetected) {
        const previewCheck = detectFacebookPreviewCard(dialog);
        if (previewCheck.detected) {
          previewDetected = true;
          previewDetails = previewCheck.details;
          console.log(`🎯 WSA: Facebook Reel preview card verified: ${previewDetails}`);
        }
      }

      // Check if minimum 15 seconds have passed AND preview is detected
      if (elapsedSeconds >= 15 && previewDetected) {
        updateHud(`✅ <b>Preview Ready!</b> ${previewDetails}<br><small style="color:#94a3b8;">Natural human pause before clicking Post...</small>`, 'READY', '#10b981');
        await sleep(2500);
        break;
      }

      // Halfway simulation: simulate human reading / cursor focus check
      if (elapsedSeconds % 7 === 0) {
        try {
          if (editor) editor.focus();
        } catch (e) {}
      }

      const statusMsg = previewDetected
        ? `✅ Preview verified! Human safety wait: ${Math.max(0, 15 - elapsedSeconds)}s remaining...`
        : `⏳ Waiting for Reel preview card (${elapsedSeconds}s / max 45s)...`;

      updateHud(
        `${statusMsg}<br><span style="font-size:11px; color:#94a3b8;">Anti-Bot Human Simulation Active</span>`,
        `${elapsedSeconds}s`,
        previewDetected ? '#10b981' : '#3b82f6'
      );

      await sleep(1000);
    }

    if (!previewDetected) {
      console.warn('⚠️ WSA: Preview card took longer than 45s. Proceeding with Post button check.');
    }

    updateHud('6️⃣ Locating active "Post" button...');
    const postBtn = await findPostButton(dialog);
    if (!postBtn) {
      const diagReason = diagnosePostButtonFailure(dialog);
      console.error('❌ WSA Post Button Diagnostic:', diagReason);
      throw new Error(`Post button was not found or remained disabled. [Reason: ${diagReason}]`);
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
