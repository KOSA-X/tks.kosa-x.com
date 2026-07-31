<div class="mainPage">
    <header class="mainPage__header">
        <div class="container">
          <h1 class='mainPage__title'><?php echo ( isset( $config['message'] ) ? $config['message'] : $lang['Data_not_found'] ); ?></h1>
        </div>
    </header>

    <div class="container">
        <div class="mainPage__wrapper">
            <h3><img src="images/icons/404.svg" style="width:75px" class="mb-3">Błąd 404</h3>
            <p class="mt-3 mb-3">Podana strona nie istnieje. Sprawdź czy wpisałeś poprawny adres URL.</p>
            <p><a href="./" class="button">Strona główna</a></p>
        </div>
    </div>
