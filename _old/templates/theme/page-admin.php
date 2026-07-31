<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';


//    require_once 'templates/'.$config['skin'].'/_title.php';


 


$day_match_result = $oSql->getQuery('SELECT * FROM pages WHERE iPage="7" LIMIT 1');
$day_match = $day_match_result->fetch(PDO::FETCH_ASSOC);
    

$info_buttons = "";
$info_result = $oSql->getQuery('SELECT * FROM info');
while( $info = $info_result->fetch(PDO::FETCH_ASSOC) ){
    
    $info_buttons .= '<button class="button info-action" data-action="'.$info['name'].'">'.$info['label'].'</button>'; 

}



    
?>
 
<div class="container">
    
    <section class="section">
      
      <div class="adminPanel">
          
          <div class="adminPanel__score">
                  <div class="team team_1">
                     <div class="button__group">
                        <button class="button score-action" data-id="1" data-action="increase">+</button>
                        <button class="button score-action" data-id="1" data-action="decrease">-</button>
                    </div>
                    <div class="label"><?php echo getData($day_match['team_1'], "sDate"); ?></div>  
                  </div>
                  
                  
                  <div class="score adminPanel__label">  <span id="score" class="strong value">0:0</span> </div>
                  
                  
                  <div class="team team_2">
                     <div class="button__group">
                    <button class="button score-action" data-id="2" data-action="increase">+</button>
                    <button class="button score-action" data-id="2" data-action="decrease">-</button>
                </div>
                    <div class="label"><?php echo getData($day_match['team_2'], "sDate"); ?></div>
                  </div>
              
                   
                  
                   
               </div>
          
          
     
            
        </div>
        
       
       <div class="adminPanel">
           <div class="adminPanel__label">
            <div class="label">Czas: <strong id="timer" class="trong value">0</strong> min</div>
            </div>
            <div class="adminPanel__content">
            <div class="actions actions-time">
                <button class="button timer-action button-50" data-action="start_1">1 połowa (00:00)</button>
                <button class="button timer-action button-50" data-action="start_2">2 połowa (45:00)</button>
                <button class="button timer-action button-33" data-action="add_1" >+1 min</button>
                <button class="button timer-action button-33" data-action="subtract_1">-1 min</button>
                <button class="button timer-action button-33" data-action="stop">⏹ Reset</button>
            </div>
            </div>
        </div>
        
        
        
        <div class="adminPanel">
          <div class="adminPanel__label">
           <div class="label">Plansze</div>
            </div>
           <div class="adminPanel__content bigbtn">
            <div class="actions actions-slide">
              <?php echo $info_buttons; ?>
            </div>
        </div>
        </div>
        
        
        
        
    </section>
    <script>
        $(document).ready(function() {
            
            
            
            function updateVisibility(button) {
                $.post('ajax.php', { action: 'get_visibility', name: button.data('action') }, function(data) {
                    if (data.visible == 1) {
                        button.addClass('active');
                    } else {
                        button.removeClass('active');
                    }
                }, 'json');
            }

            $('.info-action').each(function() {
                updateVisibility($(this)); // Pobranie aktualnego stanu dla każdego przycisku na starcie
            });

            $('.info-action').click(function() {
                var button = $(this);
                var isActive = button.hasClass('active');
                var name = button.data('action');

                // Jeśli włączamy planszę (nie "wynik") — zdejmij .active z pozostałych
                if (!isActive && name != 'wynik') {
                    $('.info-action').not('[data-action="wynik"]').removeClass('active');
                }

                $.post('ajax.php', {
                    action: 'infoBox',
                    name: name,
                    visible: isActive ? 0 : 1
                }, function(response) {
                    if (response.status === 'updated') {
                        updateVisibility(button);
                    }
                }, 'json');
            });
            
            
            
            
            
            
            function updateTimer() {
                $.post('ajax.php', { action: 'get_timer' }, function(data) {
                    $('#timer').text(Math.ceil(data.timer / 60));
                });
            }

            $('.timer-action').click(function() {
                $.post('ajax.php', { 
                    action: $(this).data('action') 
                }, updateTimer);
            });
            
            $('.clear-events').click(function() {
                $.post('ajax.php', { 
                    action: $(this).data('action') 
                }, listEvents);
            });
            
            

            function updateScore() {
                $.post('ajax.php', { action: 'get_score' }, function(data) {
                    $('#score').text(data.score);
                });
            }

            $('.score-action').click(function() {
                $.post('ajax.php', {
                    action: 'update_score',
                    team: $(this).data('id'),
                    modifier: $(this).data('action')
                }, updateScore);
            });

            setInterval(updateTimer, 1000);
            setInterval(updateScore, 5000);
        });
    </script>
    
    
    
    
    
    
    <section class="section">
        <h2 class="section__title"><?php echo getData($day_match['team_1'], "sName"); ?></h2>
       <?php echo $oPage->listPages($day_match['team_1'], array('class' => 'pagesList-'.$aData['iPage'])); ?>  
    </section>
    
    
    <section class="section">
        <h2 class="section__title"><?php echo getData($day_match['team_2'], "sName"); ?></h2>
       <?php echo $oPage->listPages($day_match['team_2'], array('class' => 'pagesList-'.$aData['iPage'])); ?>  
    </section>
    
    
    <script>
        $(document).on("click", ".action", function() {
            var $button = $(this);
            var playerId = $(this).data("id");
            var action = $(this).data("action");
            var team = $(this).data("team");
            var time = $('#timer').text();
            
            $button.addClass("disabled");
            
            listEvents();
            
            if(action == 'in'){
                $('.pageItem-'+playerId).removeClass('rezerwa');
            }
            
            if(action == 'out' || action == 'play'){
                $('.pageItem-'+playerId).addClass('rezerwa');
            }
            
            if(action == 'pause'){
                $('.pageItem-'+playerId).addClass('pause');
            }
            
            if(action == 'play'){
                $('.pageItem-'+playerId).removeClass('pause');
            }
            
            $.post("update.php", { id: playerId, action: action, time: time, team: team }, function(response) {
                // Usunięcie klasy disabled po 10 sekundach
                setTimeout(function() {
                    $button.removeClass("disabled");
                }, 10000); // 10000 milisekund = 10 sekund
            });
        });
        
        
//        $(document).on("click", ".info-action", function() {
//            var action = $(this).data("action"); // Pobieramy wartość data-action
//            var $targetDiv = $("#" + action); // Znajdujemy odpowiedni div po ID
//
//            if ($targetDiv.is(":visible")) {
//                $targetDiv.slideUp(); // Jeśli div jest widoczny, ukrywamy go
//            } else {
//                $(".infoBox").slideUp(); // Ukrywamy inne divy
//                $targetDiv.slideDown(); // Pokazujemy wybrany div
//            }
//        });
        
        
 
    
    function listEvents() {
        $.get("list_events.php", function(data) {
            $("#list_events").html(data);
            console.log("Pibieram dabe.");
        }).fail(function() {
            console.log("Błąd pobierania danych.");
        });
//        setTimeout(listEvents, 10000);
    }
    $(document).ready(listEvents);
    
    
    
    
        
        
        
        
    </script>
               
               
               
<!--
    <section class="section">
        <h2 class="section__title">Podsumowanie</h2>
        
        <div id="list_events"></div>
         
 
    </section>
-->
             
          
             
              
              
     <section class="section eventEdit">
        <h2 class="section__title">Edycja zdarzeń</h2>
        
        <?php
         

//$day_match_result = $oSql->getQuery('SELECT team_1, team_2, iPage FROM pages WHERE iPage="7" LIMIT 1');
//$day_match = $day_match_result->fetch(PDO::FETCH_ASSOC);

 
if(isset($_POST) && $_POST['update'] != ""){
    
//    print_r($_POST);
    
    
     $oSql->query("UPDATE events SET time='".$_POST['time']."', player_id='".$_POST['player_id']."' WHERE id='".$_POST['update']."' ");
    
    echo "<div class='alert alert-info mb-3'>Zaktualizowano czas.</div>";
}
         
if(isset($_POST) && $_POST['delete'] != ""){
     $oSql->query("DELETE FROM events WHERE id='".$_POST['delete']."' ");
    echo "<div class='alert alert-info mb-3'>Usunięto zdarzenie.</div>";
}
 

         
         
$dayMatch_array = $oSql->getQuery( 'SELECT * FROM events ORDER BY clock ASC ');

$events = "";
$events_content = "";

while( $data_match = $dayMatch_array->fetch( PDO::FETCH_ASSOC ) ){
    
    
     
    $events .= "<li class='pageItem pause  ".$data_match['action']."'>
    
    <form action='".$oPage->aPages[$aData['iPage']]['sLinkName']."' method='post' class='eventEdit__form' enctype='multipart/form-data'>
         
    <div class='eventEdit__time'><input type='text' class='form-control' id='time_".$data_match['id']."' name='time' value='".$data_match['time']."' ></div>
    
    
    <div class='eventEdit__player'>".list_players($data_match['player_id'], $data_match['team'])."</div>
    <div class='eventEdit__action'>".action($data_match['action'])."</div>
    <div class='eventEdit__team'>".getData($data_match['team'], "sName")."</div>
    <div class='eventEdit__buttons'><button type='submit' name='update' class='button' value='".$data_match['id']."'>Zapisz</button><button type='submit' name='delete' class='button ml-1' value='".$data_match['id']."'>Usuń</button></div></form>
    ";
    
}
         ?>
    <ul class="eventEdit__list pagesList">
         <?php echo $events; ?> 
     </ul>
     <button class="button clear-events mt-5" data-action="clear">⏹ Wyczyść historię</button>
    </section>
</div>

 
 
<?php 
    
    
    function list_players($id, $team){
        $oSql = Sql::getInstance();
        $list_players_array = $oSql->getQuery( 'SELECT iPage, iPosition, iPageParent, sName FROM pages WHERE iPageParent="'.$team.'" ORDER BY iPosition ASC ');
        
        $list_players = "";


        while( $data_player = $list_players_array->fetch( PDO::FETCH_ASSOC ) ){
            
            $list_players .= '<option value="'.$data_player['iPage'].'" '.($id == $data_player['iPage'] ? ' selected="selected"' : '').'>'.$data_player['sName'].' ('.$data_player['iPosition'].')</option>';
        }
        
        return '<select name="player_id" class="form-control">'.$list_players.'</select>';
    }


require_once 'templates/'.$config['skin'].'/_footer.php'; ?>