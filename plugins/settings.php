<?php
// ======================================================
// SETTINGS / HELPERS (CORE)
// ======================================================
 



// ------------------------------------------------------
// 1. Stałe i ścieżki
// ------------------------------------------------------
$baseUrl = rtrim('https://cms.kosa-x.com', '/'); // <-- jak chcesz, podmień tu na swoją domenę

define('BASE_URL', $baseUrl);
define('CURRENT_URL', BASE_URL.$_SERVER['REQUEST_URI']);
define('IMAGES', BASE_URL.'/images/');
define('FILES', BASE_URL.'/files/');
define('ICONS', IMAGES.'icons/');
define('THEME', BASE_URL.'/templates/'.$config['skin'].'/');
define('SHOP_PAGE', !empty($config['current_page_id']) && !empty($config['shop_page']) && $config['current_page_id'] == $config['shop_page']);
//define('PRODUCT_PAGE', $aData['sType'] == 1 ? TRUE : FALSE);

// wersjonowanie plików css/js/logo
$logoFile = __DIR__.'/images/logo.svg';
$logoVer  = file_exists($logoFile) ? filemtime($logoFile) : time();
define('LOGO_URL', IMAGES.'logo.svg?ver='.$logoVer);

$cssPath = __DIR__.'/templates/'.$config['skin'].'/css/style.css';
$jsPath  = __DIR__.'/templates/'.$config['skin'].'/js/scripts.js';

$css_file = THEME.'css/style.css?ver='.(file_exists($cssPath) ? filemtime($cssPath) : time());
$js_file  = THEME.'js/scripts.js?ver='.(file_exists($jsPath) ? filemtime($jsPath) : time());

define('FAVICON', IMAGES.'favicon.webp');

// logo html
$logo = '<img src="'.LOGO_URL.'" alt="'.$config['logo'].'" class="logo_img logo_light"><img src="'.IMAGES.'logo-dark.svg" alt="'.$config['logo'].'" class="logo_img logo_dark">';
define('LOGO', $logo);

$theme = 'templates/'.$config['skin'].'/';

// ------------------------------------------------------
// META: opis strony ($page_desc) – FIX
// ------------------------------------------------------
// DOMYŚLNE META DESCRIPTION
$page_desc = htmlspecialchars($config['description'] ?? '', ENT_QUOTES, 'UTF-8');

if (!empty($aData) && !empty($aData['sName'])) {
    if (!empty($aData['sDescriptionShort'])) {
        $page_desc = htmlspecialchars(strip_tags($aData['sDescriptionShort']), ENT_QUOTES, 'UTF-8');
    } elseif (!empty($aData['sDescriptionFull'])) {
        $page_desc = htmlspecialchars(strip_tags($aData['sDescriptionFull']), ENT_QUOTES, 'UTF-8');
    }
}

$image_for_facebook = IMAGES.'default-image.png';



 

// ------------------------------------------------------
// 2. FILTRY (config + helpery)
// ------------------------------------------------------

/**
 * Zwraca globalną konfigurację filtrów.
 */
function getFiltersConfig(): array
{
    global $filters;
    return $filters ?? [];
}

/**
 * Parsuje pole sFilter z bazy do tablicy asocjacyjnej.
 * sFilter: {"color":"1","size":"3"} → ['color' => '1', 'size' => '3']
 */
function parsePageFilters(?string $sFilter): array
{
    if (!$sFilter) {
        return [];
    }
    $decoded = json_decode($sFilter, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Render jednego filtra do <select> (np. w panelu / w edytorze)
 */
function renderFilterSelect(string $filterKey, array $selectedFilters = []): string
{
    $config = getFiltersConfig();

    // znajdź filtr po 'key'
    $filter = null;
    foreach ($config as $f) {
        if ($f['key'] === $filterKey) {
            $filter = $f;
            break;
        }
    }
    if (!$filter) {
        return '';
    }

    $current = $selectedFilters[$filterKey] ?? '';

    $html  = '<label class="form-label">'.$filter['label'].'</label>';
    $html .= '<select name="filters['.$filterKey.']" class="form-select">';
    $html .= '<option value="">-- wybierz --</option>';
    foreach ($filter['values'] as $valId => $valLabel) {
        $html .= '<option value="'.$valId.'" '.($current == $valId ? 'selected' : '').'>'.$valLabel.'</option>';
    }
    $html .= '</select>';

    return $html;
}


if (!function_exists('old')) {
    function old(string $name, string $default = ''): string
    {
        if (isset($_POST[$name])) {
            return htmlspecialchars((string)$_POST[$name], ENT_QUOTES, 'UTF-8');
        }
        return $default;
    }
}


/**
 * Rysuje prostą belkę filtrów dla JEDNEGO filtra,
 * np. dla klucza "portfolio".
 * Obsługuje wielokrotny wybór (toggle on/off).
 */
function renderFilterBar(string $filterKey, ?string $baseUrl = null, array $keepParams = []): string
{
    // Brak URL bazowego (np. strona filtra usunięta, getUrl() zwrócił null) —
    // nie da się zbudować linków, więc nie rysujemy belki.
    if ($baseUrl === null || $baseUrl === '') {
        return '';
    }

    $allFilters = getFiltersConfig();
    $cfg        = null;

    foreach ($allFilters as $f) {
        if (!empty($f['key']) && $f['key'] === $filterKey) {
            $cfg = $f;
            break;
        }
    }

    if (!$cfg || empty($cfg['values'])) {
        return '';
    }

    // Pobierz aktualne wartości (obsługa tablicy dla wielokrotnego wyboru)
    $currentVals = [];
    if (isset($_GET[$filterKey]) && $_GET[$filterKey] !== '') {
        if (is_array($_GET[$filterKey])) {
            $currentVals = array_map('strval', $_GET[$filterKey]);
        } else {
            $currentVals = [(string)$_GET[$filterKey]];
        }
    }

    $baseQuery = [];
    foreach ($keepParams as $k) {
        if (isset($_GET[$k])) {
            $baseQuery[$k] = $_GET[$k];
        }
    }

    $html  = '<ul class="buttonList">';
    $qsa = $baseQuery ? '?'.http_build_query($baseQuery) : '';
    $html .= '<li class="buttonList__item item_all">
                <a href="'.$baseUrl.$qsa.'" class="button button-light button-sm'.(empty($currentVals) ? ' selected' : '').'">Wszystkie</a>
              </li>';

    foreach ($cfg['values'] as $valId => $valName) {
        $isSelected = in_array((string)$valId, $currentVals, true);

        // Toggle: jeśli już zaznaczony, usuń z listy; jeśli nie, dodaj
        if ($isSelected) {
            $newVals = array_filter($currentVals, function($v) use ($valId) {
                return $v !== (string)$valId;
            });
        } else {
            $newVals = array_merge($currentVals, [(string)$valId]);
        }

        $params = $baseQuery;
        if (!empty($newVals)) {
            $params[$filterKey] = array_values($newVals);
        }
        $qs = !empty($params) ? '?'.http_build_query($params) : '';

        $html .= '<li class="buttonList__item item_'.$valId.'">
                    <a href="'.$baseUrl.$qs.'" class="button button-light button-sm'.($isSelected ? ' selected' : '').'">'.html($valName).'</a>
                  </li>';
    }

    $html .= '</ul>';

    return $html;
}


/**
 * Zwraca gotowy HTML z filtrami ustawionymi na stronie/produkcie.
 * Obsługuje wielokrotny wybór filtrów (tablica wartości dla każdego filtra).
 *
 * @param array|null $aOnlyKeys  ogranicza znaczniki do podanych kluczy filtrów
 *                               (np. ['brand','size'] na kafelku produktu);
 *                               null = wszystkie
 */
function getFilters(?string $sFilter, bool $wrap = true, string $extraClass = '', ?array $aOnlyKeys = null): string
{
    if (empty($sFilter)) {
        return '';
    }

    $pageFilters   = parsePageFilters($sFilter);
    if (empty($pageFilters)) {
        return '';
    }

    $filtersConfig = getFiltersConfig();
    $items         = [];

    // zachowaj kolejność z $aOnlyKeys (np. najpierw marka, potem rozmiar)
    if (is_array($aOnlyKeys)) {
        $ordered = [];
        foreach ($aOnlyKeys as $k) {
            if (array_key_exists($k, $pageFilters)) {
                $ordered[$k] = $pageFilters[$k];
            }
        }
        $pageFilters = $ordered;
    }

    foreach ($pageFilters as $filterKey => $filterVal) {
        if ($filterVal === '' || $filterVal === null) {
            continue;
        }

        // Normalizuj do tablicy (kompatybilność wsteczna)
        $filterValues = is_array($filterVal) ? $filterVal : [$filterVal];

        // Pomiń puste tablice
        $filterValues = array_filter($filterValues, function($v) {
            return $v !== '' && $v !== null;
        });

        if (empty($filterValues)) {
            continue;
        }

        $filterLabel = $filterKey;
        $valueLabels = [];

        // Znajdź konfigurację filtra
        $cfgFilter = null;
        foreach ($filtersConfig as $cfg) {
            if (!empty($cfg['key']) && $cfg['key'] === $filterKey) {
                $cfgFilter = $cfg;
                $filterLabel = $cfg['label'] ?? $filterKey;
                break;
            }
        }

        // Pobierz etykiety dla wszystkich wartości.
        // Wartość bez etykiety w konfiguracji POMIJAMY — surowe ID (np. "23")
        // nic nie mówi klientowi i oznacza rozjazd pliku filtrów z bazą.
        foreach ($filterValues as $val) {
            if ($cfgFilter && !empty($cfgFilter['values'][$val])) {
                $valueLabels[] = $cfgFilter['values'][$val];
            }
        }

        if (empty($valueLabels)) {
            continue;
        }

        // Połącz wartości przecinkami
        $valueLabel = implode(', ', $valueLabels);

        // Klasy CSS dla wszystkich wartości
        $valClasses = array_map(function($v) use ($filterKey) {
            return 'filter-'.$filterKey.'-'.$v;
        }, $filterValues);

        $items[] = '<li class="filters_item filter-'.$filterKey.' '.implode(' ', $valClasses).'">
            <span class="filters_label">'.$filterLabel.':</span>
            <span class="filters_value">'.$valueLabel.'</span>
        </li>';
    }

    if (empty($items)) {
        return '';
    }

    if ($wrap) {
        return '<ul class="filters '.($extraClass ? ' '.$extraClass : '').'">'.implode('', $items).'</ul>';
    }

    return implode('', $items);
}


/**
 * Renderuje konfigurator produktu - pola SELECT dla filtrów z więcej niż 1 wartością,
 * lub tekst informacyjny gdy jest tylko 1 wartość.
 *
 * @param string|null $sFilter - JSON z filtrami produktu z bazy
 * @param int $productId - ID produktu (do generowania unikalnych ID pól)
 * @param array $options - dodatkowe opcje ['class' => '', 'show_label' => true]
 * @return string HTML
 */
function renderProductConfigFilters(?string $sFilter, int $productId, array $options = []): string
{
    if (empty($sFilter)) {
        return '';
    }

    $pageFilters = parsePageFilters($sFilter);
    if (empty($pageFilters)) {
        return '';
    }

    $filtersConfig = getFiltersConfig();
    $items = [];
    $hasSelects = false;

    $wrapperClass = $options['class'] ?? '';
    $showLabel = $options['show_label'] ?? true;

    foreach ($pageFilters as $filterKey => $filterVal) {
        if ($filterVal === '' || $filterVal === null) {
            continue;
        }

        // Normalizuj do tablicy (kompatybilność wsteczna)
        $filterValues = is_array($filterVal) ? $filterVal : [$filterVal];

        // Pomiń puste wartości
        $filterValues = array_filter($filterValues, function($v) {
            return $v !== '' && $v !== null;
        });

        if (empty($filterValues)) {
            continue;
        }

        // Znajdź konfigurację filtra
        $filterLabel = $filterKey;
        $cfgFilter = null;
        foreach ($filtersConfig as $cfg) {
            if (!empty($cfg['key']) && $cfg['key'] === $filterKey) {
                $cfgFilter = $cfg;
                $filterLabel = $cfg['label'] ?? $filterKey;
                break;
            }
        }

        // Pobierz etykiety dla wszystkich wartości
        $valueOptions = [];
        foreach ($filterValues as $val) {
            $valLabel = ($cfgFilter && !empty($cfgFilter['values'][$val]))
                ? $cfgFilter['values'][$val]
                : $val;
            $valueOptions[$val] = $valLabel;
        }

        $fieldId = 'product-config-'.$productId.'-'.$filterKey;
        $fieldName = 'product_filters['.$filterKey.']';

        if (count($valueOptions) === 1) {
            // Tylko 1 wartość - wyświetl jako tekst + ukryte pole
            $singleVal = array_key_first($valueOptions);
            $singleLabel = reset($valueOptions);

            $html = '<div class="productConfig__item productConfig__item--single filter-'.$filterKey.'">';
            if ($showLabel) {
                $html .= '<span class="label">'.html($filterLabel).':</span> ';
            }
            $html .= '<span class="value">'.html($singleLabel).'</span>';
            $html .= '<input type="hidden" name="'.html($fieldName).'" value="'.html($singleVal).'" class="product-config-input" data-filter-key="'.html($filterKey).'">';
            $html .= '</div>';

            $items[] = $html;
        } else {
            // Więcej niż 1 wartość - wyświetl RADIO BUTTONS
            $hasSelects = true;
            $html = '<div class="productConfig__item productConfig__item--radio  filter-'.$filterKey.'">';
            if ($showLabel) {
                $html .= '<span class="label">'.html($filterLabel).':</span>';
            }
            $html .= '<div class="formCheckBox">';
          
            foreach ($valueOptions as $valId => $valLabel) {
                $radioId = html($fieldId).'-'.html($valId);
                $html .= '<div class="form-check">';
                $html .= '<input class="form-check-input product-config-radio" type="radio" name="'.html($fieldName).'" id="'.$radioId.'" value="'.html($valId).'" data-filter-key="'.html($filterKey).'" required>';
                $html .= '<label class="form-check-label" for="'.$radioId.'">'.html($valLabel).'</label>';
                $html .= '</div>';
           
            }
            $html .= '</div>';
            $html .= '</div>';
            $items[] = $html;
        }
    }

    if (empty($items)) {
        return '';
    }

    $wrapperClasses = 'productConfig';
    if ($wrapperClass) {
        $wrapperClasses .= ' '.$wrapperClass;
    }
    if ($hasSelects) {
        $wrapperClasses .= ' productConfig--has-selects';
    }

    return '<div class="'.$wrapperClasses.'" data-product-id="'.$productId.'">'.implode('', $items).'</div>';
}


/**
 * Pobiera wybrane filtry produktu z formularza/koszyka i zwraca je jako JSON.
 *
 * @param array $productFilters - tablica z POST/cookie np. ['color' => '1', 'size' => '2']
 * @param string|null $sFilter - JSON z dostępnymi filtrami produktu (do walidacji)
 * @return string JSON z wybranymi filtrami
 */
function packProductConfigFilters(array $productFilters, ?string $sFilter = null): string
{
    if (empty($productFilters)) {
        return '';
    }

    $clean = [];

    // Jeśli mamy sFilter, waliduj że wybrane wartości są dozwolone
    $allowedFilters = [];
    if ($sFilter) {
        $decoded = parsePageFilters($sFilter);
        foreach ($decoded as $key => $vals) {
            $allowedFilters[$key] = is_array($vals) ? $vals : [$vals];
        }
    }

    foreach ($productFilters as $key => $val) {
        $key = trim((string)$key);
        $val = trim((string)$val);

        if ($key === '' || $val === '') {
            continue;
        }

        // Walidacja: czy ta wartość jest dozwolona dla tego produktu
        if (!empty($allowedFilters)) {
            if (!isset($allowedFilters[$key])) {
                continue; // Ten filtr nie istnieje w produkcie
            }
            if (!in_array($val, array_map('strval', $allowedFilters[$key]), true)) {
                continue; // Ta wartość nie jest dozwolona
            }
        }

        $clean[$key] = $val;
    }

    if (empty($clean)) {
        return '';
    }

    $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
    return $json !== false ? $json : '';
}


/**
 * Formatuje wybrane filtry produktu do czytelnego tekstu.
 *
 * @param string|null $selectedFilters - JSON z wybranymi filtrami
 * @param bool $asHtml - czy zwrócić jako HTML czy plain text
 * @return string
 */
function formatSelectedFilters(?string $selectedFilters, bool $asHtml = true): string
{
    if (empty($selectedFilters)) {
        return '';
    }

    $filters = json_decode($selectedFilters, true);
    if (!is_array($filters) || empty($filters)) {
        return '';
    }

    $filtersConfig = getFiltersConfig();
    $items = [];

    foreach ($filters as $filterKey => $filterVal) {
        if ($filterVal === '' || $filterVal === null) {
            continue;
        }

        $filterLabel = $filterKey;
        $valueLabel = $filterVal;

        foreach ($filtersConfig as $cfg) {
            if (!empty($cfg['key']) && $cfg['key'] === $filterKey) {
                $filterLabel = $cfg['label'] ?? $filterKey;
                if (!empty($cfg['values'][$filterVal])) {
                    $valueLabel = $cfg['values'][$filterVal];
                }
                break;
            }
        }

        if ($asHtml) {
            $items[] = '<span class="selectedFilter selectedFilter-'.$filterKey.'">'.html($filterLabel).': <strong>'.html($valueLabel).'</strong></span>';
        } else {
            $items[] = $filterLabel.': '.$valueLabel;
        }
    }

    if (empty($items)) {
        return '';
    }

    if ($asHtml) {
        return '<div class="selectedFilters">'.implode(', ', $items).'</div>';
    }

    return implode(', ', $items);
}


function renderSiteMap($pageObj = null, ?array $aMenus = null, array $aMenuOverrides = []): string
{
	global $config, $oPage;

	// domyślnie użyj globalnego $oPage jeśli nie podano obiektu
	if ($pageObj === null) {
		$pageObj = $oPage ?? null;
	}

	// brak obiektu albo brak metody -> nic nie renderujemy
	if (!is_object($pageObj) || !method_exists($pageObj, 'listPagesMenu')) {
		return '';
	}

	// domyślnie bierzemy menu z configu
	if ($aMenus === null) {
		$aMenus = $config['pages_menus'] ?? [];
	}

	if (empty($aMenus) || !is_array($aMenus)) {
		return '';
	}

	$sections = [];

	foreach ($aMenus as $iMenu => $sTitle) {

		// POMIJAMY menu ID=0
		if ((int)$iMenu === 0) {
			continue;
		}

		// bazowe opcje (jak u Ciebie: menu=1 płycej, reszta głębiej)
		$aOpts = [
			'sClassName'  => 'siteMap',
			'bExpanded'   => true,
			'show_all'    => true,
			'iDepthLimit' => ((int)$iMenu === 1 ? 3 : 9),
		];

		// nadpisy per menu (opcjonalnie)
		if (isset($aMenuOverrides[$iMenu]) && is_array($aMenuOverrides[$iMenu])) {
			$aOpts = array_replace($aOpts, $aMenuOverrides[$iMenu]);
		}

		$sList = (string) $pageObj->listPagesMenu((int)$iMenu, $aOpts);

		// jak menu puste -> pomijamy sekcję
		if (trim($sList) === '') {
			continue;
		}

		$sections[] =
			'<h3 class="mb-2">'.htmlspecialchars((string)$sTitle, ENT_QUOTES, 'UTF-8').'</h3>'
			.$sList;
	}

	if (empty($sections)) {
		return '';
	}

	return implode('<hr class="separator">', $sections);
}





/**
 * Renderuje widget filtrów w sidebarze
 * Obsługuje wielokrotny wybór (checkboxy)
 */
function renderFiltersWidget(string $actionUrl, array $keepParams = []): string
{
    global $lang;

    // bezpieczeństwo: upewniamy się, że $lang jest tablicą
    if (!is_array($lang)) {
        $lang = [];
    }

    // etykiety z języka lub sensowne defaulty
    $labelFilters      = $lang['filters']       ?? 'Filtry';
    $labelFilterButton = $lang['filter_button'] ?? 'Filtruj';
    $labelClear        = $lang['clear']         ?? 'Wyczyść';

    $filters = getFiltersConfig();
    if (empty($filters)) return '';

    $current = $_GET;
    $formId  = 'filtersForm-'.uniqid('', true);

    $html  = '<aside class="card card-toggle">';
    $html .= '  <header class="card__header">';
    $html .= '  <h4 class="card__title"><img src="'.ICONS.'filter.svg" class="invert" alt="Filtry">'.$labelFilters.'</h4>';
    $html .= '    <span class="card__arrow"><img src="'.ICONS.'arrow.svg" alt="Strzałka"></span>';

    $html .= '  </header>';
    $html .= '  <div class="card__wrapper card__content">';
    $html .= '    <form action="'.htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8').'" method="get" class="filtersForm" id="'.$formId.'">';

    foreach ($keepParams as $p) {
        if (isset($_GET[$p])) {
            $html .= '<input type="hidden" name="'.htmlspecialchars($p, ENT_QUOTES, 'UTF-8').'" value="'.htmlspecialchars($_GET[$p], ENT_QUOTES, 'UTF-8').'">';
        }
    }

    foreach ($filters as $filterId => $filterData) {

        // filtr portfolio (Realizacje) nie dotyczy sklepu
        if (($filterData['key'] ?? '') === 'portfolio') {
            continue;
        }

        $nameKey = $filterData['key']   ?? ('f'.$filterId);
        $label   = $filterData['label'] ?? ucfirst($nameKey);
        $values  = $filterData['values'] ?? [];
        if (empty($values)) continue;

        // Pobierz aktualne wartości (obsługa tablicy dla wielokrotnego wyboru)
        $currentVals = [];
        if (isset($current[$nameKey])) {
            if (is_array($current[$nameKey])) {
                $currentVals = array_map('strval', $current[$nameKey]);
            } else {
                $currentVals = [(string)$current[$nameKey]];
            }
        }

        $html .= '<div class="form-group filter-group mb-3">';
        $html .= '  <div class="form-label">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</div>';

        // wartości filtra jako klikalne przyciski (wielokrotny wybór) —
        // ten sam komponent .formCheckBox co w konfiguratorze na karcie produktu
        $html .= '  <div class="formCheckBox filtersForm__values">';
        foreach ($values as $valKey => $valLabel) {
            $isChecked = in_array((string)$valKey, $currentVals, true);

            $html .= '  <div class="form-check">';
            $html .= '    <input class="form-check-input" type="checkbox"'
                  .  ' name="'.htmlspecialchars($nameKey, ENT_QUOTES, 'UTF-8').'[]"'
                  .  ' id="filter-'.$nameKey.'-'.$valKey.'"'
                  .  ' value="'.htmlspecialchars((string)$valKey, ENT_QUOTES, 'UTF-8').'"'
                  .  ($isChecked ? ' checked' : '')
                  .  '>';
            $html .= '    <label class="form-check-label" for="filter-'.$nameKey.'-'.$valKey.'">'
                  .  htmlspecialchars((string)$valLabel, ENT_QUOTES, 'UTF-8').'</label>';
            $html .= '  </div>';
        }
        $html .= '  </div>';

        $html .= '</div>';
    }

    $html .= '      <div class="form-button mt-2">';
    $html .= '        <button type="submit" class="button">'.$labelFilterButton.'</button>';
    $html .= '        <a href="'.htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8').'" class="link_underline ml-2 font-sm">'.$labelClear.'</a>';
    $html .= '      </div>';

    $html .= '    </form>';
    $html .= '  </div>';
    $html .= '</aside>';

    $html .= '<script>
    (function(){
        var f = document.getElementById("'.$formId.'");
        if (!f) return;
        f.addEventListener("submit", function(e){
            // Usuń puste wartości przed wysłaniem
            var fd = new FormData(f);
            var hasFilters = false;

            fd.forEach(function(v, k){
                if (v !== "") {
                    hasFilters = true;
                }
            });

            if (!hasFilters) {
                e.preventDefault();
                window.location.href = "'.addslashes($actionUrl).'";
            }
        });
    })();
    </script>';

    return $html;
}

function renderActiveFilters(string $actionUrl, array $keepParams = []): string
{
	global $lang;

	if (!is_array($lang)) {
		$lang = [];
	}

	$filters = getFiltersConfig();
	if (empty($filters)) {
		return '';
	}

	$current = $_GET;

	$labelActive    = $lang['active_filters'] ?? 'Aktywne filtry';
	$labelClearAll  = $lang['clear_all']      ?? 'Wyczyść wszystkie';
	$labelRemoveOne = $lang['remove_filter']  ?? 'Usuń filtr';

	$active = [];

	// znajdź aktywne filtry na podstawie GET i configu
	foreach ($filters as $filterId => $filterData) {

		// pomijamy np. specjalny filterId==1 jak w panelu filtrów
		if ($filterId == 1) {
			continue;
		}

		$nameKey = $filterData['key']   ?? ('f'.$filterId);
		$label   = $filterData['label'] ?? ucfirst($nameKey);
		$values  = $filterData['values'] ?? [];

		if (empty($values)) {
			continue;
		}

		if (!isset($current[$nameKey]) || $current[$nameKey] === '' || $current[$nameKey] === null) {
			continue;
		}

		// Obsługa wielokrotnego wyboru (tablica wartości)
		$selectedVals = is_array($current[$nameKey])
			? $current[$nameKey]
			: [$current[$nameKey]];

		foreach ($selectedVals as $valKey) {
			$valKey = (string)$valKey;

			if (!array_key_exists($valKey, $values)) {
				continue;
			}

			$active[] = [
				'param'       => $nameKey,
				'label'       => $label,
				'value_label' => (string)$values[$valKey],
				'value_key'   => $valKey,
			];
		}
	}

	// jeśli nie ma żadnego aktywnego filtra – nie pokazujemy widgetu
	if (empty($active)) {
		return '';
	}

	// URL "wyczyść wszystkie" – zostawiamy tylko parametry z $keepParams
	$clearQuery = [];
	foreach ($keepParams as $p) {
		if (isset($current[$p])) {
			$clearQuery[$p] = $current[$p];
		}
	}
	$clearUrl = $clearQuery
		? $actionUrl.'?'.http_build_query($clearQuery)
		: $actionUrl;

	// HTML
	$html  = '<div class="activeFilters mb-3">';
//	$html .= '  <div class="ac tiveFilters__title">'.htmlspecialchars($labelActive, ENT_QUOTES, 'UTF-8').'</div>';
	$html .= '    <ul class="buttonList">';

	foreach ($active as $item) {
		$param       = $item['param'];
		$filterLabel = $item['label'];
		$valueLabel  = $item['value_label'];
		$valueKey    = $item['value_key'];

		// budujemy URL bez tej jednej wartości filtra
		$query = $current;

		// Obsługa wielokrotnego wyboru - usuń tylko tę konkretną wartość
		if (isset($query[$param])) {
			if (is_array($query[$param])) {
				$query[$param] = array_filter($query[$param], function($v) use ($valueKey) {
					return (string)$v !== $valueKey;
				});
				// Jeśli tablica jest pusta po usunięciu, usuń cały parametr
				if (empty($query[$param])) {
					unset($query[$param]);
				} else {
					// Reindeksuj tablicę
					$query[$param] = array_values($query[$param]);
				}
			} else {
				unset($query[$param]);
			}
		}

		$removeUrl = $query
			? $actionUrl.'?'.http_build_query($query)
			: $actionUrl;

		$html .= '      <li class="buttonList__item">';

		$html .= '        <a href="'.htmlspecialchars($removeUrl, ENT_QUOTES, 'UTF-8').'"'
				. ' class="button button-light button-sm"'
				. ' title="'.htmlspecialchars($labelRemoveOne, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($filterLabel, ENT_QUOTES, 'UTF-8').' '. htmlspecialchars($valueLabel, ENT_QUOTES, 'UTF-8').' x</a>';
		$html .= '      </li>';
	}

	$html .= '    </ul>';

	$html .= '  </div>';


	return $html;
}


// ------------------------------------------------------
// 3. Baza: update views
// ------------------------------------------------------
function update_views(int $page_id): void
{
    $oSql = Sql::getInstance();
    $oSql->getQuery('UPDATE pages SET sViews = sViews + 1 WHERE iPage = '.(int)$page_id.' ');
}


// ------------------------------------------------------
// 3. UŻYTKOWNIK
// ------------------------------------------------------
// KONFIGURACJA CZASU TRWANIA SESJI UŻYTKOWNIKA
// Zmień liczbę dni, aby dostosować czas trwania sesji
$userSessionDays = 30;

// Upewniamy się, że sesja jest startnięta JEDEN raz
if (session_status() === PHP_SESSION_NONE) {
    // Konwersja dni na sekundy
    $sessionLifetime = $userSessionDays * 24 * 60 * 60;

    // Ustawienie czasu życia ciasteczka sesyjnego
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Globalny helper – czy użytkownik jest zalogowany
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}

// Stała LOGGED – można używać w warunkach w template'ach
if (!defined('LOGGED')) {
    define('LOGGED', isLoggedIn());
}


function contactIcon(): string
{
    global $lang;
 
    return '<div class="contactIconPopup"><button data-fancybox-slide-up data-src="#modal-contact" class="button button-light button-xs"><img src="'.ICONS.'email-line.svg" alt="'.$lang['email'].'" class="invert" style="margin:0"></button></div>';

}



function userIcon(): string
{
    global $config, $oPage;
    
    if ((int)$config['user_page'] === 0) {
        return '';
    }
        
    $class = '';
    if(LOGGED){
        $class = ' active';
    } 
    return '<div class="bigIcon userIcon'.$class.'"><a href="'.getUrl($config['user_page']).'"><img src="'.ICONS.'user.svg" alt="User" class="icon invert"></a></div>';

}



// ------------------------------------------------------
// 4. Skracanie stringa do X słów
// ------------------------------------------------------
function trunc(string $phrase, int $max_words): string
{
    $phrase = strip_tags($phrase);
    $words  = preg_split('/\s+/', $phrase);
    if ($max_words > 0 && count($words) > $max_words) {
        $phrase = implode(' ', array_slice($words, 0, $max_words)).'...';
    }
    return $phrase;
}


// ------------------------------------------------------
// 5. Ile zdjęć ma strona
// ------------------------------------------------------
function image_count(int $id): int
{
    $oSql = Sql::getInstance();
    $stmt = $oSql->getQuery('SELECT COUNT(*) FROM files WHERE iPage='.(int)$id.' AND iType=1');
    return (int)$stmt->fetchColumn();
}


// ------------------------------------------------------
// 6. Pobranie URL strony po ID (z cache Pages)
// ------------------------------------------------------
function acceptLabel(){
    global $config, $lang;
    return '<span class="text-danger">*</span> Akceptuję warunki zawarte w <a href="'.getUrl($config['private_policy']).'" class="link_underline" target="_blank">'.getData($config['private_policy'], 'sName').'</a> '.(isset($config['terms_page']) && $config['terms_page']!=0 ? 'oraz <a href="'.getUrl($config['terms_page']).'" class="link_underline" target="_blank">'.getData($config['terms_page'], 'sName').'</a>' : '').'.';
}

function getUrl(int $id): ?string
{
    if($id == 0){
        return null;
    }

    $oPage = Pages::getInstance();

    // Strona mogła zostać usunięta, a $config['*_page'] wciąż na nią wskazuje.
    // Zwróć null zamiast rzucać warning i budować zepsuty URL.
    return isset($oPage->aPages[$id]['sLinkName'])
        ? BASE_URL.$oPage->aPages[$id]['sLinkName']
        : null;
}

function getLink(int $id): ?string
{
    $oPage = Pages::getInstance();

    if(!isset($oPage->aPages[$id]['sLinkName'])){
        return null;
    }

    return "<a href='".BASE_URL.$oPage->aPages[$id]['sLinkName']."'>".$oPage->aPages[$id]['sName']."</a>";
}

// ------------------------------------------------------
// 7. Uniwersalne pobieranie pola z tabeli
// ------------------------------------------------------
function getData(int $id, string $column, string $table = 'pages')
{
    // Cache wyników zapytań w pamięci
    static $cache = [];

    if($id == null){
        return "";
    }

    // Sprawdź cache przed zapytaniem do bazy
    $cacheKey = "{$table}:{$id}:{$column}";
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $allowedTables = ['pages', 'files', 'orders', 'products'];
    if (!in_array($table, $allowedTables, true)) {
        return null;
    }

    $oSql = Sql::getInstance();
    $extra = ($table === 'files') ? ' AND iSize > 0 AND iDefault = 1 ' : '';
    $sql   = "SELECT {$column} FROM {$table} WHERE iPage = :id {$extra} LIMIT 1";

    $stmt = $oSql->prepare($sql);
    $stmt->execute([':id' => $id]);
    $val = $stmt->fetchColumn();

    $result = $val !== false ? $val : null;

    // Zapisz w cache
    $cache[$cacheKey] = $result;

    return $result;
}


// ------------------------------------------------------
// 8. Lista parametrów z stringa "a | b | c"
// ------------------------------------------------------
function parametrs(string $data, array $options = []): string
{
    $items = array_filter(array_map('trim', explode('|', str_replace(' ', '', $data))));
    if (!$items) {
        return '';
    }

    $class = $options['class'] ?? '';
    $lis   = '';
    foreach ($items as $item) {
        $lis .= '<li>'.htmlspecialchars($item).'</li>';
    }
    return '<ul class="parametrs__list '.$class.'">'.$lis.'</ul>';
}


// ------------------------------------------------------
// 9. Ikona wyszukiwarki
// ------------------------------------------------------
function searchIcon(): string
{
    global $config, $lang;
    
    
    if ((int)$config['search_page'] === 0) {
        return '';
    }
    
 
    
    // Bezpieczna etykieta
    $searchLabel = 'Szukaj';
    if (is_array($lang) && !empty($lang['search'])) {
        $searchLabel = $lang['search'];
    }

    // Bezpieczny URL do strony wyszukiwarki
    $url = '#';
    if (!empty($config['search_page'])) {
        $tmp = getUrl((int)$config['search_page']);
        if (!empty($tmp)) {
            $url = $tmp;
        }
    }

    return '<div class="smallIcon searchIcon">
                <a href="#" data-fancybox-slide-up data-src="#search-widget">
                    <span class="icon">
                        <img src="'.ICONS.'search.svg" alt="'.$searchLabel.'" class="invert">
                    </span>
                    <span class="label">'.$searchLabel.'</span>
                </a>
            </div>';
    
}


// ------------------------------------------------------
// 10. Lista elementów (na filtry, kategorie itp.)
// ------------------------------------------------------
//function listElements(array $categories, array $options = []): string
//{
//    $limit = (int)($options['limit'] ?? 0);
//    $url   = rtrim($options['url'] ?? '', '/');
//
//    $html  = "<ul class='listElements'>\n";
//    $html .= "<li class=\"listElements__item item_all\"><a href=\"$url#category-all\">Wszystkie</a></li>\n";
//
//    $count = 0;
//    foreach ($categories as $id => $name) {
//        if ($id == 0) {
//            continue;
//        }
//        if ($limit && $count++ >= $limit) {
//            break;
//        }
//
//        $idEsc = htmlspecialchars((string)$id);
//        $text  = htmlspecialchars((string)$name);
//        $link  = "<a href=\"$url#category-$idEsc\">$text</a>";
//
//        $html .= "<li class=\"listElements__item item_$idEsc\" data-id=\"$idEsc\">$link</li>\n";
//    }
//
//    return $html . "</ul>";
//}

function getElement($id, array $categories)
{
    return $categories[$id] ?? null;
}

/**
 * Czy dany moduł jest włączony.
 *
 * JEDYNE ŹRÓDŁO PRAWDY = panel admina → Konfiguracja → „Dopasowanie stron".
 * Moduł jest włączony, gdy przypisano mu stronę, która REALNIE ISTNIEJE i jest
 * aktywna. Trzy przypadki „off": strona = „-" (id 0), strona usunięta (config
 * wciąż wskazuje na skasowane id) oraz strona ukryta (iStatus=0). Dzięki temu
 * usunięcie zakładki wyłącza moduł wszędzie i nie zostawia sierot w UI
 * (ikony, sekcje, wywołania getUrl()/renderFilterBar() na nieistniejącej stronie).
 *
 * feature('shop') czyta się lepiej niż sprawdzanie $config['shop_page'] i ukrywa
 * mapowanie nazwy modułu na klucz strony (np. portfolio → projects_page).
 *
 * @param string $name  'shop'|'blog'|'portfolio'|'reservations'|'users'|'search'|'payments'
 * @return bool
 */
function feature(string $name): bool
{
    global $config;

    // Mapowanie: nazwa modułu → klucz strony w „Dopasowaniu stron".
    $map = [
        'shop'         => 'shop_page',
        'blog'         => 'blog_page',
        'portfolio'    => 'projects_page',
        'reservations' => 'form_page',
        'users'        => 'user_page',
        'search'       => 'search_page',
        'payments'     => 'payment_page',
    ];

    if (!isset($map[$name])) {
        return false;
    }

    $id = (int) ($config[$map[$name]] ?? 0);
    if ($id === 0) {
        return false;   // brak przypisanej strony (panel: „-")
    }

    // Strona musi istnieć i być aktywna. aPages = SELECT ... WHERE iStatus>0,
    // klucz = iPage → isset() odsiewa strony usunięte i ukryte.
    if (class_exists('Pages')) {
        $oPage = Pages::getInstance();
        if (is_array($oPage->aPages)) {
            return isset($oPage->aPages[$id]);
        }
    }

    // aPages jeszcze niezaładowane (wczesne wywołanie / kontekst bez Pages) —
    // polegaj na samym przypisaniu strony.
    return true;
}

function getElementArray($id, array $items, $key = 'label', bool $respectDisabled = true, $default = '-')
{
    $id = (int) $id;

    if (!isset($items[$id])) {
        return $default;
    }

    $item = $items[$id];

    if ($respectDisabled && !empty($item['disabled'])) {
        return $default;
    }

    // null => zwróć cały rekord (array)
    if ($key === null) {
        return $item;
    }

    // Specjalne "tryby" do UI
    if ($key === 'label_price' || $key === 'labelPrice') {
        $label = (string)($item['label'] ?? $default);

        if (!isset($item['price']) || $item['price'] === '' || $item['price'] === null) {
            return $label;
        }

        $price = (float)$item['price'];
        $formatted = rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');

        return $label . ' - ' . $formatted . ' PLN';
    }

    if ($key === 'price_formatted' || $key === 'priceFormatted') {
        if (!isset($item['price']) || $item['price'] === '' || $item['price'] === null) {
            return $default;
        }

        $price = (float)$item['price'];
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.') . ' PLN';
    }

    // Standard: zwróć wskazane pole (domyślnie label)
    return $item[$key] ?? $default;
}



// ------------------------------------------------------
// 11. Lista stron w formie widgetu
// ------------------------------------------------------
function listMenu(array $options = []): string
{
    $oSql  = Sql::getInstance();
    $oPage = Pages::getInstance();
    global $config;

    $where = 'iStatus > 0';
    if (!empty($options['sql'])) {
        $where .= ' AND '.$options['sql'];
    }

    $order = (!empty($options['random']) && $options['random'] === true)
        ? ' RANDOM() '
        : ' iPosition ASC, sDate DESC, iPage ASC ';

    $limit = !empty($options['limit']) ? 'LIMIT '.(int)$options['limit'] : '';

    $stmt = $oSql->getQuery("SELECT iPage, sName FROM pages WHERE {$where} ORDER BY {$order} {$limit}");

    $class = 'card';
    if (!empty($options['class'])) {
        $class .= ' '.$options['class'];
    }
    if (!empty($options['toggle'])) {
        $class .= ' card-toggle';
    }

    $html = "<aside class=\"{$class}\">";
    if (!empty($options['title'])) {
        $html .= '<header class="card__header">';
        $html .= '<h4 class="card__title">'.$options['title'].'</h4>';
        if (!empty($options['toggle'])) {
            $html .= '<span class="card__arrow"><img src="'.ICONS.'arrow.svg" alt="Strzałka"></span>';
        }
        $html .= '</header>';
    }
    $html .= '<div class="card__wrapper"><ul class="card__list">';

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $link     = $oPage->aPages[$row['iPage']]['sLinkName'] ?? '#';
        $selected = (!empty($config['current_page_id']) && $config['current_page_id'] == $row['iPage']) ? ' selected' : '';
        $html    .= '<li><a href="'.$link.'" class="card__link'.$selected.'">'.$row['sName'].'</a></li>';
    }

    $html .= '</ul>';

    if (!empty($options['footer'])) {
        $html .= "<footer class='card__footer'>{$options['footer']}</footer>";
    }

    $html .= '</div></aside>';

    return $html;
}


// ------------------------------------------------------
// 12. Uniwersalna lista stron z filtrami + paginacją
// ------------------------------------------------------
function listPagesQuery(array $options = []): string
{
    $oSql  = Sql::getInstance();
    $oPage = Pages::getInstance();

    $where = 'iStatus > 0';
    if (!empty($options['sql'])) {
        $where .= ' AND '.$options['sql'];
    }

    $activeFilters = [];
    if (function_exists('getFiltersConfig')) {
        $cfgFilters = getFiltersConfig();
        foreach ($cfgFilters as $fid => $fdata) {
            if (!empty($fdata['key'])) {
                $k = $fdata['key'];
                if (isset($_GET[$k]) && $_GET[$k] !== '') {
                    // Obsługa tablicy wartości (wielokrotny wybór)
                    if (is_array($_GET[$k])) {
                        $values = array_filter($_GET[$k], function($v) {
                            return $v !== '' && $v !== null;
                        });
                        if (!empty($values)) {
                            $activeFilters[$k] = $values;
                        }
                    } else {
                        $activeFilters[$k] = $_GET[$k];
                    }
                }
            }
        }
    } else {
        $cfgFilters = [];
    }

    if (!empty($activeFilters)) {
        foreach ($activeFilters as $k => $v) {
            // Obsługa wielokrotnego wyboru (tablica wartości)
            $filterValues = is_array($v) ? $v : [$v];
            $filterValues = array_filter($filterValues, function($val) {
                return $val !== '' && $val !== null;
            });

            if (!empty($filterValues)) {
                $orConditions = [];
                foreach ($filterValues as $val) {
                    // BEZPIECZEŃSTWO: $val pochodzi z $_GET. Cały wzorzec LIKE escapujemy
                    // przez PDO::quote(), żeby nie dało się wyjść z literału SQL (SQL injection).
                    // $k jest z getFiltersConfig() (zaufane). Zachowanie dla poprawnych wartości bez zmian.
                    $val = (string) $val;
                    $orConditions[] = "sFilter LIKE ".$oSql->quote('%"'.$k.'":"'.$val.'"%');
                    $orConditions[] = "sFilter LIKE ".$oSql->quote('%"'.$k.'":%"'.$val.'"%');
                }
                $where .= " AND (".implode(' OR ', $orConditions).")";
            }
        }
    }

    $pagination = $options['pagination'] ?? true;
    $pageParam  = $options['page_param'] ?? 'page';

    $perPage = isset($options['per_page']) && (int)$options['per_page'] > 0
        ? (int)$options['per_page']
        : 30;

    if ($pagination === false) {
        // kolejność: domyślna lub losowa (np. sekcja "Więcej modeli od marki")
        $orderBy = (($options['order'] ?? '') === 'random')
            ? 'RANDOM()'
            : 'iPosition DESC, iPage DESC';
        $sql = "SELECT * FROM pages WHERE {$where} ORDER BY {$orderBy} LIMIT {$perPage}";
        $stmt = $oSql->getQuery($sql);
        $class = 'pagesList';
        if (!empty($options['class'])) {
            $class .= ' '.$options['class'];
        }

        $html = '<ul class="'.$class.'">';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['sLinkName'] = $oPage->aPages[$row['iPage']]['sLinkName'] ?? '#';
            $html .= listPagesView($row, $options);
        }
        $html .= '</ul>';

        return $html;
    }

    $countSql = "SELECT COUNT(*) AS cnt FROM pages WHERE {$where}";
    $cntRow   = $oSql->getQuery($countSql)->fetch(PDO::FETCH_ASSOC);
    $total    = (int)($cntRow['cnt'] ?? 0);

    $current = isset($_GET[$pageParam]) && (int)$_GET[$pageParam] > 0
        ? (int)$_GET[$pageParam]
        : 1;

    $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
    if ($current > $totalPages) {
        $current = $totalPages;
    }

    $offset = ($current - 1) * $perPage;

    $sql = "SELECT * FROM pages WHERE {$where} ORDER BY iPosition DESC, iPage DESC LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $oSql->getQuery($sql);

    $class = 'pagesList';
    if (!empty($options['class'])) {
        $class .= ' '.$options['class'];
    }

    $html = '<ul class="'.$class.'">';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['sLinkName'] = $oPage->aPages[$row['iPage']]['sLinkName'] ?? '#';
        $html .= listPagesView($row, $options);
    }
    $html .= '</ul>';

    if ($totalPages > 1) {
        $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
        $html   .= renderPaginationForQuery($current, $totalPages, $pageParam, $baseUrl);
    }

    return $html;
}

 

/**
 * Pomocniczy matcher filtrów dla strony
 * Obsługuje wielokrotny wybór - strona pasuje jeśli ma JAKĄKOLWIEK
 * z wybranych wartości dla danego filtra.
 */
function pageMatchesFiltersArray(string $pageFilterJson, array $activeFilters): bool
{
    if (empty($activeFilters)) {
        return true;
    }
    if ($pageFilterJson === '') {
        return false;
    }

    $decoded = json_decode($pageFilterJson, true);
    if (!is_array($decoded)) {
        return false;
    }

    foreach ($activeFilters as $key => $wantedVal) {
        if ($wantedVal === '' || $wantedVal === null) {
            continue;
        }
        if (!isset($decoded[$key])) {
            return false;
        }

        // Normalizuj wartości strony do tablicy
        $pageValues = is_array($decoded[$key])
            ? array_map('strval', $decoded[$key])
            : [(string)$decoded[$key]];

        // Normalizuj wartości filtra z GET do tablicy
        $filterValues = is_array($wantedVal)
            ? array_map('strval', $wantedVal)
            : [(string)$wantedVal];

        // Sprawdź czy jest jakiekolwiek przecięcie
        $intersection = array_intersect($pageValues, $filterValues);
        if (empty($intersection)) {
            return false;
        }
    }

    return true;
}

/**
 * Prosta paginacja
 */
/**
 * Skrócona paginacja z wielokropkami
 * - do 7 stron: pełna lista
 * - powyżej: pierwsza + ostatnia + zakres ±2 wokół aktywnej + wielokropki
 */
function renderPaginationForQuery(
    int $current,
    int $totalPages,
    string $pageParam = 'page',
    ?string $baseUrl = null
): string {
    if ($totalPages <= 1) {
        return '';
    }

    if ($baseUrl === null) {
        $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
    }

    $baseParams = $_GET;
    unset($baseParams[$pageParam]);

    // Zbuduj listę elementów do wyświetlenia
    $pagesToShow = [];

    if ($totalPages <= 7) {
        // Pełna paginacja
        for ($p = 1; $p <= $totalPages; $p++) {
            $pagesToShow[] = $p;
        }
    } else {
        // Skrócona paginacja z wielokropkami (zakres ±2 od aktywnej)
        $range = 2;
        $start = max(2, $current - $range);
        $end   = min($totalPages - 1, $current + $range);

        // Pierwsza strona zawsze
        $pagesToShow[] = 1;

        // Wielokropek lub pojedyncza strona 2
        if ($start > 3) {
            $pagesToShow[] = '...';
        } elseif ($start == 3) {
            $pagesToShow[] = 2;
        }

        // Strony środkowe
        for ($p = $start; $p <= $end; $p++) {
            $pagesToShow[] = $p;
        }

        // Wielokropek lub pojedyncza strona przedostatnia
        if ($end < $totalPages - 2) {
            $pagesToShow[] = '...';
        } elseif ($end == $totalPages - 2) {
            $pagesToShow[] = $totalPages - 1;
        }

        // Ostatnia strona zawsze
        $pagesToShow[] = $totalPages;
    }

    $html = '<ul class="pagination">';

    foreach ($pagesToShow as $p) {
        if ($p === '...') {
            $html .= '<li class="page-item page-item-ellipsis"><span class="page-link">&hellip;</span></li>';
        } else {
            $params             = $baseParams;
            $params[$pageParam] = $p;
            $qs                 = http_build_query($params);
            $url                = $baseUrl . ($qs ? '?'.$qs : '');

            $html .= '<li class="page-item'.($p === $current ? ' active' : '').'">';
            $html .= '<a class="page-link" href="'.$url.'">'.$p.'</a>';
            $html .= '</li>';
        }
    }

    $html .= '</ul>';

    return $html;
}


// ------------------------------------------------------
// 13. Wysyłka maila (PHPMailer + reCAPTCHA)
// ------------------------------------------------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail(array $data): array
{
    global $config;

    // reCAPTCHA v3. Jeśli formularz jest chroniony captchą (ustawiony recaptcha_action
    // lub recaptcha_secret) i sekret jest skonfigurowany — brak tokenu MUSI być odrzucony.
    // Wcześniej pusty token po prostu omijał całą weryfikację (obejście dla botów).
    $captchaSecret  = $data['recaptcha_secret'] ?? ($config['secretKey'] ?? null);
    $captchaContext = !empty($data['recaptcha_action']) || !empty($data['recaptcha_secret']);
    if ($captchaContext && $captchaSecret && empty($data['recaptcha_token'])) {
        return [
            'success' => false,
            'message' => 'reCAPTCHA: brak weryfikacji. Odśwież stronę i spróbuj ponownie.'
        ];
    }

    if (!empty($data['recaptcha_token'])) {
        $secret   = $data['recaptcha_secret'] ?? ($config['secretKey'] ?? null);
        $token    = $data['recaptcha_token'];
        $minScore = isset($data['recaptcha_score']) ? (float)$data['recaptcha_score'] : 0.3;
        $action   = $data['recaptcha_action'] ?? null;

        if (!$secret) {
            return [
                'success' => false,
                'message' => 'Brak klucza reCAPTCHA.'
            ];
        }

        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $query = http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $respRaw = @file_get_contents($verifyUrl.'?'.$query);
        $resp    = $respRaw ? json_decode($respRaw, true) : null;

        if (empty($resp['success'])) {
            return [
                'success' => false,
                'message' => 'reCAPTCHA: weryfikacja nie powiodła się.'
            ];
        }

        if ($action && !empty($resp['action']) && $resp['action'] !== $action) {
            return [
                'success' => false,
                'message' => 'reCAPTCHA: nieprawidłowa akcja.'
            ];
        }

        if (isset($resp['score']) && $resp['score'] < $minScore) {
            return [
                'success' => false,
                'message' => 'reCAPTCHA: zbyt niski wynik ('.$resp['score'].').'
            ];
        }
    }

    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        $baseDir = __DIR__;

        $paths = [
            // 1) Twoja aktualna lokalizacja PHPMailera:
            $baseDir . '/form/PHPMailer/src/Exception.php',
            $baseDir . '/form/PHPMailer/src/PHPMailer.php',
            $baseDir . '/form/PHPMailer/src/SMTP.php',

            // 2) Stare lokalizacje jako fallback (jakbyś kiedyś miał inaczej)
            $baseDir . '/PHPMailer/src/Exception.php',
            $baseDir . '/PHPMailer/src/PHPMailer.php',
            $baseDir . '/PHPMailer/src/SMTP.php',
            $baseDir . '/plugins/PHPMailer/src/Exception.php',
            $baseDir . '/plugins/PHPMailer/src/PHPMailer.php',
            $baseDir . '/plugins/PHPMailer/src/SMTP.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }


    $defaultFromEmail = $config['email'] ?? ('no-reply@'.($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $defaultFromName  = $config['logo']  ?? 'Strona';
    $defaultToEmail   = $config['email'] ?? $defaultFromEmail;

    $to       = $data['to']       ?? $data['email'] ?? $defaultToEmail;
    $toName   = $data['to_name']  ?? $data['to_name'] ?? $config['logo'];
    $from     = $data['from']     ?? $defaultFromEmail;
    $fromName = $data['from_name']?? $defaultFromName;
    $subject  = $data['subject']  ?? ('Kontakt '.$defaultFromName);
    $body     = $data['body']     ?? $data['message'] ?? '';
    $isHtml   = $data['is_html']  ?? true;
    $alertMsg = $data['alert']    ?? 'Wiadomość została wysłana.';

    $replyTo     = $data['reply_to']      ?? null;
    $replyToName = $data['reply_to_name'] ?? null;

    $attachments = $data['attachments'] ?? [];
    if (!empty($data['attachment_tmp'] ?? null)) {
        $attachments[] = [
            $data['attachment_tmp'],
            $data['attachment_name'] ?? basename($data['attachment_tmp'])
        ];
    }

    $smtpHost   = $data['smtp_host']   ?? ($config['smtp_host']   ?? null);
    $smtpUser   = $data['smtp_user']   ?? ($config['smtp_user']   ?? null);
    $smtpPass   = $data['smtp_pass']   ?? ($config['smtp_pass']   ?? null);
    $smtpPort   = $data['smtp_port']   ?? ($config['smtp_port']   ?? 587);
    $smtpSecure = $data['smtp_secure'] ?? ($config['smtp_secure'] ?? 'tls');

    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        $headers .= 'From: "'.$fromName.'" <'.$from.'>'."\r\n";
        if ($replyTo) {
            $headers .= 'Reply-To: '.$replyTo."\r\n";
        }
        $ok = mail(is_array($to) ? reset($to) : $to, $subject, $body, $headers);
        if (!empty($data['attachment_tmp']) && file_exists($data['attachment_tmp'])) {
            @unlink($data['attachment_tmp']);
        }
        return [
            'success' => (bool)$ok,
            'message' => $ok ? $alertMsg : 'Nie udało się wysłać wiadomości (fallback).'
        ];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        if ($smtpHost && $smtpUser && $smtpPass) {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port       = $smtpPort;
        } else {
            $mail->isMail();
        }

        $mail->setFrom($from, $fromName);

        if (is_array($to)) {
            foreach ($to as $addr) {
                $mail->addAddress($addr);
            }
        } else {
            $mail->addAddress($to, $toName);
        }

        if ($replyTo) {
            $mail->addReplyTo($replyTo, $replyToName ?: $replyTo);
        }

        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                if (is_array($att)) {
                    $mail->addAttachment($att[0], $att[1] ?? '');
                } else {
                    $mail->addAttachment($att);
                }
            }
        }

        if ($isHtml) {
            $mail->isHTML(true);
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
        } else {
            $mail->isHTML(false);
            $mail->Body = $body;
        }

        $mail->Subject = $subject;

        $mail->send();

        if (!empty($data['attachment_tmp']) && file_exists($data['attachment_tmp'])) {
            @unlink($data['attachment_tmp']);
        }

        return [
            'success' => true,
            'message' => $alertMsg
        ];
    } catch (Exception $e) {
        if (!empty($data['attachment_tmp']) && file_exists($data['attachment_tmp'])) {
            @unlink($data['attachment_tmp']);
        }
        return [
            'success' => false,
            'message' => 'Nie udało się wysłać wiadomości: '.$mail->ErrorInfo
        ];
    }
}


// ------------------------------------------------------
// 14. Bloki kontaktów / social / język / motyw
// ------------------------------------------------------
function contacts(array $options = []): string
{
    global $config;

    $phone    = !empty($options['phone']);
    $email    = !empty($options['email']);
    $location = !empty($options['location']);
    $hours    = !empty($options['hours']);

    $html = '';

    if ($phone && !empty($config['phone'])) {
        $html .= '<li class="phone"><span class="label">Telefon</span><a href="tel:'.$config['phone'].'" class="value phone1"><img src="'.ICONS.'phone-line.svg" alt="Phone" class="invert">'.$config['phone'].'</a>';
        if (!empty($config['phone2'])) {
            $html .= '<a href="tel:'.$config['phone2'].'" class="value phone2">'.$config['phone2'].'</a>';
        }
        $html .= '</li>';
    }

    if ($email && !empty($config['email'])) {
        $html .= '<li class="email"><span class="label">Adres e-mail</span><a href="mailto:'.$config['email'].'" class="value"><img src="'.ICONS.'email-line.svg" alt="Email" class="invert">'.$config['email'].'</a></li>';
    }

    if ($location && !empty($config['city'])) {
        $html .= '<li class="location"><span class="label">Biuro</span><a href="'.($config['maps'] ?? '#').'" target="_blank" class="value"><img src="'.ICONS.'location-line.svg" alt="Location" class="invert">'.($config['street'] ? $config['street'].', ' : '').$config['city'].'</a>';
        if ($hours) {
            $html .= hoursOpen();
        }
        $html .= '</li>';
    }

    return $html ? '<ul class="contactsList">'.$html.'</ul>' : '';
}


function contactsButtons(): string
{
    global $config, $lang;

    $html = '';
    if (!empty($config['phone'])) {
        $html .= '<li class="contactButtons__item phone"><a href="tel:'.$config['phone'].'" class="button"><img src="'.ICONS.'phone-line.svg" alt="'.($lang['call'] ?? 'Zadzwoń').'" class="icon">'.($lang['call'] ?? 'Zadzwoń').'</a></li>';
    }
    if (!empty($config['email'])) {
        $html .= '<li class="contactButtons__item email"><a href="mailto:'.$config['email'].'" class="button"><img src="'.ICONS.'email-line.svg" alt="'.($lang['email_me'] ?? 'Napisz').'" class="icon">'.($lang['email_me'] ?? 'Napisz').'</a></li>';
    }

    return $html ? '<ul class="contactButtons">'.$html.'</ul>' : '';
}


function socialMedia(array $options = []): string
{
    global $config, $lang;
    $html = '';

    if (!empty($options['contacts'])) {
        if (!empty($config['phone'])) {
            $html .= '<li class="phone"><a href="tel:'.$config['phone'].'"><span class="icon"><img src="'.ICONS.'phone.svg" alt="'.($lang['call'] ?? 'Zadzwoń').'" class="invert"></span><span class="label">'.($lang['call'] ?? 'Zadzwoń').'</span></a></li>';
        }
        if (!empty($config['email'])) {
            $html .= '<li class="email"><a href="mailto:'.$config['email'].'"><span class="icon"><img src="'.ICONS.'email.svg" alt="'.($lang['email_me'] ?? 'Napisz').'" class="invert"></span><span class="label">'.($lang['email_me'] ?? 'Napisz').'</span></a></li>';
        }
    }

    $map = [
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'instagram'=> 'Instagram',
        'youtube'  => 'Youtube',
        'tiktok'   => 'TikTok',
        'twitter'  => 'Twitter',
    ];

    foreach ($map as $key => $label) {
        if (!empty($config[$key])) {
            $html .= '<li class="'.$key.'"><a href="'.$config[$key].'" target="_blank" rel="nofollow noopener"><span class="icon"><img src="'.ICONS.$key.'.svg" alt="'.$label.'" class="invert"></span><span class="label">'.$label.'</span></a></li>';
        }
    }

    return $html ? '<ul class="socialMediaIcons">'.$html.'</ul>' : '';
}


function language(): string
{
    global $config;
    return '<div class="chooseLanguage lang-select chooseLanguage-'.$config['language'].'">
               <div id="google_translate_element"></div>
               <div class="chooseLanguage__button lang-selected" data-val="'.$config['language'].'" translate="no"><img src="'.ICONS.'flag-'.$config['language'].'.png" class="chooseLanguage__button_flag" alt="Language '.$config['language'].'"><span class="chooseLanguage__button_arrow"></span></div>
               <ul class="chooseLanguage__list lang-select-list">
                   <li class="language-item language-item-pl lang-select-item" data-val="pl" translate="no"><img src="'.ICONS.'flag-pl.png" alt="Language PL">Polish</li>
                   <li class="language-item language-item-en lang-select-item" data-val="en" translate="no"><img src="'.ICONS.'flag-en.png" alt="Language GB">English</li>
                   <li class="language-item language-item-de lang-select-item" data-val="de" translate="no"><img src="'.ICONS.'flag-de.png" alt="Language DE">German</li>
               </ul>
            </div>';
}

function darkMode(): string
{
    return '<div class="form-switch dark-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="theme-switch-checkbox">
              <label class="form-check-label" for="theme-switch-checkbox"><img src="'.ICONS.'night.svg" alt="Ciemny motyw" class="invert"></label>
              <span class="label">Ciemny motyw</span>
            </div>';
}

function contrastMode(): string
{
    return '<div class="form-switch contrast-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="contrast-switch-checkbox">
              <label class="form-check-label" for="contrast-switch-checkbox">
                  <img src="'.ICONS.'contrast.svg" alt="Wysoki kontrast" class="invert">
              </label>
              <span class="label">Wysoki kontrast</span>
            </div>';
}

function fontSizeToggle(): string
{
    return '<div class="form-switch font-size-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="font-size-switch-checkbox">
              <label class="form-check-label" for="font-size-switch-checkbox">
                  <img src="'.ICONS.'font.svg" alt="Większa czcionka" class="invert">
              </label>
              <span class="label">Większa czcionka</span>
            </div>';
}


function location(): string
{
    global $config;
    return !empty($config['city'])
        ? '<div class="location"><img src="'.ICONS.'location.svg" alt="Location">'.($config['street'] ? $config['street'].', ' : '').$config['city'].'</div>'
        : '';
}


// ------------------------------------------------------
// 15. Godziny otwarcia + czy teraz otwarte
// ------------------------------------------------------
function hoursOpen(bool $showList = true): string
{
    global $config;

    $day   = (int)date('N');   // 1-7
    $time  = date('H,i');

    $start_tydz = $config['hours_1'] ?? '';
    $end_tydz   = $config['hours_2'] ?? '';
    $start_sob  = $config['hours_3'] ?? '';
    $end_sob    = $config['hours_4'] ?? '';
    $start_ndz  = $config['hours_5'] ?? '';
    $end_ndz    = $config['hours_6'] ?? '';

    $html = "<div class='hoursOpen'>";

    if ($showList) {
        $html .= "<div class='hoursOpen__title'><img src='".ICONS."time.svg' alt='Godziny otwarcia' class='invert'>Godziny otwarcia</div>";
        $html .= "<ul class='hoursOpen__list'>";
        if ($start_tydz && $end_tydz) {
            $html .= "<li>Poniedziałek - piątek: <strong>{$start_tydz} - {$end_tydz}</strong></li>";
        }
        if ($start_sob && $end_sob) {
            $html .= "<li>Sobota: <strong>{$start_sob} - {$end_sob}</strong></li>";
        }
        if ($start_ndz && $end_ndz) {
            $html .= "<li>Niedziela: <strong>{$start_ndz} - {$end_ndz}</strong></li>";
        }
        $html .= "</ul>";
    }

    $openedText = '<span class="hoursOpen__close"></span>Biuro obecnie jest <strong>nieczynne</strong>.';

    if ($day >= 1 && $day <= 5 && $start_tydz && $end_tydz) {
        if ($time > str_replace(':', ',', $start_tydz) && $time < str_replace(':', ',', $end_tydz)) {
            $openedText = '<span class="hoursOpen__open"></span>Biuro obecnie jest <strong>otwarte</strong> i będzie czynne do godz. '.$end_tydz.'.';
        }
    } elseif ($day == 6 && $start_sob && $end_sob) {
        if ($time > str_replace(':', ',', $start_sob) && $time < str_replace(':', ',', $end_sob)) {
            $openedText = '<span class="hoursOpen__open"></span>Biuro obecnie jest <strong>otwarte</strong> i będzie czynne do godz. '.$end_sob.'.';
        }
    } elseif ($day == 7 && $start_ndz && $end_ndz) {
        if ($time > str_replace(':', ',', $start_ndz) && $time < str_replace(':', ',', $end_ndz)) {
            $openedText = '<span class="hoursOpen__open"></span>Biuro obecnie jest <strong>otwarte</strong> i będzie czynne do godz. '.$end_ndz.'.';
        }
    }

    $html .= '<div class="hoursOpen__desc">'.$openedText.'</div>';
    $html .= "</div>";

    return $html;
}


// ------------------------------------------------------
// 16. KALENDARZ REZERWACJI
// ------------------------------------------------------
function getMonthReservations(int $year, int $month): array
{
    $oSql = Sql::getInstance();

    $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
    $endOfMonth   = date('Y-m-t', strtotime($startOfMonth));

    $sql = "
        SELECT sDateStart, sDateEnd
        FROM form
        WHERE
            (sDateStart IS NOT NULL AND sDateStart != '')
            AND (
                sDateStart <= :endOfMonth
                AND (sDateEnd >= :startOfMonth OR sDateEnd IS NULL OR sDateEnd = '')
            )
    ";

    $stmt = $oSql->prepare($sql);
    $stmt->execute([
        ':startOfMonth' => $startOfMonth,
        ':endOfMonth'   => $endOfMonth,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $busyDays = [];

    foreach ($rows as $row) {
        $start = $row['sDateStart'];
        $end   = $row['sDateEnd'] ?: $row['sDateStart'];

        $cur   = strtotime($start);
        $endTs = strtotime($end);

        while ($cur <= $endTs) {
            $day = date('Y-m-d', $cur);
            $busyDays[$day] = true;
            $cur = strtotime('+1 day', $cur);
        }
    }

    return $busyDays;
}


if (!function_exists('html')) {
    function html($string)
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}



require_once 'plugins/shop-functions.php';

// Moduł InstaFeed — udostępnia renderInstaFeed() w każdym szablonie.
// (widget.php ładuje tylko klasę; config/token czytane leniwie przy wywołaniu)
require_once 'plugins/instagram/widget.php';


?>
