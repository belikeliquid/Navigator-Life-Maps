/* Navigator Mind Maps — login / signup landing (/maps/ when logged out). Markup: templates/landing.php */
(function() {
if (!document.getElementById('nmm-login-btn')) return;
const REST  = NMM.restUrl;
let nonce   = NMM.nonce;

function showError(el, msg) { el.textContent = msg; el.style.display = 'block'; }
function hideError(el) { el.style.display = 'none'; }

async function afterAuth() {
  // Refresh nonce after login
  try {
    const nd = await fetch(REST + 'nonce').then(function(r){ return r.json(); });
    if (nd.nonce) nonce = nd.nonce;
  } catch(e) {}
  window.location.href = NMM.homeUrl + '/maps/';
}

// LOGIN
document.getElementById('nmm-login-btn').addEventListener('click', function() {
  const user = document.getElementById('nmm-login-user').value.trim();
  const pass = document.getElementById('nmm-login-pass').value;
  const err  = document.getElementById('nmm-login-error');
  hideError(err);
  if (!user || !pass) { showError(err, 'Please enter your username and password.'); return; }
  const btn = this; btn.textContent = 'Logging in...'; btn.disabled = true;
  fetch(REST + 'auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: JSON.stringify({ username: user, password: pass })
  }).then(function(r){ return r.json(); }).then(function(data) {
    if (data.success) { afterAuth(); }
    else { showError(err, data.message || 'Login failed. Please check your details.'); btn.textContent = 'Log in'; btn.disabled = false; }
  }).catch(function(){ showError(err, 'Connection error. Please try again.'); btn.textContent = 'Log in'; btn.disabled = false; });
});

document.getElementById('nmm-login-pass').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') document.getElementById('nmm-login-btn').click();
});

// SIGNUP
document.getElementById('nmm-signup-btn').addEventListener('click', function() {
  const user  = document.getElementById('nmm-signup-user').value.trim();
  const email = document.getElementById('nmm-signup-email').value.trim();
  const pass  = document.getElementById('nmm-signup-pass').value;
  const err   = document.getElementById('nmm-signup-error');
  hideError(err);
  if (!user || !email || !pass) { showError(err, 'Please fill in all fields.'); return; }

  // Cloudflare Turnstile token if present
  const tsEl = document.querySelector('.cf-turnstile iframe');
  const tsToken = tsEl ? (window.turnstile ? window.turnstile.getResponse() : '') : '';

  const btn = this; btn.textContent = 'Creating...'; btn.disabled = true;
  fetch(REST + 'auth/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: JSON.stringify({ username: user, email: email, password: pass, turnstile_token: tsToken })
  }).then(function(r){ return r.json(); }).then(function(data) {
    if (data.success) { afterAuth(); }
    else { showError(err, data.message || 'Registration failed. Please try again.'); btn.textContent = 'Create account'; btn.disabled = false; }
  }).catch(function(){ showError(err, 'Connection error.'); btn.textContent = 'Create account'; btn.disabled = false; });
});

})();
