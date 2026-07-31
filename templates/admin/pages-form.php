<?php
if (!defined('ADMIN_PAGE')) {
    exit('Script by OpenSolution.org');
}

/**
 * Pack filters from POST to JSON string for sFilter.
 * Obsługuje wielokrotny wybór filtrów (tablice wartości).
 */
function packFiltersFromPost(array $post): string
{
    if (!isset($post['filters']) || !is_array($post['filters'])) {
        return '';
    }

    $clean = [];
    foreach ($post['filters'] as $k => $v) {
        $k = trim((string) $k);
        if ($k === '') {
            continue;
        }

        // Obsługa tablicy wartości (wielokrotny wybór)
        if (is_array($v)) {
            $values = [];
            foreach ($v as $val) {
                $val = trim((string) $val);
                if ($val !== '') {
                    $values[] = $val;
                }
            }
            if (!empty($values)) {
                $clean[$k] = $values;
            }
        } else {
            // Pojedyncza wartość (kompatybilność wsteczna)
            $v = trim((string) $v);
            if ($v !== '') {
                $clean[$k] = $v;
            }
        }
    }

    if (empty($clean)) {
        return '';
    }

    $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
    return $json !== false ? $json : '';
}

// --------------------------------------------
// ZAPIS
// --------------------------------------------
if (isset($_POST['sName'])) {
    // Filtry -> JSON do sFilter
    $_POST['sFilter'] = packFiltersFromPost($_POST);

    $iPage = $oPage->savePage($_POST);

    if (isset($_POST['sOptionList'])) {
        header('Location: ' . $config['admin_file'] . '?p=pages&sOption=save');
    } elseif (isset($_POST['sOptionAddNew'])) {
        header('Location: ' . $config['admin_file'] . '?p=pages-form&sOption=save');
    } else {
        header('Location: ' . $config['admin_file'] . '?p=pages-form&sOption=save&iPage=' . (int) $iPage);
    }
    exit;
}

// --------------------------------------------
// ODCZYT
// --------------------------------------------
$aData = [];
if (isset($_GET['iPage']) && is_numeric($_GET['iPage'])) {
    $aData = $oPage->throwPageAdmin($_GET['iPage']);
}

// konfiguracja filtrów (z settings.php)
global $filters;

// obecne wartości filtrów z bazy (obsługa wielokrotnego wyboru)
$currentFilters = [];
if (!empty($aData['sFilter'])) {
    $raw = html_entity_decode($aData['sFilter'], ENT_QUOTES, 'UTF-8');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        // Normalizacja: pojedyncze wartości zamień na tablice dla spójności
        foreach ($decoded as $key => $val) {
            if (is_array($val)) {
                $currentFilters[$key] = $val;
            } else {
                $currentFilters[$key] = [$val];
            }
        }
    }
}

// menu / kontekst shop
$isShop = (
    (!empty($aData['iMenu']) && (int) $aData['iMenu'] === 2)
    || (isset($_GET['shop']) && (int) $_GET['shop'] === 1)
);

$iMenu = $isShop ? 2 : 0;
$sSelectedMenu = $isShop ? 'shop' : 'pages';

$iPageParent = $aData['iPageParent'] ?? (is_numeric($config['default_page_parent']) ? $config['default_page_parent'] : null);

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>

<form action="?p=<?php echo html($_GET['p']); ?>" name="form" method="post" class="main-form">

    <header class="mainPage__header mainPage__header_row">
        <h1 class="mainPage__title">
            <?php if (!empty($aData['iPage'])): ?>
                <?php
                $previewUrl = (!empty($aData['iPage']))  ? getUrl($aData['iPage']) : '#';
                ?>
                <a href="<?php echo html($previewUrl); ?>" title="<?php echo html($lang['Preview']); ?>">
                    <?php echo html($aData['sName'] ?? ''); ?>
                </a>
            <?php else: ?>
                <?php echo html($lang['New_page']); ?>
            <?php endif; ?>
        </h1>

        <div class="mainPage__buttons d-flex justify-content-between">
            <a href="?p=pages" class="button button-light mr-2">Wróć do listy</a>
            <input type="submit" name="sOption" class="button" value="<?php echo html($lang['save']); ?>" />
        </div>
    </header>

    <?php
    if (isset($config['manual_link'])) {
        echo '<div class="manual"><a href="' . html($config['manual_link']) . 'instruction#pages-form" title="' . html($lang['Help']) . '" target="_blank"></a></div>';
    }
    if (isset($_GET['sOption'])) {
        echo '<div class="alert alert-success">' . html($lang['Operation_completed']) . '</div>';
    }
    ?>

    <input type="hidden" name="iPage" id="iPage" value="<?php echo !empty($aData['iPage']) ? (int) $aData['iPage'] : ''; ?>" />

    <ul class="tabs">
        <li id="content" class="selected"><a href="#content"><?php echo html($lang['Content']); ?></a></li>
        <li id="options"><a href="#options"><?php echo html($lang['Options']); ?></a></li>
        <li id="seo"><a href="#seo"><?php echo html($lang['Seo']); ?></a></li>
        <li id="files"><a href="#files">Zdjęcia & <?php echo html($lang['Files']); ?></a></li>
        <li id="add-files"><a href="#add-files"><?php echo html($lang['Add_files']); ?></a></li>
    </ul>

    <!-- CONTENT -->
    <ul id="tab-content" class="forms full tabsContent">
        <li>
            <div class="form-item">
                <label for="sName"><?php echo html($lang['Name']); ?></label>
                <input
                    type="text"
                    class="form-control"
                    id="sName"
                    name="sName"
                    value="<?php echo html($aData['sName'] ?? ''); ?>"
                    placeholder="<?php echo html($lang['only_this_field_is_required']); ?>"
                    required
                    data-form-check="required"
                >
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sDesc">Slogan</label>
                <input type="text" name="sDesc" id="sDesc" value="<?php echo html($aData['sDesc'] ?? ''); ?>" />
            </div>
        </li>

        <li class="parent">
            <div class="form-item">
                <label for="iPageParent"><?php echo html($lang['Parent_page']); ?></label>
                <select name="iPageParent" id="iPageParent" size="15">
                    <option value=""<?php echo (empty($iPageParent) || (int) $iPageParent === 0) ? ' selected="selected"' : ''; ?>>
                        <?php echo html($lang['none']); ?>
                    </option>
                    <?php echo $oPage->listPagesSelectAdmin($iPageParent, $iMenu); ?>
                </select>
            </div>
        </li>

        <li class="short-description">
            <div class="form-item">
                <label for="sDescriptionShort"><?php echo html($lang['Short_description']); ?></label>
                <?php echo getTextarea('sDescriptionShort', $aData['sDescriptionShort'] ?? null, ['iHeight' => '120']); ?>
            </div>
        </li>

        <li class="full-description">
            <div class="form-item">
                <label for="sDescriptionFull"><?php echo html($lang['Full_description']); ?></label>
                <?php echo getTextarea('sDescriptionFull', $aData['sDescriptionFull'] ?? null, ['iHeight' => '300', 'sClassName' => 'text-editor full-description']); ?>
            </div>
        </li>
    </ul>

    <!-- OPTIONS -->
    <ul id="tab-options" class="forms list tabsContent">
        <li class="custom">
            <div class="form-item">
                <label for="iStatus"><?php echo html($lang['Status']); ?></label>
                <?php echo getYesNoBox('iStatus', $aData['iStatus'] ?? (isset($config['default_pages_status']) ? 1 : 0)); ?>
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="iPosition"><?php echo html($lang['Position']); ?></label>
                <input type="text" id="iPosition" name="iPosition" value="<?php echo html($aData['iPosition'] ?? 0); ?>" class="numeric" size="3" maxlength="4" />
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sRedirect"><?php echo html($lang['Address']); ?></label>
                <input type="text" name="sRedirect" value="<?php echo html($aData['sRedirect'] ?? ''); ?>" id="sRedirect" size="75" placeholder="np. kontakt, lub https://kosa-x.com" />
            </div>
        </li>
        
        <li><h5 class="form-separator">Wygląd</h5></li>

        <li class="custom">
            <div class="form-item">
                <label for="sExpandMenu">Rozwijanie w nawigacji</label>
                <?php $expand = isset($aData['sExpandMenu']) ? (int) $aData['sExpandMenu'] : 1; ?>
                <select name="sExpandMenu" id="sExpandMenu">
                    <option value="0" <?php echo $expand === 0 ? 'selected="selected"' : ''; ?>>Nie</option>
                    <option value="1" <?php echo $expand === 1 ? 'selected="selected"' : ''; ?>>Tak</option>
                </select>
            </div>
        </li>
        
        

        <li class="custom">
            <div class="form-item">
                <label for="sPanel">Panel boczny</label>
                <?php $panel = isset($aData['sPanel']) ? (int) $aData['sPanel'] : 1; ?>
                <select name="sPanel" id="sPanel">
                    <option value="0" <?php echo $panel === 0 ? 'selected="selected"' : ''; ?>>Nie</option>
                    <option value="1" <?php echo $panel === 1 ? 'selected="selected"' : ''; ?>>Tak</option>
                </select>
            </div>
        </li>
        
        <li class="custom">
            <div class="form-item">
                <label for="sType">Typ strony</label>
                <?php $sType = isset($aData['sType']) ? (int) $aData['sType'] : 1; ?>
                <select name="sType" id="sType">
                    <option value="1" <?php echo $sType === 1 ? 'selected="selected"' : ''; ?>>Zwykła</option>
                    <option value="2" <?php echo $sType === 2 ? 'selected="selected"' : ''; ?>>Produktowa</option>
                    <option value="3" <?php echo $sType === 3 ? 'selected="selected"' : ''; ?>>100 % szerokości</option>
                </select>
            </div>
        </li>

     

        <?php if ($iMenu === 20): ?>
            <input type="hidden" id="iTheme" name="iTheme" value="6" class="numeric" size="3" maxlength="4" />
            <input type="hidden" id="iMenu" name="iMenu" value="2" class="numeric" size="3" maxlength="4" />
        <?php else: ?>
            <li>
                <div class="form-item">
                    <label for="iMenu"><?php echo html($lang['Menu']); ?></label>
                    <select name="iMenu" id="iMenu">
                        <?php echo getSelectFromArray($config['pages_menus'], $aData['iMenu'] ?? $config['default_pages_menu']); ?>
                    </select>
                </div>
            </li>

            <li>
                <div class="form-item">
                    <label for="iTheme"><?php echo html($lang['Template']); ?></label>
                    <select name="iTheme" id="iTheme">
                        <?php echo getThemesSelect($aData['iTheme'] ?? $config['default_theme']); ?>
                    </select>
                </div>
            </li>
        <?php endif; ?>

        <li><h5 class="form-separator">Dodatkowe parametry</h5></li>

        <li>
            <div class="form-item">
                <label for="sDate">Data publikacji</label>
                <input type="date" id="sDate" name="sDate" value="<?php echo html($aData['sDate'] ?? ''); ?>" />
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sPrice">Cena</label>
                <input type="text" name="sPrice" value="<?php echo html($aData['sPrice'] ?? ''); ?>" id="sPrice" size="75" />
            </div>
        </li>

        <?php if (!empty($filters) && is_array($filters)): ?>
            <li>
                <?php foreach ($filters as $filterId => $filterConf): ?>
                    <?php
                    $fKey = $filterConf['key'] ?? ('filter_' . $filterId);
                    $fKeySafe = preg_replace('/[^a-z0-9_\-]/i', '', $fKey);
                    $fLabel = $filterConf['label'] ?? ('Filtr ' . $filterId);
                    $fVals = $filterConf['values'] ?? [];
                    $selectedVals = $currentFilters[$fKey] ?? [];
                    ?>
                    <div class="form-item filter-multiselect">
                        <label for="filter-<?php echo html($fKeySafe); ?>" style="display:block;font-weight:600;margin-bottom:8px;">
                            <?php echo html($fLabel); ?>
                        </label>

                        <select
                            name="filters[<?php echo html($fKey); ?>][]"
                            id="filter-<?php echo html($fKeySafe); ?>"
                            class="filter-multi-select"
                            data-placeholder="Wybierz opcje..."
                            data-search="true"
                            data-select-all="true"
                            multiple
                        >
                            <?php foreach ($fVals as $valId => $valLabel): ?>
                                <?php
                                $isSelected = in_array((string)$valId, array_map('strval', $selectedVals), true);
                                ?>
                                <option value="<?php echo html($valId); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                    <?php echo html($valLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </li>
        <?php endif; ?>
    </ul>

    <!-- SEO -->
    <ul id="tab-seo" class="forms list tabsContent">
        <li>
            <div class="form-item">
                <label for="sTitle"><?php echo html($lang['Page_title']); ?></label>
                <input type="text" name="sTitle" value="<?php echo html($aData['sTitle'] ?? ''); ?>" id="sTitle" size="75" />
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sUrl"><?php echo html($lang['Url_name']); ?></label>
                <input type="text" name="sUrl" value="<?php echo html($aData['sUrl'] ?? ''); ?>" id="sUrl" size="75" />
            </div>
        </li>

        <li>
            <div class="form-item">
                <label for="sDescriptionMeta"><?php echo html($lang['Meta_description']); ?></label>
                <input type="text" name="sDescriptionMeta" value="<?php echo html($aData['sDescriptionMeta'] ?? ''); ?>" id="sDescriptionMeta" size="75" />
            </div>
        </li>
    </ul>

    <!-- ADD FILES -->
    <section id="tab-add-files" class="forms files tabsContent">
        <script src="plugins/valums-file-uploader/fileuploader.min.js"></script>
        <div id="fileUploader"></div>

        <h5 class="form-separator mb-4">Lub wybierz pliki z serwera wgrane już wcześniej i kliknij "Zapisz".</h5>

        <div id="files-dir" class="rwd-inner-container">
            <?php echo $oFile->listFilesInDir(['sSort' => 'time']); ?>
        </div>
    </section>

    <!-- FILES -->
    <section id="tab-files" class="forms files tabsContent">
        <?php
        if (!empty($aData['iPage'])) {
            $aData['sFileList'] = $oFile->listAllFiles($aData['iPage']);
        }
        echo !empty($aData['sFileList']) ? $aData['sFileList'] : '<div class="alert alert-info">' . html($lang['Data_not_found']) . '</div>';
        ?>
    </section>

    <div class="mainPage__buttons mt-3">
        <input type="submit" name="sOption" class="button mr-2" value="<?php echo html($lang['save']); ?>" />

        <input type="submit" value="<?php echo html($lang['save_add_new']); ?>" name="sOptionAddNew" class="button button-border mr-2 mobile-hide" />
        <input type="submit" value="<?php echo html($lang['save_list']); ?>" name="sOptionList" class="button button-border mobile-hide" />

        <?php if (!empty($aData['iPage'])): ?>
            <a href="./adm.php?p=pages&iItemDelete=<?php echo (int) $aData['iPage']; ?>" onclick="return del(this)" class="ml-auto">Usuń stronę</a>
        <?php endif; ?>
    </div>

</form>

<script>
    var sTypesSelect = '<?php echo getSelectFromArray($config['images_locations'], $config['default_image_location']); ?>',
        sSizeSelect = '<?php echo getThumbnailsSelect($config['default_image_size']); ?>';

    $(function () {
        var uploader = new qq.FileUploader({
            element: document.getElementById('fileUploader'),
            action: aQuick['sPhpSelf'] + '?p=ajax-files-upload&sTokenCsrf=' + encodeURIComponent(aQuick['sTokenCsrf'] || ''),
            inputName: 'sFileName',
            uploadButtonText: '<?php echo addslashes($lang['Files_from_computer']); ?>',
            cancelButtonText: '<?php echo addslashes($lang['Cancel']); ?>',
            failUploadText: '<?php echo addslashes($lang['Upload_failed']); ?>',
            <?php echo (isset($config['enable_files_uploader_dropzone']) ? 'hideDropzones: false,' : null); ?>
            dragText: '<?php echo addslashes($lang['Drop_files_to_upload']); ?>',
            onComplete: function (id, fileName, response) {
                if (!response.success) {
                    return;
                }
                if (uploader.getInProgress() == 0) {
                    refreshFiles();
                }
                if (response.size_info) {
                    qq.addClass(uploader._getItemByFileId(id), 'qq-upload-maxdimension');
                    uploader._getItemByFileId(id).innerHTML += '<span class="qq-upload-warning"><?php echo addslashes($lang['Image_over_max_dimension']); ?></span>';
                }
            }
        });

        displayTabInit(checkPagesTab);
        checkPageParent();
        $('#iPageParent').on('change', checkPageParent);
        checkChangedFile();
        $(".main-form").quickform();

        $('#tab-content li.short-description label a.expand').click(function () {
            displayShortDescriptionField(true);
        });

        <?php
        if (!empty($aData['sDescriptionShort'])) {
            echo 'displayShortDescriptionField(false);';
        }
        ?>

//        document.querySelectorAll('select.filter-multi-select[name]').forEach(function(select) {
//            var filterName = select.getAttribute('name').replace(/\[\]$/, '');
//
//            new MultiSelect(select, {
//                placeholder: 'Wybierz opcje...',
//                search: true,
//                selectAll: true,
//                listAll: true,
//                name: filterName
//            });
//        });

        filesFromServerEvents();
    });
</script>

<?php
require_once 'templates/admin/_footer.php';
?>