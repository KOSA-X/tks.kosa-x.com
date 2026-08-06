<?php
if (!defined('CUSTOMER_PAGE')) { exit; }

/*
 * TRANSMISJA LIVE — TELEBIM (ekran LED przy boisku, kiosk mode).
 * Strona STANDALONE: bez _header/_footer i CSS motywu — własny arkusz
 * css/telebim.css (źródło: _source/telebim.scss) + vanilla JS
 * js/page-telebim.js. Layout skaluje się do dowolnej rozdzielczości LED
 * (projekt bazowy 768x512 — 1rem = 1/48 szerokości ekranu).
 *
 * Różnice vs nakładka OBS: ciemne tło (fizyczny ekran, nie chroma),
 * warstwa WIDEO — cieszynki po golu (klip mp4/webm z podstrony zawodnika,
 * preload na starcie) i powtórki z OBS (lokalny serwer replay buffera,
 * plugins/live/replay/). Plansze sterowane tymi samymi live_boards
 * co nakładka — operator przełącza raz, oba ekrany reagują.
 *
 * Kiosk (Etap 6): chrome --kiosk --autoplay-policy=no-user-gesture-required
 * --app=https://DOMENA/telebim/ na komputerze przy telebimie.
 */

require_once 'plugins/live/view-helpers.php';

$sRoot  = $config['base_path_with_slash'];
$sFiles = $sRoot.'files/500/';   // miniatury obrazków (herby, zdjęcia)
$sClips = $sRoot.'files/';       // pliki oryginalne (klipy wideo)

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

$aTeam1 = liveTeamData($iTeam1);
$aTeam2 = liveTeamData($iTeam2);

$sMatchName = (string) (getData($iMatch, 'sName') ?? '');
$sMatchDate = (string) (getData($iMatch, 'sDate') ?? '');
$sMatchDesc = (string) (getData($iMatch, 'sDescriptionFull') ?? '');
$sPoster    = livePageImage($iMatch, 1);

// sędziowie: dedykowana zakładka (opis pełny + grid zdjęć strony);
// fallback bez zakładki — opis SKRÓCONY strony meczu (stare zachowanie)
$iRefereesPage  = (int) ($config['live_referees_page'] ?? 0);
$sReferees      = $iRefereesPage > 0
    ? (string) (getData($iRefereesPage, 'sDescriptionFull') ?? '')
    : (string) (getData($iMatch, 'sDescriptionShort') ?? '');
$aRefereeImages = $iRefereesPage > 0 ? livePageImages($iRefereesPage) : Array();

$aSponsorImages    = livePageImages((int) ($config['live_sponsors_page'] ?? 0));
$aPartnerImages    = livePageImages((int) ($config['live_partners_page'] ?? 0));
$aProductionImages = livePageImages((int) ($config['live_production_page'] ?? 0));
$sProductionTitle  = (string) (getData((int) ($config['live_production_page'] ?? 0), 'sName') ?? '');

// sponsor meczu: 1 duże logo (pierwszy obrazek zakładki) + opis pełny
$iMatchSponsor     = (int) ($config['live_match_sponsor_page'] ?? 0);
$sMatchSponsorLogo = $iMatchSponsor > 0 ? livePageImage($iMatchSponsor) : '';
$sMatchSponsorDesc = $iMatchSponsor > 0 ? (string) (getData($iMatchSponsor, 'sDescriptionFull') ?? '') : '';

// cieszynki: mapa zawodnik → URL klipu (obie drużyny, preload w JS)
$aClips = Array();
foreach ((liveTeamClips($iTeam1) + liveTeamClips($iTeam2)) as $iPlayer => $sClipFile) {
    $aClips[(string) $iPlayer] = $sClips.$sClipFile;
}

$sCssVer = @filemtime('templates/'.$config['skin'].'/css/telebim.css') ?: 1;
$sJsVer  = @filemtime('templates/'.$config['skin'].'/js/page-telebim.js') ?: 1;

// nagłówek meczowy (herby + wynik) — dzień meczowy i podsumowanie
$fMatchHead = function () use ($aTeam1, $aTeam2, $sFiles) {
    $fTeamCell = function ($aTeam) use ($sFiles) {
        return '<div class="tbMatchHead__team">'
            .($aTeam['logo'] !== '' ? '<img src="'.$sFiles.html($aTeam['logo']).'" alt="" />' : '')
            .'<div class="name">'.html($aTeam['name']).'</div>'
            .'</div>';
    };
    return '<div class="tbMatchHead">'
        .$fTeamCell($aTeam1)
        .'<div class="tbMatchHead__score"><span class="js-score1">0</span><span>:</span><span class="js-score2">0</span></div>'
        .$fTeamCell($aTeam2)
        .'</div>';
};

// plansza składu jednej drużyny — telebim pokazuje UPROSZCZONY widok:
// sama lista z wielkim tekstem (bez sztabu i logo — nieczytelne z trybun)
$fSquadBoard = function ($sBoard, $iTeam, $aTeam) use ($aBoardLabels, $lang, $sMatchDate) {
    $aGroups = liveSquad($iTeam);

    $content  = '<div class="tbBoard tbShow" data-board="'.html($sBoard).'">';
    $content .= '<header class="tbBoard__header"><span class="title">'.html($aTeam['name']).'</span>'
        .($sMatchDate !== '' ? '<span class="meta">'.html($sMatchDate).'</span>' : '').'</header>';
    $content .= '<div class="tbBoard__content"><div class="tbSquad">';

    $content .= '<div class="tbSquad__columns">';
    foreach (Array('1' => $lang['live_first_squad'], '2' => $lang['live_reserve']) as $sSquad => $sHeading) {
        $content .= '<div><h3 class="tbSquad__heading">'.$sHeading.'</h3><ul class="tbSquad__list">';
        foreach ($aGroups[$sSquad] as $aPlayer) {
            $content .= '<li><span class="number">'.html((string) $aPlayer['sNumber']).'</span>'
                .'<span>'.html((string) $aPlayer['sName']).'</span></li>';
        }
        $content .= '</ul></div>';
    }
    $content .= '</div>';

    // domyka: .tbSquad, .tbBoard__content, .tbBoard
    return $content.'</div></div></div>';
};
?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html($aData['sName'] ?? 'Telebim'); ?></title>
    <link rel="stylesheet" href="<?php echo $sRoot; ?>templates/<?php echo $config['skin']; ?>/css/telebim.css?ver=<?php echo $sCssVer; ?>">
</head>
<body>
<div class="tbStage">

    <!-- PASEK WYNIKU + ZEGAR -->
    <div class="tbScorebar tbShow" data-board="wynik">
        <div class="tbScorebar__team tbScorebar__team--home">
            <?php if ($aTeam1['logo'] !== ''): ?><img class="tbScorebar__logo" src="<?php echo $sFiles.html($aTeam1['logo']); ?>" alt="" /><?php endif; ?>
            <span><?php echo html($aTeam1['short']); ?></span>
        </div>
        <div class="tbScorebar__score"><span class="js-score1">0</span><span>:</span><span class="js-score2">0</span></div>
        <div class="tbScorebar__team tbScorebar__team--away">
            <span><?php echo html($aTeam2['short']); ?></span>
            <?php if ($aTeam2['logo'] !== ''): ?><img class="tbScorebar__logo" src="<?php echo $sFiles.html($aTeam2['logo']); ?>" alt="" /><?php endif; ?>
        </div>
        <div class="tbScorebar__clock js-clock-box">
            <span class="clock js-clock">00:00</span>
            <span class="half js-half"></span>
        </div>
    </div>

    <!-- POPUPY ZDARZEŃ -->
    <div class="tbEvents" id="tb-events"></div>

    <!-- PLANSZA: DZIEŃ MECZOWY -->
    <div class="tbBoard tbShow" data-board="dzien_meczowy">
        <header class="tbBoard__header">
            <span class="title"><?php echo html($sMatchName !== '' ? $sMatchName : ($aBoardLabels['dzien_meczowy'] ?? '')); ?></span>
            <?php if ($sMatchDate !== ''): ?><span class="meta"><?php echo html($sMatchDate); ?></span><?php endif; ?>
        </header>
        <div class="tbBoard__content">
            <?php echo $fMatchHead(); ?>
            <?php if ($sMatchDesc !== ''): ?><div class="tbMatchDesc"><?php echo parseShortcodes($sMatchDesc); ?></div><?php endif; ?>
        </div>
    </div>

    <!-- PLANSZE: SKŁADY -->
    <?php echo $fSquadBoard('sklad_gospodarza', $iTeam1, $aTeam1); ?>
    <?php echo $fSquadBoard('sklad_goscia', $iTeam2, $aTeam2); ?>

    <!-- PLANSZA: PODSUMOWANIE (bez tabelki statystyk — wszystko na osi zdarzeń) -->
    <div class="tbBoard tbShow" data-board="podsumowanie">
        <header class="tbBoard__header">
            <span class="title"><?php echo html($aBoardLabels['podsumowanie'] ?? ''); ?></span>
            <?php if ($sMatchName !== ''): ?><span class="meta"><?php echo html($sMatchName); ?></span><?php endif; ?>
        </header>
        <div class="tbBoard__content">
            <?php echo $fMatchHead(); ?>
            <div class="tbSummary">
                <ul class="tbSummary__list" id="tb-summary-1"></ul>
                <ul class="tbSummary__list" id="tb-summary-2"></ul>
            </div>
        </div>
    </div>

    <?php if ($sReferees !== '' || !empty($aRefereeImages)): ?>
    <!-- PLANSZA: SĘDZIOWIE (zakładka live_referees_page: opis + grid zdjęć) -->
    <div class="tbBoard tbShow" data-board="sedziowie">
        <header class="tbBoard__header">
            <span class="title"><?php echo html($aBoardLabels['sedziowie'] ?? ''); ?></span>
            <?php if ($sMatchName !== ''): ?><span class="meta"><?php echo html($sMatchName); ?></span><?php endif; ?>
        </header>
        <div class="tbBoard__content">
            <?php if ($sReferees !== ''): ?><div class="tbReferees"><?php echo parseShortcodes($sReferees); ?></div><?php endif; ?>
            <?php if (!empty($aRefereeImages)): ?>
            <div class="tbGallery">
                <?php foreach ($aRefereeImages as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($aSponsorImages)): ?>
    <!-- PLANSZA: SPONSORZY -->
    <div class="tbBoard tbShow" data-board="sponsorzy">
        <header class="tbBoard__header"><span class="title"><?php echo html($aBoardLabels['sponsorzy'] ?? ''); ?></span></header>
        <div class="tbBoard__content">
            <div class="tbGallery">
                <?php foreach ($aSponsorImages as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($aPartnerImages)): ?>
    <!-- PLANSZA: PARTNERZY GŁÓWNI (grid logotypów jak sponsorzy) -->
    <div class="tbBoard tbShow" data-board="partnerzy_glowni">
        <header class="tbBoard__header"><span class="title"><?php echo html($aBoardLabels['partnerzy_glowni'] ?? ''); ?></span></header>
        <div class="tbBoard__content">
            <div class="tbGallery">
                <?php foreach ($aPartnerImages as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($sMatchSponsorLogo !== '' || $sMatchSponsorDesc !== ''): ?>
    <!-- PLANSZA: SPONSOR MECZU (duże logo + opis pełny) -->
    <div class="tbBoard tbShow" data-board="sponsor_meczu">
        <header class="tbBoard__header"><span class="title"><?php echo html($aBoardLabels['sponsor_meczu'] ?? ''); ?></span></header>
        <div class="tbBoard__content">
            <div class="tbMatchSponsor">
                <?php if ($sMatchSponsorLogo !== ''): ?><img class="tbMatchSponsor__logo" src="<?php echo $sFiles.html($sMatchSponsorLogo); ?>" alt="" /><?php endif; ?>
                <?php if ($sMatchSponsorDesc !== ''): ?><div class="tbMatchSponsor__desc"><?php echo parseShortcodes($sMatchSponsorDesc); ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($aProductionImages)): ?>
    <!-- PLANSZA: REALIZACJA TRANSMISJI -->
    <div class="tbBoard tbShow" data-board="realizacja_transmisji">
        <header class="tbBoard__header"><span class="title"><?php echo html($sProductionTitle !== '' ? $sProductionTitle : ($aBoardLabels['realizacja_transmisji'] ?? '')); ?></span></header>
        <div class="tbBoard__content">
            <div class="tbGallery tbGallery--big">
                <?php foreach (array_slice($aProductionImages, 0, 4) as $sImage): ?>
                    <img src="<?php echo $sFiles.html($sImage); ?>" alt="" />
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($sPoster !== ''): ?>
    <!-- PLANSZA: PLAKAT MECZOWY -->
    <div class="tbBoard tbPoster tbShow" data-board="plakat">
        <img src="<?php echo $sFiles.html($sPoster); ?>" alt="" />
    </div>
    <?php endif; ?>

    <!-- WARSTWA WIDEO: cieszynki po golu + powtórki z OBS -->
    <div class="tbVideo" id="tb-video" hidden>
        <video id="tb-video-player" playsinline preload="auto"></video>
        <div class="tbVideo__caption" id="tb-video-caption" hidden>
            <span class="tbVideo__label" id="tb-video-label"></span>
            <span class="tbVideo__name" id="tb-video-name"></span>
        </div>
    </div>

</div>

<script>
window.telebimConfig = <?php echo json_encode(Array(
    'api'       => $sRoot.'plugins/live/api.php',
    'filesUrl'  => $sFiles,
    'clipsUrl'  => $sClips,
    'actions'   => $config['live_actions'],
    'clips'     => $aClips,
    'replayUrl' => (string) ($config['live_replay_url'] ?? ''),
    'teams'     => Array(
        (string) $iTeam1 => Array('name' => $aTeam1['name'], 'short' => $aTeam1['short'], 'logo' => $aTeam1['logo']),
        (string) $iTeam2 => Array('name' => $aTeam2['name'], 'short' => $aTeam2['short'], 'logo' => $aTeam2['logo']),
    ),
    'team1'  => $iTeam1,
    'team2'  => $iTeam2,
    'labels' => Array(
        'halfShort' => $lang['live_half_short'],
        'noEvents'  => $lang['live_no_events'],
        'replay'    => $lang['live_replay'],
    ),
), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $sRoot; ?>templates/<?php echo $config['skin']; ?>/js/page-telebim.js?ver=<?php echo $sJsVer; ?>"></script>
</body>
</html>
