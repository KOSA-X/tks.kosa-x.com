<?php
/**
 * KOSA X InstaFeed — silnik modułu (Instagram Graph API → lokalny cache)
 * ---------------------------------------------------------------------------
 * Cała komunikacja z API jest server-side. URL-e mediów z IG wygasają, więc
 * obrazy są pobierane i hostowane lokalnie w cache/{account}/media/.
 *
 * Przepływ (wywoływany z cron.php): refreshTokenIfNeeded → fetchFeed →
 * downloadMedia → saveFeed. Fail-safe: gdy API padnie, stary feed.json zostaje.
 *
 * Frontend (widget.php) czyta wyłącznie gotowy feed.json — nie dotyka API.
 * ---------------------------------------------------------------------------
 */

class InstaFeed
{
    /** @var array pełna konfiguracja modułu (cron_secret + accounts) */
    private $config;

    /** @var string ścieżka do katalogu modułu (z trailing slash) */
    private $baseDir;

    /** @var string ścieżka do katalogu cache (z trailing slash) */
    private $cacheDir;

    /** próg odświeżenia tokenu w dniach (long-lived żyje 60 dni) */
    const TOKEN_REFRESH_DAYS = 45;

    /** timeout zapytań do API/CDN w sekundach */
    const HTTP_TIMEOUT = 20;

    /** timeout pobierania pliku wideo (mp4 bywa cięższy) w sekundach */
    const VIDEO_TIMEOUT = 60;

    /** ile komentarzy trzymamy per post */
    const MAX_COMMENTS = 10;

    public function __construct(?array $configOverride = null)
    {
        $this->baseDir  = __DIR__ . '/';
        $this->cacheDir = $this->baseDir . 'cache/';

        if ($configOverride !== null) {
            $this->config = $configOverride; // wstrzyknięcie (np. testy)
        } else {
            // Sterowanie w database/config_pl.php:
            //   $config['instagram_accounts'] + $config['instagram_cron_secret']
            $cms = $GLOBALS['config'] ?? [];
            $this->config = [
                'cron_secret' => $cms['instagram_cron_secret'] ?? '',
                'accounts'    => $cms['instagram_accounts'] ?? [],
            ];
        }
    }

    // ======================================================================
    // KONFIGURACJA / KONTA
    // ======================================================================

    /** @return string[] lista kluczy kont */
    public function getAccountNames(): array
    {
        return array_keys($this->config['accounts'] ?? []);
    }

    public function getCronSecret(): string
    {
        return (string) ($this->config['cron_secret'] ?? '');
    }

    private function accountConfig(string $account): ?array
    {
        return $this->config['accounts'][$account] ?? null;
    }

    /** Uchwyt do wyświetlenia w nagłówku widgetu (np. @nazwa_konta). */
    public function getHandle(string $account): string
    {
        return (string) ($this->accountConfig($account)['handle'] ?? '');
    }

    // ======================================================================
    // ŚCIEŻKI
    // ======================================================================

    private function accountDir(string $account): string
    {
        return $this->cacheDir . $this->safeName($account) . '/';
    }

    private function mediaDir(string $account): string
    {
        return $this->accountDir($account) . 'media/';
    }

    private function feedPath(string $account): string
    {
        return $this->accountDir($account) . 'feed.json';
    }

    private function tokenPath(string $account): string
    {
        return $this->accountDir($account) . 'token.json';
    }

    /** Sanityzacja nazwy konta użytej jako fragment ścieżki. */
    private function safeName(string $account): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $account);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /** Czy dany media_type to wideo (VIDEO lub REELS). */
    private function isVideoType(?string $type): bool
    {
        return in_array($type, ['VIDEO', 'REELS'], true);
    }

    /**
     * Dopisuje linię do cache/cron.log (ten sam plik, co cron.php).
     * Używane do logowania błędów pobrania mp4 i usunięć plików.
     */
    private function log(string $msg): void
    {
        $file = $this->cacheDir . 'cron.log';
        $this->ensureDir($this->cacheDir);
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    }

    // ======================================================================
    // TOKEN (runtime token.json nadrzędny wobec config.php)
    // ======================================================================

    private function getToken(string $account): ?string
    {
        $tokenFile = $this->tokenPath($account);
        if (is_file($tokenFile)) {
            $data = json_decode((string) file_get_contents($tokenFile), true);
            if (!empty($data['access_token'])) {
                return (string) $data['access_token'];
            }
        }
        $cfg = $this->accountConfig($account);
        return $cfg['access_token'] ?? null;
    }

    private function getTokenUpdatedAt(string $account): string
    {
        $tokenFile = $this->tokenPath($account);
        if (is_file($tokenFile)) {
            $data = json_decode((string) file_get_contents($tokenFile), true);
            if (!empty($data['token_updated_at'])) {
                return (string) $data['token_updated_at'];
            }
        }
        $cfg = $this->accountConfig($account);
        return (string) ($cfg['token_updated_at'] ?? date('Y-m-d'));
    }

    /**
     * Odśwież token, jeśli od ostatniej aktualizacji minęło > TOKEN_REFRESH_DAYS.
     * Zwraca true, gdy token został odświeżony (lub nie wymagał odświeżenia).
     */
    public function refreshTokenIfNeeded(string $account): bool
    {
        $updatedAt = strtotime($this->getTokenUpdatedAt($account));
        if ($updatedAt === false) {
            $updatedAt = time();
        }
        $ageDays = (time() - $updatedAt) / 86400;

        if ($ageDays <= self::TOKEN_REFRESH_DAYS) {
            return true; // jeszcze świeży
        }

        $token = $this->getToken($account);
        if (!$token) {
            return false;
        }

        $url = 'https://graph.instagram.com/refresh_access_token'
             . '?grant_type=ig_refresh_token'
             . '&access_token=' . urlencode($token);

        $resp = $this->httpGet($url);
        if ($resp === null) {
            return false;
        }

        $data = json_decode($resp, true);
        if (empty($data['access_token'])) {
            return false;
        }

        $this->ensureDir($this->accountDir($account));
        $ok = file_put_contents($this->tokenPath($account), json_encode([
            'access_token'     => $data['access_token'],
            'token_updated_at' => date('Y-m-d'),
            'expires_in'       => $data['expires_in'] ?? null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $ok !== false;
    }

    // ======================================================================
    // POBRANIE FEEDU Z API
    // ======================================================================

    /**
     * Pobiera surowe posty z Graph API. Zwraca tablicę 'data' lub null przy błędzie.
     * Null => wywołujący NIE nadpisuje istniejącego feed.json (fail-safe).
     */
    public function fetchFeed(string $account): ?array
    {
        $cfg = $this->accountConfig($account);
        if (!$cfg || empty($cfg['ig_user_id'])) {
            return null;
        }

        $token  = $this->getToken($account);
        $userId = $cfg['ig_user_id'];
        $limit  = (int) ($cfg['limit'] ?? 15);
        if ($limit < 1) {
            $limit = 15;
        }

        if (!$token || $token === 'LONG_LIVED_TOKEN_HERE' || $userId === 'IG_USER_ID_HERE') {
            return null; // brak realnych danych dostępowych
        }

        $fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,'
                . 'like_count,comments_count,'
                . 'comments.limit(' . self::MAX_COMMENTS . '){text,username,timestamp},'
                . 'children{media_url,media_type}';

        $url = 'https://graph.instagram.com/' . rawurlencode($userId) . '/media'
             . '?fields=' . rawurlencode($fields)
             . '&limit=' . $limit
             . '&access_token=' . urlencode($token);

        $resp = $this->httpGet($url);
        if ($resp === null) {
            return null;
        }

        $data = json_decode($resp, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            return null; // błąd API / struktura nieoczekiwana
        }

        return $data['data'];
    }

    // ======================================================================
    // NORMALIZACJA + POBRANIE MEDIÓW + ZAPIS
    // ======================================================================

    /**
     * Pełna aktualizacja jednego konta. Zwraca ['ok'=>bool, 'account'=>..., 'count'=>int, 'error'=>?string].
     * Używane przez cron.php.
     */
    public function updateAccount(string $account): array
    {
        if (!$this->accountConfig($account)) {
            return ['ok' => false, 'account' => $account, 'count' => 0, 'error' => 'nieznane konto'];
        }

        $this->refreshTokenIfNeeded($account);

        $raw = $this->fetchFeed($account);
        if ($raw === null) {
            // Fail-safe: nie ruszamy istniejącego feed.json.
            return ['ok' => false, 'account' => $account, 'count' => 0, 'error' => 'API niedostępne / brak danych'];
        }

        $normalized = $this->normalize($account, $raw);

        // Bezpiecznik: pusty feed z API to najczęściej błąd pobierania, nie realny
        // brak postów. NIE nadpisujemy feed.json i NIE czyścimy cache.
        if (count($normalized) === 0) {
            $this->log('OSTRZEZENIE konto=' . $account . ': pusty feed z API — pomijam zapis i czyszczenie (mozliwy blad pobierania)');
            return ['ok' => false, 'account' => $account, 'count' => 0, 'error' => 'pusty feed — pominięto (możliwy błąd API)'];
        }

        $this->downloadMedia($account, $normalized);
        $ok = $this->saveFeed($account, $normalized);

        // Po zapisaniu feed.json: usuń z dysku pliki postów, które wypadły poza limit.
        $this->cleanupMedia($account, $normalized);

        return [
            'ok'      => $ok,
            'account' => $account,
            'count'   => count($normalized),
            'error'   => $ok ? null : 'zapis feed.json nieudany',
        ];
    }

    /**
     * Normalizacja surowych postów do lekkiej struktury feed.json.
     * Ustala też REMOTE URL obrazu okładki (cover) do późniejszego pobrania.
     */
    private function normalize(string $account, array $raw): array
    {
        $out = [];

        foreach ($raw as $post) {
            if (empty($post['id'])) {
                continue;
            }

            $id   = (string) $post['id'];
            $type = $post['media_type'] ?? 'IMAGE';

            // Obraz okładki (grid):
            //  IMAGE          → media_url
            //  VIDEO / REELS  → thumbnail_url (okładka z API; media_url to mp4)
            //  CAROUSEL_ALBUM → media_url pierwszego dziecka (lub thumbnail)
            $coverRemote = null;
            $videoRemote = null;     // bezpośredni mp4 dla wideo/reelsów
            $children    = [];

            if ($this->isVideoType($type)) {
                // Okładka WYŁĄCZNIE z thumbnail_url zwróconego przez API
                // (nie generujemy miniatury z klatki wideo).
                $coverRemote = $post['thumbnail_url'] ?? null;
                $videoRemote = $post['media_url'] ?? null;
            } elseif ($type === 'CAROUSEL_ALBUM') {
                if (!empty($post['children']['data']) && is_array($post['children']['data'])) {
                    foreach ($post['children']['data'] as $child) {
                        $childType = $child['media_type'] ?? 'IMAGE';
                        $childUrl  = ($childType === 'VIDEO')
                            ? ($child['thumbnail_url'] ?? ($child['media_url'] ?? null))
                            : ($child['media_url'] ?? null);
                        if ($childUrl) {
                            $children[] = ['id' => $child['id'] ?? md5($childUrl), 'remote' => $childUrl];
                        }
                    }
                }
                $coverRemote = $children[0]['remote'] ?? ($post['media_url'] ?? null);
            } else { // IMAGE
                $coverRemote = $post['media_url'] ?? null;
            }

            // Komentarze (max MAX_COMMENTS)
            $comments = [];
            if (!empty($post['comments']['data']) && is_array($post['comments']['data'])) {
                foreach (array_slice($post['comments']['data'], 0, self::MAX_COMMENTS) as $c) {
                    $comments[] = [
                        'username'  => (string) ($c['username'] ?? ''),
                        'text'      => (string) ($c['text'] ?? ''),
                        'timestamp' => (string) ($c['timestamp'] ?? ''),
                    ];
                }
            }

            $out[] = [
                'id'             => $id,
                'type'           => $type,
                'caption'        => (string) ($post['caption'] ?? ''),
                'permalink'      => (string) ($post['permalink'] ?? ''),
                'timestamp'      => (string) ($post['timestamp'] ?? ''),
                'like_count'     => (int) ($post['like_count'] ?? 0),
                'comments_count' => (int) ($post['comments_count'] ?? 0),
                'comments'       => $comments,
                'cover_remote'   => $coverRemote,                 // tymczasowe, do pobrania
                'video_remote'   => $videoRemote,                 // tymczasowe, mp4 do pobrania
                'local_media'    => 'cache/' . $this->safeName($account) . '/media/' . $id . '.jpg',
                'children'       => $children,                    // do pobrania (galeria karuzeli)
                'is_video'       => $this->isVideoType($type),
            ];
        }

        return $out;
    }

    /**
     * Pobiera okładki (thumbnail), mp4 wideo i dzieci karuzeli do
     * cache/{account}/media/. Pobiera tylko brakujące pliki. Czyszczenie starych
     * plików robi osobno cleanupMedia() (po saveFeed).
     */
    public function downloadMedia(string $account, array $normalized): void
    {
        $mediaDir = $this->mediaDir($account);
        $this->ensureDir($mediaDir);

        $cfg = $this->accountConfig($account);
        $coverFromPermalink = !empty($cfg['cover_from_permalink']);

        foreach ($normalized as $item) {
            $id   = $item['id'];
            $file = $mediaDir . $id . '.jpg';

            // Okładka. Dla wideo z cover_from_permalink: CUSTOMOWA okładka z og:image
            // permalinku (API/thumbnail_url zwraca klatkę wideo, nie okładkę z apki).
            // Fallback → thumbnail_url. Pobieramy tylko dla nowych postów (brak pliku).
            if (!is_file($file)) {
                $coverUrl = $item['cover_remote'] ?? null;
                if ($coverFromPermalink && !empty($item['is_video']) && !empty($item['permalink'])) {
                    $reason = '';
                    $og = $this->fetchCoverUrl($item['permalink'], $reason);
                    if ($og !== null) {
                        $coverUrl = $og;
                        $this->log('COVER konto=' . $account . ' id=' . $id . ' src=' . $reason);
                    } else {
                        $this->log('OG-IMAGE brak konto=' . $account . ' id=' . $id . ' powod=' . $reason . ' — fallback thumbnail');
                    }
                }
                if (!empty($coverUrl)) {
                    $this->downloadImage($coverUrl, $file);
                }
            }

            // Wideo/reels: bezpośredni mp4 → {id}.mp4 (pliki 1:1 z API, bez re-enkodowania)
            if (!empty($item['is_video']) && !empty($item['video_remote'])) {
                $videoFile = $mediaDir . $id . '.mp4';
                if (!is_file($videoFile)) {
                    if (!$this->downloadBinary($item['video_remote'], $videoFile)) {
                        // Fail-safe: zostaje sama miniaturka; nie przerywamy cyklu.
                        $this->log('BLAD mp4 konto=' . $account . ' id=' . $id . ' (zostaje miniaturka)');
                    }
                }
            }

            // Dzieci karuzeli: {postId}_{n}.jpg
            if (!empty($item['children'])) {
                foreach ($item['children'] as $i => $child) {
                    $childFile = $mediaDir . $id . '_' . $i . '.jpg';
                    if (!is_file($childFile) && !empty($child['remote'])) {
                        $this->downloadImage($child['remote'], $childFile);
                    }
                }
            }
        }

        // Czyszczenie starych plików → cleanupMedia() (wołane po saveFeed).
    }

    /**
     * Usuwa z cache/{account}/media/ pliki (.jpg i .mp4), których media_id nie ma
     * już w bieżącym feedzie (posty, które wypadły poza limit). Loguje każde usunięcie.
     *
     * Bezpiecznik: przy pustym feedzie NIE czyścimy (obsłużone też w updateAccount) —
     * pusty feed to prawdopodobnie błąd API, nie faktyczny brak postów.
     */
    public function cleanupMedia(string $account, array $normalized): void
    {
        if (count($normalized) === 0) {
            $this->log('OSTRZEZENIE konto=' . $account . ': pusty feed — pomijam czyszczenie');
            return;
        }

        $mediaDir = $this->mediaDir($account);

        // Zbiór dozwolonych nazw plików wynikający z bieżącego feedu.
        $keep = [];
        foreach ($normalized as $item) {
            $id = $item['id'];
            $keep[$id . '.jpg'] = true;
            if (!empty($item['is_video'])) {
                $keep[$id . '.mp4'] = true;
            }
            if (!empty($item['children'])) {
                foreach ($item['children'] as $i => $child) {
                    $keep[$id . '_' . $i . '.jpg'] = true;
                }
            }
        }

        foreach (glob($mediaDir . '*.{jpg,mp4}', GLOB_BRACE) ?: [] as $existing) {
            $base = basename($existing);
            if (empty($keep[$base])) {
                if (@unlink($existing)) {
                    $this->log('USUNIETO konto=' . $account . ' plik=' . $base);
                }
            }
        }
    }

    /**
     * Zapis znormalizowanego feedu do feed.json (bez pól tymczasowych).
     */
    public function saveFeed(string $account, array $normalized): bool
    {
        $this->ensureDir($this->accountDir($account));
        $mediaDir = $this->mediaDir($account);
        $webBase  = 'cache/' . $this->safeName($account) . '/media/';

        $clean = array_map(function ($item) use ($mediaDir, $webBase) {
            unset($item['cover_remote'], $item['video_remote']); // pola tymczasowe

            // local_video tylko gdy mp4 faktycznie się pobrał — inaczej wpis
            // zostaje z samą miniaturką (fail-safe z downloadMedia).
            if (!empty($item['is_video']) && is_file($mediaDir . $item['id'] . '.mp4')) {
                $item['local_video'] = $webBase . $item['id'] . '.mp4';
            }

            // znormalizuj ścieżki dzieci karuzeli na lokalne
            if (!empty($item['children'])) {
                $localChildren = [];
                foreach ($item['children'] as $i => $child) {
                    $localChildren[] = $webBase . $item['id'] . '_' . $i . '.jpg';
                }
                $item['children'] = $localChildren;
            }
            return $item;
        }, $normalized);

        $json = json_encode([
            'updated_at' => date('c'),
            'items'      => array_values($clean),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return file_put_contents($this->feedPath($account), $json) !== false;
    }

    // ======================================================================
    // ODCZYT DLA FRONTENDU
    // ======================================================================

    /**
     * Czyta gotowy feed.json (server-side). Zwraca listę itemów (max $limit).
     * Nigdy nie dotyka API.
     */
    public function getFeed(string $account, int $limit = 0): array
    {
        $file = $this->feedPath($account);
        if (!is_file($file)) {
            return [];
        }

        $data  = json_decode((string) file_get_contents($file), true);
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    // ======================================================================
    // HTTP (cURL) — pomocnicze
    // ======================================================================

    /** GET zwracający body lub null przy błędzie/HTTP != 200. */
    private function httpGet(string $url, ?string $userAgent = null): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => $userAgent ?? 'KOSA-X-InstaFeed/1.0',
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            return null;
        }

        return (string) $body;
    }

    /** Pobiera obraz do pliku. Zwraca true przy sukcesie. */
    private function downloadImage(string $url, string $destFile): bool
    {
        $body = $this->httpGet($url);
        if ($body === null || $body === '') {
            return false;
        }
        // prosta walidacja: to musi być obraz
        $info = @getimagesizefromstring($body);
        if ($info === false) {
            return false;
        }
        return file_put_contents($destFile, $body) !== false;
    }

    /**
     * Pobiera plik binarny (np. mp4) STRUMIENIOWO do pliku (bez trzymania w RAM).
     * Zapis atomowy przez .part → rename, żeby nie zostawić uciętego mp4.
     * Zwraca true przy sukcesie.
     */
    private function downloadBinary(string $url, string $destFile): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $tmp = $destFile . '.part';
        $fp  = @fopen($tmp, 'wb');
        if (!$fp) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_TIMEOUT        => self::VIDEO_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'KOSA-X-InstaFeed/1.0',
        ]);

        $ok   = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $code !== 200 || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return false;
        }

        return @rename($tmp, $destFile);
    }

    /**
     * Best-effort: pobiera CUSTOMOWĄ okładkę reela z og:image strony permalinku.
     * Powód: Instagram API (thumbnail_url) zwraca klatkę wideo, a NIE okładkę
     * ustawioną w apce. og:image na stronie permalinku to obraz, który IG pokazuje
     * w podglądzie linku — czyli ta customowa okładka.
     *
     * Zwraca URL obrazu z CDN Instagrama albo null (wtedy wołający robi fallback
     * do thumbnail_url). Może zawieść, jeśli IG poda login-wall zamiast og-tagów.
     */
    private function fetchCoverUrl(string $permalink, string &$reason = ''): ?string
    {
        if ($permalink === '') {
            $reason = 'brak-permalink';
            return null;
        }

        $base      = rtrim($permalink, '/');
        $browserUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                   . '(KHTML, like Gecko) Chrome/124.0 Safari/537.36';

        // 1) Strona /embed/ — PUBLICZNY endpoint do osadzania (nie jest login-walled),
        //    zawiera CZYSTĄ okładkę w wysokiej rozdzielczości (image_versions2 /
        //    display_url / EmbeddedMediaImage) — bez wpalonego przycisku play.
        foreach (['/embed/captioned/', '/embed/'] as $suffix) {
            $embed = $this->httpGet($base . $suffix, $browserUA);
            if ($embed === null) {
                continue;
            }

            $best = $this->pickBestCandidate($embed);
            if ($best !== null) {
                $reason = 'embed:image_versions2';
                return $best;
            }

            if (preg_match('/"display_url":"(https:[^"]+)"/', $embed, $m)) {
                $url = json_decode('"' . $m[1] . '"');
                if (is_string($url) && stripos($url, 'scontent') !== false) {
                    $reason = 'embed:display_url';
                    return $url;
                }
            }

            // <img class="...EmbeddedMediaImage..." src="..."> (poster okładki embedu)
            if (preg_match('/<img[^>]+class="[^"]*EmbeddedMediaImage[^"]*"[^>]*\ssrc="([^"]+)"/i', $embed, $m)
                || preg_match('/<img[^>]+\ssrc="([^"]+)"[^>]*class="[^"]*EmbeddedMediaImage[^"]*"/i', $embed, $m)) {
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
                if (stripos($url, 'scontent') !== false) {
                    $reason = 'embed:img';
                    return $url;
                }
            }
        }

        // 2) FALLBACK: og:image z UA crawlera (customowa okładka, ale z wpalonym
        //    przyciskiem play i w niższej rozdzielczości).
        $fbUA = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
        $html = $this->httpGet($base . '/', $fbUA);
        if ($html !== null
            && (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
                || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m))) {
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            if (preg_match('~^https?://[^"\']*scontent~i', $url)) {
                $reason = 'og:image';
                return $url;
            }
        }

        $reason = 'brak-cover';
        return null;
    }

    /**
     * Wybiera URL okładki o NAJWYŻSZEJ rozdzielczości z osadzonego
     * "image_versions2":{"candidates":[{"url":..,"width":..},..]}.
     * To czysta okładka (bez play buttona). Zwraca null, gdy brak danych.
     */
    private function pickBestCandidate(string $html): ?string
    {
        if (!preg_match('/"image_versions2"\s*:\s*\{\s*"candidates"\s*:\s*\[(.*?)\]/s', $html, $mm)) {
            return null;
        }

        preg_match_all('/"url"\s*:\s*"(https:[^"]+?)"\s*,\s*"width"\s*:\s*(\d+)/', $mm[1], $cands, PREG_SET_ORDER);

        $bestUrl = null;
        $bestW   = 0;
        foreach ($cands as $c) {
            $w = (int) $c[2];
            $u = json_decode('"' . $c[1] . '"');   // odkodowanie \/ i \uXXXX
            if (is_string($u) && stripos($u, 'scontent') !== false && $w > $bestW) {
                $bestW   = $w;
                $bestUrl = $u;
            }
        }

        return $bestUrl;
    }
}
