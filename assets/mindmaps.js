/* Navigator Mind Maps — Global JS
   Most logic lives inline in templates for clarity.
   This file handles any global utilities. */

(function() {
  // Ensure NMM namespace exists
  if (typeof window.NMM === 'undefined') {
    console.warn('Navigator Mind Maps: NMM config not found. Is the shortcode active on this page?');
  }
})();
