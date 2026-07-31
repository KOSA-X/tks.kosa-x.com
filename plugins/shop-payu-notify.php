<?php
// Powiadomienie z PayU (notifyUrl)

chdir(__DIR__ . '/..');

require_once 'database/config.php';
require_once 'core/libraries/sql.php';
require_once 'plugins/settings.php';

// Odpowiemy zwykłym tekstem
header('Content-Type: text/plain; charset=utf-8');

// 1) Odczyt body + nagłówka z podpisem
$body            = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_OPENPAYU_SIGNATURE'] ?? '';

if (!$body) {
    http_response_code(400);
    echo 'EMPTY_BODY';
    exit;
}

// 2) Weryfikacja podpisu od PayU
if (empty($signatureHeader) || !payuVerifySignature($signatureHeader, $body, $config['payu_second_key'])) {
    http_response_code(403);
    echo 'INVALID_SIGNATURE';
    exit;
}

// 3) Dekodowanie JSON-a z PayU
$data = json_decode($body, true);
if (!is_array($data) || empty($data['order'])) {
    http_response_code(400);
    echo 'INVALID_PAYLOAD';
    exit;
}

$orderData   = $data['order'];
$extOrderRaw = $orderData['extOrderId'] ?? '';
$matches     = [];
if (is_string($extOrderRaw) && preg_match('/^(\d+)/', $extOrderRaw, $matches)) {
    $extOrderId = (int)$matches[1];
} else {
    $extOrderId = 0;
}
$payuOrderId = $orderData['orderId'] ?? '';
$payuStatus  = $orderData['status'] ?? ''; // np. COMPLETED, CANCELED itd.

if ($extOrderId <= 0) {
    http_response_code(400);
    echo 'MISSING_EXT_ORDER_ID';
    exit;
}

// 4) Pobranie zamówienia z bazy
$oSql = Sql::getInstance();
$stmt = $oSql->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $extOrderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo 'ORDER_NOT_FOUND';
    exit;
}

// 5) Ustalamy nowy status lokalny
// Założenie: 0 = nowe / 1 zrealizowane / 2 anulowane / 3 = opłacone
$newStatus = (int)$order['status'];
if ($payuStatus === 'COMPLETED' && $newStatus !== 3) {
    $newStatus = 3;
}

// 6) Aktualizacja rekordu w bazie
$updateValues = ['status' => $newStatus];
if (function_exists('ordersHasColumn') && ordersHasColumn('payu_status')) {
    $updateValues['payu_status'] = $payuStatus;
}
if (function_exists('ordersHasColumn') && ordersHasColumn('payu_order_id')) {
    $updateValues['payu_order_id'] = $payuOrderId;
}

$setParts = [];
$params   = [':id' => $extOrderId];
foreach ($updateValues as $column => $value) {
    $setParts[]          = $column.' = :'.$column;
    $params[':'.$column] = $value;
}

$sqlUpdate  = 'UPDATE orders SET '.implode(', ', $setParts).' WHERE id = :id';
$stmtUpdate = $oSql->prepare($sqlUpdate);
$stmtUpdate->execute($params);

// Link do szczegółów zamówienia (token jak w page-order.php)
$tokenSecret = $config['orderKey'] ?? ($config['secretKey'] ?? (defined('BASE_URL') ? BASE_URL : ''));
$orderToken  = hash('sha256', $extOrderId.'|'.$order['date'].'|'.$tokenSecret);

$paymentPageUrl = function_exists('getUrl') ? getUrl($config['payment_page']) : (defined('BASE_URL') ? BASE_URL : '');
$statusUrl      = rtrim($paymentPageUrl ?? '', '/').'?order='.$extOrderId.'&token='.$orderToken;

$statusUrlSafe  = htmlspecialchars($statusUrl, ENT_QUOTES, 'UTF-8');
$brand          = htmlspecialchars(($config['logo'] ?? 'Sklep'), ENT_QUOTES, 'UTF-8');

// 7) Jeśli płatność właśnie została COMPLETED i wcześniej nie była opłacona – wyślij mail potwierdzający
if ($payuStatus === 'COMPLETED' && (int)$order['status'] !== 3) {

    $subject = 'Płatność przyjęta - zamówienie '.$extOrderId;

    $message = '<!doctype html>
<html lang="pl"><head><meta charset="utf-8"></head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family:Arial, Helvetica, sans-serif;">
  <div style="padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
      <tr><td align="center">
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="border-collapse:collapse; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,0.06);">
          <tr>
            <td style="padding:18px 20px; background:#111; color:#fff;">
              <div style="font-size:18px; font-weight:700;">'.$brand.'</div>
              <div style="font-size:13px; opacity:0.9; margin-top:4px;">Status płatności PayU</div>
            </td>
          </tr>
          <tr>
            <td style="padding:20px;">
              <div style="font-size:18px; font-weight:700; color:#111; margin:0 0 8px;">Płatność przyjęta ✅</div>
              <p style="margin:0 0 10px; color:#111; line-height:1.6;">
                Twoja płatność za zamówienie <strong>#'.$extOrderId.'</strong> została zaksięgowana.
              </p>
              <p style="margin:0 0 16px; color:#111;">
                Kwota: <strong>'.number_format((float)$order['price'], 2, ',', ' ').' zł</strong>
              </p>

              <a href="'.$statusUrlSafe.'" style="display:inline-block; padding:12px 16px; background:#1a73e8; color:#fff; text-decoration:none; border-radius:10px; font-weight:700; font-size:14px;">
                Zobacz szczegóły zamówienia
              </a>

              <div style="font-size:12px; color:#777; margin-top:8px; word-break:break-all;">
                '.$statusUrlSafe.'
              </div>

              <hr style="border:none; border-top:1px solid #eee; margin:18px 0;">
              <div style="font-size:12px; color:#777; line-height:1.5;">
                W razie pytań odpowiedz na tę wiadomość.
              </div>
            </td>
          </tr>
        </table>
      </td></tr>
    </table>
  </div>
</body></html>';

    // mail do klienta
    sendEmail([
        'to'        => $order['email'],
        'to_name'   => $order['name'],
        'subject'   => $subject,
        'body'      => $message,
        'alert'     => false,
        'from'      => $config['email'],
        'from_name' => $config['logo'] ?? 'Sklep',
        'reply_to'  => $config['email'],
        'reply_to_name' => $config['logo'] ?? 'Sklep',
        'smtp_host'   => $config['smtp_host']   ?? null,
        'smtp_user'   => $config['smtp_user']   ?? null,
        'smtp_pass'   => $config['smtp_pass']   ?? null,
        'smtp_port'   => $config['smtp_port']   ?? null,
        'smtp_secure' => $config['smtp_secure'] ?? null,
    ]);

    // mail do admina
    $adminSubject = 'PayU: płatność zakończona - zamówienie '.$extOrderId;
    $adminMessage = '
    <html><body>
      <p>Płatność w PayU została zakończona statusem <strong>'.htmlspecialchars($payuStatus, ENT_QUOTES, 'UTF-8').'</strong>.</p>
      <p>Zamówienie: #'.$extOrderId.'<br>
      Klient: '.htmlspecialchars($order['name'], ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($order['email'], ENT_QUOTES, 'UTF-8').')</p>
      <p>Kwota: <strong>'.number_format((float)$order['price'], 2, ',', ' ').' zł</strong></p>
      <p><a href="'.$statusUrlSafe.'">Szczegóły zamówienia</a></p>
    </body></html>';

    sendEmail([
        'to'        => $config['email'],
        'to_name'   => $config['logo'] ?? 'Sklep',
        'subject'   => $adminSubject,
        'body'      => $adminMessage,
        'alert'     => false,
        'from'      => $config['email'],
        'from_name' => $config['logo'] ?? 'Sklep',
        'reply_to'  => $config['email'],
        'reply_to_name' => $config['logo'] ?? 'Sklep',
        'smtp_host'   => $config['smtp_host']   ?? null,
        'smtp_user'   => $config['smtp_user']   ?? null,
        'smtp_pass'   => $config['smtp_pass']   ?? null,
        'smtp_port'   => $config['smtp_port']   ?? null,
        'smtp_secure' => $config['smtp_secure'] ?? null,
    ]);
}

// 8) PayU wymaga, żeby odpowiedzieć 200 i coś w body
echo 'OK';
