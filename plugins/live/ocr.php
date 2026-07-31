<?php
/**
 * KOSA X CMS — moduł transmisji live: OCR protokołu meczowego (Anthropic API, vision).
 *
 * Wejście: zdjęcie protokołu (JPG/PNG/WEBP) z plugins/live/cache/protocols/.
 * Wyjście: tablica ['players' => [...], 'staff' => [...]] do ekranu korekty.
 *
 * Konfiguracja (database/config.php, sekcja TRANSMISJA LIVE):
 *   $config['anthropic_api_key']   — klucz API (prawdziwy w config.secrets.php)
 *   $config['anthropic_ocr_model'] — model vision (domyślnie claude-opus-5)
 *
 * Komunikacja przez surowe cURL (projekt nie używa composera — ten sam wzorzec
 * co plugins/instagram/InstaFeed.class.php i integracja PayU).
 */

if (!defined('CUSTOMER_PAGE') && !defined('ADMIN_PAGE')) {
    exit;
}

/**
 * Schemat JSON wymuszany na modelu (structured outputs) — gwarantuje
 * poprawny JSON w odpowiedzi, bez zgadywania formatu.
 */
function liveOcrSchema()
{
    return array(
        'type' => 'object',
        'properties' => array(
            'players' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'number' => array(
                            'type' => 'string',
                            'description' => 'Numer na koszulce, dokładnie jak w protokole',
                        ),
                        'name' => array(
                            'type' => 'string',
                            'description' => 'Imię i nazwisko zawodnika, z polskimi znakami',
                        ),
                        'squad' => array(
                            'type' => 'integer',
                            'enum' => array(1, 2),
                            'description' => '1 = wyjściowa jedenastka, 2 = rezerwowy',
                        ),
                    ),
                    'required' => array('number', 'name', 'squad'),
                    'additionalProperties' => false,
                ),
            ),
            'staff' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'role' => array(
                            'type' => 'string',
                            'description' => 'Funkcja, np. Trener, Kierownik drużyny',
                        ),
                        'name' => array(
                            'type' => 'string',
                            'description' => 'Imię i nazwisko członka sztabu',
                        ),
                    ),
                    'required' => array('role', 'name'),
                    'additionalProperties' => false,
                ),
            ),
        ),
        'required' => array('players', 'staff'),
        'additionalProperties' => false,
    );
}

/**
 * Przygotowuje obraz do wysyłki: pomniejsza do 2576 px na dłuższym boku
 * (limit high-res vision) i pilnuje limitu rozmiaru base64 (~5 MB).
 * Zwraca ['data' => base64, 'media_type' => ...] albo ['error' => ...].
 */
function liveOcrPrepareImage($sPath)
{
    $aInfo = @getimagesize($sPath);
    if ($aInfo === false) {
        return array('error' => 'Nie można odczytać pliku obrazu.');
    }

    $aTypes = array(
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    );
    if (!isset($aTypes[$aInfo[2]])) {
        return array('error' => 'Nieobsługiwany format obrazu.');
    }

    $iMaxEdge = 2576;
    $iLongEdge = max($aInfo[0], $aInfo[1]);
    $bTooBig = filesize($sPath) > 4500000; // limit API dla base64 to ~5 MB

    if ($iLongEdge <= $iMaxEdge && !$bTooBig) {
        return array(
            'data'       => base64_encode(file_get_contents($sPath)),
            'media_type' => $aTypes[$aInfo[2]],
        );
    }

    // pomniejszenie przez GD + zapis do JPEG
    switch ($aInfo[2]) {
        case IMAGETYPE_JPEG: $oImage = @imagecreatefromjpeg($sPath); break;
        case IMAGETYPE_PNG:  $oImage = @imagecreatefrompng($sPath);  break;
        case IMAGETYPE_WEBP: $oImage = @imagecreatefromwebp($sPath); break;
        default:             $oImage = false;
    }
    if ($oImage === false) {
        return array('error' => 'Nie udało się przetworzyć obrazu (GD).');
    }

    $fScale  = min(1.0, $iMaxEdge / $iLongEdge);
    $iWidth  = max(1, (int) round($aInfo[0] * $fScale));
    $iHeight = max(1, (int) round($aInfo[1] * $fScale));

    $oResized = imagecreatetruecolor($iWidth, $iHeight);
    imagecopyresampled($oResized, $oImage, 0, 0, 0, 0, $iWidth, $iHeight, $aInfo[0], $aInfo[1]);
    imagedestroy($oImage);

    ob_start();
    imagejpeg($oResized, null, 85);
    $sJpeg = ob_get_clean();
    imagedestroy($oResized);

    return array('data' => base64_encode($sJpeg), 'media_type' => 'image/jpeg');
}

/**
 * Normalizuje i waliduje dane z modelu (druga linia obrony za schematem).
 */
function liveOcrNormalize($aData)
{
    $aResult = array('players' => array(), 'staff' => array());

    foreach ((array) ($aData['players'] ?? array()) as $aPlayer) {
        $sName = trim((string) ($aPlayer['name'] ?? ''));
        if ($sName === '') {
            continue;
        }
        $iSquad = (int) ($aPlayer['squad'] ?? 0);
        $aResult['players'][] = array(
            'number' => trim((string) ($aPlayer['number'] ?? '')),
            'name'   => $sName,
            'squad'  => in_array($iSquad, array(1, 2), true) ? $iSquad : 2,
        );
    }

    foreach ((array) ($aData['staff'] ?? array()) as $aStaff) {
        $sName = trim((string) ($aStaff['name'] ?? ''));
        if ($sName === '') {
            continue;
        }
        $aResult['staff'][] = array(
            'role' => trim((string) ($aStaff['role'] ?? '')),
            'name' => $sName,
        );
    }

    return $aResult;
}

/**
 * Parsuje odpowiedź Messages API do znormalizowanych danych składu.
 * Wydzielone z liveOcrProtocol(), żeby dało się testować bez sieci.
 */
function liveOcrParseResponse($aResponse)
{
    if (!is_array($aResponse)) {
        return array('ok' => false, 'error' => 'Nieprawidłowa odpowiedź API (nie-JSON).');
    }

    if (($aResponse['type'] ?? '') === 'error') {
        $sMessage = $aResponse['error']['message'] ?? 'nieznany błąd';
        return array('ok' => false, 'error' => 'Błąd API: '.$sMessage);
    }

    // klasyfikatory mogą odrzucić żądanie — HTTP 200 + stop_reason "refusal"
    if (($aResponse['stop_reason'] ?? '') === 'refusal') {
        return array('ok' => false, 'error' => 'Model odmówił przetworzenia obrazu (refusal). Spróbuj z wyraźniejszym zdjęciem protokołu.');
    }
    if (($aResponse['stop_reason'] ?? '') === 'max_tokens') {
        return array('ok' => false, 'error' => 'Odpowiedź modelu została ucięta (max_tokens). Spróbuj ponownie.');
    }

    $sJson = null;
    foreach ((array) ($aResponse['content'] ?? array()) as $aBlock) {
        if (($aBlock['type'] ?? '') === 'text') {
            $sJson = $aBlock['text'];
            break;
        }
    }
    if ($sJson === null || $sJson === '') {
        return array('ok' => false, 'error' => 'Brak treści w odpowiedzi modelu.');
    }

    $aData = json_decode($sJson, true);
    if (!is_array($aData)) {
        return array('ok' => false, 'error' => 'Model zwrócił niepoprawny JSON.');
    }

    $aData = liveOcrNormalize($aData);
    if (empty($aData['players'])) {
        return array('ok' => false, 'error' => 'Model nie rozpoznał żadnego zawodnika na zdjęciu. Sprawdź, czy to protokół meczowy.');
    }

    return array('ok' => true, 'data' => $aData);
}

/**
 * Wysyła protokół do Anthropic API (vision) i zwraca rozpoznany skład.
 *
 * @param string $sImagePath ścieżka do zdjęcia protokołu
 * @param string $sTeamName  nazwa drużyny (podpowiedź, gdy protokół ma dwie drużyny)
 * @return array ['ok' => true, 'data' => [...]] albo ['ok' => false, 'error' => ...]
 */
function liveOcrProtocol($sImagePath, $sTeamName = '')
{
    global $config;

    $sApiKey = trim((string) ($config['anthropic_api_key'] ?? ''));
    if ($sApiKey === '') {
        return array('ok' => false, 'error' => 'Brak klucza anthropic_api_key — uzupełnij database/config.secrets.php (wzorzec: config.secrets.dist.php).');
    }
    if (!is_file($sImagePath)) {
        return array('ok' => false, 'error' => 'Plik protokołu nie istnieje.');
    }

    $aImage = liveOcrPrepareImage($sImagePath);
    if (isset($aImage['error'])) {
        return array('ok' => false, 'error' => $aImage['error']);
    }

    $sPrompt = 'Na zdjęciu jest protokół meczowy piłki nożnej (polski, odręczny lub drukowany). '
        .'Wyodrębnij zawodników JEDNEJ drużyny: numer na koszulce, imię i nazwisko oraz skład '
        .'(1 = wyjściowa jedenastka / zawodnicy podstawowi, 2 = rezerwowi). '
        .'Wyodrębnij też sztab szkoleniowy (funkcja + imię i nazwisko), np. trener, asystent, kierownik drużyny, masażysta. '
        .'Przepisuj nazwiska dokładnie tak, jak są zapisane, z polskimi znakami. '
        .'Nie pomijaj nikogo z listy. '
        .($sTeamName !== ''
            ? 'Jeśli protokół zawiera dwie drużyny, wybierz tę, której nazwa najlepiej odpowiada: „'.$sTeamName.'". '
            : '')
        .'Jeśli w protokole jest tylko jedna drużyna — wyodrębnij ją.';

    $aBody = array(
        'model'      => (string) ($config['anthropic_ocr_model'] ?? 'claude-opus-5'),
        'max_tokens' => 16000,
        // fallback "default": gdy klasyfikatory odrzucą żądanie, API ponawia je
        // na modelu zastępczym po stronie serwera
        'fallbacks'  => 'default',
        'output_config' => array(
            'format' => array(
                'type'   => 'json_schema',
                'schema' => liveOcrSchema(),
            ),
        ),
        'messages' => array(
            array(
                'role'    => 'user',
                'content' => array(
                    array(
                        'type'   => 'image',
                        'source' => array(
                            'type'       => 'base64',
                            'media_type' => $aImage['media_type'],
                            'data'       => $aImage['data'],
                        ),
                    ),
                    array('type' => 'text', 'text' => $sPrompt),
                ),
            ),
        ),
    );

    $oCurl = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($oCurl, array(
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 240,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => array(
            'x-api-key: '.$sApiKey,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
            'content-type: application/json',
        ),
        CURLOPT_POSTFIELDS     => json_encode($aBody),
    ));

    $sResponse = curl_exec($oCurl);
    $sCurlErr  = curl_error($oCurl);
    curl_close($oCurl);

    if ($sResponse === false) {
        return array('ok' => false, 'error' => 'Błąd połączenia z API: '.$sCurlErr);
    }

    return liveOcrParseResponse(json_decode($sResponse, true));
}
