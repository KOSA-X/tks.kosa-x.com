<?php
final class FilesAdmin extends Files
{
    private $aDirs;
    private $aFilesAll = null;
    private $aImagesThumbs = null;
    private static $oInstance = null;

    public static function getInstance( $mValue = null ){
        if( !isset( self::$oInstance ) ){
            self::$oInstance = new FilesAdmin();
        }
        return self::$oInstance;
    } // end function getInstance

    /**
     * Constructor
     * @return void
     */
    private function __construct(){
        $this->generateThumbDirs();
    } // end function __construct

    /**
     * HTML escape
     */
    private function e( $v ): string {
        return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Returns thumbs directory names
     * @return array
     */
    private function generateThumbDirs(){
        $this->aDirs = [];
        foreach( new DirectoryIterator( 'files/' ) as $oFileDir ){
            if( is_numeric( $oFileDir->getFilename() ) && $oFileDir->isDir() ){
                $this->aDirs[$oFileDir->getFilename()] = $oFileDir->getFilename();
            }
        }
    } // end function generateThumbDirs

    /**
     * Returns list of files in a directory
     * @return string
     * @param array $aParametersExt
     * Default options: sSort, iSite (nr strony), sSearch (filtr nazwy),
     * iPerPage (domyślnie 50)
     *
     * PAGINACJA: przy kilku tysiącach plików (import produktów) render
     * wszystkich wierszy z miniaturami zawieszał przeglądarkę — lista jest
     * stronicowana, a nawigacja/wyszukiwarka przeładowuje ją przez
     * ajax-files-in-dir (loadFilesDir w core/common-admin.js).
     */
    public function listFilesInDir( $aParametersExt = null ){
        global $lang, $config;

        $content = '';
        $oIJ = ImageJobs::getInstance();

        $aDirs = $this->aDirs ?? [];
        sort( $aDirs, SORT_NUMERIC );
        $iCountDirs = count( $aDirs );

        // map: filename -> thumb path
        if( !isset( $this->aImagesThumbs ) ){
            $this->aImagesThumbs = [];
            for( $i = 0; $i < $iCountDirs; $i++ ){
                if( is_dir( 'files/'.$aDirs[$i] ) ){
                    foreach( new DirectoryIterator( 'files/'.$aDirs[$i] ) as $oFileDir ){
                        if(
                            $oFileDir->isFile() &&
                            $oIJ->checkCorrectFile( $oFileDir->getFilename(), $config['allowed_image_extensions'] ) &&
                            !isset( $this->aImagesThumbs[$oFileDir->getFilename()] )
                        ){
                            $this->aImagesThumbs[$oFileDir->getFilename()] = 'files/'.$aDirs[$i].'/'.$oFileDir->getFilename();
                        }
                    }
                }
            }
        }

        $aFiles = [];
        foreach( new DirectoryIterator( 'files/' ) as $oFileDir ){
            $sFileName = $oFileDir->getFilename();
            if( $oFileDir->isFile() && $sFileName !== '.htaccess' ){
                $aFiles[$sFileName] = (int) @filemtime( 'files/'.$sFileName );
            }
        }

        if( empty( $aFiles ) ){
            return null;
        }

        // sort
        if( isset( $aParametersExt['sSort'] ) && $aParametersExt['sSort'] === 'time' ){
            arsort( $aFiles );
        } else {
            asort( $aFiles );
        }

        // filtr po nazwie pliku (wyszukiwarka nad tabelą)
        $sSearch = isset( $aParametersExt['sSearch'] ) ? trim( (string) $aParametersExt['sSearch'] ) : '';
        if( $sSearch !== '' && mb_strlen( $sSearch ) <= 100 ){
            $aFiles = array_filter(
                $aFiles,
                function( $sFileName ) use ( $sSearch ){
                    return mb_stripos( (string) $sFileName, $sSearch ) !== false;
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        // paginacja — indeksy pól ($i) liczone od globalnego offsetu strony,
        // żeby name/id nie kolidowały między stronami
        $iPerPage = isset( $aParametersExt['iPerPage'] ) ? max( 10, (int) $aParametersExt['iPerPage'] ) : 50;
        $iTotal   = count( $aFiles );
        $iPages   = max( 1, (int) ceil( $iTotal / $iPerPage ) );
        $iSite    = isset( $aParametersExt['iSite'] ) ? (int) $aParametersExt['iSite'] : 1;
        $iSite    = min( max( 1, $iSite ), $iPages );
        $iOffset  = ( $iSite - 1 ) * $iPerPage;

        $aFiles = array_slice( $aFiles, $iOffset, $iPerPage, true );

        $now = time();
        $i = $iOffset;

        foreach( $aFiles as $sFileName => $mtime ){
            $safeName = basename( (string) $sFileName );
            $safeNameEsc = $this->e( $safeName );

            $ext = pathinfo( $safeName, PATHINFO_EXTENSION );
            $short = mb_strlen( $safeName ) > 18
                ? mb_substr( $safeName, 0, 15 ).'... ['.$ext.']'
                : $safeName;

            $bImage = $oIJ->checkCorrectFile( $safeName, $config['allowed_image_extensions'] ) ? true : null;

            $isRecent = ( $now - (int)$mtime ) < 1200; // 20 min

            $thumbHtml = '';
            if( isset( $bImage ) && isset( $this->aImagesThumbs[$safeName] ) ){
                $thumbHtml = '<img src="'.$this->e( $this->aImagesThumbs[$safeName] ).'" style="max-width:100px; max-height:100px" alt="'.$safeNameEsc.'" />';
            } else {
                $thumbHtml = $this->e( $short );
            }

            $content .=
                '<tr class="l0'.( $isRecent ? ' time' : '' ).'" id="fileTr'.$i.'">'.
                    '<td class="select custom">'.
                        '<input type="checkbox" name="aDirFiles['.$i.']" value="'.$safeNameEsc.'" data-i="'.$i.'" data-img="'.( isset( $bImage ) ? 1 : 0 ).'" '.( isset( $_SESSION['aUploadedFiles'][$safeName] ) ? 'checked="checked"' : '' ).' id="oDF-'.$i.'" />'.
                    '</td>'.
                    '<td class="name'.( isset( $bImage ) ? ' image-preview' : '' ).'">'.
                        '<div class="default"></div>'.
                        '<a href="files/'.$safeNameEsc.'" target="_blank"'.( isset( $bImage ) ? ' data-fancybox' : '' ).'>'.
                            $thumbHtml.
                        '</a>'.
                    '</td>'.
                    '<td class="description">&nbsp;</td>'.
                    '<td class="position">&nbsp;</td>'.
                    '<td class="location">&nbsp;</td>'.
                    '<td class="thumb">&nbsp;</td>'.
                '</tr>';
            $i++;
        }

        if( isset( $_SESSION['aUploadedFiles'] ) ){
            unset( $_SESSION['aUploadedFiles'] );
        }

        // pasek nad tabelą: wyszukiwarka + licznik + skrócona paginacja
        $sToolbar = '<div class="files-dir-toolbar">'.
            '<input type="text" id="files-dir-search" class="files-dir-toolbar__search" placeholder="Szukaj pliku po nazwie…" value="'.$this->e( $sSearch ).'" autocomplete="off" />'.
            '<span class="files-dir-toolbar__count">'.
                ( $iTotal > 0
                    ? 'Pliki '.( $iOffset + 1 ).'–'.min( $iOffset + $iPerPage, $iTotal ).' z '.$iTotal
                    : 'Brak plików'.( $sSearch !== '' ? ' dla „'.$this->e( $sSearch ).'”' : '' )
                ).
            '</span>';

        if( $iPages > 1 ){
            $aSites = [ 1 ];
            for( $s = max( 2, $iSite - 2 ); $s <= min( $iPages - 1, $iSite + 2 ); $s++ ){
                $aSites[] = $s;
            }
            $aSites[] = $iPages;
            $aSites = array_values( array_unique( $aSites ) );

            $sToolbar .= '<span class="files-dir-toolbar__pages">';
            $iPrev = 0;
            foreach( $aSites as $s ){
                if( $iPrev && $s - $iPrev > 1 ){
                    $sToolbar .= '<span class="dots">…</span>';
                }
                $sToolbar .= $s === $iSite
                    ? '<strong>'.$s.'</strong>'
                    : '<a href="#" class="files-dir-page" data-site="'.$s.'">'.$s.'</a>';
                $iPrev = $s;
            }
            $sToolbar .= '</span>';
        }

        $sToolbar .= '</div>';

        return
            '<div class="files-dir-container table-responsive">'.
                $sToolbar.
                '<table id="files-dir-table" class="rwd-inner-container table table-xs" cellpadding="0" cellspacing="0" border="0">'.
                    '<thead>'.
                        '<tr>'.
                            '<th class="select">'.$this->e( $lang['Select'] ).'</th>'.
                            '<th class="name">'.
                                $this->e( $lang['File'] ).
                                '<br><small style="font-weight:400"><a href="#" class="clear-default d-flex" title="'.$this->e( $lang['Cancel'] ).'">[ x ] Resetuj miniaturę</a></small>'.
                            '</th>'.
                            '<th class="description">'.$this->e( $lang['Description'] ).'</th>'.
                            '<th class="position">'.$this->e( $lang['Position'] ).'</th>'.
                            '<th class="location">'.$this->e( $lang['Location_page_details'] ).'</th>'.
                            '<th class="thumb">'.$this->e( $lang['Thumbnail'] ).'</th>'.
                        '</tr>'.
                    '</thead>'.
                    '<tbody>'.$content.'</tbody>'.
                '</table>'.
            '</div>';
    } // end function listFilesInDir

    /**
     * Uploads file to a server
     * @return string
     * @param string $sFileName
     */
    public function uploadFile( $sFileName ){
        global $config;

        $oIJ = ImageJobs::getInstance();

        // Bezpieczna nazwa (bez ścieżek)
        $sFileName = basename( (string) $sFileName );
        $sFileName = preg_replace( '/[^a-zA-Z0-9_\.\-]+/u', '_', $sFileName );

        if( $sFileName === '' ){
            return '{"success":false}';
        }

        if(
            $oIJ->checkCorrectFile( $sFileName, $config['allowed_not_image_extensions'] ) ||
            $oIJ->checkCorrectFile( $sFileName, $config['allowed_image_extensions'] )
        ){
            $sFileNameNew = $oIJ->checkIsFile( $sFileName, 'files/' );

            $ok = false;

            if( isset( $_FILES['sFileName']['tmp_name'] ) ){
                $ok = move_uploaded_file( $_FILES['sFileName']['tmp_name'], 'files/'.$sFileNameNew );
            } else {
                // upload przez php://input
                $data = file_get_contents( 'php://input' );
                if( $data !== false ){
                    $ok = ( file_put_contents( 'files/'.$sFileNameNew, $data ) !== false );
                }
            }

            if( $ok ){
                $_SESSION['aUploadedFiles'][$sFileNameNew] = true;

                $sizeInfo = null;
                if( $oIJ->checkCorrectFile( $sFileNameNew, $config['allowed_image_extensions'] ) ){
                    if( $oIJ->checkImgMaxDimension( 'files/'.$sFileNameNew ) !== true ){
                        $sizeInfo = ', "size_info":true';
                    }
                }

                return '{"success":true'.$sizeInfo.'}';
            }

            return '{"success":false}';
        }

        return '{error:"Incorrect extension"}';
    } // end function uploadFile

    /**
     * Lists all files on selected page
     * @return string
     * @param int $iPage
     */
    public function listAllFiles( $iPage ){
        global $config, $lang;

        $iPage = (int) $iPage;
        if( $iPage <= 0 ){
            return null;
        }

        $content = '';
        $oSql = Sql::getInstance();

        $stmt = $oSql->prepare( 'SELECT * FROM files WHERE iPage = :page ORDER BY iType ASC, iPosition ASC, sFileName ASC' );
        $stmt->execute( [ ':page' => $iPage ] );

        $iCurrentType = null;
        $iColspan = 7; // liczba kolumn w tabeli

        while( $aData = $stmt->fetch( PDO::FETCH_ASSOC ) ){
            $fileName = basename( (string) $aData['sFileName'] );
            $fileNameEsc = $this->e( $fileName );

            // --- Wiersz nagłówka kategorii ---
            if( $iCurrentType !== (int)$aData['iType'] ){
                $iCurrentType = (int)$aData['iType'];
                $sCategoryName = isset( $config['images_locations'][$iCurrentType] )
                    ? $this->e( $config['images_locations'][$iCurrentType] )
                    : 'Inne';

                $content .=
                    '<tr class="category-header" data-type="'.$iCurrentType.'">'.
                        '<td colspan="'.$iColspan.'">'.
                            '<strong>'.$sCategoryName.'</strong>'.
                        '</td>'.
                    '</tr>';
            }

            if( (int)$aData['iSize'] > 0 && isset( $this->aImagesThumbs[$fileName] ) ){
                $sFile = '<a href="files/'.$fileNameEsc.'" data-fancybox target="_blank"><img src="'.$this->e( $this->aImagesThumbs[$fileName] ).'" style="max-width:100px; max-height:100px" alt="'.$fileNameEsc.'" class="image" /></a><div style="margin: 5px 0 0 0; font-size: 11px; font-weight:300">'.$fileNameEsc.'</div>';
            } else {
                $sFile = '<a href="files/'.$fileNameEsc.'" target="_blank">'.$fileNameEsc.'</a>';
            }

            $desc = $this->e( $aData['sDescription'] ?? '' );
            $url  = $this->e( $aData['sUrl'] ?? '' );

            $content .=
                '<tr class="l0">'.
                '<td class="position">'.
                            '<input class="mr-1" type="radio" id="defaultImage-'.$this->e( $aData['iFile'] ).'" name="iDefaultImage" value="'.$this->e( $aData['iFile'] ).'"'.( (int)$aData['iDefault'] === 1 ? ' checked="checked"' : '' ).'/>'.
                    '</td>'.
                
                    '<td class="default name'.( (int)$aData['iSize'] > 0 ? ' image-preview' : '' ).'">'.
                        
                        $sFile.
                    '</td>'.

                    '<td class="description"'.( ( (int)$aData['iSize'] === 0 ) ? ' colspan="3"' : '' ).'>'.
                        '<div>'.
                            '<input type="text" name="aFilesDescription['.$this->e( $aData['iFile'] ).']" value="'.$desc.'" size="20" class="input description mb-2" placeholder="Opis" />'.
                        '</div>'.
                        '<div>'.
                            '<input type="text" name="aFilesUrl['.$this->e( $aData['iFile'] ).']" value="'.$url.'" size="20" class="input description" placeholder="Link URL" />'.
                        '</div>'.
                    '</td>'.

                   

                    ( ( (int)$aData['iSize'] > 0 )
                        ? '<td class="location"><select name="aFilesTypes['.$this->e( $aData['iFile'] ).']" class="adv-select-not form-control">'.getSelectFromArray( $config['images_locations'], $aData['iType'] ).'</select></td>'.
                          '<td class="thumb"><select name="aFilesSizes['.$this->e( $aData['iFile'] ).']" class="adv-select-not form-control">'.getThumbnailsSelect( $aData['iSize'] ).'</select></td>'
                        : ''
                    ).
                
                 '<td class="position">'.
                        '<input type="text" name="aFilesPositions['.$this->e( $aData['iFile'] ).']" value="'.$this->e( $aData['iPosition'] ).'" size="2" maxlength="4" class="numeric" />'.
                    '</td>'.

                    '<td class="custom delete">'.
                        '<input type="checkbox" name="aFilesDelete['.$this->e( $aData['iFile'] ).']" id="oFD-'.$this->e( $aData['iFile'] ).'" class="delete mr-1" value="'.$this->e( $aData['iFile'] ).'" data-img="'.$this->e( $aData['iSize'] ).'" />'.
                        '<label for="oFD-'.$this->e( $aData['iFile'] ).'">'.$this->e( $lang['Delete'] ).'</label>'.
                    '</td>'.
                '</tr>';
        }

        if( $content === '' ){
            return null;
        }

        return
            '<input type="hidden" name="iChangedFiles" id="iChangedFiles" value="1" />'.
            '<div class="table-responsive">'.
                '<table id="files-list" class="rwd-inner-container table table-xs" cellpadding="0" cellspacing="0" border="0">'.
                    '<thead>'.
                        '<tr>'.
                            '<th class="position"><small style="font-weight:400"><a href="#" class="clear-default d-flex" title="'.$this->e( $lang['Cancel'] ).'">[ x ]</a></small></th>'.
                            '<th class="name">'.
                                $this->e( $lang['File'] ).
                                
                            '</th>'.
                            '<th class="description">'.$this->e( $lang['Description'] ).'</th>'.
                            
                            '<th class="location">'.$this->e( $lang['Location_page_details'] ).'</th>'.
                            '<th class="thumb">'.$this->e( $lang['Thumbnail'] ).'</th>'.
                            '<th class="position">'.$this->e( $lang['Position'] ).'</th>'.
                            '<th class="delete"><input type="submit" name="sOption" class="button button-border button-xs" value="'.$this->e( $lang['Delete'] ).'" /></th>'.
                        '</tr>'.
                    '</thead>'.
                    '<tbody>'.$content.'</tbody>'.
                '</table>'.
            '</div>';
    } // end function listAllFiles

    /**
     * Adds files from a server
     * @param array  $aForm
     * @param int    $iPage
     */
    public function addFilesFromServer( $aForm, $iPage ){
        global $config;

        $iPage = (int) $iPage;
        if( $iPage <= 0 ){
            return;
        }

        if( isset( $aForm['aDirFiles'] ) ){
            $oIJ = ImageJobs::getInstance();
            $oSql = Sql::getInstance();

            foreach( $aForm['aDirFiles'] as $iKey => $sFile ){
                $sFile = basename( (string) $sFile );

                if( is_file( 'files/'.$sFile ) ){
                    $sFileRaw = null;

                    if( isset( $config['change_files_names'] ) ){
                        if( isset( $aForm['sName'] ) && !empty( $aForm['sName'] ) ){
                            $sFileRaw = $sFile;
                            $sFile = $oIJ->checkIsFile( $aForm['sName'].'.'.$oIJ->getExtOfFile( $sFile ), 'files/' );
                        }
                    } else {
                        if( $oIJ->changeFileName( $oIJ->getNameOfFile( $sFile ) ).'.'.$oIJ->changeFileName( $oIJ->getExtOfFile( $sFile ) ) != $sFile ){
                            $sFileRaw = $sFile;
                            $sFile = $oIJ->checkIsFile( $sFile, 'files/' );
                        }
                    }

                    if( isset( $sFileRaw ) && !is_file( 'files/'.$sFile ) ){
                        copy( 'files/'.$sFileRaw, 'files/'.$sFile );
                    }

                    $iSize = ( isset( $aForm['aDirFilesSizes'][$iKey] ) && $oIJ->checkCorrectFile( $sFile, $config['allowed_image_extensions'] ) )
                        ? (int) $aForm['aDirFilesSizes'][$iKey]
                        : 0;

                    $iDefault = ( $iSize > 0 && isset( $aForm['iDirDefaultImage'] ) && (string)$aForm['iDirDefaultImage'] === (string)$iKey ) ? 1 : 0;

                    if( $iDefault === 1 ){
                        $stmt = $oSql->prepare( 'UPDATE files SET iDefault = 0 WHERE iPage = :page AND iSize > 0' );
                        $stmt->execute( [ ':page' => $iPage ] );
                        $this->bNewImageSetDefault = true;
                    }

                    $type = ( isset( $aForm['aDirFilesTypes'][$iKey] ) && is_numeric( $aForm['aDirFilesTypes'][$iKey] ) ) ? (int)$aForm['aDirFilesTypes'][$iKey] : 1;
                    $pos  = ( isset( $aForm['aDirFilesPositions'][$iKey] ) && is_numeric( $aForm['aDirFilesPositions'][$iKey] ) ) ? (int)$aForm['aDirFilesPositions'][$iKey] : 0;
                    $desc = isset( $aForm['aDirFilesDescriptions'][$iKey] ) ? changeTxt( trim( (string)$aForm['aDirFilesDescriptions'][$iKey] ), 'ndnl' ) : '';

                    $stmt = $oSql->prepare(
                        'INSERT INTO files ( sFileName, iSize, iType, iPosition, sDescription, iDefault, iPage )
                         VALUES ( :name, :size, :type, :pos, :desc, :def, :page )'
                    );
                    $stmt->execute( [
                        ':name' => $sFile,
                        ':size' => $iSize,
                        ':type' => $type,
                        ':pos'  => $pos,
                        ':desc' => $desc,
                        ':def'  => $iDefault,
                        ':page' => $iPage,
                    ] );

                    if( $iSize > 0 ){
                        $this->generateThumbs( $sFile, $iSize );
                        $this->bAddedImage = true;
                    }

                    if( isset( $sFileRaw ) ){
                        $this->deleteFilesFromDirs( $sFileRaw, $iSize );
                    }
                }
            }
        }
    } // end function addFilesFromServer

    /**
     * Saves data of files and images (description, position etc.) to flat files database
     * @return void
     * @param array $aForm
     * @param int $iPage
     */
    public function saveFiles( $aForm, $iPage = null ){
        global $config;

        $iPage = (int) $iPage;

        if( isset( $aForm['aDirFiles'] ) ){
            $this->addFilesFromServer( $aForm, $iPage );
        }

        if( isset( $aForm['aFilesDelete'] ) ){
            $this->deleteSelectedFiles( $aForm['aFilesDelete'] );
        }

        $oSql = Sql::getInstance();

        if(
            isset( $aForm['iChangedFiles'] ) &&
            (int)$aForm['iChangedFiles'] === 1 &&
            isset( $aForm['aFilesDescription'] ) &&
            is_array( $aForm['aFilesDescription'] )
        ){
            $stmt = $oSql->prepare( 'SELECT * FROM files WHERE iPage = :page ORDER BY iPosition ASC' );
            $stmt->execute( [ ':page' => $iPage ] );

            $aUpdate = [];
            $aUpdateSize = [];

            while( $aData = $stmt->fetch( PDO::FETCH_ASSOC ) ){
                $iFile = (int) $aData['iFile'];

                if( isset( $aForm['aFilesDelete'][$iFile] ) ){
                    continue;
                }

                if( !isset( $aForm['aFilesDescription'][$iFile] ) ){
                    continue;
                }

                if( isset( $aForm['aFilesSizes'][$iFile] ) && (int)$aForm['aFilesSizes'][$iFile] != (int)$aData['iSize'] && (int)$aData['iSize'] > 0 ){
                    $aUpdate[$iFile][] = 'iSize = :size';
                    $aUpdateSize[$iFile] = [ $aData['sFileName'], (int)$aForm['aFilesSizes'][$iFile] ];
                }

                if( isset( $aForm['aFilesTypes'][$iFile] ) && (int)$aForm['aFilesTypes'][$iFile] != (int)$aData['iType'] ){
                    $aUpdate[$iFile][] = 'iType = :type';
                }

                if( isset( $aForm['aFilesPositions'][$iFile] ) && (int)$aForm['aFilesPositions'][$iFile] != (int)$aData['iPosition'] ){
                    $aUpdate[$iFile][] = 'iPosition = :pos';
                }

                $urlPost = isset( $aForm['aFilesUrl'][$iFile] ) ? trim( (string)$aForm['aFilesUrl'][$iFile] ) : '';
                if( $urlPost !== (string)($aData['sUrl'] ?? '') ){
                    $aUpdate[$iFile][] = 'sUrl = :url';
                }

                $descPost = changeTxt( trim( (string)$aForm['aFilesDescription'][$iFile] ), 'ndnl' );
                if( $descPost !== (string)($aData['sDescription'] ?? '') ){
                    $aUpdate[$iFile][] = 'sDescription = :desc';
                }

                if( !empty( $aUpdate[$iFile] ) ){
                    // wykonujemy update od razu (łatwiej parametry)
                    $sql = 'UPDATE files SET '.implode( ', ', array_unique( $aUpdate[$iFile] ) ).' WHERE iFile = :file';

                    $params = [ ':file' => $iFile ];

                    if( strpos( $sql, ':size' ) !== false ) $params[':size'] = (int)$aForm['aFilesSizes'][$iFile];
                    if( strpos( $sql, ':type' ) !== false ) $params[':type'] = (int)$aForm['aFilesTypes'][$iFile];
                    if( strpos( $sql, ':pos' )  !== false ) $params[':pos']  = (int)$aForm['aFilesPositions'][$iFile];
                    if( strpos( $sql, ':url' )  !== false ) $params[':url']  = $urlPost;
                    if( strpos( $sql, ':desc' ) !== false ) $params[':desc'] = $descPost;

                    $u = $oSql->prepare( $sql );
                    $u->execute( $params );
                }
            }

            // thumbs regen
            if( !empty( $aUpdateSize ) ){
                foreach( $aUpdateSize as $iFile => $aValue ){
                    $this->generateThumbs( $aValue[0], $aValue[1] );
                }
            }

            // default image
            if( !isset( $this->bNewImageSetDefault ) ){
                $stmt = $oSql->prepare( 'UPDATE files SET iDefault = 0 WHERE iPage = :page AND iSize > 0' );
                $stmt->execute( [ ':page' => $iPage ] );

                if( isset( $aForm['iDefaultImage'] ) && is_numeric( $aForm['iDefaultImage'] ) ){
                    $def = (int) $aForm['iDefaultImage'];
                    if( !isset( $aForm['aFilesDelete'][$def] ) ){
                        $stmt = $oSql->prepare( 'UPDATE files SET iDefault = 1 WHERE iFile = :file' );
                        $stmt->execute( [ ':file' => $def ] );
                        $this->bImageSetDefault = true;
                    }
                }
            }
        }

        if( isset( $this->bAddedImage ) && !isset( $aForm['iChangedFiles'] ) && !isset( $this->bNewImageSetDefault ) && !isset( $this->bImageSetDefault ) ){
            $stmt = $oSql->prepare( 'SELECT iFile FROM files WHERE iPage = :page AND iSize > 0 ORDER BY iPosition ASC, sFileName ASC LIMIT 1' );
            $stmt->execute( [ ':page' => $iPage ] );
            $first = (int) $stmt->fetchColumn();

            if( $first > 0 ){
                $stmt = $oSql->prepare( 'UPDATE files SET iDefault = 1 WHERE iFile = :file' );
                $stmt->execute( [ ':file' => $first ] );
            }
        }
    } // end function saveFiles

    /**
     * Generates thumbnails
     * @return void
     * @param string $sFileName
     * @param int $iSize
     */
    private function generateThumbs( $sFileName, $iSize ){
        global $config;

        $oIJ = ImageJobs::getInstance();
        $aImgSize = $oIJ->throwImgSize( 'files/'.$sFileName );

        if(
            isset( $config['max_dimension_of_image'] ) &&
            is_numeric( $config['max_dimension_of_image'] ) &&
            ( $aImgSize['width'] > $config['max_dimension_of_image'] || $aImgSize['height'] > $config['max_dimension_of_image'] ) &&
            ( $aImgSize['width'] < MAX_IMAGE_SIZE && $aImgSize['height'] < MAX_IMAGE_SIZE )
        ){
            $oIJ->setThumbSize( $config['max_dimension_of_image'] );
            $oIJ->createThumb( 'files/'.$sFileName, 'files/', $sFileName );
        }

        $sThumbsDir = 'files/'.$iSize.'/';
        if( !is_dir( $sThumbsDir ) ){
            mkdir( $sThumbsDir );
            chmod( $sThumbsDir, FILES_CHMOD );
        }

        if( !is_file( $sThumbsDir.$sFileName ) ){
            $oIJ->createCustomThumb( 'files/'.$sFileName, $sThumbsDir, $iSize, $sFileName, true );
        }
    } // end function generateThumbs

    /**
     * Deletes all files attached to pages that are being deleted
     * @return void
     * @param array  $aPages
     */
    public function deleteFiles( $aPages ){
        global $config;

        if( empty( $aPages ) || !is_array( $aPages ) ){
            return;
        }

        $oSql = Sql::getInstance();
        $ids = array_values( array_filter( array_map( 'intval', $aPages ), fn($v) => $v > 0 ) );
        if( empty( $ids ) ){
            return;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '?' ) );

        if( isset( $config['delete_unused_files'] ) ){
            $stmt = $oSql->prepare( 'SELECT sFileName, iSize FROM files WHERE iPage IN ('.$placeholders.')' );
            $stmt->execute( $ids );

            $aDelete = [];
            while( $aData = $stmt->fetch( PDO::FETCH_ASSOC ) ){
                $aDelete[$aData['sFileName']] = (int)$aData['iSize'];
            }

            $stmt = $oSql->prepare( 'DELETE FROM files WHERE iPage IN ('.$placeholders.')' );
            $stmt->execute( $ids );

            foreach( $aDelete as $sFileName => $iSize ){
                $this->deleteFilesFromDirs( $sFileName, $iSize );
            }
        } else {
            $stmt = $oSql->prepare( 'DELETE FROM files WHERE iPage IN ('.$placeholders.')' );
            $stmt->execute( $ids );
        }
    } // end function deleteFiles

    /**
     * Deletes all files selected for deletion
     * @return void
     * @param array  $aFiles
     */
    public function deleteSelectedFiles( $aFiles ){
        global $config;

        if( empty( $aFiles ) || !is_array( $aFiles ) ){
            return;
        }

        $oSql = Sql::getInstance();
        $ids = array_values( array_filter( array_map( 'intval', $aFiles ), fn($v) => $v > 0 ) );
        if( empty( $ids ) ){
            return;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '?' ) );

        if( isset( $config['delete_unused_files'] ) ){
            $stmt = $oSql->prepare( 'SELECT sFileName, iSize FROM files WHERE iFile IN ('.$placeholders.')' );
            $stmt->execute( $ids );

            $aDelete = [];
            while( $aData = $stmt->fetch( PDO::FETCH_ASSOC ) ){
                $aDelete[$aData['sFileName']] = (int)$aData['iSize'];
            }

            $stmt = $oSql->prepare( 'DELETE FROM files WHERE iFile IN ('.$placeholders.')' );
            $stmt->execute( $ids );

            foreach( $aDelete as $sFileName => $iSize ){
                $this->deleteFilesFromDirs( $sFileName, $iSize );
            }
        } else {
            $stmt = $oSql->prepare( 'DELETE FROM files WHERE iFile IN ('.$placeholders.')' );
            $stmt->execute( $ids );
        }
    } // end function deleteSelectedFiles

    /**
     * Deletes files and images from the "files/" directory
     * @return void
     * @param string $sFileName
     * @param int    $iSize
     */
    public function deleteFilesFromDirs( $sFileName, $iSize = null ){
        $oSql = Sql::getInstance();

        $sFileName = basename( (string) $sFileName );

        $stmt = $oSql->prepare( 'SELECT iFile FROM files WHERE sFileName = :name LIMIT 1' );
        $stmt->execute( [ ':name' => $sFileName ] );
        $iData = $stmt->fetchColumn();

        if( empty( $iData ) ){
            $stmt = $oSql->prepare( 'SELECT iSlider FROM sliders WHERE sFileName = :name LIMIT 1' );
            $stmt->execute( [ ':name' => $sFileName ] );
            $iData = $stmt->fetchColumn();
        }

        if( empty( $iData ) ){
            if( isset( $iSize ) && (int)$iSize > 0 && isset( $this->aDirs ) ){
                foreach( $this->aDirs as $iDir ){
                    $path = 'files/'.$iDir.'/'.$sFileName;
                    if( is_file( $path ) ){
                        unlink( $path );
                    }
                }
            }

            $main = 'files/'.$sFileName;
            if( is_file( $main ) ){
                unlink( $main );
            }
        }
    } // end function deleteFilesFromDirs
};
?>
