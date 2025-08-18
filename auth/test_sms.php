<?php
// /var/www/nemi/auth/test_sms.php
require __DIR__ . '/../vendor/autoload.php';

$cfg = require __DIR__ . '/../config/.env.php';

$to   = '+1YOURCELLPHONE';   // <-- replace with your mobile in E.164 format
$from = $cfg['TWILIO_FROM']; // must be a valid Twilio number on your account
$code = '123456';            // sample code

try {
  if (empty($cfg['TWILIO_SID']) || empty($cfg['TWILIO_TOKEN']) || empty($from)) {
    throw new Exception('Missing Twilio config. Fill TWILIO_SID/TOKEN/FROM in config/.env.php');
  }

  $twilio = new Twilio\Rest\Client($cfg['TWILIO_SID'], $cfg['TWILIO_TOKEN']);
  $msg = $twilio->messages->create($to, [
    'from' => $from,
    'body' => "Nemi test SMS: your code is $code"
  ]);

  echo "✅ SMS sent. SID: " . $msg->sid . PHP_EOL;
} catch (Throwable $e) {
  echo "❌ SMS failed: " . $e->getMessage() . PHP_EOL;
}
