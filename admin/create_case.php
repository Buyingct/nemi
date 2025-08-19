<?php
// Admin: Create Buyer case and link roles by email
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
    $title = trim($_POST['title'] ?? 'Buyer Timeline');
    $clientEmail   = strtolower(trim($_POST['client']   ?? ''));
    $realtorEmail  = strtolower(trim($_POST['realtor']  ?? ''));
    $lenderEmail   = strtolower(trim($_POST['lender']   ?? ''));
    $attorneyEmail = strtolower(trim($_POST['attorney'] ?? ''));
    $sendInvites   = !empty($_POST['send_invites']);

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

      [$clientId,  $clientPwd,  $newClient]  = $resolve($clientEmail,  'client');
      [$realtorId, $realtorPwd, $newRealtor] = $realtorEmail  ? $resolve($realtorEmail, 'realtor')   : [null,null,false];
      [$lenderId,  $lenderPwd,  $newLender]  = $lenderEmail   ? $resolve($lenderEmail,  'lender')    : [null,null,false];
      [$attyId,    $attyPwd,    $newAtty]    = $attorneyEmail ? $resolve($attorneyEmail,'attorney')  : [null,null,false];

      jwrite($USERS,$users); jwrite($INDEX,$index);

      // create case
      @mkdir($CASES_DIR,0770,true);
      $caseId = 'case_buyer_'.date('Ymd_His');
      $caseFile = $CASES_DIR . "/{$caseId}.json";

      $case = [
        'id'=>$caseId,
        'type'=>'buyer',
        'title'=>$title ?: 'Buyer Timeline',
        'assets'=>[
          'svg'=>'assets/svgs/timeline_buyer.svg',
          'map'=>'data/timeline_map_buyer.json'
        ],
        'team'=>[
          'client'=>[$clientId],
          'realtor'=>$realtorId?[$realtorId]:[],
          'lender'=>$lenderId?[$lenderId]:[],
          'attorney'=>$attyId?[$attyId]:[]
        ],
        'timeline'=>[
          'states'=>[
            'preapproval'=>['done'=>false,'title'=>'Get Pre‑Approved','meta'=>'Lender • 1–3 days'],
            'home_search'=>['done'=>false,'title'=>'Home Search','meta'=>'Realtor • ongoing'],
            'offer'      =>['done'=>false,'title'=>'Make an Offer','meta'=>'Realtor • 1 day'],
            'inspection' =>['done'=>false,'title'=>'Inspection','meta'=>'3–5 days'],
            'appraisal'  =>['done'=>false,'title'=>'Appraisal','meta'=>'Lender • 1–2 weeks'],
            'ctc'        =>['done'=>false,'title'=>'Clear to Close','meta'=>'Title/Lender']
          ],
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
        'type'=>'buyer',
        'client_id'=>$clientId,
        'roles'=>[
          'realtor'=>$realtorId,
          'lender'=>$lenderId,
          'attorney'=>$attyId
        ],
        'created'=>date('c'),
        'status'=>'active'
      ];
      jwrite($CASE_INDEX,$caseIndex);

      // Send invites (optional)
      if ($sendInvites) {
        $app = rtrim($cfg['APP_URL'] ?? '', '/');
        $send = function($to,$pwd=null) use ($app){
          if (!$to) return;
          $html = "<p>Your Nemi account was set up.</p>"
                . "<p><b>Email:</b> {$to}" . ($pwd ? "<br><b>Temp password:</b> {$pwd}" : "") . "</p>"
                . "<p>Sign in: <a href=\"{$app}/auth/login.html\">{$app}/auth/login.html</a></p>";
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
<title>Nemi Admin — Create Buyer Case</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-lg bg-white rounded-xl shadow p-6">
    <h1 class="text-xl font-bold">Create Buyer Case</h1>
    <?php if ($msg): ?><div class="mt-3 rounded border p-3 bg-amber-50 border-amber-300 text-amber-900"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <form class="mt-4 space-y-3" method="post">
      <label class="block">
        <span class="text-sm font-medium">Admin key</span>
        <input class="mt-1 w-full rounded border-slate-300" name="key" type="password" required>
      </label>
      <label class="block">
        <span class="text-sm font-medium">Case title</span>
        <input class="mt-1 w-full rounded border-slate-300" name="title" placeholder="Buyer – 123 Main St">
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
