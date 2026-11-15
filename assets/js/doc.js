/* Navigator Life Maps — Outline: the map as a written outline, picked from the layout menu in the editor.
 * Each island (root card) is a heading; the cards under it are bullets, with arrows to open and close
 * branches (the same open / closed state as the canvas). Typing edits a card's title right there:
 *   Enter            new card below (Shift+Enter: a line break is not allowed, so it does the same)
 *   Tab / Shift+Tab  move the card in under the card above / back out a level
 *   Backspace        on an empty card: delete it and go to the line above
 *   ↑ / ↓            move between lines
 * Order on screen is the order of the cards in the map's list. The editor puts that list in reading
 * order before showing the outline, and lays the canvas out in list order after leaving it.
 * opts: el, getNodes(), find(id), title(), editable, pushUndo(), changed(), redraw(), openEdit(id), newId() */
(function() {
  const esc = NMMCore.esc;

  function create(opts) {
    const el = opts.el;
    let typing = null; // id of the card whose title is being typed, so one Undo step covers the typing

    function nodes() { return opts.getNodes(); }
    function kidsOf(id) { return nodes().filter(function(n) { return (n.parent || null) === id; }); }
    function isRoot(n) { return !n.parent || !opts.find(n.parent); }
    // A card and everything under it, in list order
    function branch(n) {
      const out = [n];
      kidsOf(n.id).forEach(function(c) { out.push.apply(out, branch(c)); });
      return out;
    }
    // Where in the list to put something that goes right after this card's branch
    function afterBranch(n) {
      const list = nodes(), b = branch(n);
      return Math.max.apply(null, b.map(function(x) { return list.indexOf(x); })) + 1;
    }
    function moveBranch(n, index) {
      const list = nodes(), b = branch(n);
      // Remove the branch, then insert it where asked (adjusting for what was removed before that spot)
      const before = b.filter(function(x) { return list.indexOf(x) < index; }).length;
      b.forEach(function(x) { list.splice(list.indexOf(x), 1); });
      list.splice.apply(list, [index - before, 0].concat(b));
    }

    function lineHtml(n, depth) {
      const ch = kidsOf(n.id), ro = !!n._readonly || !opts.editable;
      const toggle = ch.length
        ? '<button type="button" class="nmm-doc-toggle' + (n.collapsed ? ' closed' : '') + '" data-toggle="' + esc(n.id) + '" aria-label="' + (n.collapsed ? 'Open' : 'Close') + '" aria-expanded="' + !n.collapsed + '"></button>'
        : '<span class="nmm-doc-toggle-space"></span>';
      const edit = ro ? '' : '<button type="button" class="nmm-doc-edit" data-edit="' + esc(n.id) + '" title="Notes, date and stage">Edit</button>';
      const row = '<div class="nmm-doc-line" data-id="' + esc(n.id) + '">' + toggle + '<span class="nmm-doc-dot' + (ch.length && n.collapsed ? ' has-hidden' : '') + '"></span>' +
        '<span class="nmm-doc-text"' + (ro ? '' : ' contenteditable="plaintext-only" spellcheck="true"') + ' data-text="' + esc(n.id) + '">' + esc(n.title || '') + '</span>' + NMMCore.linkChip(n.link) + edit + '</div>';
      const under = ch.length && !n.collapsed
        ? '<ul class="nmm-doc-list">' + ch.map(function(c) { return '<li>' + lineHtml(c, depth + 1) + '</li>'; }).join('') + '</ul>' : '';
      return row + under;
    }

    function render() {
      // Keep the caret where it was when the outline is drawn again
      const focused = document.activeElement && el.contains(document.activeElement) ? document.activeElement.dataset.text : null;
      const caret = focused ? caretOffset(document.activeElement) : 0;
      const roots = nodes().filter(isRoot);
      el.innerHTML = '<div class="nmm-doc-page">' + (roots.length ? roots.map(function(r) {
        const ro = !!r._readonly || !opts.editable;
        return '<section class="nmm-doc-island">' +
          '<h2 class="nmm-doc-title" data-id="' + esc(r.id) + '"><span class="nmm-doc-text"' + (ro ? '' : ' contenteditable="plaintext-only" spellcheck="true"') + ' data-text="' + esc(r.id) + '">' + esc(r.title || '') + '</span>' +
          (ro ? '' : '<button type="button" class="nmm-doc-edit" data-edit="' + esc(r.id) + '" title="Notes, date and stage">Edit</button>') + '</h2>' +
          '<ul class="nmm-doc-list nmm-doc-top">' + kidsOf(r.id).map(function(c) { return '<li>' + lineHtml(c, 1) + '</li>'; }).join('') + '</ul>' +
          (ro ? '' : '<button type="button" class="nmm-doc-add" data-add="' + esc(r.id) + '">+ Add a card</button>') +
          '</section>';
      }).join('') : '<p class="nmm-doc-empty">No cards yet.</p>') + '</div>';
      if (focused) focusText(focused, caret);
    }

    function caretOffset(t) {
      const s = getSelection();
      if (!s.rangeCount || !t.contains(s.anchorNode)) return t.textContent.length;
      const r = s.getRangeAt(0).cloneRange(); r.selectNodeContents(t); r.setEnd(s.anchorNode, s.anchorOffset);
      return r.toString().length;
    }
    function focusText(id, at) {
      const t = el.querySelector('[data-text="' + CSS.escape(id) + '"]');
      if (!t) return;
      t.focus();
      const pos = Math.min(at == null ? t.textContent.length : at, t.textContent.length);
      const r = document.createRange(), s = getSelection();
      if (t.firstChild) { r.setStart(t.firstChild, pos); } else { r.setStart(t, 0); }
      r.collapse(true); s.removeAllRanges(); s.addRange(r);
    }
    function lines() { return Array.prototype.slice.call(el.querySelectorAll('.nmm-doc-text')); }

    // ---- Editing ----
    function changedStructure() { typing = null; opts.changed(); opts.redraw(); }

    function addCard(near, parentId, index) {
      opts.pushUndo();
      const c = { id: opts.newId(), x: near.x + 30, y: near.y + 60, title: '', subs: [], parent: parentId, collapsed: false, date: '', tag: '', sefirot: '' };
      nodes().splice(index, 0, c);
      changedStructure();
      focusText(c.id, 0);
    }
    function newBelow(n) {
      // Below a heading, or below an open card with cards under it: the new card goes first under it
      if (isRoot(n) || (kidsOf(n.id).length && !n.collapsed)) addCard(n, n.id, nodes().indexOf(n) + 1);
      else addCard(n, n.parent, afterBranch(n));
    }
    function indent(n) {
      const sibs = kidsOf(n.parent), i = sibs.indexOf(n);
      if (i < 1) return; // nothing above it to go under
      opts.pushUndo();
      const p = sibs[i - 1];
      p.collapsed = false;
      n.parent = p.id;
      moveBranch(n, afterBranch(p));
      changedStructure();
    }
    function outdent(n) {
      const p = opts.find(n.parent);
      if (!p || isRoot(p)) return; // cards straight under a heading stay there
      opts.pushUndo();
      n.parent = p.parent;
      moveBranch(n, afterBranch(p));
      changedStructure();
    }
    function remove(n, focusPrev) {
      opts.pushUndo();
      const list = nodes();
      list.splice(list.indexOf(n), 1);
      changedStructure();
      if (focusPrev) focusText(focusPrev);
    }

    el.addEventListener('input', function(e) {
      const id = e.target.dataset && e.target.dataset.text, n = id && opts.find(id);
      if (!n) return;
      if (typing !== id) { opts.pushUndo(); typing = id; }
      n.title = e.target.textContent.replace(/\s*\n\s*/g, ' ');
      opts.changed();
    });
    el.addEventListener('focusout', function(e) {
      const id = e.target.dataset && e.target.dataset.text, n = id && opts.find(id);
      if (!n) return;
      typing = null;
      n.title = n.title.trim();
      // A card left empty is taken away again (only when nothing hangs under it)
      if (!n.title && !isRoot(n) && !kidsOf(n.id).length) {
        setTimeout(function() { if (opts.find(id) && !opts.find(id).title) remove(n); }, 0);
      } else if (!n.title) { n.title = 'Untitled'; opts.changed(); opts.redraw(); }
    });
    el.addEventListener('keydown', function(e) {
      const t = e.target, id = t.dataset && t.dataset.text, n = id && opts.find(id);
      if (!n) return;
      const all = lines(), i = all.indexOf(t);
      if (e.key === 'Enter') { e.preventDefault(); newBelow(n); }
      else if (e.key === 'Tab') { e.preventDefault(); if (e.shiftKey) outdent(n); else indent(n); }
      else if (e.key === 'Backspace' && !t.textContent && !isRoot(n) && !kidsOf(n.id).length) {
        e.preventDefault();
        const prev = all[i - 1];
        remove(n, prev && prev.dataset.text);
      }
      else if (e.key === 'ArrowUp' && i > 0) { e.preventDefault(); focusText(all[i - 1].dataset.text); }
      else if (e.key === 'ArrowDown' && i < all.length - 1) { e.preventDefault(); focusText(all[i + 1].dataset.text); }
      else if (e.key === 'Escape') { t.blur(); }
    });
    // No pasted formatting or line breaks
    el.addEventListener('paste', function(e) {
      if (!e.target.dataset || !e.target.dataset.text) return;
      e.preventDefault();
      document.execCommand('insertText', false, (e.clipboardData.getData('text/plain') || '').replace(/\s*\n\s*/g, ' '));
    });
    el.addEventListener('click', function(e) {
      const b = e.target.closest('button');
      if (!b) return;
      if (b.dataset.toggle) { const n = opts.find(b.dataset.toggle); if (n) { n.collapsed = !n.collapsed; opts.changed(); opts.redraw(); } }
      else if (b.dataset.edit) opts.openEdit(b.dataset.edit);
      else if (b.dataset.add) {
        // A new card at the end of this island
        const r = opts.find(b.dataset.add), ch = kidsOf(r.id);
        r.collapsed = false;
        addCard(ch.length ? ch[ch.length - 1] : r, r.id, ch.length ? afterBranch(ch[ch.length - 1]) : nodes().indexOf(r) + 1);
      }
    });

    return { render: render };
  }

  window.NMMDoc = { create: create };
})();
