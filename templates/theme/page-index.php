<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

// ==========================================================
// STRONA GŁÓWNA — PRZYKŁADOWE SEKCJE (WZORY DO REPLIKACJI)
// ==========================================================
// To NIE jest sztywny układ. Każda sekcja to wzorzec pokazujący
// przyjęty styl: (1) moduł włączany przez feature() = przypisanie strony w
// panelu (Konfiguracja → „Dopasowanie stron"); pusta strona = sekcja znika,
// (2) treść ZAWSZE z bazy (getData()), etykiety z $lang[], linki z getUrl(),
// (3) własna klasa .section + .section__scroll pod animacje.
// Przy nowym projekcie: rozbudowuj, dokładaj i twórz nowe sekcje śmiało
// (patrz CLAUDE.md §9.7). Zachowaj tylko sens struktury, nie ograniczaj się do tych.

require_once $theme.'_header.php';

?>
<!-- HERO SECTION -->

        


<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "url": "<?php echo BASE_URL; ?>",
  "name": "<?php echo $config['logo']; ?>",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "<?php echo getUrl($config['search_page']); ?>?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
</script>

<?php require_once $theme.'_footer.php'; ?>
