<?php
if (!defined('CUSTOMER_PAGE')) { exit; }

/*
 * TRANSMISJA LIVE — nakładka OBS (Browser Source, projekt 1920x1080).
 * Strona STANDALONE: bez _header/_footer i CSS motywu — własny lekki arkusz
 * css/live-overlay.css (źródło: _source/live-overlay.scss) + vanilla JS
 * js/page-live-overlay.js (bez jQuery — minimum wagi, maksimum płynności).
 *
 * Dane: stan meczu i eventy z plugins/live/api.php (GET state ?since=ID
 * co 1 s — jedno żądanie); treści plansz renderowane serwerowo z bazy:
 * mecz = $config['match_page'], drużyny = live_state, składy = podstrony
 * drużyn (sNumber/sSquad z importera protokołu), sztab = blok .teamStaff
 * z opisu drużyny, sponsorzy/realizacja = $config['live_*_page'].
 */

$sRoot  = $config['base_path_with_slash'];
$sFiles = $sRoot.'files/500/';

// ------------------------------------------------------------
// DANE
// ------------------------------------------------------------
$aLive  = $oSql->throwAll('SELECT * FROM live_state WHERE id = 1') ?: Array();
$iTeam1 = (int) ($aLive['iTeam1'] ?? 0);
$iTeam2 = (int) ($aLive['iTeam2'] ?? 0);
$iMatch = (int) ($config['match_page'] ?? 0);

$aBoardLabels = Array();
foreach ($oSql->getQuery('SELECT sName, sLabel FROM live_boards ORDER BY iPosition ASC') as $aRow) {
    $aBoardLabels[$aRow['sName']] = (string) $aRow['sLabel'];
}

// pierwszy obrazek strony (preferowany iType, potem domyślny)
$fPageImage = function ($iPage, $iPreferType = 0) use ($oSql) {
    if ($iPage <= 0) {
        return '';
    }
    $oQuery = $oSql->prepare(
        'SELECT sFileName FROM files WHERE iPage = :page AND iSize > 0
         ORDER BY (iType = :type) DESC, iDefault DESC, iPosition ASC LIMIT 1'
    );
    $oQuery->execute(Array(':page' => $iPage, ':type' => $iPreferType));
    return (string) ($oQuery->fetchColumn() ?: '');
};

// wszystkie obrazki strony (galerie plansz) — plik + opis (tytuł loga)
$fPageImages = function ($iPage) use ($oSql) {
    if ($iPage <= 0) {
        return Array();
    }
    $oQuery = $oSql->prepare('SELECT sFileName, sDescription FROM files WHERE iPage = :page AND iSize > 0 ORDER BY iPosition ASC');
    $oQuery->execute(Array(':page' => $iPage));
    return $oQuery->fetchAll(PDO::FETCH_ASSOC);
};

// pozycje galerii logotypów: <li> ze zdjęciem + opisem (files.sDescription)
// pod spodem — jedna funkcja dla wszystkich plansz z obsGallery
$fGalleryItems = function ($aImages) use ($sFiles) {
    $content = '';
    foreach ($aImages as $aImage) {
        $sDesc = trim((string) ($aImage['sDescription'] ?? ''));
        $content .= '<li><img src="'.$sFiles.html((string) $aImage['sFileName']).'" alt="" />'
            .($sDesc !== '' ? '<span class="description">'.html($sDesc).'</span>' : '')
            .'</li>';
    }
    return $content;
};

// dane drużyny: nazwa, skrót (sDesc lub 3 pierwsze litery), herb (iType=2 → logo)
$fTeamData = function ($iTeam) use ($oSql, $fPageImage) {
    $aData = Array('name' => '—', 'short' => '—', 'logo' => '');
    if ($iTeam <= 0) {
        return $aData;
    }
    $oQuery = $oSql->prepare('SELECT sName, sDesc FROM pages WHERE iPage = :page');
    $oQuery->execute(Array(':page' => $iTeam));
    if ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        $aData['name']  = (string) $aRow['sName'];
        $aData['short'] = trim((string) $aRow['sDesc']) !== ''
            ? trim((string) $aRow['sDesc'])
            : mb_strtoupper(mb_substr((string) $aRow['sName'], 0, 3));
    }
    $aData['logo'] = $fPageImage($iTeam, 2);
    return $aData;
};

// skład: wyjściowa 11 + rezerwa (sSquad z importu protokołu)
$fSquad = function ($iTeam) use ($oSql) {
    $aGroups = Array('1' => Array(), '2' => Array());
    if ($iTeam <= 0) {
        return $aGroups;
    }
    $oQuery = $oSql->prepare(
        'SELECT sName, sNumber, sSquad FROM pages
         WHERE iPageParent = :team AND iStatus = 1 AND sSquad IN ("1","2")
         ORDER BY iPosition ASC, sName COLLATE NOCASE ASC'
    );
    $oQuery->execute(Array(':team' => $iTeam));
    while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        $aGroups[(string) $aRow['sSquad']][] = $aRow;
    }
    return $aGroups;
};

// sztab szkoleniowy = CAŁY opis pełny drużyny (importer zapisuje tam
// listę <ul> z rolami; wszystko, co wpiszesz w opisie drużyny, pokaże
// się nad składem — bez wyszukiwania specjalnego bloku)
$fStaffBlock = function ($iTeam) use ($oSql) {
    if ($iTeam <= 0) {
        return '';
    }
    $oQuery = $oSql->prepare('SELECT sDescriptionFull FROM pages WHERE iPage = :page');
    $oQuery->execute(Array(':page' => $iTeam));
    $sDesc = trim((string) ($oQuery->fetchColumn() ?: ''));
    return $sDesc !== '' ? parseShortcodes($sDesc) : '';
};

$aTeam1 = $fTeamData($iTeam1);
$aTeam2 = $fTeamData($iTeam2);

$sMatchName  = (string) (getData($iMatch, 'sName') ?? '');
$sMatchDate  = (string) (getData($iMatch, 'sDate') ?? '');
$sMatchRound = (string) (getData($iMatch, 'sPrice') ?? ''); // kolejka (Transmisja → Konfiguracja)
$sMatchDesc  = (string) (getData($iMatch, 'sDescriptionFull') ?? '');
$sPoster     = $fPageImage($iMatch, 1);

// belka plansz meczowych: „kolejka — data" (albo to, co jest wypełnione)
$sMatchMeta = trim($sMatchRound.($sMatchRound !== '' && $sMatchDate !== '' ? ' — ' : '').$sMatchDate);

// logo realizatora transmisji (prawy górny róg, razem z paskiem wyniku)
$sProducerLogo = is_file('images/kosax-live.png') ? $sRoot.'images/kosax-live.png' : '';

// sędziowie: dedykowana zakładka (opis pełny + grid zdjęć strony);
// fallback bez zakładki — opis SKRÓCONY strony meczu (stare zachowanie)
$iRefereesPage  = (int) ($config['live_referees_page'] ?? 0);
$sReferees      = $iRefereesPage > 0
    ? (string) (getData($iRefereesPage, 'sDescriptionFull') ?? '')
    : (string) (getData($iMatch, 'sDescriptionShort') ?? '');
$aRefereeImages = $iRefereesPage > 0 ? $fPageImages($iRefereesPage) : Array();

$aSponsorImages    = $fPageImages((int) ($config['live_sponsors_page'] ?? 0));
$aPartnerImages    = $fPageImages((int) ($config['live_partners_page'] ?? 0));
$aProductionImages = $fPageImages((int) ($config['live_production_page'] ?? 0));
$sProductionTitle  = (string) (getData((int) ($config['live_production_page'] ?? 0), 'sName') ?? '');

// wsparcie: grid logotypów zakładki (jak sponsorzy) + opis pełny pod gridem
$iSupportPage   = (int) ($config['live_match_sponsor_page'] ?? 0);
$aSupportImages = $iSupportPage > 0 ? $fPageImages($iSupportPage) : Array();
$sSupportDesc   = $iSupportPage > 0 ? (string) (getData($iSupportPage, 'sDescriptionFull') ?? '') : '';

$sCssVer = @filemtime('templates/'.$config['skin'].'/css/live-overlay.css') ?: 1;
$sJsVer  = @filemtime('templates/'.$config['skin'].'/js/page-live-overlay.js') ?: 1;

// ------------------------------------------------------------
// STOPKA PLANSZ — JEDNO źródło dla wszystkich plansz.
// Uchwyty social wyciągane z Konfiguracji (zakładka Social media) —
// zmieniasz linki w panelu i stopka aktualizuje się wszędzie.
// ------------------------------------------------------------
$fSocialHandle = function ($sUrl) {
    $sHandle = trim(basename(rtrim((string) $sUrl, '/')));
    return $sHandle !== '' ? $sHandle : '';
};
$aFooterSocial = Array(
    'instagram' => ($sH = $fSocialHandle($config['instagram'] ?? '')) !== '' ? '@'.ltrim($sH, '@') : '',
    'facebook'  => $fSocialHandle($config['facebook'] ?? ''),
    'youtube'   => $fSocialHandle($config['youtube'] ?? ''),
);
$sBoardFooter = '';
foreach ($aFooterSocial as $sIcon => $sHandle) {
    if ($sHandle !== '') {
        $sBoardFooter .= '<li><img src="'.$sRoot.'images/icons/'.$sIcon.'.svg" alt="" />'.html($sHandle).'</li>';
    }
}
$sBoardFooter = $sBoardFooter !== ''
    ? '<footer class="obsBoard__footer"><ul>'.$sBoardFooter.'</ul></footer>'
    : '';

// nagłówek meczowy (herby + wynik) — używany na 2 planszach
$fMatchHead = function () use ($aTeam1, $aTeam2, $sFiles) {
    $fTeamCell = function ($aTeam) use ($sFiles) {
        return '<div class="obsMatchHead__team">'
            .($aTeam['logo'] !== '' ? '<img src="'.$sFiles.html($aTeam['logo']).'" alt="" />' : '')
            .'<div class="name">'.html($aTeam['name']).'</div>'
            .'</div>';
    };
    return '<div class="obsMatchHead">'
        .$fTeamCell($aTeam1)
        .'<div class="obsMatchHead__score"><span class="js-score1">0</span><span>:</span><span class="js-score2">0</span></div>'
        .$fTeamCell($aTeam2)
        .'</div>';
};

// plansza składu jednej drużyny — lista w WSPÓLNEJ klasie ul.teamList
// (te same style co oś zdarzeń w podsumowaniu); --i = kolejność wiersza
// dla kaskadowej animacji wjazdu po pokazaniu planszy
$fSquadBoard = function ($sBoard, $iTeam, $aTeam) use ($fSquad, $fStaffBlock, $aBoardLabels, $sFiles, $lang, $sMatchDate, $sBoardFooter) {
    $aGroups = $fSquad($iTeam);
    $sStaff  = $fStaffBlock($iTeam);

    $content  = '<div class="obsBoard obsShow" data-board="'.html($sBoard).'">';
    $content .= '<header class="obsBoard__header"><span class="title">' .($aTeam['logo'] !== '' ? '<img src="'.$sFiles.html($aTeam['logo']).'" alt="" />' : '').html($aTeam['name']).'</span>'
        .'</header>';
    $content .= '<div class="obsBoard__content"><div class="obsSquad">';

    $content .= '<div class="obsSquad__head">'

        .($sStaff !== '' ? '<div class="obsSquad__staff">'.$sStaff.'</div>' : '')
        .'</div>';

    $content .= '<div class="obsSquad__columns">';
    foreach (Array('1' => $lang['live_first_squad'], '2' => $lang['live_reserve']) as $sSquad => $sHeading) {
        $content .= '<div class="obsSquad-'.$sSquad.'"><h3 class="obsSquad__heading">'.$sHeading.'</h3><ul class="teamList'.($sSquad === '2' ? ' teamList--dark' : '').'">';
        foreach (array_values($aGroups[$sSquad]) as $iRow => $aPlayer) {
            $content .= '<li style="--i:'.$iRow.'"><span class="number">'.html((string) $aPlayer['sNumber']).'</span>'
                .'<span>'.html((string) $aPlayer['sName']).'</span></li>';
        }
        $content .= '</ul></div>';
    }
    $content .= '</div>';

    // domyka: .obsSquad__columns zamknięte wyżej → .obsSquad, .obsBoard__content,
    // stopka, .obsBoard (licz diva przy każdej zmianie — brak jednego zamknięcia
    // wciąga kolejne plansze DO WNĘTRZA niewidocznej planszy składu)
    return $content.'</div></div>'.$sBoardFooter.'</div>';
};

// ------------------------------------------------------------
// SKŁAD 3D — wyjściowa 11 na pochylonym boisku (styl Ligi Mistrzów)
// ------------------------------------------------------------
// sloty 1-11 z pages.sLineup (panel admina → Drużyny → Kadra); przy
// duplikacie slotu wygrywa pierwszy wg iPosition. Fallback bez żadnych
// przypisań: pierwsza 11 z sSquad=1 wg iPosition → sloty 1..11 po kolei.
$fLineup = function ($iTeam) use ($oSql) {
    $aSlots = Array();
    if ($iTeam <= 0) {
        return $aSlots;
    }
    $oQuery = $oSql->prepare(
        'SELECT iPage, sName, sNumber, sLineup FROM pages
         WHERE iPageParent = :team AND iStatus = 1 AND sLineup != ""
         ORDER BY iPosition ASC, sName COLLATE NOCASE ASC'
    );
    $oQuery->execute(Array(':team' => $iTeam));
    while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        $iSlot = (int) $aRow['sLineup'];
        if ($iSlot >= 1 && $iSlot <= 11 && !isset($aSlots[$iSlot])) {
            $aSlots[$iSlot] = $aRow;
        }
    }
    if (empty($aSlots)) {
        $oQuery = $oSql->prepare(
            'SELECT iPage, sName, sNumber FROM pages
             WHERE iPageParent = :team AND iStatus = 1 AND sSquad = "1"
             ORDER BY iPosition ASC, sName COLLATE NOCASE ASC LIMIT 11'
        );
        $oQuery->execute(Array(':team' => $iTeam));
        $iSlot = 1;
        while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
            $aSlots[$iSlot++] = $aRow;
        }
    }
    return $aSlots;
};

// formacja drużyny (pages.sFormation, wybór w panelu Drużyny);
// bez wyboru albo z nieznaną nazwą — bezpieczne 4-4-2
$fFormation = function ($iTeam) use ($oSql, $config) {
    $sFormation = '';
    if ($iTeam > 0) {
        $oQuery = $oSql->prepare('SELECT sFormation FROM pages WHERE iPage = :page');
        $oQuery->execute(Array(':page' => $iTeam));
        $sFormation = (string) ($oQuery->fetchColumn() ?: '');
    }
    return isset($config['live_formations'][$sFormation]) ? $sFormation : '4-4-2';
};

// plansza „Skład 3D": boisko pochylone w 3D (rotateX), zawodnicy jako
// okrągłe zdjęcia stojące na murawie (kontr-rotacja), pozycje ze
// współrzędnych $config['live_formations'][formacja] (x wzdłuż boiska,
// własna bramka z LEWEJ; y w poprzek). Kaskadowy wjazd chipów wg --i.
$fPitchBoard = function ($sBoard, $iTeam, $aTeam) use ($fLineup, $fFormation, $fPageImage, $aBoardLabels, $sFiles, $config, $sBoardFooter) {
    $sFormation = $fFormation($iTeam);
    $aCoords    = $config['live_formations'][$sFormation];
    $aSlots     = $fLineup($iTeam);

    // linie boiska: SVG w currentColor — kolor i poświatę nadaje SCSS (§10.0);
    // viewBox 1050x680 = proporcje boiska 105x68 m, własna bramka z lewej
    $sLines = '<svg class="obsPitch__lines" viewBox="0 0 1050 680" preserveAspectRatio="none" aria-hidden="true">'
        .'<rect x="10" y="10" width="1030" height="660" />'
        .'<line x1="525" y1="10" x2="525" y2="670" />'
        .'<circle cx="525" cy="340" r="91.5" />'
        .'<circle class="spot" cx="525" cy="340" r="4" />'
        .'<rect x="10" y="138.5" width="165" height="403" />'
        .'<rect x="10" y="248.5" width="55" height="183" />'
        .'<circle class="spot" cx="120" cy="340" r="4" />'
        .'<path d="M 175 266.9 A 91.5 91.5 0 0 1 175 413.1" />'
        .'<rect x="875" y="138.5" width="165" height="403" />'
        .'<rect x="985" y="248.5" width="55" height="183" />'
        .'<circle class="spot" cx="930" cy="340" r="4" />'
        .'<path d="M 875 266.9 A 91.5 91.5 0 0 0 875 413.1" />'
        .'<path d="M 10 25 A 15 15 0 0 0 25 10" /><path d="M 1025 10 A 15 15 0 0 0 1040 25" />'
        .'<path d="M 1040 655 A 15 15 0 0 0 1025 670" /><path d="M 25 670 A 15 15 0 0 0 10 655" />'
        .'</svg>';

    $content  = '<div class="obsBoard obsBoardBig obsPitchBoard obsShow" data-board="'.html($sBoard).'">';
    $content .= '<header class="obsBoard__header">'
        .'<span class="meta">SKŁAD</span>'
        .'<span class="title">'.($aTeam['logo'] !== '' ? '<img src="'.$sFiles.html($aTeam['logo']).'" alt="" />' : '').html($aTeam['name']).'</span>'
        .'<span class="meta">'.html($sFormation).'</span>'
        .'</header>';
    $content .= '<div class="obsBoard__content"><div class="obsPitch"><div class="obsPitch__scene"><div class="obsPitch__field">'.$sLines;

    $iOrder = 0;
    foreach ($aCoords as $iSlot => $aXY) {
        if (!isset($aSlots[$iSlot])) {
            continue;
        }
        $aPlayer = $aSlots[$iSlot];
        $sPhoto  = $fPageImage((int) $aPlayer['iPage']);
        $content .= '<div class="obsPitch__player" style="--x:'.(float) $aXY[0].'%;--y:'.(float) $aXY[1].'%;--i:'.$iOrder.'">'
            .'<div class="obsPitch__inner">'
            .'<div class="obsPitch__chip">'
            .($sPhoto !== ''
                ? '<img src="'.$sFiles.html($sPhoto).'" alt="" />'
                : '<span class="obsPitch__chipNumber">'.html((string) $aPlayer['sNumber']).'</span>')
            .'</div>'
            .'<div class="obsPitch__plaque"><span class="number">'.html((string) $aPlayer['sNumber']).'</span><span class="name">'.html((string) $aPlayer['sName']).'</span></div>'
            .'</div></div>';
        $iOrder++;
    }

    // domyka: field, scene, pitch, content → stopka → obsBoard (licz divy!)
    return $content.'</div></div></div></div>'.$sBoardFooter.'</div>';
};
?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=1920">
    <title><?php echo html($aData['sName'] ?? 'Nakładka OBS'); ?></title>
    <link rel="stylesheet" href="<?php echo $sRoot; ?>templates/<?php echo $config['skin']; ?>/css/live-overlay.css?ver=<?php echo $sCssVer; ?>">
</head>
<body>
<div class="obsStage">

    <!-- PASEK WYNIKU + ZEGAR (kropki pod nazwą = kartki drużyny; bez nr połowy) -->
    <div class="obsScorebar obsShow" data-board="wynik">
        <div class="obsScorebar__team obsScorebar__team--home">
            <?php if ($aTeam1['logo'] !== ''): ?><img class="obsScorebar__logo" src="<?php echo $sFiles.html($aTeam1['logo']); ?>" alt="" /><?php endif; ?>
            <span class="obsScorebar__label">
                <span><?php echo html($aTeam1['short']); ?></span>
                <span class="obsScorebar__dots js-cards1"></span>
            </span>
        </div>
        <div class="obsScorebar__score"><span class="js-score1">0</span><span>:</span><span class="js-score2">0</span></div>
        <div class="obsScorebar__team obsScorebar__team--away">
            <span class="obsScorebar__label obsScorebar__label--away">
                <span><?php echo html($aTeam2['short']); ?></span>
                <span class="obsScorebar__dots js-cards2"></span>
            </span>
            <?php if ($aTeam2['logo'] !== ''): ?><img class="obsScorebar__logo" src="<?php echo $sFiles.html($aTeam2['logo']); ?>" alt="" /><?php endif; ?>
        </div>
        <div class="obsScorebar__clock js-clock-box">
            <span class="clock js-clock">00:00</span>
        </div>
    </div>

    <?php if ($sProducerLogo !== ''): ?>
    <!-- LOGO REALIZATORA TRANSMISJI (włączane razem z paskiem wyniku) -->
    <img class="obsProducerLogo obsShow" data-board="wynik" id="obs-producer-logo"
         src="<?php echo html($sProducerLogo); ?>" alt="" />
    <?php endif; ?>

    <!-- POPUPY ZDARZEŃ -->
    <div class="obsEvents" id="obs-events"></div>

    <!-- PLANSZA: DZIEŃ MECZOWY -->
    <div class="obsBoard obsShow" data-board="dzien_meczowy">
        <header class="obsBoard__header">
            <span class="meta">IV liga lubelska</span>
            <span class="title"><?php echo html($sMatchName !== '' ? $sMatchName : ($aBoardLabels['dzien_meczowy'] ?? '')); ?></span>
            <?php if ($sMatchMeta !== ''): ?><span class="meta"><?php echo html($sMatchRound); ?></span><?php endif; ?>
        </header>
        <div class="obsBoard__content">
            <?php echo $fMatchHead(); ?>
            <?php if ($sMatchDesc !== ''): ?><div class="obsMatchDesc"><?php echo parseShortcodes($sMatchDesc); ?></div><?php endif; ?>
        </div>
        <?php echo $sBoardFooter; ?>
    </div>

    <!-- PLANSZE: SKŁADY -->
    <?php echo $fSquadBoard('sklad_gospodarza', $iTeam1, $aTeam1); ?>
    <?php echo $fSquadBoard('sklad_goscia', $iTeam2, $aTeam2); ?>

    <!-- PLANSZE: SKŁAD 3D (wyjściowa 11 na pochylonym boisku) -->
    <?php echo $fPitchBoard('sklad3d_gospodarza', $iTeam1, $aTeam1); ?>
    <?php echo $fPitchBoard('sklad3d_goscia', $iTeam2, $aTeam2); ?>

    <!-- PLANSZA: PODSUMOWANIE (bez tabelki statystyk — wszystko na osi zdarzeń) -->
    <div class="obsBoard obsShow" data-board="podsumowanie">
        <header class="obsBoard__header">
            <span class="title"><?php echo html($aBoardLabels['podsumowanie'] ?? ''); ?></span>
<!--            <?php if ($sMatchMeta !== '' || $sMatchName !== ''): ?><span class="meta"><?php echo html($sMatchMeta !== '' ? $sMatchMeta : $sMatchName); ?></span><?php endif; ?>-->
        </header>
        <div class="obsBoard__content">
            <?php echo $fMatchHead(); ?>
            <div class="obsSummary">
                <ul class="teamList" id="obs-summary-1"></ul>
                <ul class="teamList teamList--dark" id="obs-summary-2"></ul>
            </div>
        </div>
        <?php echo $sBoardFooter; ?>
    </div>

    <?php if ($sReferees !== '' || !empty($aRefereeImages)): ?>
    <!-- PLANSZA: SĘDZIOWIE (zakładka live_referees_page: opis + grid zdjęć) -->
    <div class="obsBoard obsShow" data-board="sedziowie">
        <header class="obsBoard__header">
            <span class="title"><?php echo html($aBoardLabels['sedziowie'] ?? ''); ?></span>
        </header>
        <div class="obsBoard__content">
            <?php if ($sReferees !== ''): ?><div class="obsReferees"><?php echo parseShortcodes($sReferees); ?></div><?php endif; ?>
            <?php if (!empty($aRefereeImages)): ?>
            <ul class="obsGallery">
                <?php echo $fGalleryItems($aRefereeImages); ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php echo $sBoardFooter; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($aSponsorImages)): ?>
    <!-- PLANSZA: SPONSORZY -->
    <div class="obsBoard obsBoardBig obsShow" data-board="sponsorzy">
        <header class="obsBoard__header"><span class="title"><?php echo html($aBoardLabels['sponsorzy'] ?? ''); ?></span></header>
        <div class="obsBoard__content">
            <ul class="obsGallery">
                <?php echo $fGalleryItems($aSponsorImages); ?>
            </ul>
        </div>
        <?php echo $sBoardFooter; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($aSponsorImages)): ?>
    <!-- PLANSZA: SPONSORZY — SLIDER (dolna banda reklamowa; te same loga co grid) -->
    <div class="obsSponsorTicker obsShow" data-board="sponsorzy_slider">
        <div class="obsSponsorTicker__track" style="animation-duration: <?php echo max(24, count($aSponsorImages) * 2); ?>s">
            <?php for ($i = 0; $i < 2; $i++): // 2x ta sama grupa = pętla bez szwu ?>
            <div class="obsSponsorTicker__group">
                <?php foreach ($aSponsorImages as $aImage): ?>
                    <img src="<?php echo $sFiles.html((string) $aImage['sFileName']); ?>" alt="" />
                <?php endforeach; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($aPartnerImages)): ?>
    <!-- PLANSZA: PARTNERZY GŁÓWNI (grid logotypów jak sponsorzy) -->
    <div class="obsBoard obsShow" data-board="partnerzy_glowni">
        <header class="obsBoard__header"><span class="title"><?php echo html($aBoardLabels['partnerzy_glowni'] ?? ''); ?></span></header>
        <div class="obsBoard__content">
            <ul class="obsGallery obsGallery3">
                <?php echo $fGalleryItems($aPartnerImages); ?>
            </ul>
        </div>
        <?php echo $sBoardFooter; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($aSupportImages) || $sSupportDesc !== ''): ?>
    <!-- PLANSZA: WSPARCIE (grid logotypów jak sponsorzy + opis pełny pod gridem) -->
    <div class="obsBoard obsShow" data-board="wsparcie">
        <header class="obsBoard__header"><span class="title"><?php echo html($aBoardLabels['wsparcie'] ?? ''); ?></span></header>
        <div class="obsBoard__content">
            <?php if (!empty($aSupportImages)): ?>
            <ul class="obsGallery obsGallery3">
                <?php echo $fGalleryItems($aSupportImages); ?>
            </ul>
            <?php endif; ?>
            <?php if ($sSupportDesc !== ''): ?><div class="obsMatchSponsor__desc"><?php echo parseShortcodes($sSupportDesc); ?></div><?php endif; ?>
        </div>
        <?php echo $sBoardFooter; ?>
    </div>
    <?php endif; ?>

    <!-- PLANSZA: AKTUALNY WYNIK (dolny pasek: wynik + strzelcy bramek) -->
    <div class="obsCurrentScore obsShow" data-board="aktualny_wynik">
        <header class="obsCurrentScore__header">
            <span class="obsCurrentScore__team"><?php echo html($aTeam1['name']); ?></span>
            <span class="obsCurrentScore__score"><span class="js-score1">0</span>:<span class="js-score2">0</span></span>
            <span class="obsCurrentScore__team obsCurrentScore__team--away"><?php echo html($aTeam2['name']); ?></span>
        </header>
        <div class="obsCurrentScore__goals">
            <ul class="obsCurrentScore__list" id="obs-goals-1"></ul>
            <ul class="obsCurrentScore__list" id="obs-goals-2"></ul>
        </div>
    </div>

    <?php if (!empty($aProductionImages)): ?>
    <!-- PLANSZA: REALIZACJA TRANSMISJI -->
    <div class="obsBoard obsShow" data-board="realizacja_transmisji">
        <header class="obsBoard__header"><span class="title"><?php echo html($sProductionTitle !== '' ? $sProductionTitle : ($aBoardLabels['realizacja_transmisji'] ?? '')); ?></span></header>
        <div class="obsBoard__content">
            <ul class="obsGallery obsGallery3">
                <?php echo $fGalleryItems(array_slice($aProductionImages, 0, 4)); ?>
            </ul>
        </div>
        <?php echo $sBoardFooter; ?>
    </div>
    <?php endif; ?>

    <?php if ($sPoster !== ''): ?>
    <!-- PLANSZA: PLAKAT MECZOWY -->
    <div class="obsBoard obsPoster obsShow" data-board="plakat">
        <img src="<?php echo $sFiles.html($sPoster); ?>" alt="" />
    </div>
    <?php endif; ?>

</div>

<script>
<?php
// etykiety akcji mogą zawierać HTML (emoji, <img src="images/icons/…">) —
// względne ścieżki ikon przepinamy na root, bo nakładka żyje pod /nakladka-obs/
$aActionLabels = array_map(function ($sLabel) use ($sRoot) {
    return str_replace('src="images/', 'src="'.$sRoot.'images/', (string) $sLabel);
}, $config['live_actions']);
?>
window.liveOverlayConfig = <?php echo json_encode(Array(
    'api'      => $sRoot.'plugins/live/api.php',
    'filesUrl' => $sFiles,
    'actions'  => $aActionLabels,
    'teams'    => Array(
        (string) $iTeam1 => Array('name' => $aTeam1['name'], 'short' => $aTeam1['short'], 'logo' => $aTeam1['logo']),
        (string) $iTeam2 => Array('name' => $aTeam2['name'], 'short' => $aTeam2['short'], 'logo' => $aTeam2['logo']),
    ),
    'team1'  => $iTeam1,
    'team2'  => $iTeam2,
    'labels' => Array(
        'halfShort' => $lang['live_half_short'],
        'noEvents'  => $lang['live_no_events'],
    ),
), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $sRoot; ?>templates/<?php echo $config['skin']; ?>/js/page-live-overlay.js?ver=<?php echo $sJsVer; ?>"></script>
</body>
</html>
