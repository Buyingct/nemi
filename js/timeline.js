(function () {
  const N = window.NEMI || { states: {}, notes: {}, map: null };

  // Find the inline SVG on the page
  const svg = document.querySelector('#timeline-wrap svg');
  if (!svg) { console.warn('Nemi: no SVG found'); return; }

  // 1) Apply mapping: add data-step / data-note & optional animation classes
  if (N.map) {
    // steps
    if (N.map.stepIds) {
      for (const [svgId, key] of Object.entries(N.map.stepIds)) {
        const el = (svg.getElementById && svg.getElementById(svgId)) || document.getElementById(svgId);
        if (el) el.setAttribute('data-step', key);
      }
    }
    // notes
    if (N.map.noteIds) {
      for (const [svgId, key] of Object.entries(N.map.noteIds)) {
        const el = (svg.getElementById && svg.getElementById(svgId)) || document.getElementById(svgId);
        if (el) el.setAttribute('data-note', key);
      }
    }
    // animations (classes defined in nemi_timeline.css)
    if (N.map.animate) {
      for (const [svgId, cls] of Object.entries(N.map.animate)) {
        const el = (svg.getElementById && svg.getElementById(svgId)) || document.getElementById(svgId);
        if (el) el.classList.add(cls);
      }
    }
  }

  // 2) Bottom sheet elements
  const sheet   = document.getElementById('nt_sheet');
  const tEl     = document.getElementById('nt_title');
  const mEl     = document.getElementById('nt_meta');
  const bEl     = document.getElementById('nt_body');
  const btnX    = document.getElementById('nt_close');
  const btnDone = document.getElementById('nt_done');

  let current = null; // {type:'step'|'note', key:string}

  const openSheet  = () => sheet?.setAttribute('aria-hidden', 'false');
  const closeSheet = () => { sheet?.setAttribute('aria-hidden', 'true'); current = null; };
  btnX?.addEventListener('click', closeSheet);

  function showStep(key) {
    const s = N.states?.[key] || {};
    tEl.textContent = s.title || key;
    mEl.textContent = s.meta || '';
    bEl.innerHTML   = s.body || '';
    current = { type: 'step', key };
    openSheet();
  }
  function showNote(key) {
    const n = N.notes?.[key] || {};
    tEl.textContent = n.title || 'Note';
    mEl.textContent = '';
    bEl.innerHTML   = n.body || '';
    current = { type: 'note', key };
    openSheet();
  }

  // 3) Click handling on SVG (delegation)
  svg.addEventListener('click', (e) => {
    const g = e.target.closest('[data-step],[data-note]');
    if (!g) return;
    const sKey = g.getAttribute('data-step');
    const nKey = g.getAttribute('data-note');
    if (sKey) showStep(sKey);
    if (nKey) showNote(nKey);
  });

  // 4) Mark step done (persists)
  btnDone?.addEventListener('click', async () => {
    if (!current || current.type !== 'step') return;
    try {
      const res = await fetch('/api/mark_step.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ step: current.key, done: '1' })
      });
      const j = await res.json().catch(() => ({ ok: false }));
      if (!j.ok) return alert(j.error || 'Save failed');

      // Update UI
      (N.states[current.key] ||= {}).done = true;
      const g = svg.querySelector(`[data-step="${CSS.escape(current.key)}"]`);
      if (g) g.classList.add('nemi-done'); // style in CSS to dim
      closeSheet();
    } catch {
      alert('Network error');
    }
  });

  // 5) Highlight the next incomplete step on load
  const nextKey = Object.keys(N.states || {}).find(k => !(N.states[k]?.done));
  if (nextKey) {
    const g = svg.querySelector(`[data-step="${CSS.escape(nextKey)}"]`);
    if (g) g.classList.add('nemi-glow'); // style in CSS for glow
  }

  console.log('Nemi timeline wired');
})();
