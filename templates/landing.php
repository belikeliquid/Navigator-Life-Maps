<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-landing" class="nmm-app nmm-landing">

  <!-- Full width header bar — matches editor/kanban -->
  <div class="nmm-editor-toolbar nmm-landing-toolbar">
    <div class="nmm-logo">
      <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
        <circle cx="14" cy="14" r="12" stroke="#0BBFBF" stroke-width="1.5"/>
        <circle cx="14" cy="14" r="3" fill="#0BBFBF"/>
        <line x1="14" y1="2" x2="14" y2="8" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="14" y1="20" x2="14" y2="26" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="2" y1="14" x2="8" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="20" y1="14" x2="26" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      <span class="nmm-logo-text">NAVIGATOR MIND MAPS</span>
    </div>
  </div>

  <!-- Copy band -->
  <div class="nmm-landing-copy">
    <p>Navigator Mind Maps is where vision meets action.. where ideas find structure and goals find ground. Built on conscious, mental, and emotional models of planning and integration. Start with a list, build a map, set your compass.</p>
    <p class="nmm-landing-motif">In the ocean of life, you are the navigator.</p>
  </div>

  <!-- Two columns: Login + Signup -->
  <div class="nmm-landing-cols">

    <!-- LEFT: Login -->
    <div class="nmm-landing-card">
      <div class="nmm-landing-section-label">Welcome back</div>
      <div id="nmm-login-error" class="nmm-login-error" style="display:none"></div>
      <div class="nmm-field">
        <label>Username or email</label>
        <input type="text" id="nmm-login-user" class="nmm-input" autocomplete="username" />
      </div>
      <div class="nmm-field">
        <label>Password</label>
        <input type="password" id="nmm-login-pass" class="nmm-input" autocomplete="current-password" />
      </div>
      <button class="nmm-btn nmm-btn-primary nmm-btn-full" id="nmm-login-btn">Log in</button>
      <div style="text-align:center;margin-top:10px">
        <a href="<?php echo wp_lostpassword_url(); ?>" style="font-size:11px;color:var(--nmm-teal)">Forgot password?</a>
      </div>
    </div>

    <!-- RIGHT: Signup -->
    <div class="nmm-landing-card">
      <div class="nmm-landing-section-label">New here</div>
      <div id="nmm-signup-error" class="nmm-login-error" style="display:none"></div>
      <div class="nmm-field">
        <label>Username</label>
        <input type="text" id="nmm-signup-user" class="nmm-input" autocomplete="username" />
      </div>
      <div class="nmm-field">
        <label>Email</label>
        <input type="email" id="nmm-signup-email" class="nmm-input" autocomplete="email" />
      </div>
      <div class="nmm-field">
        <label>Password</label>
        <input type="password" id="nmm-signup-pass" class="nmm-input" autocomplete="new-password" />
      </div>
      <?php
      $ts_site_key = get_option('nmm_turnstile_site_key', '');
      if ( $ts_site_key ) : ?>
      <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($ts_site_key); ?>" data-theme="light"></div>
      <?php endif; ?>
      <button class="nmm-btn nmm-btn-primary nmm-btn-full" id="nmm-signup-btn" style="margin-top:10px">Create account</button>
      <p style="font-size:10px;color:#aaa;text-align:center;margin-top:6px">Free account. No credit card needed.</p>
    </div>

  </div>
</div>

<?php if ( get_option('nmm_turnstile_site_key') ) : ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>

<script>
(function() {
const REST  = NMM.restUrl;
let nonce   = NMM.nonce;

function api(method, endpoint, data) {
  return fetch(REST + endpoint, {
    method: method,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: data ? JSON.stringify(data) : undefined
  }).then(function(r) { return r.json(); });
}
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
</script>
