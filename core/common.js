/* 
License:
  Code in this file (or any part of it) can be used only as part of Quick.Cms v6.0 or later. All rights reserved by OpenSolution.
*/

/**
 * Sets all checkbox'es with defined class to a given state
 * @param {string} sClass
 * @param {bool} bChecked
 * @return {void}
 */
function checkAll( sClass, bChecked ){
  $( 'input:checkbox.'+sClass ).prop( 'checked', bChecked );
} // end function checkAll

/**
 * Creates a cookie
 * @param {string} sName
 * @param {string} sValue
 * @param {int} iDays
 * @return {void}
 */
function createCookie( sName, sValue, iDays ){
  sValue = encodeURIComponent( sValue );
  if( iDays ){
    var oDate = new Date();
    oDate.setTime( oDate.getTime() + ( iDays*24*60*60*1000 ) );
    var sExpires = "; expires="+oDate.toGMTString();
  }
  else
    var sExpires = "";
  document.cookie = sName+"="+sValue+sExpires;
} // end function createCookie

/**
 * Gets a cookie
 * @param {string} sName
 * @return {string}
 */
function getCookie( sName ){
  var sNameEQ = sName + "=";
  var aCookies = document.cookie.split( ';' );
  for( var i=0; i < aCookies.length; i++ ){
    var c = aCookies[i];
    while( c.charAt(0) == ' ' )
      c = c.substring( 1, c.length );
    if( c.indexOf( sNameEQ ) == 0 )
      return decodeURIComponent( c.substring( sNameEQ.length, c.length ) );
  } // end for
  return null;
} // end function getCookie

/**
 * Deletes a cookie
 * @param {string} sName
 * @return {string}
 */
function delCookie( sName ){
  createCookie( sName, "", -1 );
} // end function delCookie

/**
 * Displays confirm alert when user is trying to delete something
 * @param {string} mInfo
 * @return {bool}
 */
function del( mInfo ){
  if( typeof mInfo === 'object' ){
    var mInfo = ' "'+$( mInfo ).closest( 'tr' ).find( 'th.name a:first-child' ).text()+'"';
  }
  else if( typeof mInfo === 'string' ){}
  else
    mInfo = '';
  if( confirm( (typeof aQuick === 'undefined' ? '' : aQuick['sDelShure'])+mInfo+' ?' ) ) 
    return true;
  else 
    return false
} // end function del

/**
 * Inits mobile menu with hamburger
 */
function initMenus( ){
  var hamburger = $( 'button.hamburger' );
  if( hamburger.length == 0 )
    return false;

  var menu = new MmenuLight( // init menu
    document.querySelector( '.menu-hamburger' ),
    "(max-width: 640px)" /* alternative: you can change the 640px if you need the hamburger in a higher resolution OR you can set just "all" */
  );

  var navigator = menu.navigation({ // create navigation structure
    // theme: 'dark',
    title: ( $( '.hamburger-label' ).text() || 'Menu' )
  });
  
  var drawer = menu.offcanvas({ // get draw handler
     // position: 'left'
  });

  // show or hide menu
  var handleMenuToggle = function(){
    if( $( 'body.mm-ocd-opened' ).length ){
      hamburger.removeClass( 'is-active' );
      drawer.close();
    }
    else{
      hamburger.addClass( 'is-active' );
      drawer.open();
    }
  }

  // toogles hamburger active class when menu closed on backdrop
  var handleBackDrop = function(e){
    hamburger.removeClass( 'is-active' );
    e.stopImmediatePropagation();
  }

  hamburger.click(handleMenuToggle);
  drawer.backdrop.addEventListener('touchstart', handleBackDrop);
  drawer.backdrop.addEventListener('mousedown', handleBackDrop);

} // end function initMenus

// ONLOAD
$(function(){
  initMenus( );
  if( jQuery().quickbox ){
    oQuickBox = $('a[class*="quickbox"], .quickbox-ext').quickbox();
  }
}); // end ONLOAD 

/* PLUGINS */
