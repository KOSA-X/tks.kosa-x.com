/**
 * KOSA X CMS - Drag & Drop sorting for files list
 * Requires: SortableJS (loaded from CDN)
 */
(function(){
    'use strict';

    function initSortable(){
        var table = document.getElementById('files-list');
        if( !table ) return;

        var tbody = table.querySelector('tbody');
        if( !tbody ) return;

        if( typeof Sortable === 'undefined' ){
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js';
            script.onload = function(){ createSortable(tbody); };
            document.head.appendChild(script);
        } else {
            createSortable(tbody);
        }
    }

    function createSortable(tbody){
        Sortable.create(tbody, {
            animation: 200,
            handle: 'tr.l0',
            draggable: 'tr.l0',
            filter: '.category-header',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function(){
                updatePositions(tbody);
                updateTypes(tbody);
            }
        });

        addStyles();
    }

    function updatePositions(tbody){
        var rows = tbody.querySelectorAll('tr.l0');

        for( var i = 0; i < rows.length; i++ ){
            var input = rows[i].querySelector('input[name^="aFilesPositions["]');
            if( input ){
                input.value = i + 1;

                var evt = new Event('change', { bubbles: true });
                input.dispatchEvent(evt);
            }
        }

        var flag = document.getElementById('iChangedFiles');
        if( flag ){
            flag.value = '1';
        }
    }

    function updateTypes(tbody){
        var allRows = tbody.querySelectorAll('tr');
        var currentType = null;

        for( var i = 0; i < allRows.length; i++ ){
            var row = allRows[i];

            // Jeśli to nagłówek kategorii — zapamiętaj typ
            if( row.classList.contains('category-header') ){
                currentType = row.getAttribute('data-type');
                continue;
            }

            // Jeśli to wiersz pliku i znamy typ — ustaw select
            if( row.classList.contains('l0') && currentType !== null ){
                var select = row.querySelector('select[name^="aFilesTypes["]');
                if( select && select.value !== currentType ){
                    select.value = currentType;

                    var evt = new Event('change', { bubbles: true });
                    select.dispatchEvent(evt);
                }
            }
        }
    }

    function addStyles(){
        if( document.getElementById('sortable-styles') ) return;

        var css = document.createElement('style');
        css.id = 'sortable-styles';
        css.textContent =
            '#files-list tbody tr.l0 { cursor: grab; }' +
            '#files-list tbody tr.l0:active { cursor: grabbing; }' +
            '#files-list .sortable-ghost { opacity: 0.15; background: #e3f2fd; }' +
            '#files-list .sortable-chosen { background: #f5f5f5; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }' +
            '#files-list .sortable-drag { opacity: 0.9; }' +
            '#files-list .category-header td { background: #f0f4f8; padding: 8px 12px; font-size: 13px; color: #333; border-bottom: 2px solid #cdd7e1; }';
        document.head.appendChild(css);
    }

    if( document.readyState === 'loading' ){
        document.addEventListener('DOMContentLoaded', initSortable);
    } else {
        initSortable();
    }
})();
