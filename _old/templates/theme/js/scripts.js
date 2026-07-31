 

$(document).ready(function() {
    
    var video = $(".videoHeader__background").get(0);
    if (video && video.paused) {
        video.play().catch(function(error) {
            console.log("Autoplay zablokowany:", error);
        });
    }
    
    let lastScrollTop = 0;
    $(window).on("scroll", function() {
        requestAnimationFrame(() => {
            let scrolled = $(this).scrollTop();
            let opacity = Math.max(0.5 - (scrolled * 0.0005), 0); // Maksymalne opacity 0.5

            if (scrolled !== lastScrollTop) {
                $(".videoHeader__background").css("opacity", opacity);
                lastScrollTop = scrolled;
            }
        });
    });
    
    
    
    
    const toggleSwitch = $('#theme-switch-checkbox');

    function applyTheme(theme) {
        if (theme === 'dark') {
            $('body').addClass('dark-mode');
            toggleSwitch.prop('checked', true);
        } else {
            $('body').removeClass('dark-mode');
            toggleSwitch.prop('checked', false);
        }
        document.cookie = `theme=${theme}; path=/; max-age=31536000`; // Zapis w cookies na rok
    }

    function getThemeFromCookie() {
        const cookies = document.cookie.split('; ');
        const themeCookie = cookies.find(row => row.startsWith('theme='));
        return themeCookie ? themeCookie.split('=')[1] : null;
    }

    // Odczyt i ustawienie motywu na starcie
    const savedTheme = getThemeFromCookie() || 'light';
    applyTheme(savedTheme);

    // Obsługa przełącznika
    toggleSwitch.on('change', function() {
        const newTheme = toggleSwitch.is(':checked') ? 'dark' : 'light';
        applyTheme(newTheme);
    });
    
    
    
    
    
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
    
    $(".submenu .submenu_item.selected").parent().parent().addClass("selected");
    $(".submenu .submenu_item.selected").parent().addClass("open");
    
 
    $('.scrollTo').click (function(){
        var id = $(this).attr("data-id");
        event.preventDefault()
        $('html, body').animate({
            scrollTop: $("#section-"+id).offset().top
        }, 1000);
    });
    

    $('.faqItemCollapse').click (function(){
        var id = $(this).attr("data-id");
        event.preventDefault()
        $('.faqItem').removeClass('open');
         $('.faqItem-' + id).addClass('open');
    });
    
    
    
    
    // przyklejenie menu 
    $(document).scroll(function () {
        var y = $(this).scrollTop();
        if (y > 100) {
            $('.mainHeader').addClass('scroll');
        } else {
            $('.mainHeader').removeClass('scroll');
        }
    });
    
    
    
    ScrollReveal().reveal('.showUp',  {
        delay: 200,
        duration: 500,
        distance: 100,
        reset: true,
        scale: 1
    });
    
    Fancybox.bind("[data-fancybox]", {
      // Your custom options
    });
    
    


    
    $(document).scroll(function () {
        var y = $(this).scrollTop();
        if (y > 100) {
            $('.footerUp').addClass("show");   
            $('.mobileFooter').addClass("show");   
        }else{
            $('.footerUp').removeClass("show");   
            $('.mobileFooter').removeClass("show");   
        }
    });
    
     
    
        
});

 
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("input[type='text'], textarea").forEach(function (input) {
        input.addEventListener("keypress", function (event) {
            let char = String.fromCharCode(event.which);
            let regex = /^[a-zA-Z0-9\sąćęłńóśźżĄĆĘŁŃÓŚŹŻ\-\.]*$/; // Pozwala na litery, cyfry, spacje, polskie znaki diakrytyczne, myślniki i kropki
            
            if (!regex.test(char)) {
                event.preventDefault();
            }
        });
    });
});




// CART
// CART
// CART
 

$(document).ready(function() {
    function updateCart(productId, action, redirect = false) {
        $.post("plugins/cart.php", { product_id: productId, action: action }, function(data) {
            if (redirect) {
                window.location.href = "./?koszyk";
            } else {
                $(".cartListWrapper").load(window.location.href + " .cartListWrapper > *");
            }
        }, "json");
    }

    $(document).on("click", ".add-to-cart", function() {
        let $btn = $(this);
        let productId = $btn.data("product-id");

        if (!$btn.hasClass("button-active")) {
            updateCart(productId, "add");
            $btn.addClass("button-active").html("<img src='images/icons/check.svg'>Koszyk");

            $(".cartIcon").addClass("active");
            setTimeout(() => $(".cartIcon").removeClass("active"), 500);

            $btn.off("click").on("click", () => window.location.href = "?koszyk");
        } else {
            window.location.href = "?koszyk";
        }
    });

    $(document).on("click", ".add-to-cart-1", function() {
        updateCart($(this).data("product-id"), "add");
    });

    $(document).on("click", ".remove-from-cart", function() {
        updateCart($(this).data("product-id"), "remove");
    });

    $(document).on("click", ".delete-from-cart", function() {
        updateCart($(this).data("product-id"), "delete");
    });
});
 

// CART
// CART
// CART
// CART
// CART
 
    
$(document).ready(function() {
    
     $("html").click(function(event) {
        $(".lang-select").removeClass("open");
    });
      
    $(".lang-selected").click(function(e) {
        e.stopPropagation();
        $(".lang-select").toggleClass("open");
    });
      
    $(".lang-select-item").click(function(e) {
        e.stopPropagation();
        var dataVal = $(this).attr("data-val");
        var dataText = $(this).html();
        $(".lang-selected").attr("data-val", dataVal);
        $('.lang-selected img ').attr('src', 'images/icons/flag-' + dataVal + '.png');
        
//        $(".lang-selected").html(dataText);
        $(".lang-select").removeClass("open");
    });

    
     
    
        
});
 

 