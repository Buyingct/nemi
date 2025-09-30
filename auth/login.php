$_SESSION['uid']   = $uid;
$_SESSION['email'] = $email;
$_SESSION['role']  = $role;

// ---------- If this user has assigned cases, send them straight to first case ----------
$assignPath = __DIR__ . '/../data/cases/user_cases.json';
if (is_file($assignPath)) {
    $caseIndex = json_decode(file_get_contents($assignPath), true) ?: [];
    $userCases = $caseIndex[$uid] ?? [];
    if (!empty($userCases)) {
        // redirect to the first assigned case timeline
        header('Location: /app/client/timeline.php?case=' . urlencode($userCases[0]));
        exit;
    }
}


switch ($role) {
  case 'admin':
    header('Location: /admin/users.php'); 
    break;

  case 'realtor':
    header('Location: /app/realtor_portal.php'); 
    break;

  case 'buyer':
  case 'seller':
    header('Location: /app/timeline.php'); 
    break;

  case 'lender':
    header('Location: /tools/dashboard/lender.html'); 
    break;

  case 'attorney':
    header('Location: /tools/dashboard/attorney.html'); 
    break;

  default:
    header('Location: /tools/dashboard/index.html'); 
    break;
}
exit; // <-- ensure no more output after redirect
