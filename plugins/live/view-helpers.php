<?php
/**
 * KOSA X CMS — moduł transmisji live: wspólne gettery danych dla widoków
 * (telebim, docelowo także nakładka OBS — ta sama logika co closury
 * w page-live-overlay.php, wyniesiona do funkcji wielokrotnego użytku).
 *
 * Wszystkie zapytania przez prepared statements (§15.2).
 */

if (!defined('CUSTOMER_PAGE')) { exit; }

/** Pierwszy obrazek strony (preferowany iType, potem domyślny). */
function livePageImage($iPage, $iPreferType = 0)
{
    $oSql = Sql::getInstance();
    if ((int) $iPage <= 0) {
        return '';
    }
    $oQuery = $oSql->prepare(
        'SELECT sFileName FROM files WHERE iPage = :page AND iSize > 0
         ORDER BY (iType = :type) DESC, iDefault DESC, iPosition ASC LIMIT 1'
    );
    $oQuery->execute(Array(':page' => (int) $iPage, ':type' => (int) $iPreferType));
    return (string) ($oQuery->fetchColumn() ?: '');
}

/** Wszystkie obrazki strony (galerie plansz). */
function livePageImages($iPage)
{
    $oSql = Sql::getInstance();
    if ((int) $iPage <= 0) {
        return Array();
    }
    $oQuery = $oSql->prepare('SELECT sFileName FROM files WHERE iPage = :page AND iSize > 0 ORDER BY iPosition ASC');
    $oQuery->execute(Array(':page' => (int) $iPage));
    return $oQuery->fetchAll(PDO::FETCH_COLUMN);
}

/** Dane drużyny: nazwa, skrót (sDesc lub 3 pierwsze litery), herb (iType=2 → logo). */
function liveTeamData($iTeam)
{
    $oSql  = Sql::getInstance();
    $aData = Array('name' => '—', 'short' => '—', 'logo' => '');
    if ((int) $iTeam <= 0) {
        return $aData;
    }
    $oQuery = $oSql->prepare('SELECT sName, sDesc FROM pages WHERE iPage = :page');
    $oQuery->execute(Array(':page' => (int) $iTeam));
    if ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        $aData['name']  = (string) $aRow['sName'];
        $aData['short'] = trim((string) $aRow['sDesc']) !== ''
            ? trim((string) $aRow['sDesc'])
            : mb_strtoupper(mb_substr((string) $aRow['sName'], 0, 3));
    }
    $aData['logo'] = livePageImage((int) $iTeam, 2);
    return $aData;
}

/** Skład drużyny: wyjściowa 11 + rezerwa (sSquad z importu protokołu). */
function liveSquad($iTeam)
{
    $oSql    = Sql::getInstance();
    $aGroups = Array('1' => Array(), '2' => Array());
    if ((int) $iTeam <= 0) {
        return $aGroups;
    }
    $oQuery = $oSql->prepare(
        'SELECT sName, sNumber, sSquad FROM pages
         WHERE iPageParent = :team AND iStatus = 1 AND sSquad IN ("1","2")
         ORDER BY iPosition ASC, sName COLLATE NOCASE ASC'
    );
    $oQuery->execute(Array(':team' => (int) $iTeam));
    while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        $aGroups[(string) $aRow['sSquad']][] = $aRow;
    }
    return $aGroups;
}

/** Sztab: blok .teamStaff zapisany w opisie drużyny przez importer protokołu. */
function liveStaffBlock($iTeam)
{
    $oSql = Sql::getInstance();
    if ((int) $iTeam <= 0) {
        return '';
    }
    $oQuery = $oSql->prepare('SELECT sDescriptionShort FROM pages WHERE iPage = :page');
    $oQuery->execute(Array(':page' => (int) $iTeam));
    $sDesc = (string) ($oQuery->fetchColumn() ?: '');
    return preg_match('#<div class="teamStaff">.*?</div>#s', $sDesc, $aMatch) ? $aMatch[0] : '';
}

/**
 * Cieszynki wideo zawodników drużyny: mapa iPage zawodnika → nazwa pliku klipu.
 * Klip = plik mp4/webm wgrany standardowym uploadem (iSize=0) na podstronie
 * zawodnika. Telebim preloaduje wszystkie klipy na starcie meczu (Etap 4).
 */
function liveTeamClips($iTeam)
{
    $oSql  = Sql::getInstance();
    $aClips = Array();
    if ((int) $iTeam <= 0) {
        return $aClips;
    }
    $oQuery = $oSql->prepare(
        'SELECT f.iPage, f.sFileName FROM files f
         JOIN pages p ON p.iPage = f.iPage
         WHERE p.iPageParent = :team AND f.iSize = 0
           AND (f.sFileName LIKE "%.mp4" OR f.sFileName LIKE "%.webm")
         ORDER BY f.iPosition ASC'
    );
    $oQuery->execute(Array(':team' => (int) $iTeam));
    while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        // pierwszy klip zawodnika wygrywa (iPosition ASC)
        if (!isset($aClips[(int) $aRow['iPage']])) {
            $aClips[(int) $aRow['iPage']] = (string) $aRow['sFileName'];
        }
    }
    return $aClips;
}
