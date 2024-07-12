<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fled implementation : © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

//    !! It is not a good idea to modify this file when a game is running !!

if (!defined('BGA_STATE_START'))
{
    define('BGA_STATE_START', 1);
    define('STATE_ADD_STARTER_TILE', 10);
    define('STATE_DEBUG_SETSTATE', 11);
    define('STATE_ADD_TILE', 20);
    define('STATE_PLAY_TILES', 30);
    define('STATE_DRAW_TILES', 40);
    define('STATE_NEXT_TURN', 50);
    define('BGA_STATE_END', 99);
}

 
$machinestates = [

    // The initial state. Please do not modify.
    BGA_STATE_START => [
        'name' => 'gameSetup',
        'description' => '',
        'type' => 'manager',
        'action' => 'stGameSetup',
        'transitions' => [
            '' => STATE_ADD_STARTER_TILE,
        ],
    ],

    // Add tile to prison
    STATE_ADD_STARTER_TILE => [
        'name' => 'addStarterTile',
        'description' => clienttranslate('${actplayer} must place a tile in the prison'),
        'descriptionmyturn' => clienttranslate('${you} must add your bunk tile to the starting yard tile'),
        'type' => 'activeplayer',
        'possibleactions' => [ 'placeTile', 'debugSetState' ],
        'transitions' => [
            'nextPhase' => STATE_NEXT_TURN,
            'debugSetState' => STATE_DEBUG_SETSTATE,
        ],
    ],

    STATE_DEBUG_SETSTATE => [
        'name' => 'debugSetState',
        'type' => 'game',
        'action' => 'stDebugSetState',
        'updateGameProgression' => true,
        'transitions' => [
            'addTile' => STATE_ADD_TILE,
            'playTiles' => STATE_PLAY_TILES,
            'drawTiles' => STATE_DRAW_TILES,
        ],
    ],

    // Add tile to prison
    STATE_ADD_TILE => [
        'name' => 'addTile',
        'description' => clienttranslate('${actplayer} must add a tile to the prison'),
        'descriptionmyturn' => clienttranslate('${you} must select a tile from your hand to add to the prison'),
        'type' => 'activeplayer',
        'possibleactions' => [ 'placeTile', 'discard', 'escape' ],
        'transitions' => [
            'nextPhase' => STATE_PLAY_TILES,
            'nextTurn' => STATE_NEXT_TURN, // If player escaped
        ],
    ],

    // Play two tiles
    STATE_PLAY_TILES => [
        'name' => 'playTiles',
        'description' => clienttranslate('${actplayer} must play tiles'),
        'descriptionmyturn' => '',
        'type' => 'activeplayer',
        'possibleactions' => [ 'move', 'moveWarder', 'add', 'surrender', 'escape' ],
        'transitions' => [
            'nextTile' => STATE_PLAY_TILES,
            'nextPhase' => STATE_DRAW_TILES,
            'nextTurn' => STATE_NEXT_TURN, // If player escaped
        ],
    ],

    // Draw up to five (may draw one from Governor)
    STATE_DRAW_TILES => [
        'name' => 'drawTiles',
        'description' => clienttranslate('${actplayer} must draw tiles'),
        'descriptionmyturn' => clienttranslate('${you} must draw tiles'),
        'type' => 'activeplayer',
        'possibleactions' => [ 'drawTiles', 'escape' ],
        'transitions' => [
            'nextTurn' => STATE_NEXT_TURN,
        ],
    ],

    // This is just here to update the game progression.
    STATE_NEXT_TURN => [
        'name' => 'nextTurn',
        'description' => '',
        'type' => 'game',
        'action' => 'stNextTurn',
        'updateGameProgression' => true,
        'transitions' => [
            'nextStarterTurn' => STATE_ADD_STARTER_TILE,
            'nextTurn' => STATE_ADD_TILE,
            'gameOver' => BGA_STATE_END,
        ],
    ],
    
    // Final state.
    // Please do not modify (and do not overload action/args methods).
    BGA_STATE_END => [
        'name' => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'type' => 'manager',
        'action' => 'stGameEnd',
        'args' => 'argGameEnd'
    ],
];
