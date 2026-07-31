<?php 

define( 'CUSTOMER_PAGE', true );
require_once 'database/config.php';
header( 'Content-Type: text/html; charset=utf-8' );
require_once 'core/libraries/sql.php';

$oSql = Sql::getInstance( );
 
 

if (isset($_POST['id']) && isset($_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $clock = time();
    $team = $_POST['team'];
    $time = isset($_POST['time']) ? $_POST['time'] : "";
    
    
    if($action == 'pause'){
        $oSql->getQuery("UPDATE pages SET player_11 = 0 WHERE iPage = '".$id."'");
    }
    
    if($action == 'play'){
        $oSql->getQuery("UPDATE pages SET player_11 = 2 WHERE iPage = '".$id."'");
    }
    
    
    if($action == 'in'){
        $oSql->getQuery("UPDATE pages SET player_11 = 1 WHERE iPage = '".$id."'");
    }
    
    if($action == 'out'){
        $oSql->getQuery("UPDATE pages SET player_11 = 2 WHERE iPage = '".$id."'");
    }
    
    if($action != 'pause' && $action != 'play'){
    // Dodanie nowego wydarzenia do tabeli `events`
        $oSql->query("INSERT INTO events (player_id, action, time, clock, team) VALUES ('$id', '$action', '$time', '$clock', '$team')");
    }
    
    echo json_encode(['status' => 'success']);
}
 
 



 

?>




 