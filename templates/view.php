<?php if ( ! defined( 'ABSPATH' ) ) exit;
// Supports both direct map_id and share key from URL
$map_id = isset($map_id) ? intval($map_id) : 0;
$share_key = isset($_GET['share']) ? sanitize_text_field($_GET['share']) : '';
?>
<div id="nmm-view" class="nmm-app nmm-view-mode">
  <div class="nmm-view-header">
    <div class="nmm-logo">
      <svg width="22" height="22" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="12" stroke="#0BBFBF" stroke-width="2"/><circle cx="14" cy="14" r="3" fill="#0BBFBF"/><line x1="14" y1="2" x2="14" y2="8" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/><line x1="14" y1="20" x2="14" y2="26" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/><line x1="2" y1="14" x2="8" y2="14" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/><line x1="20" y1="14" x2="26" y2="14" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/></svg>
      <span class="nmm-logo-text">NAVIGATOR</span>
    </div>
    <span id="nmm-view-title" class="nmm-view-title"></span>
    <div class="nmm-view-actions">
      <button class="nmm-btn" onclick="nmmViewExpandAll()">Expand all</button>
      <button class="nmm-btn" onclick="nmmViewCollapseAll()">Collapse</button>
      <button class="nmm-btn" onclick="nmmViewExportPNG()">Export PNG</button>
    </div>
  </div>

  <div id="nmm-canvas-wrap" class="nmm-canvas-wrap">
    <div id="nmm-bg-layer" class="nmm-bg-layer"></div>
    <div id="nmm-bg-overlay" class="nmm-bg-overlay"></div>
    <div id="nmm-canvas" class="nmm-canvas">
      <svg id="nmm-lines"></svg>
      <div id="nmm-motif" class="nmm-motif"></div>
    </div>
  </div>
  <div id="nmm-export-overlay" class="nmm-export-overlay">Generating PNG...</div>
</div>

<script>
(function() {
const MAP_ID = <?php echo intval($map_id); ?>;
const SHARE_KEY = '<?php echo esc_js($share_key); ?>';
const REST = NMM.restUrl;
let nodes=[], settings={}, quoteIdx=0;

async function loadMap() {
  let map;
  try {
    if (SHARE_KEY) {
      const r=await fetch(REST+'share/'+SHARE_KEY);
      map=await r.json();
    } else if (MAP_ID) {
      const r=await fetch(REST+'maps/'+MAP_ID,{headers:{'X-WP-Nonce':NMM.nonce}});
      map=await r.json();
    }
    if (!map||map.code) { showError(); return; }
    nodes=map.nodes||[];
    settings=map.settings||{};
    document.getElementById('nmm-view-title').textContent=map.title||'';
    // Load linked islands
    for (const islandId of (map.islands||[])) {
      const r=await fetch(REST+'islands/'+islandId,{headers:{'X-WP-Nonce':NMM.nonce}});
      const island=await r.json();
      if (island&&island.nodes) {
        island.nodes.forEach(function(n){n._island_id=islandId;n._island_title=island.title;n._readonly=true;if(!nodes.find(x=>x.id===n.id))nodes.push(n);});
      }
    }
    render();
  } catch(e) { showError(); }
}

function showError() {
  document.getElementById('nmm-canvas').innerHTML='<div style="padding:60px;text-align:center;color:#999;font-family:Barlow Condensed,sans-serif;font-size:18px;">Map not found or no longer available.</div>';
}

function getDepth(id){let d=0,n=nodes.find(x=>x.id===id);while(n&&n.parent){d++;n=nodes.find(x=>x.id===n.parent);}return d;}
function isVisible(id){let n=nodes.find(x=>x.id===id);if(!n)return false;if(!n.parent)return true;const p=nodes.find(x=>x.id===n.parent);if(!p||p.collapsed)return false;return isVisible(p.id);}
function hasChildren(id){return nodes.some(n=>n.parent===id);}
function getEl(id){return document.querySelector('.nmm-node[data-id="'+id+'"]');}

function render() {
  const canvas=document.getElementById('nmm-canvas');
  canvas.querySelectorAll('.nmm-node').forEach(n=>n.remove());
  nodes.forEach(function(n){
    if(!isVisible(n.id))return;
    const depth=Math.min(getDepth(n.id),4);
    const el=document.createElement('div');
    el.className='nmm-node'+(n._readonly?' nmm-linked':'');
    el.dataset.depth=depth;el.dataset.id=n.id;
    el.style.left=n.x+'px';el.style.top=n.y+'px';
    const expander=hasChildren(n.id)?'<span class="nmm-expander" data-eid="'+n.id+'">'+(n.collapsed?'+':'−')+'</span>':'';
    const subs=(n.subs&&n.subs.length)?'<div class="nmm-node-sub">'+n.subs.map(s=>'<span>'+s+'</span>').join('')+'</div>':'';
    const dateStr=n.date?'<div class="nmm-node-date">📅 '+n.date+'</div>':'';
    const linkedBadge=n._readonly?'<div class="nmm-linked-badge">⛓ '+n._island_title+'</div>':'';
    el.innerHTML='<div class="nmm-node-title-row"><span class="nmm-node-title">'+n.title+'</span>'+expander+'</div>'+subs+dateStr+linkedBadge;
    el.addEventListener('mousedown',function(e){if(e.target.dataset.eid)nmmToggleCollapse(e.target.dataset.eid);});
    canvas.appendChild(el);
  });
  drawLines();renderMotif();applyBackground();
}

function drawLines(){
  const svg=document.getElementById('nmm-lines');svg.innerHTML='';
  const styles=[{stroke:'#0BBFBF',width:2,dash:'5 4'},{stroke:'#0BBFBF',width:1.5,dash:'5 4'},{stroke:'#5DCAA5',width:1.5,dash:'4 4'},{stroke:'#EF9F27',width:1.5,dash:'4 4'},{stroke:'#FAC775',width:1,dash:'3 4'}];
  nodes.forEach(function(n){
    if(!n.parent||!isVisible(n.id))return;
    const parent=nodes.find(p=>p.id===n.parent);
    if(!parent||parent.collapsed)return;
    const pEl=getEl(parent.id),cEl=getEl(n.id);if(!pEl||!cEl)return;
    const px=parent.x+pEl.offsetWidth/2,py=parent.y+pEl.offsetHeight,cx=n.x+cEl.offsetWidth/2,cy=n.y;
    const dx=Math.abs(cx-px),dy=Math.abs(cy-py);
    const depth=Math.min(getDepth(n.id),styles.length-1);
    const s=styles[depth];
    let d=dy>0&&dx/dy<0.10?'M'+px+','+py+' L'+px+','+cy:'M'+px+','+py+' C'+px+','+(py+cy)/2+' '+cx+','+(py+cy)/2+' '+cx+','+cy;
    const path=document.createElementNS('http://www.w3.org/2000/svg','path');
    path.setAttribute('d',d);path.setAttribute('fill','none');path.setAttribute('stroke',s.stroke);path.setAttribute('stroke-width',s.width);path.setAttribute('stroke-dasharray',s.dash);path.setAttribute('stroke-linecap','round');
    svg.appendChild(path);
  });
}

function nmmToggleCollapse(id){const n=nodes.find(x=>x.id===id);if(n){n.collapsed=!n.collapsed;render();}}
window.nmmViewExpandAll=function(){nodes.forEach(n=>n.collapsed=false);render();};
window.nmmViewCollapseAll=function(){nodes.forEach(n=>{if(hasChildren(n.id))n.collapsed=true;});render();};

function applyBackground(){
  const bg=document.getElementById('nmm-bg-layer'),overlay=document.getElementById('nmm-bg-overlay');
  if(settings.bgUrl){bg.style.backgroundImage='url("'+settings.bgUrl+'")';overlay.style.background='rgba(232,228,219,0.5)';}
  else{bg.style.backgroundImage='none';overlay.style.background='none';}
}
function renderMotif(){
  const el=document.getElementById('nmm-motif');
  if(settings.motif){el.textContent=settings.motif;}
  else if(settings.quotes){const qs=settings.quotes.split(',').map(q=>q.trim()).filter(Boolean);el.textContent=qs.length?qs[quoteIdx%qs.length]:'';}
  else{el.textContent='';}
}
setInterval(function(){quoteIdx++;renderMotif();},8000);

window.nmmViewExportPNG=function(){
  const overlay=document.getElementById('nmm-export-overlay');overlay.style.display='flex';
  setTimeout(function(){
    let minX=Infinity,minY=Infinity,maxX=0,maxY=0;
    nodes.forEach(function(n){if(!isVisible(n.id))return;const el=getEl(n.id);if(!el)return;minX=Math.min(minX,n.x);minY=Math.min(minY,n.y);maxX=Math.max(maxX,n.x+el.offsetWidth);maxY=Math.max(maxY,n.y+el.offsetHeight);});
    const pad=80;
    html2canvas(document.getElementById('nmm-canvas'),{backgroundColor:'#e8e4db',x:minX-pad,y:minY-pad,width:maxX-minX+pad*2,height:maxY-minY+pad*2,scale:2,logging:false})
    .then(function(c){overlay.style.display='none';const a=document.createElement('a');a.download='mindmap_share.png';a.href=c.toDataURL('image/png');a.click();})
    .catch(function(){overlay.style.display='none';});
  },150);
};

loadMap();
})();
</script>
