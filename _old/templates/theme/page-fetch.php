<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;

$live = "";


$oSql = Sql::getInstance( );
                        
$data_array = $oSql->getQuery( 'SELECT e.*, p.sName FROM events e JOIN pages p ON e.player_id = p.iPage WHERE e.status="1" ORDER BY e.time DESC LIMIT 2');
$i = 0;
while( $data = $data_array->fetch( PDO::FETCH_ASSOC ) ){

    $live .= "<p class='event {$data['action']}' data-id='{$data['id']}'><strong>{$data['sName']}</strong> - {$data['action']} ({$data['time']})</p>";

}

echo $live;


 
 



 

?>