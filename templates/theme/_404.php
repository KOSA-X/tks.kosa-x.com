<div class="mainPage">
    <header class="mainPage__header">
        <div class="container">
         <div class="mainPage__header_row">
             <div class="mainPage__header_icon"><img src="images/icons/404.svg" ></div>
         
         <div class="mainPage__header_content">
             <h1 class='mainPage__title'><?php echo ( isset( $config['message'] ) ? $config['message'] : $lang['Data_not_found'] ); ?></h1>
         </div>
         
         </div>
          
        </div>
    </header>

    <div class="container">
        <div class="mainPage__wreapper">
           
            <p class="mt-3 mb-3 font-md">Podana strona nie istnieje. Sprawdź czy wpisałeś poprawny adres URL.</p>
            <p><a href="./" class="button">Strona główna</a></p>
        </div>
    </div>
