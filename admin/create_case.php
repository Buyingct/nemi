<?php
// Admin: Create case (buyer or seller) and link roles by email
declare(strict_types=1);
session_start();

$cfg = include __DIR__ . '/../config/.env.php';
require_once __DIR__ . '/../auth/send_helpers.php'; // for send_email()

function jread($p){ return file_exists($p)?(json_decode(file_get_contents($p),true)?:[]):[]; }
function jwrite($p,$a){ @mkdir(dirname($p),0770,true); $ok=file_put_contents($p,json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chown($p,'www-data'); @chgrp($p,'www-data'); @chmod($p,0660); return (bool)$ok; }
function uid(){ return 'u_'.bin2hex(random_bytes(6)); }
function randpass($n=12){ $s='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%'; $o=''; for($i=0;$i<$n;$i++) $o.=$s[random_int(0,strlen($s)-1)]; return $o; }

$DATA = __DIR__ . '/../data';
$USERS = $DATA . '/users.json';
$INDEX = $DATA . '/user_index.json';
$CASES_DIR = $DATA . '/cases';
$USER_CASES = $CASES_DIR . '/user_cases.json';
$CASE_INDEX = $CASES_DIR . '/case_index.json';

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (($cfg['ADMIN_KEY'] ?? '') === '' || ($_POST['key'] ?? '') !== $cfg['ADMIN_KEY']) {
    $msg = 'Forbidden: bad admin key.';
  } else {
    $title         = trim($_POST['title'] ?? 'Buyer Timeline');
    $clientEmail   = strtolower(trim($_POST['client']   ?? ''));
    $realtorEmail  = strtolower(trim($_POST['realtor']  ?? ''));
    $lenderEmail   = strtolower(trim($_POST['lender']   ?? ''));
    $attorneyEmail = strtolower(trim($_POST['attorney'] ?? ''));
    $sendInvites   = !empty($_POST['send_invites']);

    $type = strtolower(trim($_POST['type'] ?? 'buyer'));
    if (!in_array($type, ['buyer','seller'], true)) $type = 'buyer';

    if (!$clientEmail || !filter_var($clientEmail,FILTER_VALIDATE_EMAIL)) {
      $msg = 'Client email is required and must be valid.';
    } else {
      $users = jread($USERS);
      $index = jread($INDEX);

      // helper to get-or-create user by email
      $resolve = function(string $email, string $defaultRole) use (&$users,&$index){
        $key = 'email:'.$email;
        if (!isset($index[$key])) {
          $id = uid();
          $pwd = randpass();
          $users[$id] = [
            'email'=>$email,
            'password_hash'=>password_hash($pwd,PASSWORD_DEFAULT),
            'role'=>$defaultRole
          ];
          $index[$key] = $id;
          return [$id, $pwd, true];
        }
        return [$index[$key], null, false];
      };

      $primaryRole = ($type === 'seller') ? 'seller' : 'buyer';
      [$clientId,  $clientPwd,  $newClient]  = $resolve($clientEmail, $primaryRole);
      [$realtorId, $realtorPwd, $newRealtor] = $realtorEmail  ? $resolve($realtorEmail, 'realtor')   : [null,null,false];
      [$lenderId,  $lenderPwd,  $newLender]  = $lenderEmail   ? $resolve($lenderEmail,  'lender')    : [null,null,false];
      [$attyId,    $attyPwd,    $newAtty]    = $attorneyEmail ? $resolve($attorneyEmail,'attorney')  : [null,null,false];

      jwrite($USERS,$users); jwrite($INDEX,$index);

      // create case
      @mkdir($CASES_DIR,0770,true);
      $caseId = 'case_' . $type . '_' . date('Ymd_His');
      $caseFile = $CASES_DIR . "/{$caseId}.json";

      // Choose assets with a safe fallback to buyer visuals
      $svgBuyer  = 'assets/svgs/timeline_buyer.svg';
      $mapBuyer  = 'data/timeline_map_buyer.json';
      $svgSeller = 'assets/svgs/timeline_seller.svg';
      $mapSeller = 'data/timeline_map_seller.json';

      $root = __DIR__ . '/../';
      if ($type === 'seller'
          && is_file($root . $svgSeller)
          && is_file($root . $mapSeller)) {
        $assets = ['svg' => $svgSeller, 'map' => $mapSeller];
      } else {
        $assets = ['svg' => $svgBuyer,  'map' => $mapBuyer];
      }

      // Team: keep 'client' as generic primary; mirror into 'seller' for seller cases
      $team = [
        'client'   => [$clientId],
        'seller'   => ($type === 'seller') ? [$clientId] : [],
        'realtor'  => $realtorId ? [$realtorId] : [],
        'lender'   => $lenderId  ? [$lenderId]  : [],
        'attorney' => $attyId    ? [$attyId]    : []
      ];

      $case = [
        'id'    => $caseId,
        'type'  => $type,
        'title' => $title ?: ($type === 'seller' ? 'Seller Timeline' : 'Buyer Timeline'),
        'assets'=> $assets,
        'team'  => $team,
        'timeline'=>[
          'states'=> ($type === 'seller'
            ? [
                // stub seller flow — customize later
                'listing'      => ['done'=>false,'title'=>'List the Home','meta'=>'Realtor • 1–3 days'],
                'showings'     => ['done'=>false,'title'=>'Showings','meta'=>'Realtor • ongoing'],
                'offer'        => ['done'=>false,'title'=>'Offer Accepted','meta'=>'Realtor • 1 day'],
                'inspection'   => ['done'=>false,'title'=>'Inspection','meta'=>'3–5 days'],
                'title_search' => ['done'=>false,'title'=>'Title Search','meta'=>'Attorney • 1–2 weeks'],
                'closing'      => ['done'=>false,'title'=>'Closing','meta'=>'Title/Attorney']
              ]
            : [
                // buyer flow
                'preapproval'=>['done'=>false,'title'=>'Get Pre-Approved','meta'=>'Lender • 1–3 days'],
                'home_search'=>['done'=>false,'title'=>'Home Search','meta'=>'Realtor • ongoing'],
                'offer'      =>['done'=>false,'title'=>'Make an Offer','meta'=>'Realtor • 1 day'],
                'inspection' =>['done'=>false,'title'=>'Inspection','meta'=>'3–5 days'],
                'appraisal'  =>['done'=>false,'title'=>'Appraisal','meta'=>'Lender • 1–2 weeks'],
                'ctc'        =>['done'=>false,'title'=>'Clear to Close','meta'=>'Title/Lender']
              ]),
          'notes'=>[
            'why-credit'=>['title'=>'Why credit matters','body'=>'Better credit can lower your rate.']
          ]
        ]
      ];
      jwrite($caseFile,$case);

      // indexes
      $userCases = jread($USER_CASES);
      $userCases[$clientId]   = array_values(array_unique(array_merge($userCases[$clientId]??[],   [$caseId])));
      if ($realtorId) $userCases[$realtorId] = array_values(array_unique(array_merge($userCases[$realtorId]??[], [$caseId])));
      if ($lenderId)  $userCases[$lenderId]  = array_values(array_unique(array_merge($userCases[$lenderId]??[],  [$caseId])));
      if ($attyId)    $userCases[$attyId]    = array_values(array_unique(array_merge($userCases[$attyId]??[],    [$caseId])));
      jwrite($USER_CASES,$userCases);

      $caseIndex = jread($CASE_INDEX);
      $caseIndex[$caseId] = [
        'type'      => $type,
        'client_id' => $clientId, // primary party (buyer OR seller)
        'roles'     => [
          'realtor'  => $realtorId,
          'lender'   => $lenderId,
          'attorney' => $attyId
        ],
        'created'   => date('c'),
        'status'    => 'active'
      ];
      jwrite($CASE_INDEX,$caseIndex);

      // Send invites (optional)
      if ($sendInvites) {
        $app = rtrim($cfg['APP_URL'] ?? '', '/');
        $send = function($to,$pwd=null) use ($app){
          if (!$to) return;
          $html = "<p>Your Nemi account was set up.</p>"
                . "<p><b>Email:</b> {$to}" . ($pwd ? "<br><b>Temp password:</b> {$pwd}" : "") . "</p>"
                . "<p>Sign in: <a href=\"{$app}/auth/login_form.php\">{$app}/auth/login_form.php</a></p>";
          @send_email($to, 'Welcome to Nemi', $html);
        };
        $send($clientEmail,  $clientPwd);
        $send($realtorEmail, $realtorPwd);
        $send($lenderEmail,  $lenderPwd);
        $send($attorneyEmail,$attyPwd);
      }

      $msg = "Case created: {$caseId}";
    }
  }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nemi Admin — Create Case</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-lg bg-white rounded-xl shadow p-6">
    <h1 class="text-xl font-bold">Create Case</h1>
    <?php if ($msg): ?><div class="mt-3 rounded border p-3 bg-amber-50 border-amber-300 text-amber-900"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <form class="mt-4 space-y-3" method="post">
      <label class="block">
        <span class="text-sm font-medium">Admin key</span>
        <input class="mt-1 w-full rounded border-slate-300" name="key" type="password" required>
      </label>
      <label class="block">
        <span class="text-sm font-medium">Case title</span>
        <input class="mt-1 w-full rounded border-slate-300" name="title" placeholder="123 Main St — Buyer or Seller">
      </label>
      <label class="block">
        <span class="text-sm font-medium">Case type</span>
        <select class="mt-1 w-full rounded border-slate-300" name="type">
          <option value="buyer" selected>Buyer</option>
          <option value="seller">Seller</option>
        </select>
      </label>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-sm font-medium">Client email *</span>
          <input class="mt-1 w-full rounded border-slate-300" name="client" type="email" required>
        </label>
        <label class="block">
          <span class="text-sm font-medium">Realtor email</span>
          <input class="mt-1 w-full rounded border-slate-300" name="realtor" type="email">
        </label>
        <label class="block">
          <span class="text-sm font-medium">Lender email</span>
          <input class="mt-1 w-full rounded border-slate-300" name="lender" type="email">
        </label>
        <label class="block">
          <span class="text-sm font-medium">Attorney email</span>
          <input class="mt-1 w-full rounded border-slate-300" name="attorney" type="email">
        </label>
      </div>

      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="send_invites" value="1" checked>
        <span>Send invites with temp passwords</span>
      </label>

      <button class="w-full mt-2 rounded-lg bg-indigo-600 text-white font-semibold py-2">Create Case</button>
    </form>
  </div>
</body>
</html>
