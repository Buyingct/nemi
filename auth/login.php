 switch ($role) {
  
  case 'admin':
    header('Location: /admin/users.php'); break;
  case 'realtor':
    header('Location: /app/realtor_portal.php'); break;
  case 'buyer':
  
    case 'seller':
    header('Location: /app/timeline.php'); break;
  case 'lender':
    header('Location: /tools/dashboard/lender.html'); break;
  case 'attorney':
    header('Location: /tools/dashboard/attorney.html'); break;
  default:
    header('Location: /tools/dashboard/index.html'); break;
}

