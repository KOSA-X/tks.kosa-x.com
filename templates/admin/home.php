<?php
if (!defined('ADMIN_PAGE')) {
    exit;
}

if (!isset($config['url_domain'])) {
    getSiteUrl();
}

$sSelectedMenu = 'home';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

/**
 * Helper do renderowania kart z tabelą
 */
function renderDashboardTableCard(array $opts): void
{
    $title      = $opts['title'] ?? '';
    $icon       = $opts['icon'] ?? '';
    $btnHref    = $opts['btnHref'] ?? '';
    $btnLabel   = $opts['btnLabel'] ?? '';
    $theadHtml  = $opts['theadHtml'] ?? '';
    $rowsHtml   = $opts['rowsHtml'] ?? '';
    $emptyText  = $opts['emptyText'] ?? 'Brak danych.';

    ?>
    <div class="card mb-4">
        <header class="card__header">
            <h5 class="card__title">
                <img src="<?php echo html($icon); ?>" alt="<?php echo html($title); ?>">
                <?php echo html($title); ?>
            </h5>

            <?php if (!empty($btnHref) && !empty($btnLabel)) { ?>
                <a href="<?php echo html($btnHref); ?>" class="button button-light button-sm">
                    <?php echo html($btnLabel); ?>
                </a>
            <?php } ?>
        </header>

        <?php if (empty($rowsHtml)) { ?>
            <div class="card__content">
                <?php echo html($emptyText); ?>
            </div>
        <?php } else { ?>
            <div class="card__wrapper table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <?php echo $theadHtml; ?>
                    </thead>
                    <tbody>
                        <?php echo $rowsHtml; ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
    <?php
}

/**
 * --------------------------------------------------
 * SKLEP: ostatnie zamówienia
 * --------------------------------------------------
 */
$shopRows = '';

$oQuery = $oSql->getQuery('
    SELECT id, name, date, price, status
    FROM orders
    ORDER BY status ASC, date DESC
    LIMIT 5
');

while ($order = $oQuery->fetch(PDO::FETCH_ASSOC)) {
    $orderId = (int)($order['id'] ?? 0);

    $createdAt = !empty($order['date'])
        ? date('d.m.Y H:i', (int)$order['date'])
        : '';

    $price = isset($order['price'])
        ? number_format((float)$order['price'], 2, ',', ' ') . ' ' . ($config['currency'] ?? 'zł')
        : '—';

    $statusValue = (int)($order['status'] ?? 0);
    $statusLabel = getElement($statusValue, $config['order_status']);

    $shopRows .= '
        <tr>
            <td class="name"><a href="adm.php?p=orders-info&id=' . $orderId . '">' . html($order['name'] ?? ('#' . $orderId)) . '</a></td>
            <td>' . html($createdAt) . '</td>
            <td>' . html($price) . '</td>
            <td><span class="badge badge-' . $statusValue . '">' . html($statusLabel) . '</span></td>
        </tr>
    ';
}

/**
 * --------------------------------------------------
 * REZERWACJE: ostatnie wpisy z form
 * --------------------------------------------------
 */
$formRows = '';

$oQuery = $oSql->getQuery('
    SELECT iForm, iDate, sUserName, sCategory, iStatus
    FROM form
    ORDER BY iStatus ASC, iForm DESC
    LIMIT 5
');

while ($row = $oQuery->fetch(PDO::FETCH_ASSOC)) {
    $formId = (int)($row['iForm'] ?? 0);

    $createdAt = !empty($row['iDate'])
        ? date('d.m.Y H:i', (int)$row['iDate'])
        : '';

    $statusValue = (int)($row['iStatus'] ?? 0);
    $statusLabel = getElement($statusValue, $config['order_status']);

    $categoryLabel = getElement($row['sCategory'] ?? '', $config['form_categories']);

    $formRows .= '
        <tr>
            <td class="name"><a href="adm.php?p=form-info&id=' . $formId . '">' . html($row['sUserName'] ?? ('#' . $formId)) . '</a></td>
            <td>' . html($createdAt) . '</td>
            <td>' . html($categoryLabel) . '</td>
            <td><span class="badge badge-' . $statusValue . '">' . html($statusLabel) . '</span></td>
        </tr>
    ';
}

/**
 * --------------------------------------------------
 * UŻYTKOWNICY: ostatni użytkownicy
 * --------------------------------------------------
 */
$userRows = '';

$oQuery = $oSql->getQuery('
    SELECT id, name, created_at, is_active
    FROM users
    ORDER BY id DESC
    LIMIT 5
');

while ($row = $oQuery->fetch(PDO::FETCH_ASSOC)) {
    $userId = (int)($row['id'] ?? 0);
    $createdAt = $row['created_at'] ?? '';

    $statusValue = (int)($row['is_active'] ?? 0);
    $statusLabel = getElement($statusValue, $config['user_status']);

    $userRows .= '
        <tr>
            <td class="name"><a href="adm.php?p=users-info&id=' . $userId . '">' . html($row['name'] ?? ('#' . $userId)) . '</a></td>
            <td>' . html($createdAt) . '</td>
            <td><span class="badge badge-' . $statusValue . '">' . html($statusLabel) . '</span></td>
        </tr>
    ';
}
?>

<header class="mainPage__header mainPage__header_row">
    <h1 class="mainPage__title"><?php echo $lang['Dashboard']; ?></h1>
</header>

<div class="row">

    <div class="col-6">
        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title">
                    <img src="templates/admin/img/icons/info.svg" alt="Pomoc">Pomoc
                </h4>
                <a href="https://kosa-x.com" target="_blank" class="button button-light button-sm">kosa-x.com</a>
            </header>
            <div class="card__content">
                <p>Jeżeli masz jakieś pytania i potrzebujesz pomocy skontaktuj się:</p>
                <a href="mailto:konrad@kosiorski.pl">konrad@kosiorski.pl</a>
            </div>
        </div>
    </div>

    <div class="col-6">
        <div class="card mb-4">
            <header class="card__header">
                <h4 class="card__title">
                    <img src="templates/admin/img/icons/page.svg" alt="Strony">Strony
                </h4>
                <a href="?p=pages-form" class="button button-light button-sm">Nowa strona</a>
            </header>
            <div class="card__wrapper">
                <ul class="card__list">
                    <li><a href="?p=pages" class="submenu_link">Lista stron</a></li>
                    <li><a href="?p=pages-form" class="submenu_link">Nowa strona</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-6">
        <?php
        renderDashboardTableCard([
            'title'     => 'Sklep',
            'icon'      => ICONS . 'cart.svg',
            'btnHref'   => '?p=pages-form&shop=1',
            'btnLabel'  => 'Nowy produkt',
            'rowsHtml'  => $shopRows,
            'emptyText' => 'Brak zamówień w sklepie.',
            'theadHtml' => '
                <tr>
                    <th>Zamówienie</th>
                    <th>Data</th>
                    <th>Kwota</th>
                    <th>Status</th>
                </tr>
            ',
        ]);
        ?>
    </div>

    <div class="col-6">
        <?php
        renderDashboardTableCard([
            'title'     => 'Rezerwacje',
            'icon'      => ICONS . 'calendar.svg',
            'btnHref'   => '?p=form',
            'btnLabel'  => 'Więcej',
            'rowsHtml'  => $formRows,
            'emptyText' => 'Brak rezerwacji.',
            'theadHtml' => '
                <tr>
                    <th>Rezerwacja</th>
                    <th>Data</th>
                    <th>Usługa</th>
                    <th>Status</th>
                </tr>
            ',
        ]);
        ?>
    </div>

    <div class="col-6">
        <?php
        renderDashboardTableCard([
            'title'     => 'Użytkownicy',
            'icon'      => ICONS . 'user.svg',
            'btnHref'   => '?p=users',
            'btnLabel'  => 'Więcej',
            'rowsHtml'  => $userRows,
            'emptyText' => 'Brak użytkowników.',
            'theadHtml' => '
                <tr>
                    <th>Nazwa</th>
                    <th>Data dołączenia</th>
                    <th>Status</th>
                </tr>
            ',
        ]);
        ?>
    </div>

</div>

<?php
require_once 'templates/admin/_footer.php';
?>
