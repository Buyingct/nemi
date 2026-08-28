<?php
declare(strict_types=1);
session_start();



if (empty($_SESSION['uid'])) {
    header('Location: /auth/login_form.php');
    exit;
}

$uid     = (string)($_SESSION['uid'] ?? '');
$meEmail = (string)($_SESSION['email'] ?? '');
$role    = strtolower((string)($_SESSION['role'] ?? ''));
$meName  = (string)($_SESSION['name'] ?? '');

if ($role !== 'realtor' && $role !== 'admin') {
    header('Location: /app/timeline.php');
    exit;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if ($meName === '') {
    $meName = $meEmail !== ''
        ? preg_replace('/@.*$/', '', $meEmail)
        : 'Realtor';
}

/*
 * Temporary V0.1 form data.
 * Later this moves into the database / Form Registry.
 */
$forms = [
    [
        'id' => 'purchase_agreement',
        'name' => 'Purchase Agreement',
        'category' => 'buyer',
        'common' => true,
        'office' => false,
    ],
    [
        'id' => 'buyer_representation',
        'name' => 'Buyer Representation Agreement',
        'category' => 'buyer',
        'common' => true,
        'office' => false,
    ],
    [
        'id' => 'listing_agreement',
        'name' => 'Listing Agreement',
        'category' => 'seller',
        'common' => true,
        'office' => false,
    ],
    [
        'id' => 'property_disclosure',
        'name' => 'Property Condition Disclosure',
        'category' => 'seller',
        'common' => true,
        'office' => false,
    ],
    [
        'id' => 'fercodini_transaction_sheet',
        'name' => 'Transaction Information Sheet',
        'category' => 'office',
        'common' => false,
        'office' => true,
    ],
    [
        'id' => 'fercodini_client_information',
        'name' => 'Client Information Form',
        'category' => 'office',
        'common' => false,
        'office' => true,
    ],
];

$commonForms = array_values(array_filter(
    $forms,
    fn($f) => !empty($f['common'])
));

$officeForms = array_values(array_filter(
    $forms,
    fn($f) => !empty($f['office'])
));

$sellerForms = array_values(array_filter(
    $forms,
    fn($f) => ($f['category'] ?? '') === 'seller'
));

$buyerForms = array_values(array_filter(
    $forms,
    fn($f) => ($f['category'] ?? '') === 'buyer'
));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forms — Nemi</title>

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">


<style>
:root{
  --ink:#172033;
  --muted:#746c66;

  --bg-1:#f7efe6;
  --bg-2:#f1e1d2;

  --glass:rgba(255,255,255,.56);
  --glass-strong:rgba(255,255,255,.78);
  --glass-edge:rgba(255,255,255,.72);

  --gold:#d49a36;
  --terracotta:#c96f52;
  --teal:#5b9090;
  --navy:#1e2d49;

  --shadow-soft:0 14px 38px rgba(75,52,35,.10);
  --shadow-hover:0 20px 45px rgba(75,52,35,.16);
}

*{
  box-sizing:border-box;
}

html{
  min-height:100%;
}

body{
  margin:0;
  min-height:100vh;
  font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
  color:var(--ink);

  background:
    radial-gradient(
      circle at 12% 8%,
      rgba(212,154,54,.22),
      transparent 28%
    ),
    radial-gradient(
      circle at 88% 15%,
      rgba(201,111,82,.18),
      transparent 31%
    ),
    radial-gradient(
      circle at 72% 88%,
      rgba(91,144,144,.15),
      transparent 34%
    ),
    linear-gradient(
      135deg,
      var(--bg-1),
      var(--bg-2)
    );

  background-attachment:fixed;
}

/* =========================
   HEADER
========================= */

header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;

  padding:18px 28px;

  background:rgba(255,255,255,.56);

  backdrop-filter:blur(22px) saturate(145%);
  -webkit-backdrop-filter:blur(22px) saturate(145%);

  border-bottom:1px solid rgba(255,255,255,.72);

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.85),
    0 8px 30px rgba(80,54,37,.06);
}

.brand{
  display:flex;
  align-items:center;
  gap:16px;
}

.logo{
  width:170px;
  height:40px;

  display:flex;
  align-items:center;
  justify-content:center;

  color:#fff;
  font-weight:800;
  letter-spacing:.02em;

  border-radius:13px;

  background:
    linear-gradient(
      135deg,
      #1f3152,
      #17233b
    );

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.18),
    0 8px 20px rgba(30,45,73,.20);
}

.account-name{
  font-weight:700;
  color:var(--navy);
}

/* =========================
   NAVIGATION
========================= */

.main-nav{
  display:flex;
  gap:8px;

  padding:10px 28px;

  overflow-x:auto;

  background:rgba(255,255,255,.40);

  backdrop-filter:blur(20px) saturate(140%);
  -webkit-backdrop-filter:blur(20px) saturate(140%);

  border-bottom:1px solid rgba(255,255,255,.58);
}

.main-nav a{
  color:var(--ink);
  text-decoration:none;

  font-weight:700;

  padding:9px 15px;

  border-radius:999px;

  white-space:nowrap;

  transition:
    background .18s ease,
    transform .18s ease,
    box-shadow .18s ease;
}

.main-nav a:hover{
  background:rgba(255,255,255,.62);
  transform:translateY(-1px);
}

.main-nav a.active{
  color:#fff;

  background:
    linear-gradient(
      135deg,
      var(--gold),
      var(--terracotta)
    );

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.28),
    0 8px 20px rgba(201,111,82,.25);
}

/* =========================
   PAGE
========================= */

.wrap{
  max-width:1040px;
  margin:0 auto;
  padding:42px 22px 70px;
}

.page-heading{
  margin-bottom:28px;
}

.page-heading h1{
  margin:0 0 5px;

  font-size:32px;
  font-weight:800;
  letter-spacing:-.035em;

  color:var(--navy);
}

.page-heading p{
  margin:0;

  color:var(--muted);

  font-size:18px;
  font-weight:500;
}

/* =========================
   SEARCH
========================= */

.search-box{
  position:relative;
  margin-bottom:40px;
}

.search-box input{
  width:100%;

  border:1px solid var(--glass-edge);

  border-radius:22px;

  padding:19px 22px 19px 54px;

  font-family:inherit;
  font-size:16px;

  color:var(--ink);

  background:rgba(255,255,255,.62);

  backdrop-filter:blur(22px) saturate(150%);
  -webkit-backdrop-filter:blur(22px) saturate(150%);

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.88),
    inset 0 -1px 0 rgba(255,255,255,.28),
    var(--shadow-soft);

  outline:none;

  transition:
    box-shadow .18s ease,
    border-color .18s ease,
    background .18s ease;
}

.search-box input::placeholder{
  color:#8b8078;
}

.search-box input:focus{
  border-color:rgba(212,154,54,.55);

  background:rgba(255,255,255,.76);

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.95),
    0 0 0 4px rgba(212,154,54,.12),
    0 18px 40px rgba(80,54,37,.12);
}

.search-icon{
  position:absolute;

  top:50%;
  left:21px;

  transform:translateY(-50%);

  font-size:20px;

  color:var(--navy);

  z-index:2;
}

/* =========================
   FORM SECTIONS
========================= */

.form-section{
  position:relative;
  margin:36px 0;
}

.section-heading{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:16px;

  margin-bottom:15px;
}

.section-heading h2{
  margin:0;

  font-size:19px;
  font-weight:800;

  letter-spacing:-.025em;
}

.form-section[data-section="common"] .section-heading h2{
  color:#a56f17;
}

.form-section[data-section="office"] .section-heading h2{
  color:#ae543a;
}

.form-section[data-section="seller"] .section-heading h2{
  color:#417b7c;
}

.form-section[data-section="buyer"] .section-heading h2{
  color:var(--navy);
}

.see-all{
  border:1px solid rgba(255,255,255,.62);

  background:rgba(255,255,255,.34);

  backdrop-filter:blur(16px);
  -webkit-backdrop-filter:blur(16px);

  color:var(--ink);

  font-family:inherit;
  font-weight:700;

  cursor:pointer;

  padding:8px 13px;

  border-radius:999px;

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.65);

  transition:
    background .18s ease,
    transform .18s ease;
}

.see-all:hover{
  background:rgba(255,255,255,.62);
  transform:translateY(-1px);
}

/* =========================
   FORM CARDS
========================= */

.form-list{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}

.form-card{
  position:relative;

  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;

  min-height:78px;

  padding:17px 19px;

  text-decoration:none;

  color:inherit;

  border:1px solid rgba(255,255,255,.76);

  border-radius:21px;

  background:rgba(255,255,255,.54);

  backdrop-filter:blur(20px) saturate(145%);
  -webkit-backdrop-filter:blur(20px) saturate(145%);

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.92),
    inset 0 -1px 0 rgba(255,255,255,.22),
    0 12px 28px rgba(77,52,36,.08);

  overflow:hidden;

  transition:
    transform .18s ease,
    box-shadow .18s ease,
    border-color .18s ease,
    background .18s ease;
}

.form-card::before{
  content:"";

  position:absolute;

  inset:0;

  pointer-events:none;

  background:
    linear-gradient(
      135deg,
      rgba(255,255,255,.28),
      transparent 35%,
      transparent 70%,
      rgba(255,255,255,.12)
    );

  opacity:.75;
}

.form-card:hover{
  transform:translateY(-3px);

  background:rgba(255,255,255,.76);

  border-color:rgba(255,255,255,.95);

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,1),
    0 20px 42px rgba(77,52,36,.14);
}

.form-name{
  position:relative;
  z-index:1;

  font-weight:750;
  line-height:1.3;
}

.form-arrow{
  position:relative;
  z-index:1;

  display:flex;
  align-items:center;
  justify-content:center;

  width:32px;
  height:32px;

  flex:0 0 32px;

  border-radius:50%;

  font-size:21px;
  font-weight:700;

  color:var(--navy);

  background:rgba(255,255,255,.54);

  border:1px solid rgba(255,255,255,.75);

  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.85);
}

.form-card:hover .form-arrow{
  background:
    linear-gradient(
      135deg,
      var(--gold),
      var(--terracotta)
    );

  color:#fff;

  border-color:transparent;
}

/* =========================
   SEARCH / HIDDEN STATES
========================= */

.hidden-form{
  display:none;
}

.empty-search{
  display:none;

  margin-top:20px;

  color:var(--muted);

  padding:20px 22px;

  border:1px solid rgba(255,255,255,.7);

  border-radius:20px;

  background:rgba(255,255,255,.46);

  backdrop-filter:blur(18px);
  -webkit-backdrop-filter:blur(18px);

  box-shadow:var(--shadow-soft);
}

/* =========================
   MOBILE
========================= */

@media(max-width:720px){

  header{
    padding:15px 17px;
  }

  .logo{
    width:145px;
    height:38px;
  }

  .account-name{
    display:none;
  }

  .main-nav{
    padding:9px 14px;
  }

  .wrap{
    padding:30px 16px 60px;
  }

  .form-list{
    grid-template-columns:1fr;
  }

  .page-heading h1{
    font-size:28px;
  }

  .page-heading p{
    font-size:17px;
  }

  .search-box{
    margin-bottom:32px;
  }

  .search-box input{
    padding-top:17px;
    padding-bottom:17px;
  }

  .form-card{
    min-height:74px;
  }
}
</style>

<link rel="stylesheet" href="/css/nemi-shell.css">

</head>

<body class="theme-fercodini-teal">

<header class="nemi-header">

  <div class="nemi-brand">
    <div class="nemi-logo">Fercodini</div>
  </div>

  <div class="nemi-account-name">
    <?= h($meName) ?>
  </div>

</header>

<nav class="nemi-nav">
  <a href="/app/realtor_portal.php">Home</a>
  <a href="/app/transactions.php">Transactions</a>
  <a href="/app/clients.php">Clients</a>
  <a class="active" href="/app/forms.php">Forms</a>
  <a href="/app/messages.php">Messages</a>
</nav>

<main class="nemi-wrap">

  <div class="nemi-page-heading">
    <h1>Forms</h1>
    <p>What shall we work on?</p>
  </div>

  <div class="search-box">
    <span class="search-icon">⌕</span>
    <input
      id="formSearch"
      type="search"
      placeholder="Search forms by name, number, or keyword"
      autocomplete="off"
    >
  </div>

  <?php
  function renderFormSection(
      string $title,
      string $sectionId,
      array $items
  ): void {
      ?>
      <section class="form-section" data-section="<?= h($sectionId) ?>">

        <div class="section-heading">
          <h2><?= h($title) ?></h2>

          <?php if (count($items) > 4): ?>
            <button
              type="button"
              class="see-all"
              data-target="<?= h($sectionId) ?>"
            >
              See all ↓
            </button>
          <?php endif; ?>
        </div>

        <div class="form-list">
          <?php foreach ($items as $i => $form): ?>
            <a
              class="form-card <?= $i >= 4 ? 'hidden-form' : '' ?>"
              data-form-name="<?= h(strtolower($form['name'])) ?>"
              href="/app/form_prepare.php?form=<?= h($form['id']) ?>"
            >
              <span class="form-name">
                <?= h($form['name']) ?>
              </span>

              <span class="form-arrow">›</span>
            </a>
          <?php endforeach; ?>
        </div>

      </section>
      <?php
  }

  renderFormSection(
      'Most Commonly Used',
      'common',
      $commonForms
  );

  renderFormSection(
      'Fercodini Forms',
      'office',
      $officeForms
  );

  renderFormSection(
      'Seller Forms',
      'seller',
      $sellerForms
  );

  renderFormSection(
      'Buyer Forms',
      'buyer',
      $buyerForms
  );
  ?>

  <div id="emptySearch" class="empty-search">
    I couldn't find a matching form. Try another word or form number.
  </div>

</main>

<script>
const searchInput = document.getElementById('formSearch');
const formCards = [...document.querySelectorAll('.form-card')];
const sections = [...document.querySelectorAll('.form-section')];
const emptySearch = document.getElementById('emptySearch');

searchInput.addEventListener('input', () => {
  const query = searchInput.value.trim().toLowerCase();
  let matches = 0;

  formCards.forEach(card => {
    const name = card.dataset.formName || '';

    if (!query) {
      card.style.display = '';
      return;
    }

    const isMatch = name.includes(query);
    card.style.display = isMatch ? 'flex' : 'none';

    if (isMatch) {
      matches++;
    }
  });

  sections.forEach(section => {
    if (!query) {
      section.style.display = '';
      return;
    }

    const visible = [...section.querySelectorAll('.form-card')]
      .some(card => card.style.display !== 'none');

    section.style.display = visible ? '' : 'none';
  });

  if (!query) {
    document.querySelectorAll('.hidden-form').forEach(card => {
      card.style.display = '';
    });

    emptySearch.style.display = 'none';
  } else {
    emptySearch.style.display = matches === 0 ? 'block' : 'none';
  }
});

document.querySelectorAll('.see-all').forEach(button => {
  button.addEventListener('click', () => {
    const section = button.closest('.form-section');
    const hiddenForms = section.querySelectorAll('.hidden-form');

    const expanded = button.dataset.expanded === 'true';

    hiddenForms.forEach(card => {
      card.style.display = expanded ? 'none' : 'flex';
    });

    button.dataset.expanded = expanded ? 'false' : 'true';
    button.textContent = expanded ? 'See all ↓' : 'Show less ↑';
  });
});
</script>

</body>
</html>