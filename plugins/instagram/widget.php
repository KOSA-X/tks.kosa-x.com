<?php
/**
 * KOSA X InstaFeed — widget frontend
 * ---------------------------------------------------------------------------
 * UŻYCIE (w dowolnym szablonie):
 *   echo renderInstaFeed('main');
 *   echo renderInstaFeed('main', ['limit' => 9, 'columns' => 4, 'header' => true]);
 *
 * OPCJE (z domyślnymi):
 *   limit      int   9      — ile postów pokazać
 *   columns    int   3      — 2/3/4 (md = columns, xl = columns+1 jak galleryGrid)
 *   class      string ''    — dodatkowe klasy na kontenerze
 *   show_reels bool  true   — czy pokazywać reels/wideo
 *   header     bool  false  — nagłówek z uchwytem konta
 *
 * Widget czyta wyłącznie gotowy feed.json (przez InstaFeed::getFeed) — nigdy nie
 * dotyka API i nigdy nie ujawnia tokenu. Caption/komentarze escapowane.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/InstaFeed.class.php';

if (!function_exists('renderInstaFeed')) {

    function renderInstaFeed(string $account, array $options = []): string
    {
        $opts = array_merge([
            'limit'      => 9,
            'columns'    => 3,
            'class'      => '',
            'show_reels' => true,
            'header'     => false,
        ], $options);

        $feed  = new InstaFeed();
        $items = $feed->getFeed($account, (int) $opts['limit']);

        if (!$items) {
            return ''; // brak feedu (np. cron jeszcze nie odpalony) → nic nie renderujemy
        }

        $esc     = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        // BASE_URL nie ma trailing slasha (konwencja CMS: BASE_URL.'/images/').
        $baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '', '/') . '/plugins/instagram/';
        $safeAcc = preg_replace('/[^a-zA-Z0-9_-]/', '', $account);
        $group   = 'instafeed-' . $safeAcc;

        $cols = (int) $opts['columns'];
        if (!in_array($cols, [2, 3, 4], true)) {
            $cols = 3;
        }

        $wrapClass = trim('instaFeed instaFeed--cols-' . $cols . ' ' . $opts['class']);

        ob_start();
        ?>
        <div class="<?= $esc($wrapClass) ?>">

            <?php if (!empty($opts['header'])):
                $handle = $feed->getHandle($account); ?>
                <header class="instaFeed__header">
                    <h3 class="instaFeed__title">Instagram</h3>
                    <?php if ($handle !== ''): ?>
                        <a class="instaFeed__handle"
                           href="https://instagram.com/<?= $esc(ltrim($handle, '@')) ?>"
                           target="_blank" rel="noopener"><?= $esc($handle) ?></a>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <ul class="cardInsta__grid gallery galleryGrid">
                <?php foreach ($items as $item):
                    $isVideo = !empty($item['is_video']);
                    if ($isVideo && empty($opts['show_reels'])) {
                        continue;
                    }

                    $type      = $item['type'] ?? 'IMAGE';
                    $imgUrl    = $esc($baseUrl . ($item['local_media'] ?? ''));
                    $videoUrl  = !empty($item['local_video']) ? $esc($baseUrl . $item['local_video']) : '';
                    $permalink = $esc($item['permalink'] ?? '');
                    $likes     = (int) ($item['like_count'] ?? 0);
                    $caption   = instaFeedCaptionHtml($item, $esc);

                    // Reel z lokalnym mp4 → odtwarzanie w Fancyboxie (html5video, MIME
                    // video/mp4 wg dokumentacji Fancyboxa). Reel bez mp4 (nie pobrał się)
                    // → fallback: link do Instagrama. Zdjęcia/karuzele → Fancybox z .jpg.
                    if ($isVideo && $videoUrl !== '') {
                        // data-width/height 1080x1920 → Fancybox wyświetla reela w 9:16.
                        $open = '<a class="instaFeed__link" data-src="#cardInsta-'. $item['id'].'" data-fancybox>';
                    } elseif ($isVideo) {
                        $open = '<a class="instaFeed__link"  data-src="#cardInsta-'. $item['id'].'"'
                              . ' target="_blank" rel="noopener" aria-label="Otwórz reel na Instagramie">';
                    } else {
                        $open = '<a class="instaFeed__link" data-fancybox-slide-up data-src="#cardInsta-'. $item['id'].'" '
                              . ' href="' . $imgUrl . '" >';
                    }
                    ?>
                    <li class="galleryItem cardInsta__item cardInsta__item--<?= $esc(strtolower($type)) ?>">
                        <?= $open ?>
                           <picture class="galleryItem__image">
                               <img src="<?= $imgUrl ?>"
                                 loading="lazy" decoding="async"
                                 alt="Post Instagram <?= $esc($account) ?>">
                           </picture>
                            

                            <div class="galleryItem__desc">
                                <?php if ($isVideo): ?>
<!--                                    <span class="instaFeed__badge instaFeed__badge--reel" aria-hidden="true"></span>-->
                                <?php elseif ($type === 'CAROUSEL_ALBUM'): ?>
<!--                                    <span class="instaFeed__badge instaFeed__badge--album" aria-hidden="true"></span>-->
                                <?php endif; ?>
                                <span class="galleryItem__desc-item cardInsta__likes"><img src="<?= ICONS ?>heart.svg" alt="Likes"><?= $likes ?></span>
                            </div>
                        </a>
                        
                        <div id="cardInsta-<?php echo $item['id']; ?>" class="modal"  style="display:none" data-selectable="true">
                            <div class="card cardInsta">
                                <div class="cardInsta__wrapper">
                                    <div class="cardInsta__image">
                                        <?php if ($isVideo): ?>
                                                <?php // reel: dźwięk + pętla; autoplay po otwarciu popupa i stop+zerowanie
                                                      // po zamknięciu steruje initInstaReelVideos() w scripts.js
                                                      // (IntersectionObserver na .cardInsta__video) ?>
                                                <video
                                                    class="cardInsta__video"
                                                    loop
                                                    playsinline
                                                    preload="none"
                                                    controls
                                                    poster="<?= $imgUrl ?>"
                                                >
                                                    <source src="<?php echo $videoUrl; ?>" type="video/mp4">
                                                    Twoja przeglądarka nie obsługuje elementu wideo.
                                                </video>
                                            <?php else: ?>
                                              <img src="<?= $imgUrl ?>" alt="">
                                            <?php endif; ?>
                                    </div>
                                    <div class="cardInsta__content">
                                        <div class="card__wrapper">
                                            <?php echo $caption; ?>
                                        </div>
                                    </div>
                                </div>

                            </div>
                         </div>

                    </li>
                    
                     

                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Buduje HTML captionu Fancyboxa dla jednego posta i escapuje CAŁOŚĆ pod
     * atrybut data-caption. Dane zewnętrzne (opis, komentarze) są escapowane
     * najpierw jako treść, potem raz jeszcze jako wartość atrybutu — przeglądarka
     * dekoduje atrybut, a Fancybox wstawia wynik jako HTML. Zero ryzyka XSS:
     * treść usera nigdy nie staje się aktywnym HTML-em.
     */
    function instaFeedCaptionHtml(array $item, callable $esc): string
    {
        $parts = [];

        $caption = trim((string) ($item['caption'] ?? ''));
        if ($caption !== '') {
            $short = mb_substr($caption, 0, 300);
            if (mb_strlen($caption) > 300) {
                $short .= '…';
            }
            $parts[] = '<div class="card__content">' . nl2br($esc($short)) . '</div>';
        }

        $likes = (int) ($item['like_count'] ?? 0);
        $parts[] = '<footer class="card__footer">';
        $parts[] = '<div class="cardInsta__likes"><img src="'.ICONS.'heart.svg" alt="Likes"> ' . $likes . ' polubień</div>';

        if (!empty($item['comments']) && is_array($item['comments'])) {
            $c = '<ul class="card__comments">';
            foreach ($item['comments'] as $com) {
                $user = $esc($com['username'] ?? '');
                $text = $esc($com['text'] ?? '');
                if ($user === '' && $text === '') {
                    continue;
                }
                $c .= '<li><strong>' . $user . '</strong> ' . $text . '</li>';
            }
            $c .= '</ul>';
            $parts[] = $c;
        }

        $permalink = (string) ($item['permalink'] ?? '');
        if ($permalink !== '') {
            $parts[] = '<a class="button button-xs button-light" href="' . $esc($permalink) . '"'
                     . ' target="_blank" rel="noopener"><img src="'.ICONS.'instagram.svg">Instagram</a>';
        }
        $parts[] = '</footer>';

        // Owijamy w .instaCaption (kartę stylowaną w CSS niezależnie od mainClass
        // Fancyboxa). Całość escapowana pod atrybut data-caption (przeglądarka zdekoduje).
        return  implode('', $parts);
    }
}
