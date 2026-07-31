<?php
/**
 * Shortcode Parser dla CMS
 * Pozwala na zagniezdanie funkcji PHP w tresci edytowanej przez TinyMCE
 *
 * Uzycie:
 * [listPages id="5"]                    - lista podstron strony o id 5
 * [listPages id="5" class="my-class"]   - z dodatkowa klasa CSS
 * [listMenu id="1"]                     - menu nr 1
 * [listFaq id="10"]                     - FAQ ze strony 10
 * [listPagesPopup id="32"]              - popup widget
 * [gallery id="5"]                      - galeria zdjec strony
 * [slider id="1"]                       - slider
 * [contact]                             - formularz kontaktowy
 * [socialMedia]                         - ikony social media
 * [sitemap]                             - mapa strony
 */

if (!defined('CUSTOMER_PAGE') && !defined('ADMIN_PAGE')) {
    exit('Quick.Cms by OpenSolution.org');
}

class Shortcode
{
    private static ?Shortcode $instance = null;

    /**
     * Zarejestrowane shortcodes
     * @var array
     */
    private array $shortcodes = [];

    /**
     * Singleton
     */
    public static function getInstance(): Shortcode
    {
        if (self::$instance === null) {
            self::$instance = new Shortcode();
        }
        return self::$instance;
    }

    /**
     * Konstruktor - rejestruje domyslne shortcodes
     */
    private function __construct()
    {
        $this->registerDefaultShortcodes();
    }

    /**
     * Rejestruje domyslne shortcodes
     */
    private function registerDefaultShortcodes(): void
    {
        // Lista stron
        $this->register('listPages', function($attrs) {
            $oPage = Pages::getInstance();
            $id = (int)($attrs['id'] ?? 0);
            if ($id <= 0) return '';

            $params = [];
            if (!empty($attrs['class'])) $params['class'] = $attrs['class'];
            if (!empty($attrs['type'])) $params['type'] = $attrs['type'];
            if (!empty($attrs['per_page'])) $params['per_page'] = (int)$attrs['per_page'];
            if (isset($attrs['footer'])) $params['footer'] = $attrs['footer'] === 'true' || $attrs['footer'] === '1';
            if (isset($attrs['random'])) $params['random'] = $attrs['random'] === 'true' || $attrs['random'] === '1';

            return $oPage->listPages($id, $params);
        });

        // Menu
        $this->register('listMenu', function($attrs) {
            $oPage = Pages::getInstance();
            $id = (int)($attrs['id'] ?? 1);

            $params = [];
            if (!empty($attrs['class'])) $params['sClassName'] = $attrs['class'];
            if (!empty($attrs['depth'])) $params['iDepthLimit'] = (int)$attrs['depth'];
            if (isset($attrs['expanded'])) $params['bExpanded'] = $attrs['expanded'] === 'true' || $attrs['expanded'] === '1';

            return $oPage->listPagesMenu($id, $params);
        });

        // FAQ
        $this->register('listFaq', function($attrs) {
            $oPage = Pages::getInstance();
            $id = (int)($attrs['id'] ?? 0);
            if ($id <= 0) return '';

            return $oPage->listFaq($id);
        });

        // Popup widget
        $this->register('listPagesPopup', function($attrs) {
            $oPage = Pages::getInstance();
            $id = (int)($attrs['id'] ?? 32);

            return $oPage->listPagesPopup($id);
        });

        // Galeria zdjec
        $this->register('gallery', function($attrs) {
            $oFile = Files::getInstance();
            $id = (int)($attrs['id'] ?? 0);
            if ($id <= 0) return '';

            $params = [
                'iType' => (int)($attrs['type'] ?? 1),
                'class' => $attrs['class'] ?? 'galleryGrid'
            ];

            if (isset($attrs['full_image'])) $params['full_image'] = $attrs['full_image'] === 'true';
            if (isset($attrs['slider'])) $params['slider'] = $attrs['slider'] === 'true';

            return $oFile->listImages($id, $params);
        });

        // Slider
        $this->register('slider', function($attrs) {
            global $oSlider;
            $id = (int)($attrs['id'] ?? 1);

            if (isset($oSlider) && method_exists($oSlider, 'listSlides')) {
                return $oSlider->listSlides($id);
            }
            return '';
        });

        // Social media
        $this->register('socialMedia', function($attrs) {
            $params = [];
            if (isset($attrs['contacts'])) $params['contacts'] = $attrs['contacts'] === 'true';
            return socialMedia($params);
        });

        // Kontakty
        $this->register('contacts', function($attrs) {
            $params = [];
            if (isset($attrs['phone'])) $params['phone'] = $attrs['phone'] === 'true';
            if (isset($attrs['email'])) $params['email'] = $attrs['email'] === 'true';
            if (isset($attrs['location'])) $params['location'] = $attrs['location'] === 'true';
            if (isset($attrs['hours'])) $params['hours'] = $attrs['hours'] === 'true';
            return contacts($params);
        });

        // Mapa strony
        $this->register('sitemap', function($attrs) {
            return renderSiteMap();
        });

        // Godziny otwarcia
        $this->register('hoursOpen', function($attrs) {
            $showList = !isset($attrs['list']) || $attrs['list'] === 'true';
            return hoursOpen($showList);
        });

        // Widget menu (lista stron z SQL)
        $this->register('listMenuWidget', function($attrs) {
            $params = [];
            if (!empty($attrs['sql'])) $params['sql'] = $attrs['sql'];
            if (!empty($attrs['title'])) $params['title'] = $attrs['title'];
            if (!empty($attrs['class'])) $params['class'] = $attrs['class'];
            if (!empty($attrs['limit'])) $params['limit'] = (int)$attrs['limit'];
            if (isset($attrs['toggle'])) $params['toggle'] = $attrs['toggle'] === 'true';
            if (isset($attrs['random'])) $params['random'] = $attrs['random'] === 'true';

            return listMenu($params);
        });

        // Strona (tresc innej strony)
        $this->register('page', function($attrs) {
            $oPage = Pages::getInstance();
            $id = (int)($attrs['id'] ?? 0);
            if ($id <= 0) return '';

            $pageData = $oPage->throwPage($id);
            if (!$pageData) return '';

            $field = $attrs['field'] ?? 'sDescriptionFull';
            return $pageData[$field] ?? '';
        });

        // Link do strony
        $this->register('link', function($attrs) {
            $id = (int)($attrs['id'] ?? 0);
            if ($id <= 0) return '';

            $text = $attrs['text'] ?? null;
            $class = $attrs['class'] ?? '';

            $oPage = Pages::getInstance();
            $pageData = $oPage->aPages[$id] ?? null;
            if (!$pageData) return '';

            $linkText = $text ?? $pageData['sName'];
            $classAttr = $class ? ' class="'.html( (string) $class ).'"' : '';

            return '<a href="'.html( (string) $pageData['sLinkName'] ).'"'.$classAttr.'>'.html( (string) $linkText ).'</a>';
        });

        // URL strony
        $this->register('url', function($attrs) {
            $id = (int)($attrs['id'] ?? 0);
            if ($id <= 0) return '';
            return getUrl($id) ?? '';
        });

        // Obrazek domyslny strony
        $this->register('image', function($attrs) {
            $oFile = Files::getInstance();
            $id = (int)($attrs['id'] ?? 0);
            if ($id <= 0) return '';

            $params = [];
            if (!empty($attrs['class'])) $params['sClassName'] = $attrs['class'];
            if (isset($attrs['full_size'])) $params['full_size'] = $attrs['full_size'] === 'true';
            if (!empty($attrs['link'])) $params['sLink'] = $attrs['link'];

            return $oFile->getDefaultImage($id, $params);
        });

        // Dark mode toggle
        $this->register('darkMode', function($attrs) {
            return darkMode();
        });

        // Ikona wyszukiwania
        $this->register('searchIcon', function($attrs) {
            return searchIcon();
        });

        // Ikona uzytkownika
        $this->register('userIcon', function($attrs) {
            return userIcon();
        });

        // Jezyk
        $this->register('language', function($attrs) {
            return language();
        });
    }

    /**
     * Rejestruje nowy shortcode
     *
     * @param string $tag Nazwa shortcode (np. 'gallery')
     * @param callable $callback Funkcja callback przyjmujaca tablice atrybutow
     */
    public function register(string $tag, callable $callback): void
    {
        $this->shortcodes[$tag] = $callback;
    }

    /**
     * Usuwa zarejestrowany shortcode
     *
     * @param string $tag Nazwa shortcode
     */
    public function unregister(string $tag): void
    {
        unset($this->shortcodes[$tag]);
    }

    /**
     * Sprawdza czy shortcode jest zarejestrowany
     *
     * @param string $tag Nazwa shortcode
     * @return bool
     */
    public function exists(string $tag): bool
    {
        return isset($this->shortcodes[$tag]);
    }

    /**
     * Zwraca liste zarejestrowanych shortcodes
     *
     * @return array
     */
    public function getRegistered(): array
    {
        return array_keys($this->shortcodes);
    }

    /**
     * Parsuje tresc i zamienia shortcodes na ich wyniki
     *
     * @param string|null $content Tresc do sparsowania
     * @return string
     */
    public function parse(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Wzorzec: [tag] lub [tag attr="value" attr2="value2"]
        // Obsluguje tez [tag /] i [tag][/tag]
        $pattern = '/\[([a-zA-Z_][a-zA-Z0-9_]*)((?:\s+[a-zA-Z_][a-zA-Z0-9_]*(?:=(?:"[^"]*"|\'[^\']*\'|[^\s\]]+))?)*)\s*\/?(?:\](?:.*?\[\/\1\])?|\])/s';

        return preg_replace_callback($pattern, function($matches) {
            $tag = $matches[1];
            $attrString = $matches[2] ?? '';

            // Sprawdz czy shortcode jest zarejestrowany
            if (!isset($this->shortcodes[$tag])) {
                // Zwroc oryginalny tekst jesli shortcode nie istnieje
                return $matches[0];
            }

            // Parsuj atrybuty
            $attrs = $this->parseAttributes($attrString);

            // Wywolaj callback
            try {
                $result = call_user_func($this->shortcodes[$tag], $attrs);
                return $result ?? '';
            } catch (Exception $e) {
                // W przypadku bledu zwroc pusty string lub komunikat w trybie debug
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    return '<!-- Shortcode error ['.$tag.']: '.$e->getMessage().' -->';
                }
                return '';
            }
        }, $content);
    }

    /**
     * Parsuje string atrybutow do tablicy
     *
     * @param string $attrString
     * @return array
     */
    private function parseAttributes(string $attrString): array
    {
        $attrs = [];

        // Wzorzec dla atrybutow: name="value" lub name='value' lub name=value
        $pattern = '/([a-zA-Z_][a-zA-Z0-9_]*)(?:=(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+)))?/';

        if (preg_match_all($pattern, $attrString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1];
                // Wartosc moze byc w roznych grupach w zaleznosci od typu cudzyslowow
                $value = $match[2] ?? $match[3] ?? $match[4] ?? true;
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }
}

/**
 * Funkcja pomocnicza do parsowania shortcodes
 *
 * @param string|null $content
 * @return string
 */
function parseShortcodes(?string $content): string
{
    $shortcode = Shortcode::getInstance();
    return $shortcode->parse($content);
}

/**
 * Rejestruje nowy shortcode
 *
 * @param string $tag
 * @param callable $callback
 */
function registerShortcode(string $tag, callable $callback): void
{
    $shortcode = Shortcode::getInstance();
    $shortcode->register($tag, $callback);
}
