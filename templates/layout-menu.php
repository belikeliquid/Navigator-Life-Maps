<?php if ( ! defined( 'ABSPATH' ) ) exit;
// Layout style menu, opened from the ▾ next to Harmony (editor) or the Layout button (read-only view).
// Kept outside the toolbar so it isn't clipped. $nmm_layout_ai: also offer Reorganize with AI (editor only).
?>
<div id="nmm-layout-menu" class="nmm-layout-menu" role="menu">
    <button type="button" data-layout="flow" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><rect x="8.5" y="2.5" width="7" height="5" rx="1.2" fill="currentColor"/><path d="M12 7.5v4M4.5 11.5h15M4.5 11.5v4M12 11.5v4M19.5 11.5v4"/><rect x="1.5" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/><rect x="9" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/><rect x="16.5" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/></svg>
      <span><strong>Flow Chart</strong><small>Top to bottom</small></span>
    </button>
    <button type="button" data-layout="mind" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"><rect x="8" y="9.5" width="8" height="5" rx="2"/><rect x="1" y="2" width="5" height="4" rx="1.2"/><rect x="18" y="2" width="5" height="4" rx="1.2"/><rect x="1" y="10" width="5" height="4" rx="1.2"/><rect x="18" y="10" width="5" height="4" rx="1.2"/><rect x="1" y="18" width="5" height="4" rx="1.2"/><rect x="18" y="18" width="5" height="4" rx="1.2"/><path d="M8 12H6M16 12h2M9.5 9.5V4H6M14.5 9.5V4H18M9.5 14.5V20H6M14.5 14.5V20H18"/></svg>
      <span><strong>Mind Map</strong><small>From the centre out</small></span>
    </button>
    <button type="button" data-layout="bubble" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9.6 9.8L6.4 6.4M14.6 10l3-3.4M9.4 14.2l-3 3.2M14.4 14.4l3 2.8M7 12H4"/><circle cx="12" cy="12" r="4" fill="currentColor"/><circle cx="4.6" cy="4.6" r="2.4"/><circle cx="19.2" cy="5" r="2.4"/><circle cx="4.8" cy="19.2" r="2.4"/><circle cx="19.2" cy="19" r="2.4"/><circle cx="2.6" cy="12" r="1.4"/></svg>
      <span><strong>Bubble</strong><small>Circles around the main idea</small></span>
    </button>
    <button type="button" data-layout="tol" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M12 2.5v19M6 6.5h12M6 11.5h12M6 16.5h12M6 6.5v10M18 6.5v10M12 2.5L6 6.5M12 2.5l6 4M6 6.5l6 3.5 6-3.5M6 11.5l6 3 6-3M6 16.5l6 2 6-2"/><g fill="currentColor" stroke="none"><circle cx="12" cy="2.5" r="1.8"/><circle cx="6" cy="6.5" r="1.8"/><circle cx="18" cy="6.5" r="1.8"/><circle cx="12" cy="9.5" r="1.8"/><circle cx="6" cy="11.5" r="1.8"/><circle cx="18" cy="11.5" r="1.8"/><circle cx="12" cy="14.5" r="1.8"/><circle cx="6" cy="16.5" r="1.8"/><circle cx="18" cy="16.5" r="1.8"/><circle cx="12" cy="18.5" r="1.8"/><circle cx="12" cy="21.5" r="1.8"/></g></svg>
      <span><strong>Tree of Life</strong><small>Cards by their Tree of Life position</small></span>
    </button>
    <button type="button" data-layout="outline" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M7 4.5h14M11 9.5h10M11 14.5h10M7 19.5h14"/><g fill="currentColor" stroke="none"><circle cx="3.5" cy="4.5" r="1.6"/><circle cx="7.5" cy="9.5" r="1.6"/><circle cx="7.5" cy="14.5" r="1.6"/><circle cx="3.5" cy="19.5" r="1.6"/></g></svg>
      <span><strong>Outline</strong><small><?php echo empty( $nmm_layout_ai ) ? 'Your map as a list' : 'Your map as a list you can type in'; ?></small></span>
    </button>
<?php if ( ! empty( $nmm_layout_ai ) ) : ?>
    <div class="nmm-layout-sep"></div>
    <button type="button" id="nmm-reorganize-btn" role="menuitem" onclick="nmmOpenReorganize()">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"><path d="M12 2.5l1.6 4.4 4.4 1.6-4.4 1.6L12 14.5l-1.6-4.4L6 8.5l4.4-1.6z" fill="currentColor"/><path d="M18.5 14l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8zM5.5 15l.6 1.6 1.6.6-1.6.6-.6 1.6-.6-1.6-1.6-.6 1.6-.6z"/></svg>
      <span><strong>Reorganize with AI</strong><small>Rewrite the map the way you ask</small></span>
    </button>
<?php endif; ?>
</div>
