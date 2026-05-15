<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Outfit Recommender</title>
<meta name="description" content="Discover your perfect outfit with AI-powered fashion recommendations. Personalized styling for every occasion.">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}

body{
  font-family:'Poppins',sans-serif;
  background:#060a14;
  color:#fff;
  overflow-x:hidden;
}

/* ===== ANIMATED BACKGROUND ===== */
.bg-canvas{
  position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;
}
.bg-orb{
  position:absolute;border-radius:50%;filter:blur(100px);opacity:.45;animation:orbFloat 18s ease-in-out infinite;
}
.bg-orb.o1{width:600px;height:600px;background:radial-gradient(circle,#ff7eb3,transparent 70%);top:-120px;left:-100px;animation-delay:0s;}
.bg-orb.o2{width:500px;height:500px;background:radial-gradient(circle,#4ac3ff,transparent 70%);bottom:-80px;right:-80px;animation-delay:-6s;}
.bg-orb.o3{width:400px;height:400px;background:radial-gradient(circle,#a855f7,transparent 70%);top:40%;left:50%;animation-delay:-12s;}
.bg-orb.o4{width:350px;height:350px;background:radial-gradient(circle,#ffd866,transparent 70%);top:60%;left:10%;opacity:.25;animation-delay:-4s;}

@keyframes orbFloat{
  0%,100%{transform:translate(0,0) scale(1);}
  25%{transform:translate(60px,-40px) scale(1.1);}
  50%{transform:translate(-30px,50px) scale(.95);}
  75%{transform:translate(-50px,-30px) scale(1.05);}
}

/* Particle stars */
.stars{position:fixed;inset:0;z-index:0;pointer-events:none;}
.star{
  position:absolute;width:2px;height:2px;background:#fff;border-radius:50%;
  animation:twinkle 3s ease-in-out infinite;
}
@keyframes twinkle{0%,100%{opacity:.2;}50%{opacity:.9;}}

/* ===== NAV ===== */
nav{
  position:fixed;top:0;left:0;width:100%;z-index:100;
  padding:18px 40px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(6,10,20,.6);backdrop-filter:blur(16px);
  border-bottom:1px solid rgba(255,255,255,.06);
  transition:background .3s;
}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo span.icon{font-size:28px;}
.nav-logo span.text{font-size:22px;font-weight:800;color:#fff;letter-spacing:-.02em;}
.nav-links{display:flex;gap:28px;align-items:center;}
.nav-links a{color:rgba(255,255,255,.75);text-decoration:none;font-size:14px;font-weight:500;transition:color .2s;}
.nav-links a:hover{color:#fff;}
.nav-cta{
  padding:10px 24px;border-radius:999px;
  background:linear-gradient(135deg,#ff7eb3,#ff758c);
  color:#fff;font-weight:700;font-size:13px;text-decoration:none;
  transition:transform .2s,box-shadow .2s;border:none;cursor:pointer;
}
.nav-cta:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(255,126,179,.35);}

/* ===== HERO ===== */
.hero{
  position:relative;z-index:1;
  min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:120px 24px 80px;
}
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;
  padding:8px 20px;border-radius:999px;
  background:rgba(255,126,179,.12);border:1px solid rgba(255,126,179,.25);
  font-size:13px;color:#ff9ec7;font-weight:600;margin-bottom:28px;
  animation:fadeUp .8s ease both;
}
.hero-badge .dot{width:8px;height:8px;border-radius:50%;background:#ff7eb3;animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(.8);}}

.hero h1{
  font-family:'Playfair Display',serif;
  font-size:clamp(36px,6vw,78px);font-weight:900;
  line-height:1.08;letter-spacing:-.03em;
  max-width:900px;margin-bottom:24px;
  animation:fadeUp .8s ease .15s both;
}
.hero h1 .gradient-text{
  background:linear-gradient(135deg,#ff7eb3,#4ac3ff,#a855f7);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
}
.hero-sub{
  font-size:clamp(15px,2vw,19px);color:rgba(255,255,255,.7);
  max-width:600px;line-height:1.8;margin-bottom:40px;
  animation:fadeUp .8s ease .3s both;
}
.hero-actions{
  display:flex;gap:16px;flex-wrap:wrap;justify-content:center;
  animation:fadeUp .8s ease .45s both;
}
.btn-primary{
  padding:16px 38px;border-radius:999px;border:none;
  background:linear-gradient(135deg,#4ac3ff,#2b7dff);
  color:#fff;font-size:16px;font-weight:700;cursor:pointer;text-decoration:none;
  transition:transform .25s,box-shadow .25s;
  display:inline-flex;align-items:center;gap:8px;
}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 20px 50px rgba(42,126,255,.3);}
.btn-secondary{
  padding:16px 38px;border-radius:999px;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);
  color:#fff;font-size:16px;font-weight:600;cursor:pointer;text-decoration:none;
  transition:transform .25s,background .25s;
}
.btn-secondary:hover{transform:translateY(-3px);background:rgba(255,255,255,.15);}

.hero-stats{
  display:flex;gap:40px;margin-top:60px;
  animation:fadeUp .8s ease .6s both;
}
.hero-stat{text-align:center;}
.hero-stat .num{font-size:32px;font-weight:800;color:#fff;}
.hero-stat .lbl{font-size:13px;color:rgba(255,255,255,.5);margin-top:4px;}

@keyframes fadeUp{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:translateY(0);}}

/* ===== SECTION SHARED ===== */
section{position:relative;z-index:1;padding:100px 24px;}
.section-center{max-width:1100px;margin:0 auto;}
.section-label{
  display:inline-block;padding:6px 16px;border-radius:999px;
  background:rgba(74,195,255,.1);color:#4ac3ff;
  font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:16px;
}
.section-title{font-size:clamp(28px,4vw,44px);font-weight:800;line-height:1.15;letter-spacing:-.03em;margin-bottom:18px;}
.section-desc{font-size:16px;color:rgba(255,255,255,.65);max-width:560px;line-height:1.75;}

/* ===== HOW IT WORKS ===== */
.steps-grid{
  display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:50px;
}
.step-card{
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
  border-radius:24px;padding:36px 28px;text-align:center;
  transition:transform .3s,border-color .3s,background .3s;position:relative;overflow:hidden;
}
.step-card:hover{transform:translateY(-6px);border-color:rgba(255,126,179,.3);background:rgba(255,255,255,.07);}
.step-num{
  width:48px;height:48px;border-radius:50%;
  background:linear-gradient(135deg,#ff7eb3,#ff758c);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:20px;font-weight:800;margin-bottom:20px;
}
.step-card h3{font-size:20px;font-weight:700;margin-bottom:12px;}
.step-card p{font-size:14px;color:rgba(255,255,255,.6);line-height:1.7;}
.step-icon{font-size:40px;margin-bottom:16px;display:block;}

/* ===== FEATURES ===== */
.features-grid{
  display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-top:50px;
}
.feature-card{
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
  border-radius:22px;padding:32px 28px;
  transition:transform .3s,border-color .3s;display:flex;gap:18px;align-items:flex-start;
}
.feature-card:hover{transform:translateY(-4px);border-color:rgba(74,195,255,.25);}
.feature-icon{
  width:52px;height:52px;border-radius:16px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:26px;
}
.fi-1{background:rgba(255,126,179,.15);}
.fi-2{background:rgba(74,195,255,.15);}
.fi-3{background:rgba(168,85,247,.15);}
.fi-4{background:rgba(255,216,102,.15);}
.fi-5{background:rgba(74,222,128,.15);}
.fi-6{background:rgba(251,146,60,.15);}
.feature-card h3{font-size:17px;font-weight:700;margin-bottom:8px;}
.feature-card p{font-size:13.5px;color:rgba(255,255,255,.6);line-height:1.65;}

/* ===== PLATFORMS ===== */
.platforms-row{
  display:flex;gap:24px;justify-content:center;margin-top:50px;flex-wrap:wrap;
}
.platform-card{
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
  border-radius:20px;padding:28px 36px;text-align:center;min-width:180px;
  transition:transform .3s,border-color .3s;
}
.platform-card:hover{transform:translateY(-4px);border-color:rgba(255,126,179,.3);}
.platform-card .p-icon{font-size:36px;margin-bottom:12px;display:block;}
.platform-card h3{font-size:18px;font-weight:700;margin-bottom:4px;}
.platform-card p{font-size:13px;color:rgba(255,255,255,.5);}

/* ===== TESTIMONIAL / CTA ===== */
.cta-section{
  text-align:center;padding:100px 24px 120px;
}
.cta-card{
  max-width:700px;margin:0 auto;
  background:linear-gradient(135deg,rgba(255,126,179,.1),rgba(74,195,255,.08));
  border:1px solid rgba(255,255,255,.1);border-radius:32px;
  padding:60px 40px;
}
.cta-card h2{font-size:clamp(26px,4vw,40px);font-weight:800;margin-bottom:16px;line-height:1.2;}
.cta-card p{font-size:16px;color:rgba(255,255,255,.65);margin-bottom:36px;line-height:1.7;}

/* ===== FOOTER ===== */
footer{
  position:relative;z-index:1;
  border-top:1px solid rgba(255,255,255,.06);
  padding:40px 24px;text-align:center;
}
footer p{font-size:13px;color:rgba(255,255,255,.35);}
footer a{color:rgba(255,255,255,.5);text-decoration:none;}
footer a:hover{color:#ff7eb3;}

/* ===== SCROLL ANIMATIONS ===== */
.reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease;}
.reveal.visible{opacity:1;transform:translateY(0);}

/* ===== RESPONSIVE ===== */
@media(max-width:900px){
  .steps-grid{grid-template-columns:1fr;}
  .features-grid{grid-template-columns:1fr;}
  .hero-stats{gap:24px;}
  .hero-stats .num{font-size:26px;}
}
@media(max-width:640px){
  nav{padding:14px 16px;}
  .nav-links a:not(.nav-cta){display:none;}
  .nav-logo span.text{font-size:18px;}
  .hero{padding:100px 16px 60px;}
  .hero h1{margin-bottom:18px;}
  .hero-sub{font-size:15px;margin-bottom:30px;}
  .btn-primary,.btn-secondary{padding:14px 28px;font-size:14px;}
  .hero-stats{flex-direction:column;gap:16px;margin-top:40px;}
  .cta-card{padding:40px 20px;border-radius:24px;}
  .step-card{padding:28px 20px;}
  .feature-card{flex-direction:column;padding:24px 20px;}
  .platforms-row{flex-direction:column;align-items:center;}
  .platform-card{width:100%;max-width:280px;}
  section{padding:70px 16px;}
}
@media(max-width:400px){
  .hero h1{font-size:28px;}
  .nav-cta{padding:8px 18px;font-size:12px;}
}
</style>
</head>
<body>

<!-- ANIMATED BG -->
<div class="bg-canvas">
  <div class="bg-orb o1"></div>
  <div class="bg-orb o2"></div>
  <div class="bg-orb o3"></div>
  <div class="bg-orb o4"></div>
</div>
<div class="stars" id="stars"></div>

<!-- NAV -->
<nav>
  <a href="#" class="nav-logo">
    <span class="icon"></span>
    <span class="text"></span>
  </a>
  <div class="nav-links">
    <a href="#how">How It Works</a>
    <a href="#features">Features</a>
    <a href="#platforms">Platforms</a>
    <a href="login.php" class="nav-cta">Get Started →</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-badge"><span class="dot"></span> AI-Powered Fashion Intelligence</div>
  <h1>Your Personal <span class="gradient-text">AI Stylist</span> for Every Occasion</h1>
  <p class="hero-sub">Upload a photo, tell us the occasion, and let our AI recommend the perfect outfit tailored to your skin tone, age, and style — sourced from top Indian platforms.</p>
  <div class="hero-actions">
    <a href="login.php" class="btn-primary">Get Started — It's Free <span>→</span></a>
    <a href="#how" class="btn-secondary">See How It Works</a>
  </div>
  <div class="hero-stats">
    <div class="hero-stat"><div class="num">30+</div><div class="lbl">Outfit Ideas Per Session</div></div>
    <div class="hero-stat"><div class="num">3</div><div class="lbl">Shopping Platforms</div></div>
    <div class="hero-stat"><div class="num">AI</div><div class="lbl">Gemini Powered</div></div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how">
  <div class="section-center">
    <span class="section-label">How It Works</span>
    <h2 class="section-title reveal">Three Steps to Your Perfect Look</h2>
    <p class="section-desc reveal">Our AI analyzes your photo, understands your style context, and delivers curated outfit recommendations in seconds.</p>
    <div class="steps-grid">
      <div class="step-card reveal">
        <span class="step-icon">📸</span>
        <div class="step-num">1</div>
        <h3>Capture Your Photo</h3>
        <p>Take a quick selfie using your device camera. Our AI analyzes your skin tone, current clothing, and physical features.</p>
      </div>
      <div class="step-card reveal">
        <span class="step-icon">🎯</span>
        <div class="step-num">2</div>
        <h3>Choose Your Occasion</h3>
        <p>Tell us what you're dressing up for — a wedding, office meeting, casual brunch, date night, or weekend hangout.</p>
      </div>
      <div class="step-card reveal">
        <span class="step-icon">✨</span>
        <div class="step-num">3</div>
        <h3>Get AI Recommendations</h3>
        <p>Receive 30 personalized outfit ideas with fabric details, styling tips, real prices, and direct links to buy.</p>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section id="features">
  <div class="section-center">
    <span class="section-label">Features</span>
    <h2 class="section-title reveal">Packed with Smart Features</h2>
    <p class="section-desc reveal">Everything you need to elevate your style game, powered by cutting-edge AI technology.</p>
    <div class="features-grid">
      <div class="feature-card reveal">
        <div class="feature-icon fi-1">🎨</div>
        <div><h3>Skin Tone Color Matching</h3><p>Our AI identifies your exact skin tone and undertone, then recommends colors that genuinely complement your complexion.</p></div>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon fi-2">🧠</div>
        <div><h3>Gemini AI Engine</h3><p>Powered by Google's Gemini models with multi-model fallback ensuring fast, reliable recommendations every time.</p></div>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon fi-3">💰</div>
        <div><h3>Real-Time Pricing</h3><p>See actual product prices fetched live from Amazon, Flipkart, and Meesho — no guesswork, just real deals.</p></div>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon fi-4">💾</div>
        <div><h3>Save Your Favorites</h3><p>Bookmark outfits you love and access them anytime from your personal saved collection in the sidebar.</p></div>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon fi-5">🔗</div>
        <div><h3>Direct Product Links</h3><p>Every recommendation links directly to the exact product page — not a search page — for instant shopping.</p></div>
      </div>
      <div class="feature-card reveal">
        <div class="feature-icon fi-6">👤</div>
        <div><h3>Personalized Profiles</h3><p>Upload your profile picture, track your search history, and get recommendations tailored to you.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- PLATFORMS -->
<section id="platforms">
  <div class="section-center" style="text-align:center;">
    <span class="section-label">Shop Anywhere</span>
    <h2 class="section-title reveal" style="max-width:600px;margin-left:auto;margin-right:auto;">Recommendations from India's Top Platforms</h2>
    <div class="platforms-row">
      <div class="platform-card reveal"><span class="p-icon">🛒</span><h3>Amazon</h3><p>Premium brands & fast delivery</p></div>
      <div class="platform-card reveal"><span class="p-icon">🛍️</span><h3>Flipkart</h3><p>Best deals & wide selection</p></div>
      <div class="platform-card reveal"><span class="p-icon">👗</span><h3>Meesho</h3><p>Budget-friendly fashion finds</p></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-card reveal">
    <h2>Ready to Upgrade Your Style?</h2>
    <p>Join now and let AI find the perfect outfits for every moment in your life. It's free, fast, and personalized just for you.</p>
    <a href="login.php" class="btn-primary" style="font-size:17px;padding:18px 44px;">Get Started Now →</a>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <p>© 2026 HARU — AI Outfit Recommender. Built with ❤️ and Gemini AI.</p>
</footer>

<script>
// Generate random stars
(function(){
  var c=document.getElementById('stars');
  for(var i=0;i<80;i++){
    var s=document.createElement('div');
    s.className='star';
    s.style.left=Math.random()*100+'%';
    s.style.top=Math.random()*100+'%';
    s.style.animationDelay=Math.random()*5+'s';
    s.style.animationDuration=(2+Math.random()*4)+'s';
    s.style.width=s.style.height=(1+Math.random()*2)+'px';
    c.appendChild(s);
  }
})();

// Scroll reveal
var reveals=document.querySelectorAll('.reveal');
function checkReveal(){
  reveals.forEach(function(el){
    var top=el.getBoundingClientRect().top;
    if(top<window.innerHeight-80){
      el.classList.add('visible');
    }
  });
}
window.addEventListener('scroll',checkReveal);
window.addEventListener('load',checkReveal);

// Nav background on scroll
window.addEventListener('scroll',function(){
  var nav=document.querySelector('nav');
  if(window.scrollY>50){
    nav.style.background='rgba(6,10,20,.92)';
  }else{
    nav.style.background='rgba(6,10,20,.6)';
  }
});
</script>
</body>
</html>
