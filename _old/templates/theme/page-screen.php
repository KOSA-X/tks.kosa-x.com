<?php 

//768x512

if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';

$day_match_result = $oSql->getQuery('SELECT * FROM pages WHERE iPage="7" LIMIT 1');
$day_match = $day_match_result->fetch(PDO::FETCH_ASSOC);

$team_1_name = getData($day_match['team_1'], "sName");
$team_1_short_name = getData($day_match['team_1'], "sDate");
$team_2_name = getData($day_match['team_2'], "sName");
$team_2_short_name = getData($day_match['team_2'], "sDate");
    
$team_1_herb = $oFile->listImages( $day_match['team_1'], Array( 'iType' => 1, 'class' => 'herb',  'bNoLinks' => TRUE, 'full_image' => TRUE)); 
$team_2_herb = $oFile->listImages( $day_match['team_2'], Array( 'iType' => 1, 'class' => 'herb', 'full_image' => TRUE,  'bNoLinks' => TRUE)); 

$team_list_array = $oSql->getQuery( 'SELECT * FROM pages WHERE (iPageParent='.$day_match['team_1'].' OR iPageParent='.$day_match['team_2'].') AND iStatus=1 AND iPosition!=0 AND player_11>0 ORDER BY sDesc DESC, iPosition ASC ');

$first_squad_1 = "";
$first_squad_2 = "";
$reserve_1 = "";
$reserve_2 = "";

while( $data_team = $team_list_array->fetch( PDO::FETCH_ASSOC ) ){
    $team_content = "<li class=''><span class='number'>".$data_team['iPosition']."</span><span class='name'>".$data_team['sName']."</span></li>";
    
    if($data_team['iPageParent'] == $day_match['team_1']){
        if($data_team['player_11'] == 1){
            $first_squad_1 .= $team_content;
        }elseif($data_team['player_11'] == 2){
            $reserve_1 .= $team_content;
        }
    }
    
    if($data_team['iPageParent'] == $day_match['team_2']){
        if($data_team['player_11'] == 1){
            $first_squad_2 .= $team_content;
        }elseif($data_team['player_11'] == 2){
            $reserve_2 .= $team_content;
        }
    }
}
?>

<style>
body {
    color: #fff;
    background: #000;
}
.mainHeader { display: none; }
.mainPage__header { padding: 30px 0 !important; }
.pagesTree { display: none; }
</style>

<script>
$(document).ready(function() {

    // =============================================
    // JEDEN ZBIORCZY AJAX — timer, score, panele
    // =============================================
    function updateAll() {
        $.post('ajax.php', { action: 'get_all' }, function(data) {
            
            // --- Timer ---
            var totalSeconds = parseInt(data.timer);
            var half = parseInt(data.half);
            $('.telebimWindow .time').attr('id', 'half_' + data.half);

            if (!isNaN(totalSeconds)) {
                // Limit czasu: 1. połowa = 45:00, 2. połowa = 90:00
                if (half == 1 && totalSeconds > 2700) {
                    totalSeconds = 2700;
                } else if (half == 2 && totalSeconds > 5400) {
                    totalSeconds = 5400;
                }

                var minutes = Math.floor(totalSeconds / 60);
                var seconds = totalSeconds % 60;

                if ((half == 1 && minutes >= 45) || (half == 2 && minutes >= 90)) {
                    $('.telebimWindow .time').addClass('extra');
                } else {
                    $('.telebimWindow .time').removeClass('extra');
                }

                var formattedTime =
                    (minutes < 10 ? "0" : "") + minutes + ":" +
                    (seconds < 10 ? "0" : "") + seconds;
                $('#timer').text(formattedTime);
            }

            // --- Score ---
            $('.score-result').text(data.score);

            // --- Visibility plansz ---
            $.each(data.panels, function(name, visible) {
                var box = $('#' + name);
                if (visible == 1) {
                    box.addClass('is-visible');
                } else {
                    box.removeClass('is-visible');
                }
            });

        }, 'json');
    }

    setInterval(updateAll, 1000);
    updateAll();


    // =============================================
    // EVENTY LIVE — bez duplikacji, auto-ukrywanie
    // =============================================
    var displayedEvents = {};

    function fetchEvents() {
        $.get("fetch_events.php", function(data) {
            var $events = $(data).filter('.obsEvent');

            $events.each(function() {
                var $event = $(this);
                var eventId = $event.data('id');

                // Pomijaj już wyświetlone
                if (displayedEvents[eventId]) return;
                displayedEvents[eventId] = true;

                // Dodaj z ukryciem, potem animuj wjazd
                $event.css({ opacity: 0, transform: 'translate3d(-60px, 0, 0)' });
                $('#live_events').append($event);
//                $('.obsVisibility').addClass("is-muted");

                // Wjazd z animacją
                setTimeout(function() {
                    $event.css({
                        transition: 'opacity 0.4s ease, transform 0.4s ease',
                        opacity: 1,
                        transform: 'translate3d(0, 0, 0)'
                    });
                }, 50);

                // Auto-ukrycie po 15 sekundach
                setTimeout(function() {
                    $event.css({
                        transition: 'opacity 0.4s ease, transform 0.4s ease',
                        opacity: 0,
                        transform: 'translate3d(-60px, 0, 0)'
                    });
                    // Usuń z DOM po animacji
                    setTimeout(function() {
                        $event.remove();
                        // Odkryj telebim jeśli nie ma już żadnych aktywnych eventów
                        if ($('#live_events').children('.obsEvent').length === 0) {
//                            $('.obsVisibility').removeClass("is-muted");
                        }
                    }, 500);
                }, 15000);
            });

        }).fail(function() {
            console.log("Błąd pobierania eventów.");
        });
    }

    setInterval(fetchEvents, 3000);
    fetchEvents();


    // =============================================
    // LISTA ZDARZEŃ (podsumowanie)
    // =============================================
    function listEvents() {
        $.get("list_events.php", function(data) {
            $("#list_events").html(data);
            $("#list_events_2").html(data);
        }).fail(function() {
            console.log("Błąd pobierania listy zdarzeń.");
        });
    }

    setInterval(listEvents, 10000);
    listEvents();

});
</script>

<div class="telebimWindow">

    <!-- GÓRNY PASEK: WYNIK + CZAS -->
    <div class="obsVisibility" id="wynik">
        <div class="telebimMatch">
           
           
           <div class="telebimMatch__time">
               <div class="time"><span id="timer">00:00</span></div>
           </div>
           
           <div class="telebimMatch__result">
               <div class="result"><span class="score-result">0:0</span></div>
           </div>
           
           <div class="telebimMatch__row">
               <div class="telebimMatch__team team_1">
                   <?php echo $team_1_herb; ?>
                   <div class="telebimMatch__name"><?php echo $team_1_short_name; ?></div>
               </div>
               <div class="telebimMatch__team team_2">
                   <?php echo $team_2_herb; ?>
                   <div class="telebimMatch__name"><?php echo $team_2_short_name; ?></div>
               </div>
               
           </div>
           
           
            
        </div>
    </div>

    <!-- LIVE EVENTY (gol, kartka, zmiana) -->
    <div class="telebimLive">
        <div id="live_events" class="obsEvent__list"></div>
    </div>

    <!-- PLANSZE INFO -->
    <div class="telebimInfoBox">

        <!-- DZIEŃ MECZOWY -->
<!--
        <div class="infoBox dayMatch obsVisibility" id="dzien_meczowy">
            <header class="infoBox__header">
                <span class="title"><?php echo getData(7, "sDate"); ?></span>
                <span class="title"><?php echo getData(7, "sPrice"); ?></span>
            </header>
            <div class="infoBox__content">
                <div class="dayMatch__header">
                    <div class="team_1">
                        <?php echo $team_1_herb; ?>
                        <div class="name"><?php echo $team_1_short_name; ?></div>
                    </div>
                    <div class="dayMatch__result">
                        <span class="score-result">0:0</span>
                    </div>
                    <div class="team_1">
                        <?php echo $team_2_herb; ?>
                        <div class="name"><?php echo $team_2_short_name; ?></div>
                    </div>
                </div>
                <?php echo getData(7, "sDescriptionFull"); ?>
            </div>
        
        </div>
-->
        
        
        <!-- SĘDZIOWIE -->
<!--
        <div class="infoBox dayMatch obsVisibility" id="sedziowie">
            <header class="infoBox__header">
                <span class="title"><?php echo getData(343, "sName"); ?></span>
            </header>
            <div class="infoBox__content">

                <?php echo getData(343, "sDescriptionFull"); ?>
            </div>
         
        </div>
-->

        <!-- SKŁAD GOSPODARZA -->
<!--
        <div class="infoBox teamList obsVisibility" id="sklad_gospodarza">
            <header class="infoBox__header">
                <span class="title"><?php echo $team_1_name; ?></span>
            </header>
            <div class="infoBox__content">
                <div class="teamList__header d-none">
                    <?php echo $team_1_herb; ?>
                    <div class="teamList__coach">
                        <?php echo getData($day_match['team_1'], "sDescriptionFull"); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <h3 class="infoBox__heading">Wyjściowa 11</h3>
                        <ul class="teamList__list first_squad">
                            <?php echo $first_squad_1; ?>
                        </ul>
                    </div>
                    <div class="col-6">
                        <h3 class="infoBox__heading">Rezerwa</h3>
                        <ul class="teamList__list">
                            <?php echo $reserve_1; ?>
                        </ul>
                    </div>
                </div>
            </div>
         
        </div>
-->

        <!-- SKŁAD GOŚCIA -->
<!--
        <div class="infoBox teamList obsVisibility" id="sklad_goscia">
            <header class="infoBox__header">
                <span class="title"><?php echo getData($day_match['team_2'], "sName"); ?></span>
            </header>
            <div class="infoBox__content">
                <div class="teamList__header">
                    <?php echo $team_2_herb; ?>
                    <div class="teamList__coach">
                        <?php echo getData($day_match['team_2'], "sDescriptionFull"); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <h3 class="infoBox__heading">Wyjściowa 11</h3>
                        <ul class="teamList__list first_squad">
                            <?php echo $first_squad_2; ?>
                        </ul>
                    </div>
                    <div class="col-6">
                        <h3 class="infoBox__heading">Rezerwa</h3>
                        <ul class="teamList__list">
                            <?php echo $reserve_2; ?>
                        </ul>
                    </div>
                </div>
            </div>
          
        </div>
-->

        <!-- PODSUMOWANIE -->
        <div class="infoBox dayMatch obsVisibility" id="podsumowanie">
            <header class="infoBox__header">
                <span class="title"><?php echo getData(25, "sName"); ?></span>
            </header>
            <div class="infoBox__content">
                <div class="dayMatch__header">
                    <div class="team_1">
                        <?php echo $team_1_herb; ?>
                        <div class="name"><?php echo $team_1_short_name; ?></div>
                    </div>
                    <div class="dayMatch__result">
                        <span class="score-result">0:0</span>
                    </div>
                    <div class="team_1">
                        <?php echo $team_2_herb; ?>
                        <div class="name"><?php echo $team_2_short_name; ?></div>
                    </div>
                </div>
                <div id="list_events"></div>
            </div>
        </div>

        <!-- SPONSORZY & PARTNERZY -->
        <div class="infoBox dayMatch obsVisibility" id="sponsorzy">
            <header class="infoBox__header">
                <span class="title"><?php echo getData(4, "sName"); ?></span>
            </header>
            <div class="infoBox__content">
                <?php echo $oFile->listImages( 4, Array( 'iType' => 1, 'class' => 'galleryGrid galleryGridBig', 'bNoLinks' => TRUE)); ?>
                <div class="mt-3 d-none">
                    <?php // echo $oFile->listImages( 4, Array( 'iType' => 0, 'class' => 'galleryGrid galleryGridSmall', 'bNoLinks' => TRUE)); ?>
                </div>
            </div>
          
        </div>

        <!-- REALIZACJA LIVE -->
        <div class="infoBox dayMatch obsVisibility" id="realizacja_transmisji">
            <header class="infoBox__header">
                <span class="title">🛑 <?php echo getData(16, "sName"); ?> 🛑</span>
            </header>
            <div class="infoBox__content">
                <h3 class="infoBox__heading">Realizacja LIVE</h3>
                <?php echo $oFile->listImages(16, Array( 'iType' => 1, 'class' => 'galleryGrid galleryGridBig2', 'full_image' => TRUE, 'bNoLinks' => TRUE)); ?>
            </div>
          
        </div>
      
 

        <!-- SPONSOR MECZU -->
        <div class="infoBox obsVisibility" id="sponsor_meczu">
            <header class="infoBox__header">
                <span class="title">Sponsor meczu i transmisji na żywo</span>
            </header>
            <div class="infoBox__content">
                <div class="sponsorMatch">
                    <?php echo $oFile->listImages(232, Array( 'iType' => 1, 'class' => 'galleryGrid sponsorMatch__logo', 'bNoLinks' => TRUE)); ?>
                    <div class="sponsorMatch__content">
                        <?php echo getData(232, "sDescriptionFull"); ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    

    <!-- PLAKAT MECZOWY -->
<!--
    <div class="infoBox matchPoster obsVisibility" id="plakat">
        <?php echo $oFile->listImages(7, Array( 'iType' => 1, 'class' => '', 'full_image' => TRUE, 'bNoLinks' => TRUE)); ?>
    </div>
-->

</div>

<?php
require_once 'templates/'.$config['skin'].'/_footer.php';
?>