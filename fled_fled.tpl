{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- Fled implementation : © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<div id="fled_body">
    <div id="fled_surface">
        <div id="fled_governors-area"></div>
        <div id="fled_table">
            <div id="fled_board-container">
                <div id="fled_board" style="transform: scale(2);"></div>
            </div>
            <div id="fled_board-button-expand" class="fled_board-button">
                <i class="fa fa-expand"></i>
            </div>
            <div id="fled_board-button-collapse" class="fled_board-button fled_hidden">
                <i class="fa fa-compress"></i>
            </div>
        </div>
        <div id="fled_player-areas" class="fled_sticky"></div>
    </div>
</div>

<script type="text/javascript">

const fled_Templates = {
    piles:
        '<div id="fled_piles">' +
            '<div id="fled_draw-pile" class="fled_pile">' +
                '<div id="fled_draw-pile-count" class="fled_pile-count"></div>' +
                '<label>' +
                    '${DRAW}' +
                '</label>' +
            '</div>' +
            '<div id="fled_discard-pile" class="fled_pile">' +
                '<div id="fled_discard-pile-count" class="fled_pile-count"></div>' +
                '<label>' +
                    '${DISCARD}' +
                '</label>' +
            '</div>' +
        '</div>',

    miniMap:
        '<div id="fled_minimap">' +
            '<div id="fled_minimap-focus"></div>' +
        '</div>',

    playerArea:
        '<div id="fled_player-area-${PID}" class="fled_player-area whiteblock fled_color-${COLOR}">' +
            '<h2>${NAME}</h2>' +
            '<div id="fled_player-${PID}-hand" class="fled_player-hand-tiles">' +
            '</div>' +
            '<div class="fled_player-area-tiles">' +
                '<div id="fled_shackle-slot-${PID}" class="fled_shackle-slot"></div>' +
                '<div id="fled_reference-tile-${PID}" class="fled_reference-tile"></div>' +
                '<div id="fled_prisoner-tile-${PID}" class="fled_tile fled_prisoner-tile fled_color-${COLOR}">' +
                    '<div class="fled_tile-face-front"></div>' +
                    '<div class="fled_tile-face-back"></div>' +
                '</div>' +
                '<div id="fled_inventory-${PID}" class="fled_inventory"></div>' +
            '</div>' +
        '</div>',

    refTileZone:
        '<div id="fled_ref-tile-${PID}-zone-${INDEX}" class="fled_zone fled_ref-tile-zone-${INDEX}"></div>',

    prisonerTileZone:
        '<div id="fled_prisoner-tile-${PID}-zone-${INDEX}" class="fled_zone fled_prisoner-tile-zone-${INDEX}"></div>',

    tile:
        '<div ' +
            'id="${DIV_ID}" ' +
            'class="fled_tile ${CLASS}" ' +
            'style="transform: translate(${X_EM}em, ${Y_EM}em) rotateZ(${DEG}deg) rotateY(${Y_DEG}deg)" ' +
        '>' +
            '<div class="fled_tile-face-front"></div>' +
            '<div class="fled_tile-face-back"></div>' +
        '</div>',

    tilePlaceholder:
        '<div ' +
            'id="${DIV_ID}" ' +
            'class="fled_tile fled_hidden" ' +
            'style="transform: translate(${X_EM}em, ${Y_EM}em) rotateZ(${DEG}deg)" ' +
        '>' +
        '</div>',

    inventorySlot:
        '<div id="fled_inventory-${PID}-slot-${INDEX}" class="fled_inventory-slot"></div>',

    rollCallTile:
        '<div ' +
            'id="${DIV_ID}" ' +
            'class="fled_tile fled_tile-rc ${CLASS}" ' +
        '>' +
            '<div class="fled_tile-face-front"></div>' +
            '<div class="fled_tile-face-back"></div>' +
        '</div>',

    whistle:
        '<div id="fled_whistle-host" class="fled_whistle-pos-${POS}">' +
            '<div id="fled_whistle"></div>' +
            '<div id="fled_whistle-shadow"></div>' +
        '</div>',

    slot:
        '<div ' +
            'id="${DIV_ID}" ' +
            'class="fled_slot" ' +
            'style="left: ${X_EM}em; top: ${Y_EM}em;" ' +
        '></div>',

    rotateButton:
        '<div ' +
            'id="fled_rotate-button" ' +
            'class="fled_hidden fled_no-transition" ' +
        '>' +
            '${TEXT}' +
        '</div>',

    meeple:
        '<div ' +
            'id="${DIV_ID}" ' +
            'class="fled_meeple fled_meeple-${TYPE} fled_hidden" ' +
            'style="transform: translate(${X_EM}em, ${Y_EM}em); z-index: ${Z};" ' +
        '>' +
        '</div>',

    tooltip:
        '<div class="fled_tooltip">' +
            '<div class="fled_tooltip-image-${TYPE}"></div>' +
            '<span class="fled_tooltip-text">' +
                '${TEXT}' +
            '</span>' +
        '</div>',

    roomTooltip:
        '<div ' +
            'id="fled_tile-${TILE_ID}-${ROOM}" ' +
            'class="fled_tile fled_tile-${TILE_ID} fled_room-tooltip fled_tile-${TILE_ID}-${ROOM}" '+
            'style="transform: translate(${X_EM}em, ${Y_EM}em) rotateZ(${DEG}deg) scale(1.5);" '+
        '></div>',

    doubleRoomTooltip:
        '<div ' +
            'class="fled_tile fled_tile-${TILE_ID} fled_room-tooltip fled_double ${CLASS}" '+
            'style="transform: translate(${X_EM}em, ${Y_EM}em) rotateZ(${DEG}deg) scale(1.5);" '+
        '></div>',

    tileLog: // TODO: use this for scrolls... need new CSS rules for each tile
        '<span class="fled_log-tile fled_log-tile-${DATA}"></span>',

    moonLog:
        '<span class="fled_log-moon"></span>',
    
    roomLog:
        '<span class="fled_log-room fled_log-room-${DATA}" tooltip="${TEXT}"></span>',
    
    npcLog:
        '<span class="fled_log-npc fled_log-npc-${DATA}" tooltip="${TEXT}"></span>',

    actionBarResourceType:
        '<span ' +
            'class="fled_icon fled_icon-${TYPE}" ' +
            'data-type="${TYPE}" ' +
        '></span>',

    confirmCountdown:
        '<svg ' +
            'version="1.1" ' +
            'xmlns="http://www.w3.org/2000/svg" ' +
            'class="fled_countdown" ' +
            'width="1.25em" ' +
            'height="1.25em" ' +
            'viewBox="0 0 100 100" ' +
        '>' +
            '<path fill="#fff" d="M50, 50 L50,0 A50,50 0 1 0 51,0 Z"></path></svg>' +
        '</svg>',
};

</script>  

{OVERALL_GAME_FOOTER}
