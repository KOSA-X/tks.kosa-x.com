<?php
class Files
{

  public $aDefaultImages;
  private static $oInstance = null;

  public static function getInstance( ){  
    if( !isset( self::$oInstance ) ){  
      self::$oInstance = new Files( );  
    }  
    return self::$oInstance;  
  } // end function getInstance

  /**
  * Constructor
  * @return void
  * @param mixed $mValue
  */
  private function __construct( ){
    $this->generateCache( );
  } // end function __construct

  /**
  * Generates cache variables
  * @return void
  */
  public function generateCache( ){
    global $config;

    $this->aDefaultImages = null;
    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT files.iFile, files.iPage, files.iSize, files.sFileName, files.sDescription FROM files, pages WHERE files.iDefault = 1 AND pages.iPage=files.iPage AND pages.sLang = "'.$config['language'].'"' );
    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $this->aDefaultImages[$aData['iPage']] = $aData;
    } // end while

  } // end function generateCache

  /**
  * Displays default image
  * @return string
  * @param int $iPage
  * @param array $aParametersExt
  * Default options: sClassName, bNoLinks, sLink
  */
  public function getDefaultImage( $iPage, $aParametersExt = null ){
    if( isset( $this->aDefaultImages[$iPage] ) ){
      $sLink = null;
      if( !isset( $aParametersExt['bNoLinks'] ) || isset( $aParametersExt['sLink'] ) ){
        $sLink = isset( $aParametersExt['sLink'] ) ? $aParametersExt['sLink'] : 'files/'.$this->aDefaultImages[$iPage]['sFileName'];
      }
      return '<div class="'.( isset( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'] : 'image' ).'">'.$sLink.'<img src="files/'.(isset($aParametersExt['full_size']) && $aParametersExt['full_size'] == TRUE ? '' : $this->aDefaultImages[$iPage]['iSize'].'/' ).$this->aDefaultImages[$iPage]['sFileName'].'" alt="'.( !empty( $this->aDefaultImages[$iPage]['sDescription'] ) ? $this->aDefaultImages[$iPage]['sDescription'] : null ).'" />'.( isset( $sLink ) ? '</a>' : null ).'</div>';
    }
  } // end function getDefaultImage

public function getDefaultImageBackground( $iPage, $aParametersExt = null ){
    if( isset( $this->aDefaultImages[$iPage] ) ){
      $sLink = null;
      if( !isset( $aParametersExt['bNoLinks'] ) || isset( $aParametersExt['sLink'] ) ){
        $sLink = isset( $aParametersExt['sLink'] ) ? '<a href="'.$aParametersExt['sLink'].'">' : '<a href="files/'.$this->aDefaultImages[$iPage]['sFileName'].'" class="quickbox['.$iPage.']">';
      }
      return '<picture class="'.( isset( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'] : 'image' ).'"><div class="image_background" style="background-image:url(files/'.$this->aDefaultImages[$iPage]['iSize'].'/'.$this->aDefaultImages[$iPage]['sFileName'].')">'.$sLink.( isset( $sLink ) ? '</a>' : null ).'</div></picture>';
    }
  }
    
public function getDefaultImageUrl( $iPage, $aParametersExt = null ){
     

    $content = null;
    $oSql = Sql::getInstance( );
     $oQuery = $oSql->getQuery( 'SELECT * FROM files WHERE iPage = "'.$iPage.'"'.( isset( $aParametersExt['iType'] ) ? ' AND iType = "'.$aParametersExt['iType'].'"' : null ).' AND  iSize > 0 ORDER BY iPosition ASC, sFileName ASC LIMIT 1 ');
    $i = 1;

    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $aParametersExt['iElement'] = $i;
      $content .= 'files/'.$aData['sFileName'];
      $i++;
    } // end while

    if( isset( $content ) )
      return $content;
  }
    
  /**
  * Displays images
  * @return strings
  * @param int $iPage
  * @param array $aParametersExt
  * Default options: sClassName, bNoStyleId, bNoLinks, iType
  */
  public function listImages( $iPage, $aParametersExt = null ){
    global $config;

    $content = null;
    $slider = null;
    $class = ( isset( $aParametersExt['class'] ) ? $aParametersExt['class'] : 'gallery-'.$iPage.'-'.( isset( $aParametersExt['iType'] ) ? $aParametersExt['iType'] : '0' ));
      
    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT * FROM files WHERE iPage = "'.$iPage.'"'.( isset( $aParametersExt['iType'] ) ? ' AND iType = "'.$aParametersExt['iType'].'"' : null ).' AND iSize > 0 ORDER BY iPosition ASC, sFileName ASC' );
    $i = 1;

    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $aParametersExt['iElement'] = $i;
      $content .= listImagesView( $aData, $aParametersExt );
      $i++;
    } // end while
      
    if(isset($aParametersExt['slider']) && $aParametersExt['slider'] == TRUE){
        $slider = '<script>
                    $(document).ready(function() {
                        $(".'.$class.'-slider").owlCarousel({
                            loop:false,
                            margin:10,
                            nav:true,
                            dots:true,
                            autoplay:false,
                            autoplayHoverPause:true,
                            items:1,
                            autoplayTimeout: 5000,
                        });
                    });
                </script>';
    }

    if( isset( $content ) )
      return $slider.'<ul class="gallery '.$class.($slider ? ' '.$class.'-slider owl-carousel gallerySlider' : '').'">'.$content.'</ul>';
  } // end function listImages

  /**
  * Displays files
  * @return string
  * @param int $iPage
  * @param array $aParametersExt
  * Default options: sClassName
  */
  public function listFiles( $iPage, $aParametersExt = null ){
    global $config;

    $content = null;
    $oSql = Sql::getInstance( );
    $oFJ = new FileJobs( );
    $oQuery = $oSql->getQuery( 'SELECT * FROM files WHERE iPage = "'.$iPage.'" AND iSize = 0 ORDER BY iPosition ASC, sFileName ASC' );
    $i = 1;

    while( $aData = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $aParametersExt['iElement'] = $i;
      $sExt = $oFJ->getExtOfFile( $aData['sFileName'] );
      if( !isset( $config['ext_icons'][$sExt] ) )
        $config['ext_icons'][$sExt] = 'nn';
      $aData['sIconStyle'] = $config['ext_icons'][$sExt];
      $content .= listFilesView( $aData, $aParametersExt );
      $i++;
    } // end while

    if( isset( $content ) )
      return '<ul class="'.( isset( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'] : 'files-list' ).'">'.$content.'</ul>';
  } // end function listFiles
    
    
    
public function metaFacebook( $iPage, $aParametersExt = null ){
    global $config;

    $content = null;
    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT * FROM files WHERE iPage = "'.$iPage.'"'.( isset( $aParametersExt['iType'] ) ? ' AND iType = "'.$aParametersExt['iType'].'"' : null ).' AND iSize > 0 ORDER BY iDefault DESC, iPosition ASC' );

	$aData = $oQuery->fetch( PDO::FETCH_ASSOC );
	$content .= $aData['sFileName'];

    if( isset( $content ) )
      return $content;
  }
};
?>