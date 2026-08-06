<?php
if (!defined('ADMIN_PAGE')) {
    exit;
}

$p = $_GET['p'] ?? '';

// komunikat o JS – tylko gdy to nie ajax
if (strpos($p, 'ajax-') === false) {
    ?>
    <div id="javascript" class="top-alert">
        <?php echo ($config['admin_lang'] === 'pl' ? 'Włącz obsługę JavaScript w przeglądarce.' : 'Enable JavaScript in your web browser'); ?>
    </div>
    <script>
        document.getElementById('javascript').style.display = 'none';
    </script>
    <?php
}

$adminDropdown = '
    <div class="dropdown dropdown-right">
        <button class="button button-light button-sm dropdown__button">
            <img src="images/icons/user.svg" alt="" class="m-0">
        </button>
        <ul class="dropdown__list">
            <li><a href="?p=logout">' . $lang['Log_out'] . '</a></li>
        </ul>
    </div>
';

$menu = [
    [
        'id'    => 'home',
        'label' => 'Pulpit',
        'icon'  => 'home-line.svg',
    ],
    [
        'id'    => 'pages',
        'label' => $lang['Pages'],
        'icon'  => 'page.svg',
        'submenu' => [
            ['id' => 'pages',      'label' => 'Lista stron'],
            ['id' => 'pages-form', 'label' => 'Nowa strona'],
        ],
    ],
    [
        'id'    => 'teams',
        'label' => 'Drużyny',
        'icon'  => 'users.svg',
        'submenu' => [
            ['id' => 'teams',             'label' => 'Lista drużyn'],
            ['id' => 'teams&sOption=new', 'label' => 'Nowa drużyna'], // wyjątek: dodatkowy parametr
        ],
    ],
    [
        'id'    => 'sliders',
        'label' => $lang['Sliders'],
        'icon'  => 'slider.svg',
        'submenu' => [
            ['id' => 'sliders',      'label' => 'Lista sliderów'],
            ['id' => 'sliders-form', 'label' => 'Nowy slider'],
        ],
    ],
    [
        'id'    => 'form',
        'label' => 'Rezerwacje',
        'icon'  => 'form.svg',
    ],
    [
        'id'    => 'users',
        'label' => 'Użytkownicy',
        'icon'  => 'users.svg',
    ],
    [
        'id'    => 'squad-import',
        'label' => 'Transmisja',
        'icon'  => 'play.svg',
        'submenu' => [
            ['id' => 'squad-import', 'label' => 'Import składu'],
            ['id' => 'live-config',  'label' => 'Konfiguracja'],
        ],
    ],
    [
        'id'    => 'settings',
        'label' => $lang['Settings'],
        'icon'  => 'users.svg',
    ],
];

function admin_menu_url(string $id): string
{
    // id może zawierać dodatkowe parametry typu "pages-form&shop=1"
    return '?p=' . $id;
}

function admin_is_selected(string $selectedMenu, string $id): bool
{
    // wybór menu jest sterowany przez $sSelectedMenu (tak jak miałeś)
    return $selectedMenu === $id;
}
?>

<header class="mainHeader">
    <div class="mainHeader__column">
        <div class="mainHeader__head">
            <div class="mainHeader__menu_button">
                <div class="menuHamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>

            <div class="mainHeader__logo">
                <div class="logo_text">
                    <a class="value" href="./adm.php">Panel Admina</a>
                </div>
            </div>

            <div class="mainHeader__dropdown">
                <?php echo $adminDropdown; ?>
            </div>
        </div>

        <div class="mainHeader__menu">
            <nav id="menu-1" class="menu-1 headerMenu" aria-label="menu-1">
                <ul class="headerMenu__list">
                    <?php
                    $submenuIndex = 1;

                    foreach ($menu as $item) {
                        $id    = (string)($item['id'] ?? '');
                        $label = (string)($item['label'] ?? '');
                        $icon  = (string)($item['icon'] ?? '');
                        $sub   = $item['submenu'] ?? null;

                        if ($id === '' || $label === '') {
                            continue;
                        }

                        $hasSub = is_array($sub) && !empty($sub);

                        $liClass = 'menu_item';
                        if ($hasSub) {
                            $liClass .= ' menu_item_submenu';
                        }
                        if (!empty($sSelectedMenu) && admin_is_selected($sSelectedMenu, $id)) {
                            $liClass .= ' selected';
                        }

                        $url = admin_menu_url($id);
                        $iconSrc = 'templates/admin/img/icons/' . $icon;

                        echo '<li class="' . $liClass . '" id="menu_item_' . $submenuIndex . '">';
                        echo    '<a href="' . $url . '" class="menu_link">';
                        echo        '<img src="' . $iconSrc . '" class="menu_icon" alt="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
                        echo        htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                        echo    '</a>';

                        if ($hasSub) {
                            $submenuId = $submenuIndex;
                            echo '<span class="menu_arrow invert" data-menu="' . $submenuId . '"></span>';
                            echo '<div class="submenu" id="submenu_' . $submenuId . '"><ul class="submenu_list level-1-menu">';

                            foreach ($sub as $subItem) {
                                $sid    = (string)($subItem['id'] ?? '');
                                $slabel = (string)($subItem['label'] ?? '');

                                if ($sid === '' || $slabel === '') {
                                    continue;
                                }

                                echo '<li class="submenu_item">';
                                echo    '<a href="' . admin_menu_url($sid) . '" class="submenu_link" title="' . htmlspecialchars($slabel, ENT_QUOTES, 'UTF-8') . '">';
                                echo        htmlspecialchars($slabel, ENT_QUOTES, 'UTF-8');
                                echo    '</a>';
                                echo '</li>';
                            }

                            echo '</ul></div>';
                        }

                        echo '</li>';

                        $submenuIndex++;
                    }
                    ?>
                </ul>
            </nav>

            <div class="mainHeader__menu_help">
                <p class="strong mb-1"><strong><a href="https://kosa-x.com" target="_blank">KOSA X</a></strong></p>
                <p><a href="mailto:konrad@kosiorski.pl">konrad@kosiorski.pl</a></p>
            </div>
        </div>
    </div>
</header>

<main class="mainBody" id="smooth-scroll">
    <div class="mainAdmin">
        <div class="container">
            <div class="mainAdmin__wrapper">
                <div class="mainAdmin__logo"><a href="./"><?php echo $config['logo']; ?></a></div>
                <div class="mainAdmin__options">
                    <span class="mainAdmin__options_email"><?php echo $config['email']; ?></span>
                    <?php echo $adminDropdown; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
