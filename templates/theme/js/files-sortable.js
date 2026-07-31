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

        // Load SortableJS from CDN
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
            handle: 'tr',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function(){
                updatePositions(tbody);
            }
        });

        // Set initial positions
        updatePositions(tbody);

        // Add CSS
        addStyles();
    }

    function updatePositions(tbody){
        var rows = tbody.querySelectorAll('tr');
        for( var i = 0; i < rows.length; i++ ){
            var input = rows[i].querySelector('input[name^="aFilesPositions["]');
            if( input ){
                input.value = i + 1;
            }
        }
    }

    function addStyles(){
        if( document.getElementById('sortable-styles') ) return;

        var css = document.createElement('style');
        css.id = 'sortable-styles';
        css.textContent =
            '#files-list tbody tr { cursor: grab; }' +
            '#files-list tbody tr:active { cursor: grabbing; }' +
            '#files-list .sortable-ghost { opacity: 0.15; background: #e3f2fd; }' +
            '#files-list .sortable-chosen { background: #f5f5f5; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }' +
            '#files-list .sortable-drag { opacity: 0.9; }';
        document.head.appendChild(css);
    }

    // Init on DOM ready
    if( document.readyState === 'loading' ){
        document.addEventListener('DOMContentLoaded', initSortable);
    } else {
        initSortable();
    }
})();
