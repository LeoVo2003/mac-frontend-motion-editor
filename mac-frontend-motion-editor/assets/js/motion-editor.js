/* global MFME_DATA */
(() => {
  'use strict';
  const boot = () => {
    if (!window.MFME_DATA || document.getElementById('mfme-editor')) return;
    const data = MFME_DATA;
    const root = document.querySelector(`.elementor-${data.docId}[data-elementor-id="${data.docId}"]`) || document.querySelector(`.elementor-${data.docId}`);
    if (!root) return;
    const ui = document.createElement('div');
    ui.id = 'mfme-editor';
    ui.innerHTML = `
      <div class="mfme-toolbar"><strong>✦ Motion Edit ON</strong><span data-count></span><a data-off>Turn off</a></div>
      <div class="mfme-outline" hidden><button type="button" class="mfme-chip">✦ Motion</button></div>
      <section class="mfme-panel" role="dialog" aria-modal="false" aria-label="Motion editor" hidden>
        <header><div><small>ENTRANCE ANIMATION</small><h2>Motion editor</h2></div><button type="button" data-close aria-label="Close editor">×</button></header>
        <p class="mfme-label"></p><button type="button" data-parent>↑ Select parent</button>
        <fieldset><legend>Device</legend><div class="mfme-devices">
          <button type="button" data-device="desktop">Desktop</button><button type="button" data-device="tablet">Tablet</button><button type="button" data-device="mobile">Mobile</button>
        </div></fieldset>
        <label>Animation<select data-animation><option value="">Default / clear override</option><option value="none">None — show immediately</option></select></label>
        <label>Speed<select data-duration><option value="slow">Slow</option><option value="">Normal</option><option value="fast">Fast</option></select></label>
        <label>Delay (ms)<input data-delay type="number" min="0" max="10000" step="100" value="0"></label>
        <div class="mfme-presets">${[0, 100, 200, 300, 500].map(n => `<button type="button" data-delay-preset="${n}">${n}</button>`).join('')}</div>
        <p class="mfme-note">Speed and delay apply to every device. Device tabs change the animation setting; they do not resize the page.</p>
        <p class="mfme-warning" hidden></p>
        <div class="mfme-actions"><button type="button" data-replay>↻ Replay</button><button type="button" data-apply class="mfme-primary">Apply</button><button type="button" data-clear>Clear</button><button type="button" data-undo>Undo</button></div>
        <p class="mfme-status" role="status" aria-live="polite"></p>
        <details><summary>Motion guidelines</summary><p>Aim for 3–6 motion moments per page. Keep hero text, CTA, prices and phone numbers immediate. Stagger cards 0/100/200 ms. Prefer animating a group over every image. Avoid entrance effects on video/carousels; reduce movement on mobile.</p></details>
      </section>`;
    document.body.append(ui);
    const $ = selector => ui.querySelector(selector);
    const panel = $('.mfme-panel'), outline = $('.mfme-outline'), chip = $('.mfme-chip');
    const animation = $('[data-animation]'), duration = $('[data-duration]'), delay = $('[data-delay]');
    $('[data-off]').href = data.offUrl;
    for (const [label, options] of Object.entries(data.animations)) {
      const group = document.createElement('optgroup');
      group.label = label;
      for (const [slug, name] of Object.entries(options)) {
        const option = document.createElement('option');
        option.value = slug; option.textContent = name; group.append(option);
      }
      animation.append(group);
    }
    let hovered = null, selected = null, device = 'desktop', busy = false, conflicted = false;
    let preview = null, timer = null, frame = null;
    const history = [];
    const entry = el => el && Object.prototype.hasOwnProperty.call(data.elements, el.dataset.id) ? data.elements[el.dataset.id] : null;
    // A nested Elementor root is a different document even if its data-id collides.
    const ownDocument = el => el && root.contains(el) && el.closest('[data-elementor-id]') === root;
    const editable = el => ownDocument(el) && entry(el)?.editable;
    const animated = info => Object.values(info.motion).some(m => m.animation && m.animation !== 'none');
    const ancestorAnimated = info => {
      const visited = new Set();
      for (let id = info?.parent; id && !visited.has(id); id = data.elements[id]?.parent) {
        visited.add(id);
        if (data.elements[id] && animated(data.elements[id])) return true;
      }
      return false;
    };
    const counters = () => {
      const count = Object.values(data.elements).filter(animated).length;
      const nested = Object.values(data.elements).some(info => animated(info) && ancestorAnimated(info));
      $('[data-count]').textContent = `${count > 10 ? '⚠ ' : ''}Animated: ${count}${nested ? ' · ⚠ nested' : ''}`;
    };
    const message = (text, error = false) => {
      $('.mfme-status').textContent = text;
      $('.mfme-status').classList.toggle('mfme-error', error);
    };
    const restore = () => {
      clearTimeout(timer);
      if (preview) {
        // Restore only the classes preview touched; retain unrelated live classes.
        for (const [name, present] of preview.classes) preview.el.classList.toggle(name, present);
        preview = null;
      }
    };
    const setControls = () => {
      const locked = busy || conflicted || !editable(selected);
      for (const input of panel.querySelectorAll('input, select, button')) input.disabled = busy;
      for (const input of panel.querySelectorAll('input, select, [data-replay], [data-apply], [data-clear], [data-device], [data-delay-preset]')) input.disabled = locked;
      $('[data-undo]').disabled = busy || conflicted || !history.length;
      $('[data-parent]').disabled = busy || !entry(selected)?.parent;
    };
    const values = () => ({ animation: animation.value, duration: duration.value, delay: delay.value });
    const effectiveAnimation = () => {
      if (animation.value || device === 'desktop') return animation.value;
      const motion = entry(selected).motion;
      return device === 'mobile' ? (motion.tablet.animation || motion.desktop.animation) : motion.desktop.animation;
    };
    const fill = () => {
      const info = entry(selected);
      if (!info) return;
      const motion = info.motion[device];
      // Retain a saved custom effect even if its provider has been deactivated.
      if (motion.animation && !Array.from(animation.options).some(o => o.value === motion.animation)) {
        const option = document.createElement('option'); option.value = motion.animation;
        option.textContent = `${motion.animation} (unavailable)`; animation.append(option);
      }
      animation.value = motion.animation; duration.value = motion.duration; delay.value = motion.delay;
      $('.mfme-label').textContent = `${info.label} · ${selected.dataset.id}`;
      for (const button of panel.querySelectorAll('[data-device]')) button.setAttribute('aria-pressed', String(button.dataset.device === device));
      $('.mfme-warning').hidden = !ancestorAnimated(info);
      $('.mfme-warning').textContent = '⚠ An ancestor has animation. Animating this child can cause nested movement.';
      setControls();
    };
    const position = () => {
      frame = null;
      const el = selected || hovered;
      if (!el?.isConnected) { outline.hidden = true; return; }
      const rect = el.getBoundingClientRect();
      outline.hidden = rect.width === 0 || rect.height === 0;
      Object.assign(outline.style, { top: `${rect.top}px`, left: `${rect.left}px`, width: `${rect.width}px`, height: `${rect.height}px` });
      chip.textContent = !ownDocument(el) || !entry(el) ? 'Shared template — locked' : !entry(el).editable ? 'V4 / unsupported — locked' : '✦ Motion';
      chip.disabled = !editable(el) || busy;
    };
    const schedulePosition = () => { if (!frame) frame = requestAnimationFrame(position); };
    const select = el => {
      if (busy || !editable(el)) return;
      restore(); selected = el; panel.hidden = false; message(''); fill(); position();
      $('[data-close]').focus({ preventScroll: true });
    };
    const close = () => {
      if (busy) return;
      restore(); selected = null; panel.hidden = true; position(); chip.focus({ preventScroll: true });
    };
    document.addEventListener('pointermove', event => {
      if (selected || ui.contains(event.target)) return;
      hovered = event.target.closest?.('.elementor-element[data-id]');
      if (!hovered || !root.contains(hovered)) hovered = null;
      position();
    });
    chip.addEventListener('click', () => select(hovered));
    $('[data-close]').addEventListener('click', close);
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) close(); });
    for (const button of panel.querySelectorAll('[data-device]')) button.addEventListener('click', () => { restore(); device = button.dataset.device; fill(); message(''); });
    for (const button of panel.querySelectorAll('[data-delay-preset]')) button.addEventListener('click', () => { delay.value = button.dataset.delayPreset; });
    $('[data-parent]').addEventListener('click', () => {
      const parentId = entry(selected)?.parent;
      const parent = Array.from(root.querySelectorAll('.elementor-element[data-id]')).find(el => el.dataset.id === parentId && ownDocument(el));
      if (parent) select(parent);
    });
    $('[data-replay]').addEventListener('click', () => {
      if (!delay.checkValidity()) { delay.reportValidity(); return; }
      restore();
      const effect = effectiveAnimation();
      const names = new Set(['elementor-invisible', 'animated', 'animated-slow', 'animated-fast', ...Object.keys(data.animations).flatMap(group => Object.keys(data.animations[group]))]);
      if (effect && effect !== 'none') names.add(effect);
      preview = { el: selected, classes: Array.from(names).map(name => [name, selected.classList.contains(name)]) };
      for (const name of names) selected.classList.remove(name);
      if (!effect || effect === 'none') { message('Preview: visible immediately. Nothing saved.'); return; }
      const el = selected;
      el.classList.add('elementor-invisible');
      void el.offsetWidth;
      timer = setTimeout(() => {
        el.classList.remove('elementor-invisible'); el.classList.add('animated', effect);
        if (duration.value) el.classList.add(`animated-${duration.value}`);
      }, Number(delay.value));
      message(`Previewing ${effect} at the current page width. Nothing saved.`);
    });
    const save = async (id, targetDevice, settings, undo = false) => {
      if (busy || conflicted) return;
      restore(); busy = true; setControls(); message('Saving…');
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 20000);
      try {
        const body = new URLSearchParams({ action: 'mfme_apply', nonce: data.nonce, doc_id: String(data.docId), element_id: id, device: targetDevice, hash: data.hash, ...settings });
        const response = await fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal });
        const result = await response.json();
        if (!response.ok || !result.success) {
          if (response.status === 409 || response.status >= 500) conflicted = true;
          throw new Error(result.data?.message || (response.status === 403 ? 'Session expired or permission denied. Reload the page.' : 'Could not save. Reload before retrying.'));
        }
        data.hash = result.data.hash;
        data.elements[id].motion = result.data.anim;
        if (undo) history.pop();
        else history.push({ id, device: targetDevice, values: result.data.previous[targetDevice] });
        fill(); counters(); message(undo ? 'Previous settings restored.' : 'Saved to Elementor.');
      } catch (error) {
        // An interrupted response may have committed. Block retries until a reload.
        if (error instanceof TypeError || error instanceof SyntaxError || error.name === 'AbortError') conflicted = true;
        message(error.message + (conflicted ? ' Reload the page to continue.' : ''), true);
      } finally { clearTimeout(timeout); busy = false; setControls(); }
    };
    $('[data-apply]').addEventListener('click', () => {
      if (!delay.checkValidity()) { delay.reportValidity(); return; }
      save(selected.dataset.id, device, values());
    });
    $('[data-clear]').addEventListener('click', () => save(selected.dataset.id, device, { animation: '', duration: '', delay: '0' }));
    $('[data-undo]').addEventListener('click', () => {
      const previous = history[history.length - 1];
      if (previous) save(previous.id, previous.device, previous.values, true);
    });
    window.addEventListener('scroll', schedulePosition, true);
    window.addEventListener('resize', schedulePosition);
    if (window.ResizeObserver) new ResizeObserver(schedulePosition).observe(root);
    window.addEventListener('pagehide', restore);
    counters();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
