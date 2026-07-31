<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;

$oSql = Sql::getInstance();

if (isset($_POST['id']) && isset($_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $time = date("H:i:s");
    
    // Dodanie nowego wydarzenia do tabeli `events`
    $oSql->query("INSERT INTO events (player_id, action, time, status) VALUES ('$id', '$action', '$time', 1)");
    
    echo "Akcja zapisana!";
}

?>