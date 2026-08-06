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

// wszystkie obrazki strony (galerie plansz)
$fPageImages = function ($iPage) use ($oSql) {
    if ($iPage <= 0) {
        return Array();
    }
    $oQuery = $oSql->prepare('SELECT sFileName FROM files WHERE iPage = :page AND iSize > 0 ORDER BY iPosition ASC');
    $oQuery->execute(Array(':page' => $iPage));
    return $oQuery->fetchAll(PDO::FETCH_COLUMN);
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

// sztab: blok .teamStaff zapisany w opisie drużyny przez importer protokołu
$fStaffBlock = function ($iTeam) use ($oSql) {
    if ($iTeam <= 0) {
        return '';
    }
    $oQuery = $oSql->prepare('SELECT sDescriptionShort FROM pages WHERE iPage = :page');
    $oQuery->execute(Array(':page' => $iTeam));
    $sDesc = (string) ($oQuery->fetchColumn() ?: '');
    return preg_match('#<div class="teamStaff">.*?</div>#s', $sDesc, $aMatch) ? $aMatch[0] : '';
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

// sponsor meczu: 1 duże logo (pierwszy obrazek zakładki) + opis pełny
$iMatchSponsor     = (int) ($config['live_match_sponsor_page'] ?? 0);
$sMatchSponsorLogo = $iMatchSponsor > 0 ? $fPageImage($iMatchSponsor) : '';
$sMatchSponsorDesc = $iMatchSponsor > 0 ? (string) (getData($iMatchSponsor, 'sDescriptionFull') ?? '') : '';

$sCssVer = @filemtime('templates/'.$config['skin'].'/css/live-overlay.css') ?: 1;
$sJsVer  = @filemtime('templates/'.$config['skin'].'/js/page-live-overlay.js') ?: 1;

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

// plansza składu jednej drużyny
$fSquadBoard = function ($sBoard, $iTeam, $aTeam) use ($fSquad, $fStaffBlock, $aBoardLabels, $sFiles, $lang, $sMatchDate) {
    $aGroups = $fSquad($iTeam);
    $sStaff  = $fStaffBlock($iTeam);

    $content  = '<div class="obsBoard obsShow" data-board="'.html($sBoard).'">';
    $content .= '<header class="obsBoard__header"><span class="title">'.html($aTeam['name']).'</span>'
        .($sMatchDate !== '' ? '<span class="meta">'.html($sMatchDate).'</span>' : '').'</header>';
    $content .= '<div class="obsBoard__content"><div class="obsSquad">';

    $content .= '<div class="obsSquad__head">'
        .($aTeam['logo'] !== '' ? '<img src="'.$sFiles.html($aTeam['logo']).'" alt="" />' : '')
        .($sStaff !== '' ? '<div class="obsSquad__staff">'.$sStaff.'</div>' : '')
        .'</div>';

    $content .= '<div class="obsSquad__columns">';
    foreach (Array('1' => $lang['live_first_squad'], '2' => $lang['live_reserve']) as $sSquad => $sHeading) {
        $content .= '<div><h3 class="obsSquad__heading">'.$sHeading.'</h3><ul class="obsSquad__list">';
        foreach ($aGroups[$sSquad] as $aPlayer) {
            $content .= '<li><span class="number">'.html((string) $aPlayer['sNumber']).'</span>'
                .'<span>'.html((string) $aPlayer['sName']).'</span></li>';
        }
        $content .= '</ul></div>';
    }
    $content .= '</div>';

    return $content.'</div></div></div>';
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
            <span class="title"><?php echo html($sMatchName !== '' ? $sMatchName : ($aBoardLabels['dzien_meczowy'] ?? '')); ?></span>
            <?php if ($sMatchMeta !== ''): ?><span class="meta"><?php echo html($sMatchMeta); ?></span><?php endif; ?>
        </header>
        <div class="obsBoard__content">
            <?php echo $fMatchHead(); ?>
            <?php if ($sMatchDesc !== ''): ?><div class="obsMatchDesc"><?php echo parseShortcodes($sMatchDesc); ?></div><?php endif; ?>
        </div>
        <footer class="obsBoard__footer"><?php echo html($config['logo']); ?> • <?php echo $lang['live_broadcast_footer']; ?></footer>
    </div>

    <!-- PLANSZE: SKŁADY -->
    <?php echo $fSquadBoard('sklad_gospodarza', $iTeam1, $aTeam1); ?>
    <?php echo $fSquadBoard('sklad_goscia', $iTeam2, $aTeam2); ?>

    <!-- PLANSZA: PODSUMOWANIE (bez tabelki statystyk — wszystko na osi zdarzeń) -->
    <div class="obsBoard obsShow" data-board="podsumowanie">
        <header class="obsBoard__header">
            <span class="title"><?php echo html($aBoardLabels['podsumowanie'] ?? ''); ?></span>
            <?php if ($sMatchMeta !== '' || $sMatchName !== ''): ?><span class="meta"><?php echo html($sMatchMeta !== '' ? $sMatchMeta : $sMatchName); ?></span><?php endif; ?>
        </header>
        <div class="obsBoard__content">
            <?php echo $fMatchHead(); ?>
            <div class="obsSummary">
                <ul class="obsSummary__list" id="obs-summary-1"></ul>
                <ul class="obsSummary__list" id="obs-summary-2"></ul>
            </div>
        </div>
        <footer class="obsBoard__footer"><?php echo html($config['logo']); ?> • <?php echo $lang['live_broadcast_footer']; ?></footer>
    </div>

    <?php if ($sReferees !== '' || !empty($aRefereeImages)): ?>
    <!-- PLANSZA: SĘDZIOWIE (zakładka live_referees_page: opis + grid zdjęć) -->
    <div class="obsBoard obsShow" data-board="sedziowie">
        <header class="obsBoard__header">
            <span class="title"><?php echo html($aBoardLabels['sedziowie'] ?? ''); ?></span>
            <?php if ($sMatchName !== ''): ?><span class="meta"><?php echo html($sMatchName); ?></span><?php endif; ?>
        </header>
        <div class="obsBoard__content">
            <?php if ($sReferees !== ''): ?><div class="obsReferees"><?php echo parseShortcodes($sReferees); ?></div><?php endif; ?>
            <?php if (!empty($aRefereeImages)): ?>
            <div class="obsGallery">
                <?php foreach ($aRefereeImages as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <footer class="obsBoard__footer"><?php echo html($config['logo']); ?> • <?php echo $lang['live_broadcast_footer']; ?></footer>
    </div>
    <?php endif; ?>

    <?php if (!empty($aSponsorImages)): ?>
    <!-- PLANSZA: SPONSORZY -->
    <div class="obsBoard obsShow" data-board="sponsorzy">
        <header class="obsBoard__header"><span class="title"><?php echo html($aBoardLabels['sponsorzy'] ?? ''); ?></span></header>
        <div class="obsBoard__content">
            <div class="obsGallery">
                <?php foreach ($aSponsorImages as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
        </div>
        <footer class="obsBoard__footer"><?php echo html($config['logo']); ?> • <?php echo $lang['live_broadcast_footer']; ?></footer>
    </div>
    <?php endif; ?>

    <?php if (!empty($aPartnerImages)): ?>
    <!-- PLANSZA: PARTNERZY GŁÓWNI (grid logotypów jak sponsorzy) -->
    <div class="obsBoard obsShow" data-board="partnerzy_glowni">
        <header class="obsBoard__header"><span class="title"><?php echo html($aBoardLabels['partnerzy_glowni'] ?? ''); ?></span></header>
        <div class="obsBoard__content">
            <div class="obsGallery">
                <?php foreach ($aPartnerImages as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
        </div>
        <footer class="obsBoard__footer"><?php echo html($config['logo']); ?> • <?php echo $lang['live_broadcast_footer']; ?></footer>
    </div>
    <?php endif; ?>

    <?php if ($sMatchSponsorLogo !== '' || $sMatchSponsorDesc !== ''): ?>
    <!-- PLANSZA: SPONSOR MECZU (duże logo + opis pełny) -->
    <div class="obsBoard obsShow" data-board="sponsor_meczu">
        <header class="obsBoard__header"><span class="title"><?php echo html($aBoardLabels['sponsor_meczu'] ?? ''); ?></span></header>
        <div class="obsBoard__content">
            <div class="obsMatchSponsor">
                <?php if ($sMatchSponsorLogo !== ''): ?><img class="obsMatchSponsor__logo" src="<?php echo $sFiles.html($sMatchSponsorLogo); ?>" alt="" /><?php endif; ?>
                <?php if ($sMatchSponsorDesc !== ''): ?><div class="obsMatchSponsor__desc"><?php echo parseShortcodes($sMatchSponsorDesc); ?></div><?php endif; ?>
            </div>
        </div>
        <footer class="obsBoard__footer"><?php echo html($config['logo']); ?> • <?php echo $lang['live_broadcast_footer']; ?></footer>
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
            <div class="obsGallery obsGallery--big">
                <?php foreach (array_slice($aProductionImages, 0, 4) as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
        </div>
        <footer class="obsBoard__footer"><?php echo html($config['logo']); ?> • <?php echo $lang['live_broadcast_footer']; ?></footer>
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
window.liveOverlayConfig = <?php echo json_encode(Array(
    'api'      => $sRoot.'plugins/live/api.php',
    'filesUrl' => $sFiles,
    'actions'  => $config['live_actions'],
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
