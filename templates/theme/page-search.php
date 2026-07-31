<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

require_once $theme.'_header.php';

if (isset($aData['sName'])) {

    require_once $theme.'_title.php';

    // ----------------------------------------
    // 1. Pobierz frazę z GET
    // ----------------------------------------
    $query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    // ----------------------------------------
    // 2. Wyniki wyszukiwania z bazy
    // ----------------------------------------
    $results      = [];
    $resultsCount = 0;

    if ($query !== '') {
        // escapujemy % i _ żeby nie psuły LIKE
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

        $sql = <<<SQL
            SELECT iPage, sName, sPrice, sDescriptionShort, sDescriptionFull
            FROM pages
            WHERE iStatus > 0
              AND sLang = :lang
              AND (
                    sName LIKE :like ESCAPE '\'
                    OR sDescriptionShort LIKE :like ESCAPE '\'
                    OR sDescriptionFull LIKE :like ESCAPE '\'
                  )
            ORDER BY iPosition DESC, iPage DESC
            LIMIT 100
        SQL;

        $stmt = $oSql->prepare($sql);
        $stmt->execute([
            ':lang' => $config['language'],
            ':like' => $like,
        ]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id   = (int)$row['iPage'];
            $name = $row['sName'];
            $link = getUrl($id) ?? '#';

            // opis:
            // 1) sDescriptionShort jeśli jest
            // 2) w przeciwnym wypadku ucięty sDescriptionFull
            $desc = (string)($row['sDescriptionShort'] ?? '');
            if ($desc === '' && !empty($row['sDescriptionFull'])) {
                $desc = mb_substr(strip_tags($row['sDescriptionFull']), 0, 180).'...';
            }

            $results[] = [
                'id'   => $id,
                'name' => $name,
                'link' => $link,
                'desc' => $desc,
            ];
        }

        $resultsCount = count($results);
    }

    // ----------------------------------------
    // 3. Treść CMS (opis pełny na dole)
    // ----------------------------------------
    $pageFullDesc = '';
    if (!empty($aData['sDescriptionFull']) && $aData['sDescriptionFull'] !== $aData['sDescriptionShort']) {
        $pageFullDesc = $aData['sDescriptionFull'];
    }
    ?>

    <div class="container pageSearch">
        <div class="mainPage__wrapper">
            <?php require_once $theme.'/_column.php'; ?>

            <div class="mainPage__content">

                <?php if (!empty($aData['sDescriptionShort'])): ?>
                    <div class="mainPage__descShort">
                        <?php echo $aData['sDescriptionShort']; ?>
                    </div>
                <?php endif; ?>

                <!-- FORMULARZ SZUKANIA -->
                <form action="<?php echo getUrl($aData['iPage']); ?>" method="get" class="searchForm mb-3">
                    <div class="row">
                        <div class="col-4">
                            <div class="form-floating form-item">
                                <input
                                    type="text"
                                    name="q"
                                    id="q"
                                    class="form-control"
                                    placeholder="Szukaj..."
                                    value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                                <label for="q">Wpisz szukaną frazę</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="button button-lg w-100 h-100">
                                Szukaj
                            </button>
                        </div>
                    </div>
                </form>

                <?php if ($query !== ''): ?>
                    <?php if ($resultsCount === 0): ?>

                        <div class="alert alert-info">
                            Brak wyników dla szukanej frazy.
                        </div>

                    <?php else: ?>

                        <p class="mb-3">Znalezione wyniki: <?php echo $resultsCount; ?></p>

                        <ul class="pagesList searchList">
                            <?php foreach ($results as $row): ?>
                                <li class="pageItem">
                                    <div class="content">
                                        <h4 class="title">
                                            <a href="<?php echo $row['link']; ?>">
                                                <?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </h4>

                                        <?php if ($row['desc'] !== ''): ?>
                                            <div class="desc">
                                                <?php echo $row['desc']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <footer class="footer">
                                        <a class="link_underline" href="<?php echo $row['link']; ?>">
                                            Zobacz więcej
                                        </a>
                                    </footer>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($pageFullDesc !== ''): ?>
                    <div class="mainPage__desc">
                        <?php echo $pageFullDesc; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php
} else {
    require_once $theme.'_404.php';
}

require_once $theme.'_footer.php';
?>
