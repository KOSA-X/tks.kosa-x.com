<?php 

define( 'CUSTOMER_PAGE', true );
require_once 'database/config.php';
header( 'Content-Type: text/html; charset=utf-8' );
require_once 'core/libraries/sql.php';
require_once 'plugins/settings.php';


function getData($id, $name, $type = 'pages'){
	$oSql = Sql::getInstance( );
    
    $oQuery = $oSql->getQuery('SELECT iPage, '.$name.' FROM '.$type.' 
    WHERE iPage="'.$id.'"
    '.($type == 'files' ? 'AND iSize > 0  AND iDefault = "1" ' : '').' LIMIT 1');
    
    $getData = $oQuery->fetch(PDO::FETCH_ASSOC);
    
    if($getData[$name] != ""){
        return $getData[$name];
    } else {
        return FALSE;
    }
}


 
$oSql = Sql::getInstance( );
$live = "";

 $oneMinuteAgo = time() - 16; // 10 sekund wstecz


$data_array = $oSql->getQuery( 'SELECT e.*, p.sName, p.iPosition FROM events e 
JOIN pages p ON e.player_id = p.iPage 
 
AND e.clock >= "'.$oneMinuteAgo.'"  ORDER BY e.clock ASC LIMIT 4');
$i = 0;
while( $data = $data_array->fetch( PDO::FETCH_ASSOC ) ){
    
    
    if($data['action'] != "play" && $data['action'] != "pause"){

        $live .= "<div class='obsEvent event_".$data['action']."' data-id='".$data['id']."'><div class='wrapper'>";
        $live .= "<div class='content'>";

        $live .= "<div class='event'>".action($data['action'])." <span class='time'>".$data['time']."\"</span></div>";

        $live .= "<div class='name'><span class='number'>".$data['iPosition']."</span>".$data['sName']."</div>";

        $live .= "</div>";

        $image = getData($data['player_id'], "sFileName", "files");

        if(isset($image) && $image != "" ){
            $live .= "<div class='image'><img src='files/500/".$image."'></div>";    
        }else{
            $live .= "<div class='image herb'><img src='files/500/".getData($data['team'], "sFileName", "files")."'></div>";
        }

        $live .= "</div></div>";
    }
    
    
    
    
//    <strong>{}</strong> - {$data['action']} ({$data['time']}\")</div>";

}

echo $live;


 

?>




 