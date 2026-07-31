<?php 

define( 'CUSTOMER_PAGE', true );
require_once 'database/config.php';
header( 'Content-Type: text/html; charset=utf-8' );
require_once 'core/libraries/sql.php';
require_once 'plugins/settings.php';
$oSql = Sql::getInstance( );

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


$day_match_result = $oSql->getQuery('SELECT team_1, team_2, iPage FROM pages WHERE iPage="7" LIMIT 1');
$day_match = $day_match_result->fetch(PDO::FETCH_ASSOC);

 

 
$dayMatch_array = $oSql->getQuery( 'SELECT * FROM events ORDER BY clock ASC ');

$events_1 = "";
$events_2 = "";
$events_content = "";

while( $data_match = $dayMatch_array->fetch( PDO::FETCH_ASSOC ) ){
    
    $avatar = "";
//    $image = getData($data_match['player_id'], "sFileName", "files");
    
//    if(isset($image) && $image != "" ){
//        $avatar = "<div class='avatar'><img src='files/500/".$image."'></div>";    
//    }else{
//        $avatar = "<div class='avatar herb'><img src='files/500/".getData($data_match['team'], "sFileName", "files")."'></div>";
//    }
//    
    if($data_match['action'] != "pause"){
    
        $events_content = "<li class='eventItem ".$data_match['action']."'>".action($data_match['action'])."<span class='minute'> ".$data_match['time']."\"</span><span class='name'>".getData($data_match['player_id'], "sName")."</span> ".($data_match['action'] == "own_goal" ? "<span class='own_goal'>(samobój)</span>" : "")." ".$avatar."</li>";

    }    
    
    
    if($data_match['team'] == $day_match['team_1']){
        $events_1 .= $events_content;
    }elseif($data_match['team'] == $day_match['team_2']){
        $events_2 .= $events_content;
    }
    
}
?>

<div class="row">
<div class="col-6">
<ul class="teamList__list ">
    <?php echo $events_1; ?> 
</ul>
</div>
<div class="col-6">
<ul class="teamList__list ">
    <?php echo $events_2; ?> 
</ul>
</div>
</div> 