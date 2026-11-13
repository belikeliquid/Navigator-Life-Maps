/* Navigator Life Maps — login / signup landing (/maps/ when logged out). Markup: templates/landing.php */
(function() {
if (!document.getElementById('nmm-login-btn')) return;
const REST  = NMM.restUrl;

function showError(el, msg) { el.textContent = msg; el.style.display = 'block'; }
function hideError(el) { el.style.display = 'none'; }

// Opened from an invitation email: /maps/?invite=TOKEN
const INVITE = new URLSearchParams(location.search).get('invite') || '';
let inviteOk = false;
async function afterAuth(data) {
  window.location.href = data && data.map_id ? NMM.editorUrl + '?map_id=' + data.map_id : NMM.homeUrl + '/maps/';
}
if (INVITE) {
  fetch(REST + 'invite/' + encodeURIComponent(INVITE)).then(function(r) { return r.json(); }).then(function(inv) {
    const banner = document.getElementById('nmm-invite-banner');
    banner.style.display = '';
    if (!inv || !inv.valid) {
      banner.classList.add('nmm-invite-expired');
      banner.textContent = 'This invitation has expired or was already used. Ask the person who invited you to share the map again.';
      return;
    }
    inviteOk = true;
    const esc = NMMCore.esc;
    banner.innerHTML = '<strong>' + esc(inv.by) + ' invited you to collaborate on “' + esc(inv.map_title) + '”.</strong>' +
      '<span>Create your free account to open it (no invitation code needed), or log in if you already have one.</span>';
    const email = document.getElementById('nmm-signup-email');
    if (email && !email.value) email.value = inv.email;
    const code = document.getElementById('nmm-signup-invite-field');
    if (code) code.style.display = 'none';
  }).catch(function() {});
}

// LOGIN
document.getElementById('nmm-login-btn').addEventListener('click', function() {
  const user = document.getElementById('nmm-login-user').value.trim();
  const pass = document.getElementById('nmm-login-pass').value;
  const err  = document.getElementById('nmm-login-error');
  hideError(err);
  if (!user || !pass) { showError(err, 'Please enter your username and password.'); return; }
  const tsToken = turnstileToken('login');
  if (tsEls.login && !tsToken) { showError(err, TS_WAIT); return; }
  const btn = this; btn.textContent = 'Logging in...'; btn.disabled = true;
  fetch(REST + 'auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }, // no nonce: this page acts for nobody yet, and an old one would be refused
    body: JSON.stringify({ username: user, password: pass, turnstile_token: tsToken, invite: INVITE })
  }).then(function(r){ return r.json(); }).then(function(data) {
    if (data.success) { afterAuth(data); }
    else { showError(err, data.message || 'Login failed. Please check your details.'); btn.textContent = 'Log in'; btn.disabled = false; resetTurnstile('login'); }
  }).catch(function(){ showError(err, 'Connection error. Please try again.'); btn.textContent = 'Log in'; btn.disabled = false; resetTurnstile('login'); });
});

document.getElementById('nmm-login-pass').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') document.getElementById('nmm-login-btn').click();
});

// Cloudflare Turnstile. Other forms on the page (the email popup) load the same script
// in explicit mode, which leaves a plain .cf-turnstile empty, so login and signup render
// their own widgets and read each token by widget id.
const tsEls = { login: document.getElementById('nmm-login-turnstile'), signup: document.getElementById('nmm-signup-turnstile') };
const tsWidgets = {};
function renderTurnstile() {
  if (!tsEls.login && !tsEls.signup) return;
  if (!window.turnstile) {
    if (!document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]')) {
      const s = document.createElement('script');
      s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'; s.async = true;
      document.head.appendChild(s);
    }
    setTimeout(renderTurnstile, 200);
    return;
  }
  ['login', 'signup'].forEach(function(k) {
    if (tsEls[k]) tsWidgets[k] = window.turnstile.render(tsEls[k], { sitekey: tsEls[k].dataset.sitekey, theme: 'light' });
  });
}
function turnstileToken(k) { return tsWidgets[k] !== undefined ? window.turnstile.getResponse(tsWidgets[k]) || '' : ''; }
// A token works once, so a failed attempt needs a fresh one
function resetTurnstile(k) { if (tsWidgets[k] !== undefined) window.turnstile.reset(tsWidgets[k]); }
const TS_WAIT = 'The security check above hasn\'t finished. Please complete it, then try again.';

// SIGNUP
document.getElementById('nmm-signup-btn').addEventListener('click', function() {
  const user  = document.getElementById('nmm-signup-user').value.trim();
  const email = document.getElementById('nmm-signup-email').value.trim();
  const pass  = document.getElementById('nmm-signup-pass').value;
  const err   = document.getElementById('nmm-signup-error');
  const inviteEl = document.getElementById('nmm-signup-invite');
  const invite   = inviteEl ? inviteEl.value.trim() : '';
  hideError(err);
  if (!user || !email || !pass || (inviteEl && !invite && !inviteOk)) { showError(err, 'Please fill in all fields.'); return; }

  const tsToken = turnstileToken('signup');
  if (tsEls.signup && !tsToken) { showError(err, TS_WAIT); return; }

  const btn = this; btn.textContent = 'Creating...'; btn.disabled = true;
  fetch(REST + 'auth/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }, // no nonce: this page acts for nobody yet, and an old one would be refused
    body: JSON.stringify({ username: user, email: email, password: pass, invite_code: invite, invite: INVITE, turnstile_token: tsToken })
  }).then(function(r){ return r.json(); }).then(function(data) {
    if (data.success) { afterAuth(data); }
    else { showError(err, data.message || 'Registration failed. Please try again.'); btn.textContent = 'Create account'; btn.disabled = false; resetTurnstile('signup'); }
  }).catch(function(){ showError(err, 'Connection error.'); btn.textContent = 'Create account'; btn.disabled = false; resetTurnstile('signup'); });
});


renderTurnstile();
})();
