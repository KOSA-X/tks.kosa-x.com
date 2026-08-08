<?php
/**
 * KOSA X CMS — moduł transmisji live: wspólne gettery danych dla widoków
 * (telebim, docelowo także nakładka OBS — ta sama logika co closury
 * w page-live-overlay.php, wyniesiona do funkcji wielokrotnego użytku).
 *
 * Wszystkie zapytania przez prepared statements (§15.2).
 */

// używane też w panelu admina (Drużyny, import składu) — stąd podwójny guard
if (!defined('CUSTOMER_PAGE') && !defined('ADMIN_PAGE')) { exit; }

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

/**
 * Skład drużyny: wyjściowa 11 + rezerwa (sSquad z importu protokołu).
 * Obie grupy w porządku pozycyjnym: bramkarz → obrona → pomoc → atak
 * (linia ze slotu sLineup + formacji drużyny), w linii wg numeru.
 */
function liveSquad($iTeam)
{
    $oSql    = Sql::getInstance();
    $aGroups = Array('1' => Array(), '2' => Array());
    if ((int) $iTeam <= 0) {
        return $aGroups;
    }
    $oQuery = $oSql->prepare(
        'SELECT sName, sNumber, sSquad, sLineup FROM pages
         WHERE iPageParent = :team AND iStatus = 1 AND sSquad IN ("1","2")
         ORDER BY iPosition ASC, sName COLLATE NOCASE ASC'
    );
    $oQuery->execute(Array(':team' => (int) $iTeam));
    while ($aRow = $oQuery->fetch(PDO::FETCH_ASSOC)) {
        $aGroups[(string) $aRow['sSquad']][] = $aRow;
    }
    $sFormation = liveTeamFormation((int) $iTeam);
    $aGroups['1'] = liveSortPlayers($aGroups['1'], $sFormation);
    $aGroups['2'] = liveSortPlayers($aGroups['2'], $sFormation);
    return $aGroups;
}

/**
 * Sztab szkoleniowy = CAŁY opis pełny drużyny (importer zapisuje tam listę
 * <ul> z rolami; wszystko z opisu drużyny pokazuje się nad składem).
 */
function liveStaffBlock($iTeam)
{
    $oSql = Sql::getInstance();
    if ((int) $iTeam <= 0) {
        return '';
    }
    $oQuery = $oSql->prepare('SELECT sDescriptionFull FROM pages WHERE iPage = :page');
    $oQuery->execute(Array(':page' => (int) $iTeam));
    $sDesc = trim((string) ($oQuery->fetchColumn() ?: ''));
    return $sDesc !== '' ? parseShortcodes($sDesc) : '';
}

/**
 * Mapa slot 1-11 → linia formacji: 0 = bramkarz, 1 = obrona, dalej kolejne
 * człony nazwy formacji (ostatni = atak); 9 = slot poza znaną formacją.
 */
function liveSlotLines($sFormation)
{
    global $config;
    $aLines = Array(1 => 0);
    $iSlot  = 2;
    if ((string) $sFormation !== '' && isset($config['live_formations'][$sFormation])) {
        foreach (array_map('intval', explode('-', (string) $sFormation)) as $iIndex => $iCount) {
            for ($i = 0; $i < $iCount && $iSlot <= 11; $i++) {
                $aLines[$iSlot++] = $iIndex + 1;
            }
        }
    }
    for (; $iSlot <= 11; $iSlot++) {
        $aLines[$iSlot] = 9;
    }
    return $aLines;
}

/**
 * Sortowanie zawodników wg pozycji na boisku: NAJPIERW zawodnicy
 * z przypisanym slotem sLineup w kolejności numeracji formacji
 * (1-BR, 2-OBR, 3-OBR… 11), POTEM zawodnicy bez przypisanej pozycji —
 * ci wg numeru na koszulce (na końcu bez numeru, alfabetycznie).
 * Wiersze muszą mieć klucze sLineup/sNumber/sName.
 * Formacja nie wpływa już na porządek (slot sam niesie kolejność) —
 * parametr zostaje dla zgodności wywołań.
 */
function liveSortPlayers($aPlayers, $sFormation = '')
{
    usort($aPlayers, function ($a, $b) {
        $iSlotA = (int) ($a['sLineup'] ?? 0);
        $iSlotB = (int) ($b['sLineup'] ?? 0);
        $bSlotA = ($iSlotA >= 1 && $iSlotA <= 11);
        $bSlotB = ($iSlotB >= 1 && $iSlotB <= 11);
        if ($bSlotA !== $bSlotB) {
            return $bSlotA ? -1 : 1; // przypisani do slotu przed resztą
        }
        if ($bSlotA && $bSlotB && $iSlotA !== $iSlotB) {
            return $iSlotA <=> $iSlotB; // porządek slotów formacji: 1, 2, 3…
        }
        $iNumA = (int) ($a['sNumber'] ?? 0) ?: 999;
        $iNumB = (int) ($b['sNumber'] ?? 0) ?: 999;
        if ($iNumA !== $iNumB) {
            return $iNumA <=> $iNumB;
        }
        return strcasecmp((string) ($a['sName'] ?? ''), (string) ($b['sName'] ?? ''));
    });
    return $aPlayers;
}

/** Formacja drużyny (pages.sFormation) — pusta, gdy nieustawiona/nieznana. */
function liveTeamFormation($iTeam)
{
    global $config;
    $oSql = Sql::getInstance();
    if ((int) $iTeam <= 0) {
        return '';
    }
    $oQuery = $oSql->prepare('SELECT sFormation FROM pages WHERE iPage = :page');
    $oQuery->execute(Array(':page' => (int) $iTeam));
    $sFormation = (string) ($oQuery->fetchColumn() ?: '');
    return isset($config['live_formations'][$sFormation]) ? $sFormation : '';
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
