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
  --ink:#0d1330;
  --muted:#647481;
  --edge:#d9e2e8;
  --trim:#8ec3c7;
  --accent:#d4af37;
  --pill:#eef7f8;
  --bg:#f7fbfd;
  --card:#fff;
}

*{
  box-sizing:border-box;
}

body{
  margin:0;
  font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
  color:var(--ink);
  background:var(--bg);
}

header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  padding:18px 28px;
  background:#fff;
  border-bottom:1px solid var(--edge);
}

.brand{
  display:flex;
  align-items:center;
  gap:16px;
}

.logo{
  width:170px;
  height:40px;
  background:#001a3a;
  border-radius:8px;
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:800;
}

.account-name{
  font-weight:700;
}

.main-nav{
  display:flex;
  gap:8px;
  padding:10px 28px;
  background:#fff;
  border-bottom:1px solid var(--edge);
  overflow-x:auto;
}

.main-nav a{
  color:var(--ink);
  text-decoration:none;
  font-weight:700;
  padding:9px 14px;
  border-radius:999px;
  white-space:nowrap;
}

.main-nav a:hover{
  background:var(--pill);
}

.main-nav a.active{
  background:var(--ink);
  color:#fff;
}

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
  font-size:30px;
}

.page-heading p{
  margin:0;
  color:var(--muted);
  font-size:18px;
}

.search-box{
  position:relative;
  margin-bottom:38px;
}

.search-box input{
  width:100%;
  border:1px solid var(--edge);
  border-radius:16px;
  padding:18px 20px 18px 52px;
  font-family:inherit;
  font-size:16px;
  background:#fff;
  outline:none;
}

.search-box input:focus{
  border-color:#6ca1a7;
  box-shadow:0 0 0 3px rgba(108,161,167,.12);
}

.search-icon{
  position:absolute;
  top:50%;
  left:20px;
  transform:translateY(-50%);
  font-size:20px;
}

.form-section{
  margin:34px 0;
}

.section-heading{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:16px;
  margin-bottom:14px;
}

.section-heading h2{
  margin:0;
  font-size:18px;
}

.see-all{
  border:0;
  background:transparent;
  color:var(--ink);
  font-family:inherit;
  font-weight:700;
  cursor:pointer;
  padding:8px;
}

.form-list{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:12px;
}

.form-card{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  min-height:72px;
  padding:16px 18px;
  background:#fff;
  border:1px solid var(--edge);
  border-radius:14px;
  text-decoration:none;
  color:inherit;
}

.form-card:hover{
  border-color:#9db7bb;
  background:#fbfefe;
}

.form-name{
  font-weight:700;
}

.form-arrow{
  font-size:20px;
}

.hidden-form{
  display:none;
}

.empty-search{
  display:none;
  color:var(--muted);
  padding:24px 0;
}

@media(max-width:720px){
  header{
    padding:16px 18px;
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
    font-size:26px;
  }

  .page-heading p{
    font-size:17px;
  }
}
</style>
</head>

<body>

<header>
  <div class="brand">
    <div class="logo">Fercodini</div>
  </div>

  <div class="account-name">
    <?= h($meName) ?>
  </div>
</header>

<nav class="main-nav">
  <a href="/app/realtor_portal.php">Home</a>
  <a href="/app/transactions.php">Transactions</a>
  <a href="/app/clients.php">Clients</a>
  <a class="active" href="/app/forms.php">Forms</a>
  <a href="/app/messages.php">Messages</a>
</nav>

<main class="wrap">

  <div class="page-heading">
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