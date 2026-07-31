<?php

/**
* Function returns code for tinymce wysiwyg editor
* @return string
* @param  string  $sName
* @param  string  $sContent
* @param array $aParametersExt
* Default options: sToolbar, sPlugins
*/
function getWysiwygTinymce( $sName, $aParametersExt ){
  $content = null;
    global $config;
  if( !defined( 'WYSIWYG_START' ) ){
    define( 'WYSIWYG_START', true );
    $content .= '<script src="templates/admin/js/tinymce/tinymce.min.js"></script>';
  }
    
    if($sName == 'sDescriptionShort'){
        // PROSTY UKŁAD
        
        $content .= '<script>
      tinymce.init({
        selector: "textarea#'.$sName.'",
        content_css: "templates/theme/css/style.css", 
        body_class: "pl-3 pr-3 pb-3 pt-3 bg-white",
        content_style: `

            
            /* Mechanizm wyłączania stylów */
            body.no-preview-styles * {
                all: revert !important;
                font-family: Arial, sans-serif !important;
                font-size: 14px !important;
                color: #000 !important;
                background: #fff !important;
                border: none !important;
                margin: 0.5em 0 !important;
                padding: 0 !important;
            }
        `,
         convert_urls: true,
         relative_urls: false,
          remove_script_host: false,
          invalid_styles: {
                "table": "width height",
                "tr":    "width height",
                "td":    "width height",
                "th":    "width height"
            },
  
        language: "pl",
              height : 300,
            language_url : "templates/admin/js/tinymce/langs/pl.js",
            
        license_key: "gpl|'.$config['tinymce'].'",
         plugins: " autolink charmap codesample image link lists  searchreplace  code",
    toolbar: "bold italic underline strikethrough | link  | numlist bullist | removeformat | togglecss",
    paste_as_text: true,
    
    setup: function(editor) {
        var cssEnabled = true;
        
        // Przycisk toggle CSS
        editor.ui.registry.addToggleButton("togglecss", {
            text: "CSS",
            icon: "palette",
            tooltip: "Włącz/Wyłącz podgląd stylów CSS",
            onAction: function() {
                cssEnabled = !cssEnabled;
                
                if (cssEnabled) {
                    editor.dom.removeClass(editor.getBody(), "no-preview-styles");
                } else {
                    editor.dom.addClass(editor.getBody(), "no-preview-styles");
                }
            },
            onSetup: function(api) {
                api.setActive(cssEnabled);
                return function() {};
            }
        });
    }
      });
    </script>';
        
    }else{
        $content .= '<script>
      tinymce.init({
        selector: "textarea#'.$sName.'",
        content_css: "templates/theme/css/style.css",
        body_class: "pl-3 pr-3 pb-3 pt-3 bg-white",
        content_style: `

            
            /* Mechanizm wyłączania stylów */
            body.no-preview-styles * {
                all: revert !important;
                font-family: Arial, sans-serif !important;
                font-size: 14px !important;
                color: #000 !important;
                background: transparent !important;
                border: none !important;
                margin: 0.5em 0 !important;
                padding: 15px !important;
            }
        `,
        convert_urls: true,
         relative_urls: false,
          remove_script_host: false,
          invalid_styles: {
                "table": "width height",
                "tr":    "width height",
                "td":    "width height",
                "th":    "width height"
            },
        language: "pl",
              height : 600,
            language_url : "templates/admin/js/tinymce/langs/pl.js",

        license_key: "gpl|'.$config['tinymce'].'",
         plugins: "anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code",
    toolbar: "undo redo | blocks fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat | togglecss | shortcode",
    paste_as_text: true,
    setup: function(editor) {
        var cssEnabled = true;
        
        // Przycisk toggle CSS
        editor.ui.registry.addToggleButton("togglecss", {
            text: "CSS",
            icon: "palette",
            tooltip: "Włącz/Wyłącz podgląd stylów CSS",
            onAction: function() {
                cssEnabled = !cssEnabled;
                
                if (cssEnabled) {
                    editor.dom.removeClass(editor.getBody(), "no-preview-styles");
                } else {
                    editor.dom.addClass(editor.getBody(), "no-preview-styles");
                }
            },
            onSetup: function(api) {
                api.setActive(cssEnabled);
                return function() {};
            }
        });
        
        // Menu shortcode (zachowane z oryginalnego kodu)
        editor.ui.registry.addMenuButton("shortcode", {
            text: "Shortcode",
            icon: "code-sample",
            fetch: function(callback) {
                var items = [
                    {
                        type: "nestedmenuitem",
                        text: "Lista stron",
                        icon: "list-bull-default",
                        getSubmenuItems: function() {
                            return [
                                {
                                    type: "menuitem",
                                    text: "Lista podstron",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "Lista podstron",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID strony nadrz\u0119dnej" },
                                                    { type: "input", name: "class", label: "Klasa CSS (opcjonalnie)" },
                                                    { type: "input", name: "per_page", label: "Ilo\u015b\u0107 na stron\u0119 (opcjonalnie)" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                var shortcode = "[listPages id=\"" + data.id + "\"";
                                                if (data.class) shortcode += " class=\"" + data.class + "\"";
                                                if (data.per_page) shortcode += " per_page=\"" + data.per_page + "\"";
                                                shortcode += "]";
                                                editor.insertContent(shortcode);
                                                api.close();
                                            }
                                        });
                                    }
                                },
                                {
                                    type: "menuitem",
                                    text: "Menu",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "Menu",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID menu (1-nawigacja, 2-sklep)" },
                                                    { type: "input", name: "depth", label: "G\u0142\u0119boko\u015b\u0107 (opcjonalnie)" },
                                                    { type: "checkbox", name: "expanded", label: "Rozszerzone" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                var shortcode = "[listMenu id=\"" + (data.id || "1") + "\"";
                                                if (data.depth) shortcode += " depth=\"" + data.depth + "\"";
                                                if (data.expanded) shortcode += " expanded=\"true\"";
                                                shortcode += "]";
                                                editor.insertContent(shortcode);
                                                api.close();
                                            }
                                        });
                                    }
                                },
                                {
                                    type: "menuitem",
                                    text: "FAQ (Accordion)",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "FAQ",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID strony z FAQ" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                editor.insertContent("[listFaq id=\"" + data.id + "\"]");
                                                api.close();
                                            }
                                        });
                                    }
                                }
                            ];
                        }
                    },
                    {
                        type: "nestedmenuitem",
                        text: "Media",
                        icon: "image",
                        getSubmenuItems: function() {
                            return [
                                {
                                    type: "menuitem",
                                    text: "Galeria zdj\u0119\u0107",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "Galeria zdj\u0119\u0107",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID strony" },
                                                    { type: "input", name: "type", label: "Typ galerii (1-g\u0142\u00f3wna, 3-dodatkowa)" },
                                                    { type: "input", name: "class", label: "Klasa CSS" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                var shortcode = "[gallery id=\"" + data.id + "\"";
                                                if (data.type) shortcode += " type=\"" + data.type + "\"";
                                                if (data.class) shortcode += " class=\"" + data.class + "\"";
                                                shortcode += "]";
                                                editor.insertContent(shortcode);
                                                api.close();
                                            }
                                        });
                                    }
                                },
                                {
                                    type: "menuitem",
                                    text: "Slider",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "Slider",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID slidera" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                editor.insertContent("[slider id=\"" + (data.id || "1") + "\"]");
                                                api.close();
                                            }
                                        });
                                    }
                                },
                                {
                                    type: "menuitem",
                                    text: "Obrazek strony",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "Obrazek strony",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID strony" },
                                                    { type: "input", name: "class", label: "Klasa CSS" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                var shortcode = "[image id=\"" + data.id + "\"";
                                                if (data.class) shortcode += " class=\"" + data.class + "\"";
                                                shortcode += "]";
                                                editor.insertContent(shortcode);
                                                api.close();
                                            }
                                        });
                                    }
                                }
                            ];
                        }
                    },
                    {
                        type: "nestedmenuitem",
                        text: "Linki i tre\u015b\u0107",
                        icon: "link",
                        getSubmenuItems: function() {
                            return [
                                {
                                    type: "menuitem",
                                    text: "Link do strony",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "Link do strony",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID strony" },
                                                    { type: "input", name: "text", label: "Tekst linku (opcjonalnie)" },
                                                    { type: "input", name: "class", label: "Klasa CSS" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                var shortcode = "[link id=\"" + data.id + "\"";
                                                if (data.text) shortcode += " text=\"" + data.text + "\"";
                                                if (data.class) shortcode += " class=\"" + data.class + "\"";
                                                shortcode += "]";
                                                editor.insertContent(shortcode);
                                                api.close();
                                            }
                                        });
                                    }
                                },
                                {
                                    type: "menuitem",
                                    text: "URL strony",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "URL strony",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID strony" }
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                editor.insertContent("[url id=\"" + data.id + "\"]");
                                                api.close();
                                            }
                                        });
                                    }
                                },
                                {
                                    type: "menuitem",
                                    text: "Tre\u015b\u0107 innej strony",
                                    onAction: function() {
                                        editor.windowManager.open({
                                            title: "Tre\u015b\u0107 strony",
                                            body: {
                                                type: "panel",
                                                items: [
                                                    { type: "input", name: "id", label: "ID strony" },
                                                    { type: "selectbox", name: "field", label: "Pole", items: [
                                                        { value: "sDescriptionFull", text: "Pe\u0142ny opis" },
                                                        { value: "sDescriptionShort", text: "Kr\u00f3tki opis" }
                                                    ]}
                                                ]
                                            },
                                            buttons: [
                                                { type: "cancel", text: "Anuluj" },
                                                { type: "submit", text: "Wstaw", primary: true }
                                            ],
                                            onSubmit: function(api) {
                                                var data = api.getData();
                                                var shortcode = "[page id=\"" + data.id + "\"";
                                                if (data.field && data.field !== "sDescriptionFull") {
                                                    shortcode += " field=\"" + data.field + "\"";
                                                }
                                                shortcode += "]";
                                                editor.insertContent(shortcode);
                                                api.close();
                                            }
                                        });
                                    }
                                }
                            ];
                        }
                    },
                    {
                        type: "nestedmenuitem",
                        text: "Widgety",
                        icon: "preferences",
                        getSubmenuItems: function() {
                            return [
                                {
                                    type: "menuitem",
                                    text: "Social Media",
                                    onAction: function() { editor.insertContent("[socialMedia]"); }
                                },
                                {
                                    type: "menuitem",
                                    text: "Kontakty",
                                    onAction: function() { editor.insertContent("[contacts phone=\"true\" email=\"true\" location=\"true\"]"); }
                                },
                                {
                                    type: "menuitem",
                                    text: "Mapa strony",
                                    onAction: function() { editor.insertContent("[sitemap]"); }
                                },
                                {
                                    type: "menuitem",
                                    text: "Godziny otwarcia",
                                    onAction: function() { editor.insertContent("[hoursOpen]"); }
                                },
                                {
                                    type: "menuitem",
                                    text: "Prze\u0142\u0105cznik ciemnego motywu",
                                    onAction: function() { editor.insertContent("[darkMode]"); }
                                },
                                {
                                    type: "menuitem",
                                    text: "Ikona wyszukiwania",
                                    onAction: function() { editor.insertContent("[searchIcon]"); }
                                },
                                {
                                    type: "menuitem",
                                    text: "Ikona u\u017cytkownika",
                                    onAction: function() { editor.insertContent("[userIcon]"); }
                                },
                                {
                                    type: "menuitem",
                                    text: "Wyb\u00f3r j\u0119zyka",
                                    onAction: function() { editor.insertContent("[language]"); }
                                }
                            ];
                        }
                    }
                ];
                callback(items);
            }
        });
    }
      });
    </script>';
    }

  
  return $content;
} // end function getWysiwygTinymce

?>