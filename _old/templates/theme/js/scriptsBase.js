 

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

    
    
     class GoogTrans {
        constructor() {
    
            this.events();
    
            this.currLng = this.readCookie('googtrans');
    
            if (this.currLng == "/en/pl") {
                this.setLng("pl");
            }
    
            if (this.currLng == "/en/en") {
                this.setLng("en");
            }
            if (this.currLng == "/en/uk") {
                this.setLng("uk");
            }
            if (this.currLng == "/en/ru") {
                this.setLng("ru");
            }
            if (this.currLng == "/en/de") {
                this.setLng("de");
            }
            
            if (this.currLng == "/fr/fr") {
                this.setLng("fr");
            }
            
        }
    
        events() {
    
            var self = this;                           
    
            document.querySelectorAll(".lang-select-item").forEach(function (item, index) {
    
                item.addEventListener("click", function (e) {
                    e.stopPropagation();
                    e.preventDefault()
                            
                    let lng = $(item).attr("data-val");                                        
                    
                    self.setLng(lng);
                });
            });
    
        }
    
        setLng(lng) {    
    
            if (lng === "pl") {
                jQuery('#\\:1\\.container').contents().find('#\\:1\\.restore').click(); //reset google translate                                          
            } else {
                jQuery('.goog-te-combo').val(lng);
                
                this.triggerHtmlEvent();
            }                                
    
            this.currLng = "/en/" + lng;
        }
    
    
        triggerHtmlEvent() {
            var event;
    
            try {
                let element = document.querySelector('.goog-te-combo');
                let eventName = "change";
        
                if (document.createEvent) {
                    event = document.createEvent('HTMLEvents');
                    event.initEvent(eventName, true, true);
                    element.dispatchEvent(event);            
                } else {
                    event = document.createEventObject();
                    event.eventType = eventName;
                    element.fireEvent('on' + event.eventType, event);            
                }
              }
              catch(err) {
                //err.message;
            }
         
        }
    
        readCookie(name) {
            var c = document.cookie.split('; '),
                cookies = {},
                i, C;
        
            for (i = c.length - 1; i >= 0; i--) {
                C = c[i].split('=');
                cookies[C[0]] = C[1];
            }
        
            return cookies[name];
        }
    }

    const googTrans = new GoogTrans();
    

});
 


// TŁUMACZENIA 
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'pl',
        includedLanguages: 'en,pl,uk,de,fr', 
        layout: google.translate.TranslateElement.FloatPosition.SIMPLE
    }, 'google_translate_element');           
}
var first_load_lang = document.querySelector('html').getAttribute('lang');
var times_run = 0;
check_lang = setInterval(function(){
    times_run++;
    if(times_run >= 20){
        clearInterval(check_lang);
    }
    var lang = document.querySelector('html').getAttribute('lang');
    if(lang != first_load_lang) {
        jQuery('.lang-selected img ').attr('src', 'images/icons/flag-' + lang + '.png');
        clearInterval(check_lang);
    }
},200);

 


// COOCKIES
function WHCreateCookie(name, value, days) {
    var date = new Date();
    date.setTime(date.getTime() + (days*24*60*60*1000));
    var expires = "; expires=" + date.toGMTString();
	document.cookie = name+"="+value+expires+"; path=/";
}
function WHReadCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

window.onload = WHCheckCookies;

function WHCheckCookies() {
    if(WHReadCookie('cookies_accepted') != 'T') {
        var message_container = document.createElement('div');
        message_container.id = 'cookies-message-container';
        var html_code = '<div id="cookies-message"><img src="images/icons/info.svg" class="invert" alt="Informacja"><p>Korzystając z tej strony akceptujesz warunki zawarte w <a href="./?polityka-prywatnosci">Polityce Prywatności</a>.</p><a href="javascript:WHCloseCookiesWindow();" id="accept-cookies-checkbox" name="accept-cookies" class="button">Akceptuję</a></div>';
        message_container.innerHTML = html_code;
        document.body.appendChild(message_container);
    }
}

function WHCloseCookiesWindow() {
    WHCreateCookie('cookies_accepted', 'T', 365);
    document.getElementById('cookies-message-container').removeChild(document.getElementById('cookies-message'));
}

 