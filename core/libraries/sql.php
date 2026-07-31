<?php
/**
* Sql
* @access   public 
* @version  1.0
* @author   OpenSolution
*/
class Sql extends PDO
{

  private static $oInstance = null;
  private $aFields = null;

  public static function getInstance( ){  
    if( !isset( self::$oInstance ) ){
      self::$oInstance = new Sql( 'sqlite:'.$GLOBALS['config']['database'] );
    }  
    return self::$oInstance;  
  } // end function getInstance

  /**
  * Returns one column from database
  * @return mixed
  * @param string $sQuery
  */
  public function getColumn( $sQuery ){
    $oQuery = $this->query( $sQuery );
    $this->checkQuery( $oQuery );
    return $oQuery->fetchColumn( );
  } // end function getColumn

  /**
  * Returns one row from database
  * @return mixed
  * @param string $sQuery
  */
  public function throwAll( $sQuery ){
    $oQuery = $this->query( $sQuery );
    $this->checkQuery( $oQuery );
    return $oQuery->fetch( PDO::FETCH_ASSOC );
  } // end function throwAll

  /**
  * Returns query
  * @return mixed
  * @param string $sQuery
  */  
  public function getQuery( $sQuery ){
    $oQuery = $this->prepare( $sQuery );
    $this->checkQuery( $oQuery );
    $oQuery->execute( );
    return $oQuery;
  } // end function prepareQuery

  /**
  * Updating data in database
  * @return void
  * @param string $sTable
  * @param array  $aData
  * @param array  $aWhereStatement
  * @param bool   $bVerifyArray
  * @param bool   $bAddMark
  */
  public function update( $sTable, $aData, $aWhereStatement, $bVerifyArray = null, $bAddMark = null ){

    if( isset( $bVerifyArray ) && !isset( $this->aFields[$sTable] ) ){
      $oQuery = $this->getQuery( 'PRAGMA table_info( \''.$sTable.'\' );' );
      while( $aField = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
        $this->aFields[$sTable][$aField['name']] = true;
      } // end while
    }

    // OPTYMALIZACJA: Użyj prepared statements
    $aWhereParts = [];
    $aWhereValues = [];
    foreach( $aWhereStatement as $sKey => $sValue ){
      $aWhereParts[] = $sKey.' = ?';
      $aWhereValues[] = $sValue;
    } // end foreach

    $aUpdateParts = [];
    $aUpdateValues = [];
    foreach( $aData as $sKey => $sValue ){
      if( $sValue !== false && !is_array( $sValue ) && ( !isset( $bVerifyArray ) || ( isset( $bVerifyArray ) && isset( $this->aFields[$sTable][$sKey] ) ) ) ){
        $aUpdateParts[] = $sKey.' = ?';
        $aUpdateValues[] = $sValue;
      }
    } // end foreach

    $sql = 'UPDATE '.$sTable.' SET '.implode( ', ', $aUpdateParts ).' WHERE '.implode( ' AND ', $aWhereParts );
    $stmt = $this->prepare( $sql );
    $stmt->execute( array_merge( $aUpdateValues, $aWhereValues ) );

    if( isset( $bAddMark ) )
      $this->addMarkToFile( );

  } // end function update

  /**
  * Inserting data in database
  * @return int
  * @param string $sTable
  * @param array  $aData
  * @param bool   $bVerifyArray
  * @param bool   $bAddMark
  */
  public function insert( $sTable, $aData, $bVerifyArray = null, $bAddMark = null ){

    if( isset( $bVerifyArray ) && !isset( $this->aFields[$sTable] ) ){
      $oQuery = $this->getQuery( 'PRAGMA table_info( \''.$sTable.'\' );' );
      while( $aField = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
        $this->aFields[$sTable][$aField['name']] = true;
      } // end while
    }

    // OPTYMALIZACJA: Użyj prepared statements
    $aColumns = [];
    $aValues = [];
    $aPlaceholders = [];
    foreach( $aData as $sKey => $sValue ){
      if( $sValue !== false && !is_array( $sValue ) && ( !isset( $bVerifyArray ) || ( isset( $bVerifyArray ) && isset( $this->aFields[$sTable][$sKey] ) ) ) ){
        $aColumns[] = $sKey;
        $aValues[] = $sValue;
        $aPlaceholders[] = '?';
      }
    } // end foreach

    $sql = 'INSERT INTO '.$sTable.' ( '.implode( ', ', $aColumns ).' ) VALUES ( '.implode( ', ', $aPlaceholders ).' )';
    $stmt = $this->prepare( $sql );
    $stmt->execute( $aValues );
    $this->checkQuery( $stmt );

    if( isset( $bAddMark ) )
      $this->addMarkToFile( );

    return $this->lastInsertId( );
  } // end function insert

  /**
  * Adds mark to file if database was changed
  * @return void
  */
  public function addMarkToFile( ){
    if( isset( $GLOBALS['config']['dir_database'] ) ){
      file_put_contents( $GLOBALS['config']['dir_database'].'database-last-modification', time( ) );
    }
  } // end function addMarkToFile

  /**
  * Checks last mark in the file
  * @return bool
  */
  public function checkMarkFile( ){
    if( isset( $GLOBALS['config']['dir_database'] ) && is_file( $GLOBALS['config']['dir_database'].'database-last-modification' ) && ( time( ) - file_get_contents( $GLOBALS['config']['dir_database'].'database-last-modification' ) ) <= 1 ){
      return false;
    }
    else
      return true;
  } // end function checkMarkFile

  /**
  * Return HTML select from data in database table
  * @return string
  * @param string $sKey
  * @param string $sValue
  * @param string $sQuery
  * @param mixed $mData
  */
  public function getSelect( $sKey, $sValue, $sQuery, $mData ){
    $oQuery = $this->query( 'SELECT '.$sKey.( ( $sValue != $sKey ) ? ', '.$sValue : null ).' FROM '.$sQuery );
    $content = null;
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $content .= '<option value="'.$aData[$sKey].'" '.( ( isset( $mData ) && $mData == $aData[$sKey] ) ? 'selected="selected"' : null ).'>'.$aData[$sValue].'</option>';  
    }
    return $content;    
  } // end function getSelect

  /**
  * Checks query and display errors
  * @return void
  * @param object $oQuery
  */
  public function checkQuery( $oQuery ){
  if( !$oQuery ){
    // Tryb developerski – pokaż konkretny błąd SQL
    if( defined('DEVELOPER_MODE') && DEVELOPER_MODE ){
      echo '<pre>';
      var_dump( $this->errorInfo() );
      echo '</pre>';
      exit;
    }
    else {
      // Produkcja – próbujemy przekierować, ale tylko jeśli nagłówki jeszcze nie poszły
      if ( !headers_sent() ) {
        header( 'Location: '.$_SERVER['PHP_SELF'].'?error='.md5( $_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'] ) );
      }
      exit;
    }
  }
} // end function checkQuery

};
?>