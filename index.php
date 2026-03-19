<?php
declare(strict_types=1);
session_start();

$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Nemi – Your Personalized Path to Homeownership</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preload" as="image" href="./assets/svgs/signinrenamed" type="image/svg+xml" />
</head>
<body class="bg-white text-slate-900">
  <!-- Fixed background for MOBILE ONLY (JS toggles visibility) -->
  <div id="bgFixed" aria-hidden="true"></div>

  <section id="nemiMobile"
    class="relative mx-auto w-[min(520px,96vw)] overflow-hidden invisible"
    style="aspect-ratio:9/16">

    <!-- Frosted card bg -->
    <div id="slot-loginboxbg" class="slot-bg absolute hidden" aria-hidden="true"></div>

    <!-- SVG host -->
    <div id="mobileArt" class="absolute inset-0" style="z-index:1;"></div>

    <!-- Error message -->
    <?php if ($loginError !== ''): ?>
      <div
        class="absolute left-1/2 top-4 z-[40] w-[min(92%,420px)] -translate-x-1/2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow"
      >
        <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <!-- ======================= [OVERLAY SLOTS] ======================= -->
    <div id="slot-teamiconbox" class="slot absolute hidden">
      <img src="./assets/img/teamicon.svg" alt="Team" class="w-full h-full object-contain">
    </div>

    <div id="slot-titletextbox" class="slot absolute hidden">
      <div class="titlecopy text-slate-900" style="--fsTitle:22px;"></div>
    </div>

    <div id="slot-oneteam" class="slot absolute hidden">
      <div class="otcopy text-slate-800" style="--fsSub:16px;">
        <span id="ot-line1"></span>
        <span id="ot-line2"></span>
      </div>
    </div>

    <a id="slot-getstartedwithnemi" href="#"
       class="slot absolute hidden inline-flex items-center justify-center rounded-full border-2 border-slate-800/90 bg-yellow-400 font-semibold text-slate-900"></a>

    <div id="slot-alreadyamember" class="slot absolute hidden">
      <p class="amcopy"></p>
    </div>

    <!-- ======================= LOGIN FORM LAYER ======================= -->
    <form id="nemiLoginForm" method="post" action="/auth/login.php" class="form-layer" novalidate>
      <div id="slot-emailbox" class="slot absolute hidden">
        <input
          class="w-full h-full rounded-full border-2 border-slate-300 px-3 text-[16px]"
          type="text"
          name="identifier"
          placeholder="Email or phone"
          autocomplete="username"
          required
        >
      </div>

      <div id="slot-passwordbox" class="slot absolute hidden">
  <input
    class="w-full h-full rounded-full border-2 border-slate-300 px-3 text-[16px]"
    type="tel"
    name="pin"
    placeholder="4-digit PIN"
    autocomplete="off"
    inputmode="numeric"
    maxlength="4"
    required
  >
</div>

      <input type="hidden" name="device_id" value="d_abc123">

      <button id="slot-logintext" type="submit" class="slot absolute hidden rounded-full border-2 font-semibold">🔒 Login</button>
    </form>

    <a id="slot-forgotpassword" href="/auth/forgot.php"
       class="slot absolute hidden underline text-center">Forgot PIN?</a>

    <a id="slot-appdownload" href="#" class="slot absolute hidden block" aria-label="Download on the App Store">
      <img src="./assets/img/appstore.svg" class="w-full h-full object-contain" alt="">
    </a>
    <a id="slot-androiddownload" href="#" class="slot absolute hidden block" aria-label="Get it on Google Play">
      <img src="./assets/img/googleplay.svg" class="w-full h-full object-contain" alt="">
    </a>

    <a id="slot-explorenemibox" href="#"
       class="slot absolute hidden inline-flex items-center justify-center rounded-full border-2 font-semibold"></a>

    <div id="slot-realtorapply" class="slot absolute hidden flex items-center justify-center text-slate-600 text-center">
      <span id="apply-line"></span>&nbsp;<a id="apply-link" href="/pros/apply" class="underline text-slate-800">Apply Here!</a>
    </div>
  </section>

  <style>
html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

#bgFixed{
  position: fixed;
  inset: 0;
  z-index: 0;
  display: none;
}
#bgFixed svg{
  width: 100%;
  height: 100%;
  display: block;
  pointer-events: none;
}

#nemiMobile{
  --card-opacity: .65;
  --title-nudge-y: 2px;
  --oneteam-nudge-y: -1px;
  --oneteam-gap: 4px;
}

#nemiMobile .slot-bg{
  z-index: 6;
  background: rgba(255,255,255,var(--card-opacity));
  border-radius: 24px;
  box-shadow: 0 10px 30px rgba(20,35,58,0.15);
  pointer-events: none;
  backdrop-filter: blur(8px) saturate(1.1);
  -webkit-backdrop-filter: blur(8px) saturate(1.1);
  border: 1px solid rgba(255,255,255,0.35);
}

#nemiMobile .slot { z-index: 10; }

/* Form layer to preserve your exact positioned pills/inputs */
#nemiLoginForm.form-layer{
  position:absolute;
  inset:0;
  z-index:20;
  pointer-events:none;
}
#nemiLoginForm .slot,
#nemiLoginForm input,
#nemiLoginForm button{
  pointer-events:auto;
}

#slot-titletextbox, #slot-oneteam{
  display:flex; align-items:center; justify-content:center; overflow:hidden;
}

#slot-titletextbox .titlecopy{
  width:100%;
  white-space:nowrap;
  text-align:center;
  line-height:2;
  font-weight:800;
  font-size:var(--fsTitle) !important;
  box-sizing:border-box;
  padding-inline:6px;
  letter-spacing:-0.15px;
  transform: translateY(var(--title-nudge-y));
}

#slot-oneteam .otcopy{
  width:100%;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  text-align:center; padding-inline:2px;
  line-height:1.05;
  font-size:var(--fsSub);
  transform: translateY(var(--oneteam-nudge-y));
}
#slot-oneteam .otcopy > span{ display:block; white-space:nowrap; }
#slot-oneteam .otcopy > span + span{ margin-top: var(--oneteam-gap); }
#slot-oneteam{ z-index: 11; }

#slot-getstartedwithnemi{
  z-index:15; display:flex !important; align-items:center; justify-content:center;
  width:100%; height:100%; border-radius:9999px; text-decoration:none;
  font-weight:800; letter-spacing:.2px; color:#0f1e3a;
  border:3px solid #1c2c46;
  background: linear-gradient(#f6d768, #e3be32);
  box-shadow: 0 3px 0 #1c2c46, 0 6px 18px rgba(28,44,70,.25), inset 0 2px 0 rgba(255,255,255,.65);
  font-size:inherit;
}
#slot-getstartedwithnemi:hover { filter: brightness(1.02); }
#slot-getstartedwithnemi:active { transform: translateY(1px); box-shadow: 0 1px 0 #1c2c46, 0 4px 12px rgba(28,44,70,.22), inset 0 1px 0 rgba(255,255,255,.55); }
#slot-getstartedwithnemi:focus-visible { outline:3px solid #0ea5e9; outline-offset:2px; }

#slot-logintext{
  z-index:15; display:flex !important; align-items:center; justify-content:center;
  width:100%; height:100%; border-radius:9999px; text-decoration:none;
  font-weight:800; letter-spacing:.2px; color:#0f1e3a;
  border:3px solid #1c2c46;
  background: linear-gradient(#f6d768, #e3be32);
  box-shadow: 0 3px 0 #1c2c46, 0 6px 18px rgba(28,44,70,.25), inset 0 2px 0 rgba(255,255,255,.65);
  font-size:inherit;
}
#slot-logintext:hover { filter: brightness(1.02); }
#slot-logintext:active { transform: translateY(1px); box-shadow: 0 1px 0 #1c2c46, 0 4px 12px rgba(28,44,70,.22), inset 0 1px 0 rgba(255,255,255,.55); }
#slot-logintext:focus-visible { outline:3px solid #0ea5e9; outline-offset:2px; }

#slot-explorenemibox{
  z-index:15; display:flex !important; align-items:center; justify-content:center;
  width:100%; height:100%; border-radius:9999px;
  font-weight:800; letter-spacing:.2px; color:#0f1e3a;
  border:3px solid #1c2c46;
  background: #ffffff;
  box-shadow: 0 3px 0 #1c2c46, 0 6px 18px rgba(28,44,70,.18), inset 0 2px 0 rgba(255,255,255,.85);
  font-size:inherit;
}

#slot-alreadyamember .amcopy{ font-size: var(--alreadyFs, calc(var(--bodyScale,1) * clamp(12px, 2.8vw, 14px))); }
#slot-forgotpassword     { font-size: var(--forgotFs,  calc(var(--bodyScale,1) * 12px)); }
#slot-realtorapply       { font-size: var(--realtorFs, calc(var(--bodyScale,1) * 12px)); }

#nemiMobile input:focus{ outline:none; border-color:#1c2c46; box-shadow:0 0 0 4px rgba(28,44,70,.12); }

@media (max-width: 600px){
  #nemiMobile{
    --title-nudge-y:   -6px;
    --oneteam-nudge-y: -4px;
    --oneteam-gap:      6px;
  }
  #slot-oneteam .otcopy{ padding-top: 2px; }
  #slot-realtorapply{ white-space: nowrap; }
  #slot-titletextbox,
  #slot-oneteam{
    overflow: visible;
  }
  #slot-alreadyamember{ overflow: visible; }
  #slot-alreadyamember .amcopy{
    display:block;
    width:100%;
    white-space:nowrap;
    text-align:center;
  }

  #slot-appdownload,
  #slot-androiddownload,
  #slot-explorenemibox,
  #slot-realtorapply { overflow: visible; }
}

#slot-emailbox input{
  font-size: var(--emailFs, 16px);
  border-radius: var(--emailRadius, 9999px);
  padding-inline: var(--emailPadX, 12px);
}
#slot-passwordbox input{
  font-size: var(--passwordFs, 16px);
  border-radius: var(--passwordRadius, 9999px);
  padding-inline: var(--passwordPadX, 12px);
}
  </style>

  <script>
(function(){
  const TEXT = {
    title: "Take Control of Your Homebuying Journey",
    sub1:  "One <strong>team</strong>. Every step, <strong>always</strong> clear.",
    sub2:  "Your Realtor, Lender &amp; Attorney in <strong>One</strong> Hub.",
    cta:   "Get Started With Nemi",
    login: "🔒 Login",
    explore: "Explore Nemi Features",
    already: "Already a Nemi Member? Sign in below!",
    applyLead: "Have what it takes to be a Nemi Pro?"
  };

  const LINKS = {
    cta: "/auth/login_form.php",
    explore: "/features",
    appstore: "#",
    play: "#",
    apply: "/pros/apply",
    forgot: "/auth/forgot.php"
  };

  const MOBILE_KNOBS = {
    height: { min: 720, scale: 1.06, max: 1000 },

    spacing: {
      density: 0.88,
      minRowGap: 10,
      globalLiftY: -8
    },

    fonts: {
      titleMin: 16, titleMax: 28,
      subMin:   15, subMax:   18,
      emailFs:  17, passwordFs: 17,
      alreadyFs: null, forgotFs: 12, realtorFs: 12,
      alreadyMin: 11,
      alreadyMax: 14,
    },

    pills: {
      cta: { scale: 0.50, min: 16, max: 28, extraNudgeY: -14 },
      login: { scale: 0.50, min: 16, max: 28, extraNudgeY: 0 },
      explore: { scale: 0.46, min: 16, max: 24, extraNudgeY: 0 }
    },

    afterForgotLift: -106,

    bumps: {
      already:   4,
      email:     12,
      password:  26,
      login:     38,
      forgot:    30,
      appstore:  30,
      play:      30,
      explore:   56,
      realtor:   26
    },

    bumpScaleMin: 0.90,
    bumpScaleMax: 1.35,
    bumpBaseHeight: 820,

    panelGrow: { x: 0, y: 16 },

    slotTweak: {
      titletextbox:       { dy: 1 },
      oneteam:            { dy: -10 },
      getstartedwithnemi: { dx: 0, dy: -10, growX: 8 },
      alreadyamember:     { dy: -45, growX: 9 },
      emailbox:           { dy: -60 },
      passwordbox:        { dy: -80 },
      logintext:          { dy: -90 },
      forgotpassword:     { dy: -100 },
      appdownload:        { dy: -12, growX: 10, growY: 6 },
      androiddownload:    { dy: -12, growX: 10, growY: 6 },
      explorenemibox:     { dy: -18, growX: 8,  growY: 4 },
      realtorapply:       { dy: -14, growY: 2 }
    },

    panelBottomTrim: 28,

    bgImages: {
      nemilogo:  { src: 'assets/img/nemilogo.png',  preserve: 'xMidYMid meet'  },
      finalgoal: { src: 'assets/img/finalgoal.png', preserve: 'xMidYMid meet'  }
    }
  };

  const CFG = {
    svgCandidates: [
      'assets/svgs/signinrenamed','/assets/svgs/signinrenamed','/nemi/assets/svgs/signinrenamed',
      'assets/svgs/signinrenamed.svg','/assets/svgs/signinrenamed.svg','/nemi/assets/svgs/signinrenamed.svg'
    ],
    cardGrowX: 10,

    mobileBgCandidates: [
      'assets/svgs/mobilebackground.svg',
      '/assets/svgs/mobilebackground.svg',
      '/nemi/assets/svgs/mobilebackground.svg'
    ],
    mobileOverlayCandidates: [
      'assets/svgs/loginboxmobile.svg',
      '/assets/svgs/loginboxmobile.svg',
      '/nemi/assets/svgs/loginboxmobile.svg'
    ],

    titleInsetLR: -12,  titleInsetT: 3, titleInsetB: 0,
    titleFontMin: 18,   titleFontMax: 32,
    titleFlex:    { dx: 0, dy: 0,  growX: 0, growY: 0 },

    oneteamPadPx: 0, oneteamBumpUp: 8, oneteamExtraHeight: 16,
    oneteamFontMin: 18, oneteamFontMax: 22,
    oneteamFlex:   { dx: 0, dy: 0,  growX: 0, growY: 0 },

    teamIconFlex:  { dx: 0, dy: 0,  growX: 0, growY: 0 },

    ctaInset: { l:-10, r:-10, t:0, b:0 },
    ctaNudgeY: 4,
    ctaFontMin: 16, ctaFontMax: 28, ctaScale: 0.50,
    ctaFlex:   { dx: 0, dy: -6, growX: 8,  growY: 4 },

    loginFontMin: 16, loginFontMax: 28, loginScale: 0.50,
    loginFlex: { dx: 0, dy: -6, growX: 8, growY: 4 },

    exploreFontMin: 16, exploreFontMax: 24, exploreScale: 0.46,
    exploreFlex: { dx: 0, dy: 0, growX: 0, growY: 0 },

    emailFlex: { dx: 0, dy: 0, growX: 0, growY: 0 },
    passwordFlex: { dx: 0, dy: 0, growX: 0, growY: 0 },
    emailFs: 17,  passwordFs: 17,
    emailRadius: 9999, passwordRadius: 9999,
    emailPadX: 14, passwordPadX: 14,

    alreadyFlex: { dx: 0, dy: 0, growX: 0, growY: 0 }, alreadyFs: null,
    forgotFlex:  { dx: 0, dy: 0, growX: 0, growY: 0 }, forgotFs:  null,
    realtorFlex: { dx: 0, dy: 0, growX: 0, growY: 0 }, realtorFs: null,

    appstoreFlex: { dx: 0, dy: 0, growX: 0, growY: 0 },
    playFlex:     { dx: 0, dy: 0, growX: 0, growY: 0 }
  };

  const MOBILE_TUNING = [
    {
      maxWidth: 340,
      bodyScale: 1.08, titleScale: 1.00,
      titleMax: 24, titleMin: 16,
      subMax: 17,  subMin: 15,
      subTopPad: 2,
      extraCtaNudge: 8,

      alreadyFlex:  { dy:  6 },
      emailFlex:    { dy: 12, growY: 6 },
      passwordFlex: { dy: 24, growY: 6 },
      loginFlex:    { dy: 38 },
      forgotFlex:   { dy: 50 },  forgotFs: 12,
      exploreFlex:  { dy: 62 },
      appstoreFlex: { dy: 70 },
      playFlex:     { dy: 70 },

      realtorFlex:  { dy: 74, growX: 14 }, realtorFs: 12,
      emailFs: 19, passwordFs: 19,

      panelGrowY: -6, panelGrowX: 0,
      panelBottomTrim: 16,
    },
    {
      maxWidth: 380,
      bodyScale: 1.08, titleScale: 1.00,
      titleMax: 26, titleMin: 16,
      subMax: 17,  subMin: 15,
      extraCtaNudge: 6,

      alreadyFlex:  { dy:  4 },
      emailFlex:    { dy: 10, growY: 4 },
      passwordFlex: { dy: 20, growY: 4 },
      loginFlex:    { dy: 32 },
      forgotFlex:   { dy: 40 }, forgotFs: 12,
      exploreFlex:  { dy: 52 },
      appstoreFlex: { dy: 60 },
      playFlex:     { dy: 60 },

      realtorFlex:  { dy: 64, growX: 12 }, realtorFs: 12,
      emailFs: 18, passwordFs: 18,

      panelGrowY: -4, panelGrowX: 0,
      panelBottomTrim: 20,
    }
  ];

  const DESKTOP_TUNING = {
    bodyScale: 1,
    titleScale: 1.15,
    titleMin: undefined,
    titleMax: 28
  };

  function getMobileTuning(vw){
    for (const p of MOBILE_TUNING) if (vw <= p.maxWidth) return p;
    return null;
  }

  document.querySelector('#slot-getstartedwithnemi').textContent = TEXT.cta;
  document.querySelector('#slot-getstartedwithnemi').href = LINKS.cta;
  document.querySelector('#slot-logintext').textContent = TEXT.login;
  document.querySelector('#ot-line1').innerHTML = TEXT.sub1;
  document.querySelector('#ot-line2').innerHTML = TEXT.sub2;
  document.querySelector('#slot-titletextbox .titlecopy').innerHTML = TEXT.title;
  document.querySelector('#slot-alreadyamember .amcopy').textContent = TEXT.already;
  document.querySelector('#slot-explorenemibox').textContent = TEXT.explore;
  document.querySelector('#slot-explorenemibox').href = LINKS.explore;
  document.querySelector('#slot-appdownload').href = LINKS.appstore;
  document.querySelector('#slot-androiddownload').href = LINKS.play;
  document.querySelector('#apply-line').textContent = TEXT.applyLead;
  document.querySelector('#apply-link').href = LINKS.apply;
  document.querySelector('#slot-forgotpassword').href = LINKS.forgot;

  const section = document.getElementById('nemiMobile');
  const host    = document.getElementById('mobileArt');
  const bgFixed = document.getElementById('bgFixed');

  function fetchFirst(urls, done){
    let i = 0;
    (function next(){
      if (i >= urls.length) return done(null);
      fetch(urls[i], { cache:'no-cache' })
        .then(r => { if (!r.ok) throw 0; return r.text(); })
        .then(txt => done(txt))
        .catch(() => { i++; next(); });
    })();
  }

  const mqPhone = '(max-width: 600px)';
  let isPhoneInit = window.matchMedia(mqPhone).matches;

  function patchBgImage(svgRoot, anchorId, src, preserve){
    if (!svgRoot || !src) return;
    const anchor = svgRoot.querySelector('#' + anchorId);
    if (!anchor) return;
    const svgNS = 'http://www.w3.org/2000/svg';
    const xlinkNS = 'http://www.w3.org/1999/xlink';

    function setHref(el, val){
      el.setAttribute('href', val);
      try { el.setAttributeNS(xlinkNS, 'xlink:href', val); } catch(e){}
    }

    if (anchor.tagName.toLowerCase() === 'image'){
      setHref(anchor, src);
      if (preserve) anchor.setAttribute('preserveAspectRatio', preserve);
      return;
    }

    const bb = {
      x: parseFloat(anchor.getAttribute('x') || 0),
      y: parseFloat(anchor.getAttribute('y') || 0),
      width:  parseFloat(anchor.getAttribute('width')  || 0),
      height: parseFloat(anchor.getAttribute('height') || 0)
    };
    if (bb.width && bb.height){
      const img = document.createElementNS(svgNS, 'image');
      img.setAttribute('x', bb.x);
      img.setAttribute('y', bb.y);
      img.setAttribute('width', bb.width);
      img.setAttribute('height', bb.height);
      img.setAttribute('preserveAspectRatio', preserve || 'xMidYMid meet');
      setHref(img, src);
      anchor.setAttribute('fill', 'none');
      anchor.setAttribute('stroke', 'none');
      anchor.parentNode.insertBefore(img, anchor.nextSibling);
    }
  }

  function bootWithSvg(overlayText, bgText){
    if (!overlayText) { section.classList.remove('invisible'); return; }

    host.innerHTML = overlayText.replace(
      '<svg',
      '<svg id="mobileSVG" style="display:block;width:100%;height:100%;pointer-events:none"'
    );
    const svg = document.getElementById('mobileSVG');

    if (bgText) {
      bgFixed.innerHTML = bgText.replace(
        '<svg',
        '<svg id="mobileSVGBg" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;pointer-events:none"'
      );
      const bgSVG = document.getElementById('mobileSVGBg');
      const imgs = MOBILE_KNOBS.bgImages || {};
      if (bgSVG){
        if (imgs.nemilogo?.src)  patchBgImage(bgSVG, 'nemilogo',  imgs.nemilogo.src,  imgs.nemilogo.preserve);
        if (imgs.finalgoal?.src) patchBgImage(bgSVG, 'finalgoal', imgs.finalgoal.src, imgs.finalgoal.preserve);
      }
    } else {
      bgFixed.innerHTML = '';
    }

    const vb = svg.viewBox?.baseVal || null;
    if (vb && vb.width && vb.height) section.style.aspectRatio = vb.width + ' / ' + vb.height;

    function placeInset(rectId, slotId, inset={}){
      const rEl = svg.querySelector('#'+rectId);
      const slot= document.getElementById(slotId);
      if (!rEl || !slot) return;
      const hb = host.getBoundingClientRect(), r = rEl.getBoundingClientRect();
      const l = inset.l||0, t = inset.t||0, rr = inset.r||0, b = inset.b||0;
      slot.style.position='absolute';
      slot.style.display='block';
      slot.style.left   = (r.left - hb.left + l) + 'px';
      slot.style.top    = (r.top  - hb.top  + t) + 'px';
      slot.style.width  = (r.width  - l - rr) + 'px';
      slot.style.height = (r.height - t - b) + 'px';
    }

    function placeFlex(rectId, slotId, baseInset, flex, extraDy){
      const inset = Object.assign({l:0,r:0,t:0,b:0}, baseInset||{});
      const dx = (flex?.dx||0), dy = (flex?.dy||0) + (extraDy||0);
      const gx = (flex?.growX||0), gy = (flex?.growY||0);
      inset.l += dx; inset.r -= dx; inset.t += dy; inset.b -= dy;
      inset.l -= gx; inset.r -= gx; inset.t -= gy; inset.b -= gy;
      placeInset(rectId, slotId, inset);
    }

    function fitVar(slotSel, blockSel, varName, minPx, maxPx){
      const slot  = document.querySelector(slotSel);
      const block = slot ? slot.querySelector(blockSel) : null;
      if (!slot || !block) return minPx;
      let lo=minPx, hi=maxPx, best=minPx;
      for (let i=0;i<12;i++){
        const mid=(lo+hi)/2;
        block.style.setProperty(varName, mid+'px');
        const fits = (block.scrollWidth <= slot.clientWidth + 0.5) &&
                     (block.scrollHeight <= slot.clientHeight + 0.5);
        if (fits) { best=mid; lo=mid; } else { hi=mid; }
      }
      block.style.setProperty(varName, Math.floor(best)+'px');
      return best;
    }

    function fitOneLine(slotSel, blockSel, varName, minPx, maxPx){
      const slot  = document.querySelector(slotSel);
      const block = slot ? slot.querySelector(blockSel) : null;
      if (!slot || !block) return minPx;
      const isiOS = /iP(hone|ad|od)/.test(navigator.platform) ||
                    (navigator.userAgent.includes('Mac') && 'ontouchend' in document);
      const SAFETY = isiOS ? 32 : 18;
      let lo=minPx, hi=maxPx, best=minPx;
      for (let i=0;i<12;i++){
        const mid=(lo+hi)/2;
        block.style.setProperty(varName, mid+'px');
        const fitsW = block.scrollWidth <= (slot.clientWidth - SAFETY);
        const fitsH = block.getBoundingClientRect().height <= slot.clientHeight;
        if (fitsW && fitsH){ best=mid; lo=mid; } else { hi=mid; }
      }
      block.style.setProperty(varName, Math.max(minPx, Math.floor(best)-1) + 'px');
      return best;
    }

    function scalePill(slotId, spec){
      const el = document.getElementById(slotId);
      if (!el) return;
      const h  = el.clientHeight || 0;
      const fs = Math.max(spec.min, Math.min(spec.max, Math.floor(h * spec.scale)));
      el.style.fontSize   = fs + 'px';
      el.style.lineHeight = '1';
      el.style.whiteSpace = 'nowrap';
    }

    function layout(){
      const vw = section.clientWidth;
      const preset = getMobileTuning(vw);
      const small  = !!preset;
      const isPhone = window.matchMedia('(max-width: 600px)').matches;

      let mobileHeight;
      if (isPhone) {
        const vh = Math.max(window.innerHeight || 0, document.documentElement.clientHeight || 0);
        const H  = MOBILE_KNOBS.height || {};
        mobileHeight = Math.max(H.min || 720, Math.min(Math.round(vh * (H.scale || 1.06)), H.max || 1000));
        section.style.aspectRatio = '';
        section.style.height = mobileHeight + 'px';

        bgFixed.style.display = bgFixed.innerHTML ? 'block' : 'none';
        host.style.opacity = '0';
        host.style.pointerEvents = 'none';
      } else {
        section.style.height = '';
        bgFixed.style.display = 'none';
        section.style.aspectRatio = '9 / 16';
        const vb = document.getElementById('mobileSVG')?.viewBox?.baseVal || null;
        if (vb && vb.width && vb.height) section.style.aspectRatio = vb.width + ' / ' + vb.height;
        host.style.opacity = '1';
        host.style.pointerEvents = '';
      }

      const bumpScale = isPhone
        ? Math.max(MOBILE_KNOBS.bumpScaleMin, Math.min(MOBILE_KNOBS.bumpScaleMax, (mobileHeight || 820) / (MOBILE_KNOBS.bumpBaseHeight || 820)))
        : 1;

      const LIFT = isPhone ? (MOBILE_KNOBS.afterForgotLift || 0) : 0;
      const DENSITY = MOBILE_KNOBS.spacing?.density ?? 1;
      const MIN_GAP = MOBILE_KNOBS.spacing?.minRowGap ?? 8;

      const bump = n => {
        if (!isPhone) return 0;
        const v = Math.round(n * bumpScale * DENSITY);
        return Math.max(v, MIN_GAP);
      };

      const F = MOBILE_KNOBS.fonts || {};
      const bodyScale  = preset ? (preset.bodyScale ?? 1)  : (DESKTOP_TUNING.bodyScale ?? 1);
      const titleScale = preset ? (preset.titleScale ?? 1) : (DESKTOP_TUNING.titleScale ?? 1);

      const titleMinBase = isPhone
        ? (F.titleMin ?? preset?.titleMin ?? CFG.titleFontMin)
        : (DESKTOP_TUNING.titleMin ?? CFG.titleFontMin);
      const titleMaxBase = isPhone
        ? (F.titleMax ?? preset?.titleMax ?? CFG.titleFontMax)
        : (DESKTOP_TUNING.titleMax ?? CFG.titleFontMax);
      const subMinBase = isPhone
        ? (F.subMin ?? preset?.subMin ?? CFG.oneteamFontMin)
        : CFG.oneteamFontMin;
      const subMaxBase = isPhone
        ? (F.subMax ?? preset?.subMax ?? CFG.oneteamFontMax)
        : CFG.oneteamFontMax;

      const titleMin = Math.round(titleMinBase * titleScale);
      const titleMax = Math.round(titleMaxBase * titleScale);
      const subMin   = Math.round(subMinBase   * bodyScale);
      const subMax   = Math.round(subMaxBase   * bodyScale);

      section.style.setProperty('--bodyScale', bodyScale);
      if (preset?.oneteamGap != null) section.style.setProperty('--oneteam-gap', preset.oneteamGap + 'px');

      const pg = isPhone ? (MOBILE_KNOBS.panelGrow || {x:0,y:0}) : {x:0,y:0};
      const growX = (preset?.panelGrowX || 0) + (pg.x||0);
      const growY = (preset?.panelGrowY || 0) + (pg.y||0);
      const trimBottom = isPhone ? ((MOBILE_KNOBS.panelBottomTrim || 0) + (preset?.panelBottomTrim || 0)) : 0;

      placeInset('loginbox','slot-loginboxbg', {
        l: 3 - growX,
        r: 3 - growX,
        t: isPhone ? 3 : (3 - growY),
        b: isPhone ? (3 + trimBottom) : (3 - growY)
      });

      (function syncRadius(){
        const rEl = svg.querySelector('#loginbox'); if (!rEl) return;
        const rx = parseFloat(rEl.getAttribute('rx') || 0);
        const ry = parseFloat(rEl.getAttribute('ry') || rx || 0);
        const bg = document.getElementById('slot-loginboxbg');
        if (bg && (rx || ry)) bg.style.borderRadius = Math.max(rx, ry) + 'px';
      })();

      function flexPlus(base, slotKey, apply) {
        const merged = Object.assign({}, base);
        if (!apply) return merged;
        const t = (MOBILE_KNOBS.slotTweak || {})[slotKey] || {};
        if (t.dx != null)    merged.dx    = (merged.dx    || 0) + t.dx;
        if (t.dy != null)    merged.dy    = (merged.dy    || 0) + t.dy;
        if (t.growX != null) merged.growX = (merged.growX || 0) + t.growX;
        if (t.growY != null) merged.growY = (merged.growY || 0) + t.growY;
        return merged;
      }

      placeFlex('teamiconbox','slot-teamiconbox', {l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.teamIconFlex, preset?.teamIconFlex || {}), 'teamiconbox', isPhone), 0);

      const titleInsetLR = small ? Math.min(-6, CFG.titleInsetLR) : CFG.titleInsetLR;
      const baseTitle = { l:titleInsetLR, r:titleInsetLR, t:CFG.titleInsetT, b:CFG.titleInsetB };
      placeFlex('titletextbox','slot-titletextbox', baseTitle,
        flexPlus(Object.assign({}, CFG.titleFlex, preset?.titleFlex || {}), 'titletextbox', isPhone), 0);

      const baseSub = {
        l:CFG.oneteamPadPx|0,
        r:CFG.oneteamPadPx|0,
        t:(CFG.oneteamPadPx|0)-(CFG.oneteamBumpUp|0),
        b:(CFG.oneteamPadPx|0)+(CFG.oneteamBumpUp|0)-(CFG.oneteamExtraHeight|0)
      };
      placeFlex('oneteam','slot-oneteam', baseSub,
        flexPlus(Object.assign({}, CFG.oneteamFlex, preset?.oneteamFlex || {}), 'oneteam', isPhone), 0);
      document.querySelector('#slot-oneteam .otcopy').style.paddingTop = (preset?.subTopPad || 0) + 'px';

      fitVar('#slot-oneteam', '.otcopy', '--fsSub', subMin, subMax);
      fitOneLine('#slot-titletextbox', '.titlecopy', '--fsTitle', titleMin, titleMax);

      const pills = MOBILE_KNOBS.pills || {};
      const ctaSpec = { scale: pills.cta?.scale ?? CFG.ctaScale, min: pills.cta?.min ?? CFG.ctaFontMin, max: pills.cta?.max ?? CFG.ctaFontMax };
      const ctaNudge = CFG.ctaNudgeY
        + (preset?.extraCtaNudge || 0)
        + (isPhone ? (pills.cta?.extraNudgeY || 0) : 0);
      const baseCTA = { l: CFG.ctaInset.l - CFG.cardGrowX, r: CFG.ctaInset.r - CFG.cardGrowX, t: CFG.ctaInset.t, b: CFG.ctaInset.b };
      placeFlex('getstartedwithnemi', 'slot-getstartedwithnemi', baseCTA,
        flexPlus(Object.assign({}, CFG.ctaFlex, preset?.ctaFlex || {}), 'getstartedwithnemi', isPhone), ctaNudge);
      scalePill('slot-getstartedwithnemi', ctaSpec);

      placeFlex('alreadyamember','slot-alreadyamember',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.alreadyFlex, preset?.alreadyFlex || {}), 'alreadyamember', isPhone),
        bump(MOBILE_KNOBS.bumps.already||6));
      const alreadyEl = document.querySelector('#slot-alreadyamember .amcopy');
      const af = (MOBILE_KNOBS.fonts.alreadyFs ?? CFG.alreadyFs);
      if (af) alreadyEl.style.setProperty('--alreadyFs', af + 'px');

      placeFlex('emailbox','slot-emailbox',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.emailFlex, preset?.emailFlex || {}), 'emailbox', isPhone),
        bump(MOBILE_KNOBS.bumps.email||16));
      placeFlex('passwordbox','slot-passwordbox',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.passwordFlex, preset?.passwordFlex || {}), 'passwordbox', isPhone),
        bump(MOBILE_KNOBS.bumps.password||32));
      const emailBox = document.getElementById('slot-emailbox');
      const passBox  = document.getElementById('slot-passwordbox');
      emailBox.style.setProperty('--emailFs', (MOBILE_KNOBS.fonts.emailFs ?? preset?.emailFs ?? CFG.emailFs) + 'px');
      emailBox.style.setProperty('--emailRadius', (CFG.emailRadius) + 'px');
      emailBox.style.setProperty('--emailPadX',   (CFG.emailPadX) + 'px');
      passBox.style.setProperty('--passwordFs', (MOBILE_KNOBS.fonts.passwordFs ?? preset?.passwordFs ?? CFG.passwordFs) + 'px');
      passBox.style.setProperty('--passwordRadius', (CFG.passwordRadius) + 'px');
      passBox.style.setProperty('--passwordPadX',   (CFG.passwordPadX) + 'px');

      const loginSpec = { scale: pills.login?.scale ?? CFG.loginScale, min: pills.login?.min ?? CFG.loginFontMin, max: pills.login?.max ?? CFG.loginFontMax };
      placeFlex('logintext','slot-logintext',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.loginFlex, preset?.loginFlex || {}), 'logintext', isPhone),
        bump(MOBILE_KNOBS.bumps.login||46) + (isPhone ? (pills.login?.extraNudgeY||0) : 0));
      scalePill('slot-logintext', loginSpec);

      placeFlex('forgotpassword','slot-forgotpassword',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.forgotFlex, preset?.forgotFlex || {}), 'forgotpassword', isPhone),
        bump(MOBILE_KNOBS.bumps.forgot||60));
      const ff = (MOBILE_KNOBS.fonts.forgotFs ?? CFG.forgotFs);
      if (ff) document.getElementById('slot-forgotpassword').style.setProperty('--forgotFs', ff + 'px');

      placeFlex('appdownload','slot-appdownload',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.appstoreFlex, preset?.appstoreFlex || {}), 'appdownload', isPhone),
        bump(MOBILE_KNOBS.bumps.appstore||72) + LIFT);
      placeFlex('androiddownload','slot-androiddownload',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.playFlex, preset?.playFlex || {}), 'androiddownload', isPhone),
        bump(MOBILE_KNOBS.bumps.play||72) + LIFT);

      const exploreSpec = { scale: pills.explore?.scale ?? CFG.exploreScale, min: pills.explore?.min ?? CFG.exploreFontMin, max: pills.explore?.max ?? CFG.exploreFontMax };
      placeFlex('explorenemibox','slot-explorenemibox',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.exploreFlex, preset?.exploreFlex || {}), 'explorenemibox', isPhone),
        bump(MOBILE_KNOBS.bumps.explore||82) + (isPhone ? (pills.explore?.extraNudgeY||0) : 0) + LIFT);
      scalePill('slot-explorenemibox', exploreSpec);

      placeFlex('realtorapply','slot-realtorapply',{l:0,r:0,t:0,b:0},
        flexPlus(Object.assign({}, CFG.realtorFlex, preset?.realtorFlex || {}), 'realtorapply', isPhone),
        bump(MOBILE_KNOBS.bumps.realtor||92) + LIFT);
      const rf = (MOBILE_KNOBS.fonts.realtorFs ?? CFG.realtorFs);
      if (rf) document.getElementById('slot-realtorapply').style.setProperty('--realtorFs', rf + 'px');

      section.classList.remove('invisible');
    }

    (document.fonts && document.fonts.ready?.then) ? document.fonts.ready.then(layout) : layout();
    window.addEventListener('resize', layout, { passive:true });
    window.addEventListener('orientationchange', layout, { passive:true });
    window.addEventListener('pageshow', layout, { passive:true });
    new ResizeObserver(() => layout()).observe(host);
    requestAnimationFrame(() => setTimeout(layout, 60));
  }

  if (isPhoneInit) {
    fetchFirst(CFG.mobileOverlayCandidates, function(overlayText){
      fetchFirst(CFG.mobileBgCandidates, function(bgText){
        if (!overlayText) {
          fetchFirst(CFG.svgCandidates, function(svgText){ bootWithSvg(svgText, null); });
        } else {
          bootWithSvg(overlayText, bgText);
        }
      });
    });
  } else {
    fetchFirst(CFG.svgCandidates, function(svgText){ bootWithSvg(svgText, null); });
  }
})();
  </script>

  <script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('nemiLoginForm');
  if (!form) return;

  const pinInput = form.querySelector('input[name="pin"]');
  if (!pinInput) return;

  pinInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
  });

  form.addEventListener('submit', function (e) {
    pinInput.value = pinInput.value.replace(/\D/g, '').slice(0, 4);
  });
});
</script>

</body>
</html>