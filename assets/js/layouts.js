/* Navigator Life Maps — map layouts, shared by the editor (Harmony and the layout menu) and the
 * read-only view (its layout menu arranges the map on screen only, never saving).
 * NMMLayouts.create(C, getNodes) -> { run(style, live), STYLES, styleOf(settings), hasSefirot(), isRoot(n),
 *   keepOnCanvas(), listOrder(true) }
 * Styles: flow (Flow Chart), mind (Mind Map), bubble (Bubble), tol (Tree of Life). Each takes the editable
 * cards ("live") and sets their x, y. See the notes on each one below. */
(function() {
function create(C, getNodes) {
  const find = C.find;
  let bubbleFresh = false; // set when the next Bubble layout should follow list order, not positions

  // HARMONY — lays the map out so nothing overlaps
  // Flow Chart (tidy): each card sits centred above its children, which keep their
  // current left-to-right order. A card whose children are all leaves gets them
  // in one stacked column; with mixed children, 3+ leaves share one stacked
  // column and each branch gets its own column. Card sizes are measured.
  // Mind Map: the main card sits in the middle and its branches grow out to the
  // left and right; each card is centred beside its children, which stack top
  // to bottom in their current order.
  // Bubble: round cards around the main one, each branch fanning outward (bubbleLayout).
  // Tree of Life: each sefirot is a slot in the three-pillar shape; cards sharing a slot sit
  // side by side, each with its own branch laid out beneath it; rows of the tree grow to fit.
  const LAYOUT = { gapX: 40, gapY: 70, stackGap: 22, islandGap: 140, margin: 40, pillarGap: 40, sideGap: 60, maxStack: 5 };
  // A long list of end cards wraps into side-by-side columns of at most LAYOUT.maxStack, filled in turn
  function wrapList(list) {
    const cols = Math.ceil(list.length / LAYOUT.maxStack), per = Math.ceil(list.length / cols), out = [];
    for (let i = 0; i < list.length; i += per) out.push(list.slice(i, i + per));
    return out;
  }
  const SEFIROT_ROWS = [
    [['kether', 'mid']],
    [['chochma', 'left'], ['binah', 'right']],
    [['chesed', 'left'], ['geburah', 'right']],
    [['tiphereth', 'mid']],
    [['netzach', 'left'], ['hod', 'right']],
    [['yesod', 'mid']],
    [['malkuth', 'mid']]
  ];

  // Measures and places subtrees. `stop(c)` marks children that start their own
  // group elsewhere (Tree of Life slots) and are left out of their parent's subtree.
  // Collapsed branches get space too, so expanding them later never causes overlaps.
  // Render everything expanded once to measure every card, then restore.
  function measureSizes(live) {
    const collapsed = live.filter(function(n) { return n.collapsed; });
    collapsed.forEach(function(n) { n.collapsed = false; });
    C.render();
    const sizes = {};
    live.forEach(function(n) { const el = C.getEl(n.id); sizes[n.id] = { w: el ? el.offsetWidth : 180, h: el ? el.offsetHeight : 50 }; });
    collapsed.forEach(function(n) { n.collapsed = true; });
    return sizes;
  }

  function treeEngine(live, stop) {
    const sizes = measureSizes(live);

    // By centre, then top: stacked cards share a centre, so they keep their top-to-bottom order
    function centre(c) { return c.x + sizes[c.id].w / 2; }
    function byPosition(a, b) { const d = centre(a) - centre(b); return Math.abs(d) > 20 ? d : a.y - b.y; }
    function kids(n) {
      return live.filter(function(c) { return c.parent === n.id && !(stop && stop(c)); }).sort(byPosition);
    }
    function isLeaf(n) { return !kids(n).length; }

    const box = {};
    function measure(n) {
      const s = sizes[n.id], ch = kids(n);
      const b = box[n.id] = { w: s.w, h: s.h, nodeW: s.w, nodeH: s.h, cols: [] };
      if (!ch.length) return b;
      ch.forEach(measure);
      const leaves = ch.filter(isLeaf);
      const stackAll = leaves.length === ch.length && ch.length > 1;
      const stackLeaves = !stackAll && leaves.length > 2;
      let stack = null;
      ch.forEach(function(c) {
        if (stackAll || (stackLeaves && isLeaf(c))) {
          if (!stack) { stack = { items: [] }; b.cols.push(stack); }
          stack.items.push(c);
        } else {
          b.cols.push({ items: [c] });
        }
      });
      // Long stacks wrap into several columns side by side
      b.cols = b.cols.reduce(function(acc, col) {
        return acc.concat(col.items.length > LAYOUT.maxStack ? wrapList(col.items).map(function(items) { return { items: items }; }) : [col]);
      }, []);
      let rowW = 0, rowH = 0;
      b.cols.forEach(function(col) {
        col.w = Math.max.apply(null, col.items.map(function(c) { return box[c.id].w; }));
        col.h = col.items.reduce(function(sum, c) { return sum + box[c.id].h; }, 0) + LAYOUT.stackGap * (col.items.length - 1);
        rowW += col.w; rowH = Math.max(rowH, col.h);
      });
      b.rowW = rowW + LAYOUT.gapX * (b.cols.length - 1);
      b.w = Math.max(s.w, b.rowW);
      b.h = s.h + LAYOUT.gapY + rowH;
      return b;
    }

    // cx = centre line of the subtree, top = its top edge
    function place(n, cx, top) {
      const b = box[n.id];
      n.x = cx - b.nodeW / 2; n.y = top;
      let left = cx - (b.rowW || 0) / 2;
      const y0 = top + b.nodeH + LAYOUT.gapY;
      b.cols.forEach(function(col) {
        const colCx = left + col.w / 2;
        let y = y0;
        col.items.forEach(function(c) { place(c, colCx, y); y += box[c.id].h + LAYOUT.stackGap; });
        left += col.w + LAYOUT.gapX;
      });
    }

    // Lays several subtrees side by side; returns the row's width and height.
    function row(list) {
      list.forEach(measure);
      const w = list.reduce(function(s, n) { return s + box[n.id].w; }, 0) + LAYOUT.gapX * 2 * Math.max(0, list.length - 1);
      const h = Math.max.apply(null, [0].concat(list.map(function(n) { return box[n.id].h; })));
      return { w: w, h: h, place: function(left, top) {
        list.forEach(function(n) { place(n, left + box[n.id].w / 2, top); left += box[n.id].w + LAYOUT.gapX * 2; });
      } };
    }

    return { box: box, kids: kids, measure: measure, place: place, row: row, byPosition: byPosition };
  }

  function isRoot(n) { return !n.parent || !find(n.parent); }

  function tidyLayout(live) {
    const T = treeEngine(live);
    const roots = live.filter(isRoot);
    if (!roots.length) return;
    // The root with the most cards stays where it is; other islands go in a row underneath
    function count(n) { return 1 + T.kids(n).reduce(function(s, c) { return s + count(c); }, 0); }
    roots.forEach(T.measure);
    const main = roots.slice().sort(function(a, b) { return count(b) - count(a); })[0];
    const mb = T.box[main.id], cx = main.x + mb.nodeW / 2;
    T.place(main, cx, main.y);
    const others = T.row(roots.filter(function(r) { return r !== main; }).sort(T.byPosition));
    others.place(cx - mb.w / 2, main.y + mb.h + LAYOUT.islandGap);
  }

  function mindMapLayout(live) {
    const sizes = measureSizes(live);
    function kids(n) { return live.filter(function(c) { return c.parent === n.id; }); }
    function byTop(a, b) { return a.y - b.y || a.x - b.x; }
    function centreX(n) { return n.x + sizes[n.id].w / 2; }
    // Height of a branch growing sideways: its children stacked, or the card itself
    const H = {};
    // A long list of end cards grows outward in columns instead of one tall stack
    function grid(n) {
      const ch = kids(n);
      return ch.length > LAYOUT.maxStack && ch.every(function(c) { return !kids(c).length; }) ? wrapList(ch.sort(byTop)) : null;
    }
    function height(n) {
      const ch = kids(n), g = grid(n);
      const stacked = g
        ? Math.max.apply(null, g.map(function(col) { col.forEach(height); return stackHeight(col); }))
        : ch.reduce(function(sum, c) { return sum + height(c); }, 0) + LAYOUT.stackGap * Math.max(0, ch.length - 1);
      return (H[n.id] = Math.max(sizes[n.id].h, stacked));
    }
    function stackHeight(list) { return list.reduce(function(sum, c) { return sum + H[c.id]; }, 0) + LAYOUT.stackGap * Math.max(0, list.length - 1); }
    // Places a branch: side 1 grows right from `edge`, side -1 grows left; cy = its vertical centre
    function place(n, edge, cy, side) {
      const sz = sizes[n.id];
      // Whole pixels, so pressing Harmony again reproduces exactly the same positions
      n.x = Math.round(side > 0 ? edge : edge - sz.w); n.y = Math.round(cy - sz.h / 2);
      let next = side > 0 ? n.x + sz.w + LAYOUT.sideGap : n.x - LAYOUT.sideGap;
      const g = grid(n);
      if (!g) { placeStack(kids(n).sort(byTop), next, cy, side); return; }
      g.forEach(function(col) {
        placeStack(col, next, cy, side);
        next += side * (Math.max.apply(null, col.map(function(c) { return sizes[c.id].w; })) + LAYOUT.gapX);
      });
    }
    function placeStack(list, edge, cy, side) {
      let y = cy - stackHeight(list) / 2;
      list.forEach(function(c) { place(c, edge, y + H[c.id] / 2, side); y += H[c.id] + LAYOUT.stackGap; });
    }
    // One island: its root stays put; branches already on the left stay on the left
    function layoutIsland(r) {
      height(r);
      const sz = sizes[r.id], rcx = centreX(r), cy = r.y + sz.h / 2;
      const ch = kids(r);
      let right = ch.filter(function(c) { return centreX(c) >= rcx; }), left = ch.filter(function(c) { return centreX(c) < rcx; });
      if (ch.length > 1 && (!right.length || !left.length)) {
        // Everything on one side (e.g. coming from Flow Chart): split in order, balancing the heights
        const ordered = ch.slice().sort(function(a, b) { return a.x - b.x || a.y - b.y; });
        const half = stackHeight(ordered) / 2;
        right = []; left = [];
        let acc = 0;
        ordered.forEach(function(c) { (acc < half ? right : left).push(c); acc += H[c.id] + LAYOUT.stackGap; });
      }
      placeStack(right.sort(byTop), r.x + sz.w + LAYOUT.sideGap, cy, 1);
      placeStack(left.sort(byTop), r.x - LAYOUT.sideGap, cy, -1);
    }
    function islandNodes(r) { return live.filter(function(n) { return n === r || C.isDescendant(n.id, r.id); }); }
    function bounds(list) {
      return {
        x0: Math.min.apply(null, list.map(function(n) { return n.x; })), y0: Math.min.apply(null, list.map(function(n) { return n.y; })),
        x1: Math.max.apply(null, list.map(function(n) { return n.x + sizes[n.id].w; })), y1: Math.max.apply(null, list.map(function(n) { return n.y + sizes[n.id].h; }))
      };
    }

    const roots = live.filter(isRoot);
    if (!roots.length) return;
    // The root with the most cards stays where it is; other islands go in a row underneath
    const main = roots.slice().sort(function(a, b) { return islandNodes(b).length - islandNodes(a).length; })[0];
    layoutIsland(main);
    const mb = bounds(islandNodes(main));
    let x = mb.x0;
    roots.filter(function(r) { return r !== main; }).sort(function(a, b) { return a.x - b.x || a.y - b.y; }).forEach(function(r) {
      layoutIsland(r);
      const list = islandNodes(r), b = bounds(list), dx = x - b.x0, dy = mb.y1 + LAYOUT.islandGap - b.y0;
      list.forEach(function(n) { n.x += dx; n.y += dy; });
      x += b.x1 - b.x0 + LAYOUT.gapX * 2;
    });
  }

  // Bubble: cards are circles (canvas class nmm-bubble), the main one in the middle. Its branches sit
  // all the way around it, and every card's own branches fan outward, away from where it came from.
  // Built from the outside in: each branch is first laid out on its own, then its parent packs its
  // branches side by side as tightly as their actual circles allow (seen from the parent, no two
  // branches share an angle, and nothing comes closer than GAP), so groups never run into each other
  // and nothing is spread further apart than it needs to be.
  function bubbleLayout(live) {
    const sizes = measureSizes(live);
    const GAP = 26, FAN = Math.PI * 1.1, TOP = -Math.PI / 2;
    function kids(n) { return live.filter(function(c) { return c.parent === n.id; }); }
    // A dated or linked card has a badge just outside the circle: give it a little more room
    function r(n) { return Math.max(sizes[n.id].w, sizes[n.id].h) / 2 + (n.date || (n.link && !n.link.locked) ? 12 : 0); }
    function centre(n) { return { x: n.x + sizes[n.id].w / 2, y: n.y + sizes[n.id].h / 2 }; }
    function wrapAngle(a) { while (a > Math.PI) a -= 2 * Math.PI; while (a <= -Math.PI) a += 2 * Math.PI; return a; }

    // Order of each card's children, read clockwise from where they are now: around the main card
    // from the top, elsewhere from one side of the fan to the other. Coming from another layout (or a
    // map the AI just wrote), positions mean nothing here, so the list order is kept.
    const byList = bubbleFresh; bubbleFresh = false;
    const order = {};
    function sortKids(n, dir, full) {
      const p = centre(n), ch = kids(n);
      order[n.id] = byList ? ch : ch.map(function(c) {
        const q = centre(c), d = wrapAngle(Math.atan2(q.y - p.y, q.x - p.x) - dir);
        return { c: c, a: full && d < 0 ? d + 2 * Math.PI : d };
      }).sort(function(a, b) { return a.a - b.a; }).map(function(x) { return x.c; });
      order[n.id].forEach(function(c) { const q = centre(c); sortKids(c, Math.atan2(q.y - p.y, q.x - p.x), false); });
    }

    // build(n) lays n's branch out around n, facing +x, and returns its circles ({x, y, r}) relative to n.
    // rel[c.id] = where child c sits from its parent: angle off the parent's facing, and distance.
    // A long list of end cards sits in two staggered rows (every other card a step further out),
    // so it packs into about half the angle.
    const rel = {};
    function build(n, fan) {
      const ch = order[n.id], rn = r(n);
      if (!ch.length) return [{ x: 0, y: 0, r: rn }];
      const sub = ch.map(function(c) { return build(c, FAN); });
      const far = Math.max.apply(null, ch.map(r));
      const half = ch.length > 6 && ch.every(function(c) { return !order[c.id].length; });
      const step = half ? 2 * far + GAP / 2 : 0;
      function dist(i, rho) { return rho + (half && i % 2 ? step : 0); }
      // The angle each branch takes up seen from n, with its children at distance rho; null if too close
      function spans(rho) {
        const out = [];
        for (let i = 0; i < ch.length; i++) {
          const d0 = dist(i, rho);
          let lo = Infinity, hi = -Infinity;
          for (let j = 0; j < sub[i].length; j++) {
            const q = sub[i][j], px = d0 + q.x, py = q.y, d = Math.hypot(px, py);
            if (d < rn + q.r + GAP) return null;
            const a = Math.atan2(py, px), w = Math.asin(Math.min(1, (q.r + GAP / 2) / d)) * (half ? 0.55 : 1);
            lo = Math.min(lo, a - w); hi = Math.max(hi, a + w);
          }
          out.push({ lo: lo, hi: hi });
        }
        return out;
      }
      function total(sp) { return sp.reduce(function(s, x) { return s + x.hi - x.lo; }, 0); }
      let rho = rn + far + GAP, sp = spans(rho);
      while (!sp || total(sp) > fan) { rho += 4; sp = spans(rho); }
      // All the way round the main card, clockwise from the top; a gentle spread elsewhere
      const full = fan >= 2 * Math.PI - 0.01, spare = fan - total(sp);
      const extra = full ? spare : Math.min(spare, total(sp) * 0.25), each = extra / ch.length;
      let a = full ? 0 : -(total(sp) + extra) / 2;
      const out = [{ x: 0, y: 0, r: rn }];
      ch.forEach(function(c, i) {
        const theta = a - sp[i].lo + each / 2, d0 = dist(i, rho), cos = Math.cos(theta), sin = Math.sin(theta);
        rel[c.id] = { a: theta, d: d0 };
        sub[i].forEach(function(q) { out.push({ x: cos * (d0 + q.x) - sin * q.y, y: sin * (d0 + q.x) + cos * q.y, r: q.r }); });
        a += sp[i].hi - sp[i].lo + each;
      });
      return out;
    }
    function place(n, cx, cy, dir) {
      n.x = Math.round(cx - sizes[n.id].w / 2); n.y = Math.round(cy - sizes[n.id].h / 2);
      order[n.id].forEach(function(c) {
        const a = dir + rel[c.id].a, d = rel[c.id].d;
        place(c, cx + d * Math.cos(a), cy + d * Math.sin(a), a);
      });
    }
    function islandNodes(root) { return live.filter(function(n) { return n === root || C.isDescendant(n.id, root.id); }); }
    function bounds(list) {
      return {
        x0: Math.min.apply(null, list.map(function(n) { return n.x; })), y0: Math.min.apply(null, list.map(function(n) { return n.y; })),
        x1: Math.max.apply(null, list.map(function(n) { return n.x + sizes[n.id].w; })), y1: Math.max.apply(null, list.map(function(n) { return n.y + sizes[n.id].h; }))
      };
    }
    // Branches go clockwise from the top; the main card stays where it is
    function layoutIsland(root) {
      sortKids(root, TOP, true);
      build(root, 2 * Math.PI);
      const c = centre(root);
      place(root, c.x, c.y, TOP);
    }
    const roots = live.filter(isRoot);
    if (!roots.length) return;
    const main = roots.slice().sort(function(a, b) { return islandNodes(b).length - islandNodes(a).length; })[0];
    layoutIsland(main);
    const mb = bounds(islandNodes(main));
    let x = mb.x0;
    roots.filter(function(r) { return r !== main; }).sort(function(a, b) { return a.x - b.x || a.y - b.y; }).forEach(function(r) {
      layoutIsland(r);
      const list = islandNodes(r), b = bounds(list), dx = x - b.x0, dy = mb.y1 + LAYOUT.islandGap - b.y0;
      list.forEach(function(n) { n.x += dx; n.y += dy; });
      x += b.x1 - b.x0 + LAYOUT.gapX * 2;
    });
  }

  function treeOfLifeLayout(live) {
    const isSlotted = function(n) { return !!NMMCore.SEFIROT_LABELS[n.sefirot]; };
    const T = treeEngine(live, isSlotted);
    // Root cards without a sefirot (usually the map's centre) go in a row above Kether
    const loose = live.filter(function(n) { return isRoot(n) && !isSlotted(n); });
    const cx = 900;
    let top = LAYOUT.margin;
    const looseRow = T.row(loose.sort(T.byPosition));
    if (loose.length) { looseRow.place(cx - looseRow.w / 2, top); top += looseRow.h + LAYOUT.gapY * 1.5; }

    SEFIROT_ROWS.forEach(function(slots) {
      const groups = slots.map(function(s) {
        return { side: s[1], g: T.row(live.filter(function(n) { return n.sefirot === s[0]; }).sort(T.byPosition)) };
      }).filter(function(x) { return x.g.w > 0; });
      if (!groups.length) return;
      groups.forEach(function(x) {
        const left = x.side === 'left' ? cx - LAYOUT.pillarGap - x.g.w : x.side === 'right' ? cx + LAYOUT.pillarGap : cx - x.g.w / 2;
        x.g.place(left, top);
      });
      top += Math.max.apply(null, groups.map(function(x) { return x.g.h; })) + LAYOUT.gapY * 1.5;
    });
  }

  function hasSefirot() { return getNodes().some(function(n) { return !n._readonly && NMMCore.SEFIROT_LABELS[n.sefirot]; }); }
  // The style a map's settings ask for; without one, Tree of Life when cards have positions on it, else Flow Chart
  function styleOf(settings) {
    return LAYOUT_STYLES[settings && settings.layout] ? settings.layout : hasSefirot() ? 'tol' : 'flow';
  }
  const LAYOUT_STYLES = { flow: tidyLayout, mind: mindMapLayout, bubble: bubbleLayout, tol: treeOfLifeLayout };

  // The cards in the order the map reads in a layout style (for the Outline): each card, then its
  // children in reading order. Returns a new array; the cards themselves are unchanged.
  function readingOrder(style) {
    const all = getNodes().slice();
    function mid(n) { const el = C.getEl(n.id); return { x: n.x + (el ? el.offsetWidth : 180) / 2, y: n.y + (el ? el.offsetHeight : 50) / 2 }; }
    function angle(a, b) { return Math.atan2(b.y - a.y, b.x - a.x); }
    function turn(a) { while (a < 0) a += 2 * Math.PI; while (a >= 2 * Math.PI) a -= 2 * Math.PI; return a; }
    function kidsInOrder(n) {
      const ch = all.filter(function(c) { return c.parent === n.id; });
      if (style === 'mind') {
        // The right side, then the left; outward column by column (long lists wrap); top to bottom in a column
        const p = mid(n), pe = C.getEl(n.id), pw = pe ? pe.offsetWidth : 180;
        const key = function(c) {
          const e = C.getEl(c.id), w = e ? e.offsetWidth : 180, right = mid(c).x >= p.x;
          return { side: right ? 0 : 1, col: Math.round((right ? c.x - (n.x + pw) : n.x - (c.x + w)) / 40) };
        };
        return ch.sort(function(a, b) { const ka = key(a), kb = key(b); return ka.side - kb.side || ka.col - kb.col || a.y - b.y; });
      }
      if (style === 'bubble') {
        // Clockwise: from the top around the main card, from the start of the fan elsewhere
        const p = mid(n), gp = n.parent && find(n.parent);
        const from = gp ? angle(mid(gp), p) - Math.PI : -Math.PI / 2;
        return ch.sort(function(a, b) { return turn(angle(p, mid(a)) - from) - turn(angle(p, mid(b)) - from); });
      }
      // Left to right, and top to bottom within a column (cards in one column share a centre)
      return ch.sort(function(a, b) { const d = mid(a).x - mid(b).x; return Math.abs(d) > 20 ? d : a.y - b.y; });
    }
    const out = [];
    (function walk(list) { list.forEach(function(n) { if (out.indexOf(n) < 0) { out.push(n); walk(kidsInOrder(n)); } }); })(all.filter(isRoot));
    all.forEach(function(n) { if (out.indexOf(n) < 0) out.push(n); });
    return out;
  }
  // The canvas can't scroll to negative positions
  function keepOnCanvas() {
    bubbleFresh = false; // runs after every layout, so the list-order request never lingers
    const nodes = getNodes();
    const minX = Math.min.apply(null, nodes.map(function(n) { return n.x; }));
    const minY = Math.min.apply(null, nodes.map(function(n) { return n.y; }));
    const sx = Math.max(0, LAYOUT.margin - minX), sy = Math.max(0, LAYOUT.margin - minY);
    nodes.forEach(function(n) { n.x = Math.round(n.x + sx); n.y = Math.round(n.y + sy); });
  }
  // Lays the cards out in a style (Tree of Life with no positions set falls back to Flow Chart)
  function run(style, live) {
    if (live.length) (style === 'tol' && !hasSefirot() ? tidyLayout : LAYOUT_STYLES[style] || tidyLayout)(live);
    keepOnCanvas();
  }
  return {
    LAYOUT: LAYOUT, STYLES: LAYOUT_STYLES, run: run, styleOf: styleOf, hasSefirot: hasSefirot, isRoot: isRoot,
    keepOnCanvas: keepOnCanvas, readingOrder: readingOrder, listOrder: function(on) { bubbleFresh = !!on; }
  };
}
window.NMMLayouts = { create: create };
})();
