<?php
class Sliders
{

  public $aSliders;
  private static $oInstance = null;

  public static function getInstance( ){  
    if( !isset( self::$oInstance ) ){  
      self::$oInstance = new Sliders( );  
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

    $this->aSliders = null;
    $oSql = Sql::getInstance( );
    $oQuery = $oSql->getQuery( 'SELECT * FROM sliders WHERE sLang = "'.$config['language'].'" ORDER BY iPosition ASC' );
    while( $aValue = $oQuery->fetch( PDO::FETCH_ASSOC ) ){
      $this->aSliders[$aValue['iSlider']] = $aValue;
    } // end while
  } // end function generateCache

  /**
  * Displays slider
  * @return string
  * @param array $aParametersExt
  * Default options: sClassName, bNoLinks, iType, sConfig
  */
  public function listSliders( $aParametersExt = null ){
    global $lang, $config;

    if( isset( $this->aSliders ) ){

      $oFJ = new FileJobs( );

      // OPTYMALIZACJA: Cache dla sprawdzania plików i wymiarów obrazków
      static $fileExistsCache = [];
      static $imageSizeCache = [];

      $content = null;
      $i = 1;
      foreach( $this->aSliders as $iKey => $aValue ){
        $aParametersExt['iElement'] = $i;

        if( !empty( $aValue['sFileName'] ) && isset( $config['slider_srcset'] ) ){
          foreach( $config['slider_srcset'] as $iSize ){
            $iSize = trim( $iSize );
            $aNameExt = $oFJ->throwNameExtOfFile( $aValue['sFileName'] );
            $sFileNameSrcSet = 'files/'.$aNameExt[0].'_srcset_'.$iSize.'.'.$aNameExt[1];

            // OPTYMALIZACJA: Sprawdź cache przed wywołaniem is_file()
            if( !isset( $fileExistsCache[$sFileNameSrcSet] ) ){
              $fileExistsCache[$sFileNameSrcSet] = is_file( $sFileNameSrcSet );
            }

            if( $fileExistsCache[$sFileNameSrcSet] ){
              $aValue['aSrcSetFile'][] = $sFileNameSrcSet.' '.$iSize.'w';
            }
          } // end foreach

          $mainFilePath = 'files/'.$aValue['sFileName'];

          // OPTYMALIZACJA: Sprawdź cache przed wywołaniem getImageSize()
          if( !isset( $imageSizeCache[$mainFilePath] ) ){
            $imageInfo = getImageSize( $mainFilePath );
            $imageSizeCache[$mainFilePath] = $imageInfo ? $imageInfo[0] : 0;
          }

          $iImageWidth = $imageSizeCache[$mainFilePath];
          $aValue['aSrcSetFile'][] = $mainFilePath.' '.$iImageWidth.'w';

          if( isset( $aValue['aSrcSetFile'] ) ){
            $aValue['sSrcSet'] = implode( ', ', $aValue['aSrcSetFile'] );
          }
        }

        $content .= listSlidersView( $aValue, $aParametersExt );
        $i++;
      } // end foreach

      if( isset( $content ) )
        return '<div class="'.( isset( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'] : 'slider' ).'" id="mainSlider"><div class="'.( isset( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'].'__progress' : 'slider__progress' ).'"></div><ul class="'.( isset( $aParametersExt['sClassName'] ) ? $aParametersExt['sClassName'].'__list' : 'slider__list' ).' carousel owl-carousel">'.$content.'</ul></div>';
    }
  } // end function listSliders

};
?>