<?php
/**
* Displays date changed by $config['time_diff']
* @return string
* @param int $iTime
* @param string $sFormat
*/
function displayDate( $iTime = null, $sFormat = 'Y-m-d H:i' ){
  global $config;
  return isset( $iTime ) ? date( $sFormat, $iTime + ( $config['time_diff'] * 60 ) ) : date( $sFormat, time( )  + ( $config['time_diff'] * 60 ) );
} // end function displayDate

/**
* Return configuration from table bin
* @return void
* @param bool $bInsert
*/
function getBinValues( $bInsert = null ){
  global $config;
  $oSql = Sql::getInstance( );
  $oQuery = $oSql->getQuery( 'SELECT sKey, sValue FROM bin' );
  while( $aValue = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
    if( !isset( $config[$aValue['sKey']] ) )
      $config[$aValue['sKey']] = $aValue['sValue'];
  } // end while

  if( isset( $bInsert ) ){
    if( isset( $config['session_key_name'] ) ){
      if( time( ) - substr( $config['session_key_name'], 1, 10 ) > 86400 ){
        $oSql->query( 'DELETE FROM bin WHERE sKey = "session_key_name"' );
        $config['session_key_name'] = null;
      }
    }

    if( !isset( $config['session_key_name'] ) ){
      // Bezpieczeństwo: klucz sesji/token CSRF musi być nieprzewidywalny.
      // Format: 's' + 10-cyfrowy timestamp (używany do wygaśnięcia po 24h) + losowy sekret.
      $sRandom = function_exists( 'random_bytes' )
        ? bin2hex( random_bytes( 16 ) )
        : bin2hex( openssl_random_pseudo_bytes( 16 ) );
      $config['session_key_name'] = 's'.time( ).$sRandom;
      $stmt = $oSql->prepare( 'INSERT INTO bin ( "sKey", "sValue" ) VALUES( "session_key_name", :val )' );
      $stmt->execute( array( ':val' => $config['session_key_name'] ) );
    }
  }
} // end function getBinValues

/**
* Returns URL
* @return void
*/
function getSiteUrl( ){
  global $config;
  if( !isset( $config['url_domain'] ) ){
    $aData = parse_url( $_SERVER['REQUEST_URI'] );
    $config['url_domain'] = ( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) ? 'https://' : 'http://' ).$_SERVER['HTTP_HOST'].( isset( $aData['host'] ) ? $aData['host'] : null ).( isset( $aData['path'] ) ? substr( $aData['path'], 0, strrpos( $aData['path'], '/' ) + 1 ) : null );
  }
} // end function getSiteUrl

?>