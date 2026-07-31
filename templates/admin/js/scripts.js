 

$(document).ready(function() {
    
    
 
	// Dla pewności – ukryj dzieci po załadowaniu
	$('.page-child').hide();

	// Kliknięcie w "rozwiń / zwiń"
	$(document).on('click', '.page-toggle', function(e) {
		e.preventDefault();

		var $btn     = $(this);
		var pageId   = $btn.data('page-id');
		var expanded = $btn.data('expanded') == 1;

		if (expanded) {
			// aktualnie rozwinięte → zwiń
			hideChildren(pageId);
			$btn.data('expanded', 0).text('rozwiń');
		} else {
			// aktualnie zwinięte → rozwiń dzieci
			showChildren(pageId);
			$btn.data('expanded', 1).text('zwiń');
		}
	});

	// pokazuje bezpośrednie dzieci danego rodzica
	function showChildren(parentId) {
		var $children = $('tr.page-child[data-parent-id="' + parentId + '"]');
		$children.show();
	}

	// chowa wszystkie potomne (rekurencyjnie)
	function hideChildren(parentId) {
		var $children = $('tr.page-child[data-parent-id="' + parentId + '"]');

		$children.each(function() {
			var $row    = $(this);
			var childId = $row.data('page-id');

			// rekurencyjnie chowamy dzieci tego dziecka
			hideChildren(childId);

			// reset przycisku na tym poziomie
			$row.find('.page-toggle').data('expanded', 0).text('rozwiń');
		});

		$children.hide();
	}
 
    
    
 
    
   

 
    
    // MENU MOBILNE
    $('.menuHamburger').click (function(){
        $(this).toggleClass('open');
        $('.mainHeader__menu').toggleClass('open');
        $('.mainBody, .mainFooter, .mainHeader__logo').toggleClass('blurEffect');
        
    });
    
    
     
    
    $('.menu_arrow').click (function(){
        var id = $(this).attr("data-menu");
        $('#submenu_' + id).toggleClass('open');
    });
    
    $(".submenu_list .submenu_item.selected").parent().parent().parent().addClass("selected");
    $(".submenu_list .submenu_item.selected").parent().parent().addClass("open");
    
  
    

 
    
    
  
    
    
     
    Fancybox.bind("[data-fancybox]", {
      // Your custom options
    }); 
});



// DROPDOWN


$(document).ready(function () {

  $(document).on('click', '.dropdown__button', function (e) {
    e.stopPropagation();

    const $dropdown = $(this).closest('.dropdown');
    const isOpen = $dropdown.hasClass('open');

    // zamknij wszystkie
    $('.dropdown').removeClass('open')
      .find('.dropdown__button').removeClass('selected');

    // jeśli był zamknięty → otwórz
    if (!isOpen) {
      $dropdown.addClass('open');
      $(this).addClass('selected');
    }
  });

  // klik poza dropdownem
  $(document).on('click', function () {
    $('.dropdown').removeClass('open')
      .find('.dropdown__button').removeClass('selected');
  });

});


 



// menu nawigacji:


$(function () {
  // =========================================
  // NAWIGACJA (MENU)
  // =========================================
  const $body = $('body');
  const $html = $('html');
  const $pageBody = $('body');

  function isMenuOpen() {
    return $('.mainHeader').hasClass('mainHeaderOpen');
  }

  // --- SCROLL LOCK (bez skoku) ---
  function setScrollLock(active) {
    if (active) {
      if ($html.hasClass('scroll-lock')) return;

      const docEl = document.documentElement;
      const scrollbarWidth = window.innerWidth - docEl.clientWidth;

      if (scrollbarWidth > 0) {
        $html.css('padding-right', scrollbarWidth + 'px');
      }

      $html.addClass('scroll-lock');
      $pageBody.addClass('scroll-lock');
      $body.addClass('blurEffect');
    } else {
      if (!$html.hasClass('scroll-lock')) return;

      $html.removeClass('scroll-lock').css('padding-right', '');
      $pageBody.removeClass('scroll-lock');
      $body.removeClass('blurEffect');
    }
  }

  function setMenu(open) {
    $('.mainHeader').toggleClass('mainHeaderOpen', open);
    setScrollLock(open);
  }

  // --- TOGGLE MENU ---
  $(document).on('click', '.menuHamburger', function () {
    setMenu(!isMenuOpen());
  });

  // --- KLIK POZA MENU ---
  $(document).on('mousedown', function (e) {
    const $t = $(e.target);

    if (
      isMenuOpen() &&
      !$t.closest('.mainHeader__menu, .menuHamburger').length
    ) {
      setMenu(false);
    }
  });

  // --- ESC ---
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' && isMenuOpen()) {
      setMenu(false);
    }
  });
});








 


 


 



 


 
//document.addEventListener("DOMContentLoaded", function () {
//    document.querySelectorAll("input[type='text'], textarea").forEach(function (input) {
//        input.addEventListener("keypress", function (event) {
//            let char = String.fromCharCode(event.which);
//            let regex = /^[a-zA-Z0-9\sąćęłńóśźżĄĆĘŁŃÓŚŹŻ\-\+\?\!\.\_\.]*$/; // Pozwala na litery, cyfry, spacje, polskie znaki diakrytyczne, myślniki i kropki
//            
//            if (!regex.test(char)) {
//                event.preventDefault();
//            }
//        });
//    });
//});

  


 