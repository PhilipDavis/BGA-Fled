<?php
// © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)

require_once('FledEvents.php');

// These colour indices correspond to the gameinfos->player_color field
define('FLED_PLAYER_YELLOW', 0);
define('FLED_PLAYER_BLUE', 1);
define('FLED_PLAYER_ORANGE', 2);
define('FLED_PLAYER_GREEN', 3);

define('FLED_ROOM_YARD', 1);
define('FLED_ROOM_CORRIDOR', 2);
define('FLED_ROOM_BUNK', 3);
define('FLED_ROOM_MESSHALL', 4);
define('FLED_ROOM_WASHROOM', 5);
define('FLED_ROOM_QUARTERS', 6);
define('FLED_ROOM_COURTYARD', 7);
define('FLED_ROOM_FOREST', 8);

define('FLED_EGRESS_NONE', 0); // Special-case return value for when there is no tile there
define('FLED_EGRESS_OPEN', 1); // Used for double tiles where there is no interior wall between the two halves of the one room
define('FLED_EGRESS_ARCHWAY', 2);
define('FLED_EGRESS_WINDOW', 3);
define('FLED_EGRESS_DOOR', 4);
define('FLED_EGRESS_ESCAPE', 5); // Player needs the tool indicated on the tile to escape in this direction

define('FLED_EMPTY', 0);

define('FLED_COLOR_NONE', 0);
define('FLED_COLOR_BLUE', 1);
define('FLED_COLOR_PURPLE', 2);
define('FLED_COLOR_GREEN', 3);
define('FLED_COLOR_GOLD', 4);

define('FLED_CONTRABAND_BUTTON', 10);
define('FLED_CONTRABAND_STAMP', 11);
define('FLED_CONTRABAND_COMB', 12);
define('FLED_CONTRABAND_CAKE', 13);

define('FLED_TOOL_KEY', 20);
define('FLED_TOOL_FILE', 21);
define('FLED_TOOL_BOOT', 22);
define('FLED_TOOL_SPOON', 23);

define('FLED_SHAMROCK', 30);

define('FLED_WHISTLE', 40);


define('FLED_TILE_YELLOW_BUNK', 1);
define('FLED_TILE_BLUE_BUNK', 2);
define('FLED_TILE_ORANGE_BUNK', 7);
define('FLED_TILE_GREEN_BUNK', 8);
define('FLED_TILE_SOLITARY_CONFINEMENT', 26);
define('FLED_TILE_CHAPEL', 31);
define('FLED_TILE_DOUBLE_WASHROOM', 36);
define('FLED_TILE_DOUBLE_CORRIDOR', 41);
define('FLED_TILE_START', 46);
define('FLED_TILE_DOUBLE_MESSHALL', 51);

define('FLED_ORIENTATION_NORTH_SOUTH', 0); // Same orientation as the tile graphics
define('FLED_ORIENTATION_WEST_EAST', 100);   // One rotation counter-clockwise
define('FLED_ORIENTATION_SOUTH_NORTH', 200); // Rotated upside down
define('FLED_ORIENTATION_EAST_WEST', 300);   // One rotation clockwise

define('FLED_DIRECTION_NORTH', 0);
define('FLED_DIRECTION_EAST', 1);
define('FLED_DIRECTION_SOUTH', 2);
define('FLED_DIRECTION_WEST', 3);

define('FLED_WIDTH', 14);
define('FLED_HEIGHT', 13);


class FledLogic
{
    private object $data;
    private FledEvents $eventHandlers;

    // Note: All bunk, yard, and courtyard tiles have a tunnel

    public static $RollCallTiles = [
        0 => [
            FLED_ROOM_MESSHALL => FLED_CONTRABAND_CAKE,
            FLED_ROOM_YARD => FLED_CONTRABAND_BUTTON,
        ],
        1 => [
            FLED_ROOM_WASHROOM => FLED_CONTRABAND_COMB,
            FLED_ROOM_MESSHALL => FLED_CONTRABAND_CAKE,
        ],
        2 => [
            FLED_ROOM_WASHROOM => FLED_CONTRABAND_COMB,
            FLED_ROOM_BUNK => FLED_CONTRABAND_STAMP,
        ],
        3 => [
            FLED_ROOM_BUNK => FLED_CONTRABAND_STAMP,
            FLED_ROOM_YARD => FLED_CONTRABAND_BUTTON,
        ],
        4 => [ // The Governor tile
            FLED_ROOM_BUNK => FLED_CONTRABAND_STAMP,
        ],
    ];

    public static $ColorNames = [ // Internal only; not for translation
        'yellow',
        'blue',
        'orange',
        'green',
    ];

    // These are the playable tiles only.
    public static $FledTiles = [
        FLED_TILE_YELLOW_BUNK => [
            'color' => FLED_COLOR_NONE,
            'contains' => FLED_EMPTY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_BLUE_BUNK => [
            'color' => FLED_COLOR_NONE,
            'contains' => FLED_EMPTY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        3 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_WHISTLE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        4 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_COMB,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        5 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        6 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_STAMP,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_ORANGE_BUNK => [
            'color' => FLED_COLOR_NONE,
            'contains' => FLED_EMPTY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_GREEN_BUNK => [
            'color' => FLED_COLOR_NONE,
            'contains' => FLED_EMPTY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        9 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_COMB,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        10 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_CAKE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        11 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_BUTTON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        12 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_STAMP,
            'rooms' => [
                [
                    'type' => FLED_ROOM_BUNK,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        13 => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_KEY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                    'escape' => FLED_TOOL_FILE,
                ],
            ],
            'minPlayers' => 1,
        ],
        14 => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_FILE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                    'escape' => FLED_TOOL_BOOT,
                ],
            ],
            'minPlayers' => 1,
        ],
        15 => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_BOOT,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                    'escape' => FLED_TOOL_SPOON,
                ],
            ],
            'minPlayers' => 1,
        ],
        16 => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_SPOON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                    'escape' => FLED_TOOL_KEY,
                ],
            ],
            'minPlayers' => 1,
        ],
        17 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_BOOT,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                    'escape' => FLED_TOOL_FILE,
                ],
            ],
            'minPlayers' => 1,
        ],
        18 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_SPOON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                    'escape' => FLED_TOOL_BOOT,
                ],
            ],
            'minPlayers' => 1,
        ],
        19 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_KEY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                    'escape' => FLED_TOOL_SPOON,
                ],
            ],
            'minPlayers' => 1,
        ],
        20 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_FILE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_FOREST,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_OPEN, FLED_EGRESS_ESCAPE, FLED_EGRESS_OPEN ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_ESCAPE, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                    'escape' => FLED_TOOL_KEY,
                ],
            ],
            'minPlayers' => 1,
        ],
        21 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_KEY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        22 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_BOOT,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        23 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_FILE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        24 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_KEY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        25 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_WHISTLE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_SOLITARY_CONFINEMENT => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_KEY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_OPEN, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        27 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        28 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_KEY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        29 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_CAKE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        30 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_CHAPEL => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_BOOT,
            'rooms' => [
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        32 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_WHISTLE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        33 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_STAMP,
            'rooms' => [
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        34 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        35 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_SPOON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_DOUBLE_WASHROOM => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_FILE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        37 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_BUTTON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        38 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_BOOT,
            'rooms' => [
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        39 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_SPOON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        40 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_CAKE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_DOUBLE_CORRIDOR => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_STAMP,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_OPEN, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        42 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        43 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_FILE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        44 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_WHISTLE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        45 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_START => [
            'color' => FLED_COLOR_NONE,
            'contains' => FLED_EMPTY,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        47 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_FILE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        48 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_STAMP,
            'rooms' => [
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        49 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        50 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_WHISTLE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 1,
        ],
        FLED_TILE_DOUBLE_MESSHALL => [
            'color' => FLED_COLOR_GOLD,
            'contains' => FLED_TOOL_SPOON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_OPEN, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        52 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_COMB,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        53 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 1,
        ],
        54 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_BOOT,
            'rooms' => [
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        55 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_BUTTON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        56 => [
            'color' => FLED_COLOR_PURPLE,
            'contains' => FLED_TOOL_SPOON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 1,
        ],
        70 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_COMB,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_MESSHALL,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR ],
                ],
            ],
            'minPlayers' => 3,
        ],
        71 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_CAKE,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 3,
        ],
        72 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_BUTTON,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW ],
                ],
            ],
            'minPlayers' => 3,
        ],
        73 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_YARD,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_WASHROOM,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 3,
        ],
        74 => [
            'color' => FLED_COLOR_BLUE,
            'contains' => FLED_CONTRABAND_STAMP,
            'rooms' => [
                [
                    'type' => FLED_ROOM_CORRIDOR,
                    'egress' => [ FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 3,
        ],
        75 => [
            'color' => FLED_COLOR_GREEN,
            'contains' => FLED_SHAMROCK,
            'rooms' => [
                [
                    'type' => FLED_ROOM_QUARTERS,
                    'egress' => [ FLED_EGRESS_DOOR, FLED_EGRESS_DOOR, FLED_EGRESS_WINDOW, FLED_EGRESS_WINDOW ],
                ],
                [
                    'type' => FLED_ROOM_COURTYARD,
                    'egress' => [ FLED_EGRESS_WINDOW, FLED_EGRESS_DOOR, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY ],
                ],
            ],
            'minPlayers' => 3,
        ],
    ];

/*
// Specter Expansion
S1 - [ghost] double corridor: FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, , FLED_EGRESS_ARCHWAY | , FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY, FLED_EGRESS_ARCHWAY / gold key
S2 - ghost tile

// Governor's Hound Expansion
six tiles... TODO
*/

    private function __construct($data, FledEvents $handlers = null)
    {
        $this->data = $data;
        $this->eventHandlers = $handlers;
    }

    static function fromJson(string $json, FledEvents $handlers)
    {
        return new FledLogic(json_decode($json), $handlers);
    }

    private static $debug = false;
    static function enableDebug($enabled)
    {
        FledLogic::$debug = $enabled;
    }

    static function debugPrint($msg)
    {
        if (FledLogic::$debug)
            print $msg;
    }

    //
    // $playerColors is Record<PlayerId, ColorIndex>
    //
    static function newGame($playerColors, $options, FledEvents $events)
    {
        $playerIds = array_keys($playerColors);
        $playerCount = count($playerColors);

        $starterTileIds = [
            FLED_TILE_YELLOW_BUNK,
            FLED_TILE_BLUE_BUNK,
            FLED_TILE_ORANGE_BUNK,
            FLED_TILE_GREEN_BUNK,
            FLED_TILE_START,
        ];

        // Set the initial draw pile to be the set of tiles except the starter tiles
        // and also remove tiles that are incompatible with the current player count.
        $drawPile = array_diff(array_keys(FledLogic::$FledTiles), $starterTileIds);
        $drawPile = array_filter($drawPile, fn($tileId) => FledLogic::$FledTiles[$tileId]['minPlayers'] <= $playerCount);
        shuffle($drawPile);

        // Create an empty board
        $width = 14;
        $height = 13;
        $board = [];
        foreach (range(1, $width * $height) as $i)
            $board[] = 0;

        // Place the starter tile on the board
        $headIndex = FledLogic::makeIndex(6, 6);
        $tailIndex = FledLogic::makeIndex(7, 6);
        $board[$headIndex] = FLED_TILE_START + FLED_ORIENTATION_WEST_EAST;
        $board[$tailIndex] = FLED_TILE_START + FLED_ORIENTATION_WEST_EAST;

        // Assign a random order for the Roll Call tiles
        $rollCall = [ 0, 1, 2, 3 ];
        shuffle($rollCall);
        $rollCall[] = 4; // Governor Tile is always at the end

        // Set up each player with their color and starting tiles
        $players = (object)[];
        foreach ($playerColors as $playerId => $colorIndex)
        {
            $players->$playerId = (object)[
                'inventory' => [],
                'hand' => array_splice($drawPile, 0, 5),
                'shackleTile' => 0,
                'inSolitary' => false,
                'color' => $colorIndex,
                'pos' => null, // Will become an array of [x,y] once the bunk tile is placed
                'placedTile' => false,
                'actionsPlayed' => 0,
                'escaped' => false,
            ];
        }

        return new FledLogic((object)[
            'version' => 1, // Only need to increment for breaking changes after beta release
            'options' => (array)$options,
            'nextPlayer' => 0,
            'order' => $playerIds,
            'players' => $players,
            'npcs' => [
                'warder1' => [
                    'pos' => [ 6, 6 ],
                ],
                /* These get added as the game progressese
                'warder2' => null,
                'warder3' => null,
                'chaplain' => null,
                'ghost' => null, // Specter Expansion
                'hound' => null, // Hound Expansion
                */
            ],
            'governorInventory' => [],
            'whistlePos' => 4, // 0-based index; 4 is the last position (Governor Tile)
            'rollCall' => $rollCall,
            'openWindow' => 3,
            'board' => $board,
            'drawPile' => $drawPile,
            'discardPile' => [],
            'hardLabor' => 0, // Set to 1 once Hard Labor is called
            'finalTurns' => null, // Once a player has escaped, this counts down to how many turns are left; game over at 0
            'setup' => 0, // Switches to 1 once everyone has played their starter bunks
            'moves' => 0,
        ], $events);
    }

    // Only intended for unit testing
    public function setBoard($board)
    {
        $this->data->board = $board;
    }

    public static function makeIndex($x, $y)
    {
        // The top-left forest cell is (0,0)
        return $y * 14 + $x; 
    }

    public static function parseIndex($index)
    {
        $x = $index % 14;
        $y = ($index - $x) / 14;
        return [ $x, $y ]; 
    }

    public static function getTileColor($tileId)
    {
        return FledLogic::$FledTiles[$tileId % 100]['color'];
    }

    public static function getTileScore($tileId)
    {
        // Note: the tile score is the same as the color value
        return FledLogic::$FledTiles[$tileId % 100]['color'];
    }

    public static function roomHasTunnel($room)
    {
        if (!$room) return false;
        return $room['type'] === FLED_ROOM_BUNK
            || $room['type'] === FLED_ROOM_YARD
            || $room['type'] === FLED_ROOM_COURTYARD
        ;
    }

    public static function isDoubleTile($tileId)
    {
        switch ($tileId % 100)
        {
            case FLED_TILE_SOLITARY_CONFINEMENT:
            case FLED_TILE_CHAPEL:
            case FLED_TILE_DOUBLE_WASHROOM:
            case FLED_TILE_DOUBLE_CORRIDOR:
            case FLED_TILE_START:
            case FLED_TILE_DOUBLE_MESSHALL:
                return true;
            default:
                return false;
        }
    }

    public function isInSameRoom($pos1, $pos2)
    {
        if ($pos1[0] == $pos2[0] && $pos1[1] == $pos2[1])
            return true;
        $tile1 = $this->getTileAt($pos1[0], $pos1[1]);
        $tile2 = $this->getTileAt($pos2[0], $pos2[1]);
        return $tile1 === $tile2;
    }

    public function getTileAt($x, $y)
    {
        if ($x < 0 || $x >= FLED_WIDTH || $y < 0 || $y >= FLED_HEIGHT)
            return 0;
        $index = $this->makeIndex($x, $y);
        return $this->data->board[$index];
    }
    
    public function getRoomAt($x, $y)
    {
        $tileIdAndOrientation = $this->getTileAt($x, $y);
        $tileId = $tileIdAndOrientation % 100;
        $tile = FledLogic::$FledTiles[$tileId];
        $headPos = $this->getTileHeadPos($x, $y);
        $isHead = $headPos[0] == $x && $headPos[1] == $y;
        return $tile['rooms'][$isHead ? 0 : 1];
    }
    
    public function getRoomTypeAt($x, $y)
    {
        $room = $this->getRoomAt($x, $y);
        return $room['type'];
    }
    
    public function setTileAt($tileId, $x, $y, $orientation)
    {
        if ($x < 0 || $x >= FLED_WIDTH || $y < 0 || $y >= FLED_HEIGHT)
            throw new Exception('invalid location');

        // Place the tile head
        $index = $this->makeIndex($x, $y);
        $this->data->board[$index] = $tileId + $orientation;

        // Place the tile tail
        $x2 = $x;
        $y2 = $y;
        if ($orientation == FLED_ORIENTATION_NORTH_SOUTH) $y2++;
        if ($orientation == FLED_ORIENTATION_SOUTH_NORTH) $y2--;
        if ($orientation == FLED_ORIENTATION_EAST_WEST) $x2--;
        if ($orientation == FLED_ORIENTATION_WEST_EAST) $x2++;
        $index2 = $this->makeIndex($x2, $y2);
        $this->data->board[$index2] = $tileId + $orientation;
    }

    public static function isMoonTile($tileId)
    {
        return $tileId == FLED_TILE_CHAPEL
            || $tileId == FLED_TILE_DOUBLE_WASHROOM
            || $tileId == FLED_TILE_START
            || $tileId == FLED_TILE_DOUBLE_MESSHALL
        ;
    }

    //
    // Given a board and a cell position, find the
    // position of the head of the tile at (x, y).
    // Precondition: (x, y) is not empty
    //
    private function getTileHeadPos($x, $y)
    {
        if ($x < 0 || $y < 0 || $x >= 14 || $y >= 13)
            throw new Exception('Invalid location');

        // The borders of the playable surface are all forest locations
        // and the tiles with forest all have forest at the head of the
        // tile. Therefore, any coordinate laying on the border must be
        // the tile head.
        if ($x == 0 || $y == 0 || $x == 13 || $y == 12)
            return [ $x, $y ];

        $index = FledLogic::makeIndex($x, $y);
        $tileIdAndOrientation = $this->data->board[$index];
        if (!$tileIdAndOrientation)
            throw new Exception('precondition failed: empty cell');
        $tileId = $tileIdAndOrientation % 100;
        $orientation = $tileIdAndOrientation - $tileId;

        // Based on tile orientation, look at an adjacent cell
        // to determine if it is actually the head of the tile
        $xMod = $x;
        $yMod = $y;
        switch ($orientation)
        {
            case FLED_ORIENTATION_NORTH_SOUTH: $yMod = $y - 1; break;
            case FLED_ORIENTATION_WEST_EAST:   $xMod = $x - 1; break;
            case FLED_ORIENTATION_SOUTH_NORTH: $yMod = $y + 1; break;
            case FLED_ORIENTATION_EAST_WEST:   $xMod = $x + 1; break;
            default: throw new Exception('invalid orientation');
        }

        return $this->getTileAt($xMod, $yMod) % 100 === $tileId
            ? [ $xMod, $yMod ]
            : [ $x, $y ];
    }
    
    public function getNextPlayerId()
    {
        return $this->data->order[$this->data->nextPlayer];
    }

    public function isGameSetup()
    {
        return !!$this->data->setup;
    }

    public function isTileOnBoard($tileId)
    {
        return array_search($tileId, array_map(fn($tileId) => $tileId % 100, $this->data->board)) !== false;
    }

    public function getTilePosition($tileId)
    {
        // Precondition is that the tile is known to be on the board
        $index = array_search($tileId, array_map(fn($tileId) => $tileId % 100, $this->data->board));
        $pos = FledLogic::parseIndex($index);
        return $this->getTileHeadPos($pos[0], $pos[1]);
    }

    public function getStartingBunkTileId($playerId)
    {
        switch ($this->data->players->$playerId->color)
        {
            case FLED_PLAYER_YELLOW: return FLED_TILE_YELLOW_BUNK;
            case FLED_PLAYER_BLUE:   return FLED_TILE_BLUE_BUNK;
            case FLED_PLAYER_ORANGE: return FLED_TILE_ORANGE_BUNK;
            case FLED_PLAYER_GREEN:  return FLED_TILE_GREEN_BUNK;
        }
        throw new Exception('Invalid color');
    }

    public function isTileInPlayersInventory($tileId, $playerId)
    {
        return array_search($tileId, $this->data->players->$playerId->inventory) !== false;
    }

    public function isTileInPlayersHand($tileId, $playerId)
    {
        if (array_search($tileId, $this->data->players->$playerId->hand) === false)
            throw new Exception(json_encode([ 'tile' => $tileId, 'player' => $this->data->players->$playerId->hand ])); // KILL
        return array_search($tileId, $this->data->players->$playerId->hand) !== false;
    }

    public function removeTileFromHand($tileId, $playerId)
    {
        if (!$this->isTileInPlayersHand($tileId, $playerId))
            throw new Exception('Tile not in player hand');

        $hand = $this->data->players->$playerId->hand;
        $this->data->players->$playerId->hand = array_values(array_diff($hand, [ $tileId ]));

        // Return value is only used in test code
        return $this->data->players->$playerId->hand;
    }

    public function removeRandomTileFromHand($playerId)
    {
        $hand = $this->data->players->$playerId->hand;
        $index = rand(0, count($hand) - 1);
        $tileId = $hand[$index];
        $this->removeTileFromHand($tileId, $playerId);
        return $tileId;
    }

    public function removeTileFromInventory($tileId, $playerId)
    {
        if (!$this->isTileInPlayersInventory($tileId, $playerId))
            throw new Exception('Tile not in player inventory');

        $inventory = $this->data->players->$playerId->inventory;
        $this->data->players->$playerId->inventory = array_values(array_diff($inventory, [ $tileId ]));

        $this->addTileToDiscardPile($tileId);

        // Return value is only used in test code
        return $this->data->players->$playerId->inventory;
    }
    
    public function addTileToDiscardPile($tileId)
    {
        $this->data->discardPile[] = $tileId;
    }

    public function discardTile($tileId)
    {
        $playerId = $this->getNextPlayerId();

        // Move tile from player's hand to the Governor's inventory
        $this->removeTileFromHand($tileId, $playerId);
        $this->data->governorInventory[] = $tileId;

        $this->eventHandlers->onUnableToAddTile($playerId);
        $this->eventHandlers->onTileDiscarded($playerId, $tileId, true);

        // Technically, the player hasn't added a tile...
        // but we're just using this flag to indicate that
        // the addTile phase has been completed.
        $this->data->players->$playerId->addedTile = true;
        $this->data->moves++;
    }

    public function placeTile($tileId, $x, $y, $orientation)
    {
        if ($x < 0 || $x >= 14 || $y < 0 || $y >= 13)
            throw new Exception('Invalid location');

        // Allow the starter tile for the current player if
        // it hasn't been played yet. All else are invalid.
        $playerId = $this->getNextPlayerId();
        $bunkTileId = $this->getStartingBunkTileId($playerId);
        $isBunkTile = $tileId === $bunkTileId && !$this->isTileOnBoard($tileId);

        // Validate that the player has the tile in hand (or is the starter tile)
        if (!$isBunkTile && !$this->isTileInPlayersHand($tileId, $playerId))
            throw new Exception('Tile not in player hand');

        if (!$this->isLegalTilePlacement($tileId, $x, $y, $orientation, $isBunkTile))
            throw new Exception('Illegal tile placement');

        // Remove the tile from hand (except starter bunk tile, which doesn't come from hand)
        if (!$isBunkTile)
            $this->removeTileFromHand($tileId, $playerId);

        $this->setTileAt($tileId, $x, $y, $orientation);

        // Set the player's meeple position if this is the starter bunk tile
        // And advance to the next player.
        if ($isBunkTile)
        {
            $this->eventHandlers->onTilePlaced($playerId, $tileId, $x, $y, $orientation);

            // Check to see if all players have now placed their bunk tiles
            $isSetupComplete = true;
            foreach ($this->data->players as $pid => $player)
            {
                $tileId = $this->getStartingBunkTileId($pid);
                if (!$this->isTileOnBoard($tileId))
                    $isSetupComplete = false;
            }

            $this->data->players->$playerId->pos = $this->getTileHeadPos($x, $y);
            if ($isSetupComplete)
            {
                $this->data->setup = 1;
                $this->eventHandlers->onSetupComplete($this->getPlayerPositions());
            }
            return;
        }

        // Otherwise, increase the move counter because this was a real move
        $this->data->moves++;

        $this->data->players->$playerId->placedTile = true;
        $this->eventHandlers->onTilePlaced($playerId, $tileId, $x, $y, $orientation);
        
        // Check for Moon symbol on the tile and add a new NPC
        // Also, the open window changes when a moon tile is played.
        $moon = FledLogic::isMoonTile($tileId);
        if ($tileId === FLED_TILE_CHAPEL)
        {
            $this->data->npcs->chaplain = (object)[
                'pos' => [ $x, $y ],
            ];
            $this->eventHandlers->onNpcAdded('chaplain', $this->data->npcs->chaplain);
            $this->data->openWindow--; // TODO: emit open window changed?
        }
        else if ($moon)
        {
            $nextWarder = array_key_exists('warder2', (array)$this->data->npcs) ? 'warder3' : 'warder2';
            $this->data->npcs->$nextWarder = (object)[
                'pos' => [ $x, $y ],
            ];
            $this->eventHandlers->onNpcAdded($nextWarder, $this->data->npcs->$nextWarder);
            $this->data->openWindow--; // TODO: emit open window changed?
        }
    }

    public function discardTileToMove($tileId, $x, $y)
    {
        $playerId = $this->getNextPlayerId();

        // Verify that the player holds this tile in hand
        if (!$this->isTileInPlayersHand($tileId, $playerId))
            throw new Exception('Invalid tile');
        
        // Verify the player has actions remaining
        $actionsPlayed = $this->data->players->$playerId->actionsPlayed;
        if ($actionsPlayed >= 2)
            throw new Exception('Too many actions');

        // Remove the card from the player's hand
        // and put it in the discard pile
        $this->removeTileFromHand($tileId, $playerId);
        $this->addTileToDiscardPile($tileId);

        // Consider the head room if the target is a double tile
        $targetTileId = $this->getTileAt($x, $y);
        if (FledLogic::isDoubleTile($targetTileId))
        {
            $headPos = $this->getTileHeadPos($x, $y);
            $x = $headPos[0];
            $y = $headPos[1];
        }

        // Validate that the move is legal
        $isLegal = false;
        $legalMoves = $this->getLegalMovementMoves($tileId);
        foreach ($legalMoves as $move)
        {
            $path = $move['path'];
            $tool = $move['type']; // The effective tool, not a shamrock/whistle
            $destination = $path[count($path) - 1];
            if ($destination[0] === $x && $destination[1] === $y)
            {
                $isLegal = true;
                break;
            }
        }
        if (!$isLegal)
            throw new Exception('Illegal move');

        $this->data->players->$playerId->pos = [ $x, $y ];

        $this->data->players->$playerId->actionsPlayed = ++$actionsPlayed;
        $this->data->moves++;

        $this->eventHandlers->onTilePlayedToMove($playerId, $tileId, $x, $y, $tool, $path);
        $this->eventHandlers->onActionComplete($actionsPlayed);
    }

    public function discardTilesToEscape($discards)
    {
        $playerId = $this->getNextPlayerId();
        $player = $this->data->players->$playerId;
        $x = $player->pos[0];
        $y = $player->pos[1];

        // Everybody else gets one more turn
        $finalTurns = $this->data->finalTurns ?? count($this->data->order);
        $this->data->finalTurns = $finalTurns - 1;

        // Validate that the player can escape
        if (!$this->canPlayerEscape($playerId))
            throw new Exception('Illegal escape');

        $tileId = $this->getTileAt($x, $y) % 100;
        $tile = FledLogic::$FledTiles[$tileId];
        $toolNeeded = $tile['rooms'][1]['escape'];
        $isNight = $this->data->whistlePos == $this->data->openWindow;
        $toolsRemaining = $isNight ? 1 : 2;

        foreach ($discards as $tileId)
        {
            $item = $this->getTileItem($tileId);
            if ($item == FLED_SHAMROCK)
                $toolsRemaining--;
            else if ($item != $toolNeeded)
                continue;
            else if ($this->getTileColor($tileId) == FLED_COLOR_GOLD)
                $toolsRemaining -= 2;
            else
                $toolsRemaining--;
        }
    
        if ($toolsRemaining > 0)
            throw new Exception('Not enough tools to escape');

        // Remove the discard tiles from the player's inventory
        // (this also validates that the tiles exist there)
        foreach ($discards as $tileId)
            $this->removeTileFromInventory($tileId, $playerId);

        $score = $this->getPlayerScore($playerId);
    
        $this->eventHandlers->onInventoryDiscarded($playerId, $discards, $score);

        $this->data->players->$playerId->escaped = true;

        $score = $this->getPlayerScore($playerId);
        $auxScore = $this->getPlayerAuxScore($playerId);
        $this->eventHandlers->onPlayerEscaped($playerId, $score, $auxScore);
        $this->eventHandlers->onEndTurn();
    }

    public function isSafeForRollCall($x, $y)
    {
        $roomType = $this->getRoomTypeAt($x, $y);
        $safeRoomTypes = $this->getSafeRollCallRooms();
        return array_search($roomType, $safeRoomTypes) !== false;
    }

    public function getSafeRollCallRooms()
    {
        $rollCallTileIndex = $this->data->rollCall[$this->data->whistlePos];
        return array_keys(FledLogic::$RollCallTiles[$rollCallTileIndex]);
    }

    public function getPlayerIdByColorName($colorName)
    {
        $color = array_search($colorName, FledLogic::$ColorNames, true);
        foreach ($this->data->players as $playerId => $player)
        {
            if ($player->color == $color)
                return $playerId;
        }
        return null;
    }

    public function discardTileToMoveWarder($tileId, $x, $y, $npcName, $targetPlayerId)
    {
        $toBunk = false;
        $toSolitary = false;
        $targetIsSafe = false;
        $shackleTile = null;
        $unshackleTile = null;
        $playerId = $this->getNextPlayerId();

        // Make sure that (x, y) refer to the head room of a double tile
        $targetTileId = $this->getTileAt($x, $y);
        if ($this->isDoubleTile($targetTileId))
        {
            $headPos = $this->getTileHeadPos($x, $y);
            $x = $headPos[0];
            $y = $headPos[1];
        }
        
        // Verify that target player is at (x, y)
        $targetPlayer = null;
        if ($targetPlayerId)
        {
            $targetPlayer = $this->data->players->$targetPlayerId;
            if ($targetPlayer->pos[0] != $x || $targetPlayer->pos[1] != $y)
                throw new Exception('Target player is not there');
        }

        if (!isset($this->data->npcs->$npcName))
            throw new Exception('No NPC named ' . $npcName);

        // Verify that the player holds this tile in hand
        if (!$this->isTileInPlayersHand($tileId, $playerId))
            throw new Exception('Invalid tile');

        // Verify the player has actions remaining
        $actionsPlayed = $this->data->players->$playerId->actionsPlayed;
        if ($actionsPlayed >= 2)
            throw new Exception('Too many actions');

        // Remove the card from the player's hand
        // and put it in the discard pile
        $this->removeTileFromHand($tileId, $playerId);
        $this->addTileToDiscardPile($tileId);

        // TODO: validate that the move is legal! For now we just assume it's legal

        // TODO: calculate the actual path through each cell
        $path = [
            $this->data->npcs->$npcName->pos,
            [ $x, $y ],
        ];

        // Move the warder / chaplain
        $this->data->npcs->$npcName->pos = [ $x, $y ];

        if ($targetPlayer)
        {
            if ($npcName == 'chaplain')
            {
                // Free the player from their shackles (if they were shackled) -- unless they're in solitary
                if (!$this->data->players->$targetPlayerId->inSolitary) {
                    $unshackleTile = $targetPlayer->shackleTile;
                    $this->data->players->$targetPlayerId->shackleTile = 0;
                }
            }
            else if (str_contains($npcName, 'warder'))
            {
                // Shackle a player if not in a safe room
                $targetIsSafe = $this->isSafeForRollCall($x, $y);
                if (!$targetIsSafe)
                {
                    // But if player is already shackled...
                    if ($this->data->players->$targetPlayerId->shackleTile)
                    {
                        // When Solitary Confinement is in play, send the player there
                        if ($this->isTileOnBoard(FLED_TILE_SOLITARY_CONFINEMENT))
                        {
                            $this->data->players->$targetPlayerId->inSolitary = true;
                            $this->data->players->$targetPlayerId->pos = $this->getTilePosition(FLED_TILE_SOLITARY_CONFINEMENT);
                            $toSolitary = true;
                        }
                        else
                        {
                            // Otherwise, send player back to his bunk and remove shackles
                            $bunkTileId = $this->getStartingBunkTileId($targetPlayerId);
                            $this->data->players->$targetPlayerId->pos = $this->getTilePosition($bunkTileId);
                            $unshackleTile = $this->data->players->$targetPlayerId->shackleTile;
                            $this->data->players->$targetPlayerId->shackleTile = 0;
                            $this->data->governorInventory[] = $unshackleTile;
                            $toBunk = true;
                        }
                    }
                    else
                    {
                        // Shackle the player with a random tile from their hand
                        $shackleTile = $this->removeRandomTileFromHand($targetPlayerId);
                        $this->data->players->$targetPlayerId->shackleTile = $shackleTile;
                    }
                }
            }
        }

        // Move whistle left by one spot (there are five spots)
        // Also, note that the whistle moves *after* the warder move is resolved
        $this->data->whistlePos = ($this->data->whistlePos + 4) % 5;

        $this->data->players->$playerId->actionsPlayed = ++$actionsPlayed;
        $this->data->moves++;

        $this->eventHandlers->onTilePlayedToMoveWarder($playerId, $targetPlayerId, $tileId, $x, $y, $npcName, $path);

        if ($toBunk)
            $this->eventHandlers->onPlayerSentToBunk($targetPlayerId);

        if ($shackleTile)
            $this->eventHandlers->onPlayerShackled($playerId, $targetPlayerId, $shackleTile, $this->getPlayerScore($targetPlayerId));

        if ($unshackleTile)
            $this->eventHandlers->onPlayerUnshackled($targetPlayerId, $unshackleTile, $this->getPlayerScore($targetPlayerId));

        if ($toSolitary)
            $this->eventHandlers->onPlayerSentToSolitary($targetPlayerId);

        if ($targetIsSafe)
        {
            $roomType = $this->getRoomTypeAt($x, $y);
            $this->eventHandlers->onPlayerIsSafe($targetPlayerId, $roomType);
        }

        $this->eventHandlers->onWhistleMoved($playerId, $this->getSafeRollCallRooms());
        $this->eventHandlers->onActionComplete($actionsPlayed);
    }

    public function releasePlayerFromSolitary($playerId)
    {
        if (!$this->data->players->$playerId->shackleTile)
            throw new Exception('player ' . $playerId . ' not shackled');

        $bunkTileId = $this->getStartingBunkTileId($playerId);
        $this->data->players->$playerId->pos = $this->getTilePosition($bunkTileId);

        $unshackleTile = $this->data->players->$playerId->shackleTile;
        $this->data->players->$playerId->shackleTile = 0;
        $this->data->players->$playerId->inSolitary = false;

        $this->data->governorInventory[] = $unshackleTile;

        $this->eventHandlers->onMissedTurn($playerId);
        $this->eventHandlers->onPlayerSentToBunk($playerId);
        $this->eventHandlers->onPlayerUnshackled($playerId, $unshackleTile, $this->getPlayerScore($playerId));
    }

    public function addTileToInventory($tileId, $discards)
    {
        $playerId = $this->getNextPlayerId();

        // Verify that the player holds this tile and can legally acquire it
        $eligibleTileIds = $this->getHandTilesEligibleForInventory();
        if (array_search($tileId, $eligibleTileIds) === false)
            throw new Exception('Ineligible tile');

        // TODO: verify that the discards meet the requirements to add the tile

        // Verify the player has actions remaining
        $actionsPlayed = $this->data->players->$playerId->actionsPlayed;
        if ($actionsPlayed >= 2)
            throw new Exception('Too many actions');

        // Remove the discards from the player's inventory (also validates that they exist there)
        foreach ($discards as $discardTileId)
            $this->removeTileFromInventory($discardTileId, $playerId);

        $score = $this->getPlayerScore($playerId);
        $this->eventHandlers->onInventoryDiscarded($playerId, $discards, $score);

        // Remove the card from the player's hand
        // And add it to the player's inventory
        $this->removeTileFromHand($tileId, $playerId);
        $this->data->players->$playerId->inventory[] = $tileId;

        $this->data->players->$playerId->actionsPlayed = ++$actionsPlayed;
        $this->data->moves++;

        $score = $this->getPlayerScore($playerId);
        $auxScore = $this->getPlayerAuxScore($playerId);
        $this->eventHandlers->onTileAddedToInventory($playerId, $tileId, $discards, $score, $auxScore);

        $this->eventHandlers->onActionComplete($actionsPlayed);
    }

    public function surrenderTile($tileId)
    {
        $playerId = $this->getNextPlayerId();

        // Verify that the player holds this tile in hand
        $hand = $this->data->players->$playerId->hand;
        if (array_search($tileId, $hand) === false)
            throw new Exception('Tile not held');

        // Verify the player has actions remaining
        $actionsPlayed = $this->data->players->$playerId->actionsPlayed;
        if ($actionsPlayed >= 2)
            throw new Exception('Too many actions');

        // Remove the card from the player's hand
        // And add it to the Governor's inventory
        $this->removeTileFromHand($tileId, $playerId);
        $this->data->governorInventory[] = $tileId;

        $this->data->players->$playerId->actionsPlayed = ++$actionsPlayed;
        $this->data->moves++;

        $this->eventHandlers->onTileSurrendered($playerId, $tileId);
        $this->eventHandlers->onActionComplete($actionsPlayed);
    }

    public function drawTiles($governorTileId)
    {
        $playerId = $this->getNextPlayerId();

        // Verify the player has played their actions
        $actionsPlayed = $this->data->players->$playerId->actionsPlayed;
        if ($actionsPlayed < 2)
            throw new Exception('Not ready');

        // No more room in the hand
        if (count($this->data->players->$playerId->hand) >= 5)
            throw new Exception('Hand is full');

        if ($governorTileId)
        {
            // Verify that the Governor holds this tile in inventory
            if (array_search($governorTileId, $this->data->governorInventory) === false)
                throw new Exception('Tile missing');

            // Remove the card from the Governor's inventory
            // and add it to the player's hand
            $this->data->governorInventory = array_values(array_diff($this->data->governorInventory, [ $governorTileId ]));
            $this->data->players->$playerId->hand[] = $governorTileId;

            $this->eventHandlers->onTookFromGovernor($playerId, $governorTileId);
        }

        $wasShuffled = false;
        $drawnBeforeShuffle = [];
        $drawnAfterShuffle = [];
        while (count($this->data->players->$playerId->hand) < 5)
        {
            // Reshuffle the discard pile into a new draw pile if the draw pile is empty
            if (!count($this->data->drawPile))
            {
                if (!count($this->data->discardPile))
                {
                    $drawnBeforeShuffle = [];
                    $drawnAfterShuffle = [];
                    $this->data->hardLabor = 1;
                    break;
                }

                $this->data->drawPile = $this->data->discardPile;
                $this->data->discardPile = [];
                shuffle($this->data->drawPile);
                $wasShuffled = true;
            }

            $tileId = array_shift($this->data->drawPile);
            $this->data->players->$playerId->hand[] = $tileId;
            if ($wasShuffled)
                $drawnAfterShuffle[] = $tileId;
            else
                $drawnBeforeShuffle[] = $tileId;
        }
        
        $this->data->moves++;

        $drawPileCount = count($this->data->drawPile);
        $this->eventHandlers->onTilesDrawn($playerId, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileCount);
        $this->eventHandlers->onEndTurn();
    }

    public function advanceNextPlayer()
    {
        do
        {
            $this->data->nextPlayer = ($this->data->nextPlayer + 1) % count($this->data->order);

            $playerId = $this->getNextPlayerId();
            $this->data->players->$playerId->placedTile = false;
            $this->data->players->$playerId->actionsPlayed = 0;

            // Determine if the next player should lose a turn from being in Solitary Confinement
            if (!$this->isPlayerInSolitary($playerId))
                break;
    
            $this->releasePlayerFromSolitary($playerId);
        }
        while (true);

        // TODO: check for if we're in end game and this was last turn for this player. also reduce the counter

        return $this->getNextPlayerId();
    }

    public function isLegalTilePlacement($tileId, $x, $y, $orientation, $isStarterBunk = false)
    {
        // Can't place the head room outside the board
        if ($x < 0 || $x >= FLED_WIDTH || $y < 0 || $y >= FLED_HEIGHT)
            return false;

        // Bail out if the head cell is occupied
        if ($this->getTileAt($x, $y))
            return false;

        // Calculate where the tail room will be placed
        $x2 = $x;
        $y2 = $y;
        if ($orientation == FLED_ORIENTATION_NORTH_SOUTH) $y2++;
        if ($orientation == FLED_ORIENTATION_SOUTH_NORTH) $y2--;
        if ($orientation == FLED_ORIENTATION_EAST_WEST) $x2--;
        if ($orientation == FLED_ORIENTATION_WEST_EAST) $x2++;

        // Can't place the tail room outside the board
        // (but also can't place the tail cell on the border
        // due to the forest placement rule and the fact
        // that all forest rooms are in the head position)
        if ($x2 <= 0 || $x2 >= FLED_WIDTH - 1 || $y2 <= 0 || $y2 >= FLED_HEIGHT - 1)
            return false;

        // Bail out if the tail cell is occupied
        if ($this->getTileAt($x2, $y2))
            return false;

        // The starter bunk tile must have the corridor room adjacent to the yard.
        if ($isStarterBunk)
        {
            // ($x2, $y2) represents the corridor room for the bunk tiles
            return $this->isAdjacentToRoomType($x2, $y2, FLED_ROOM_YARD);
        }

        $tile = FledLogic::$FledTiles[$tileId];
        $headRoom = $tile['rooms'][0];
        $tailRoom = $tile['rooms'][1];

        // Either the head or the tail must be placed adjacent to a matching room type 
        if (!$this->isAdjacentToRoomType($x, $y, $headRoom['type']) && !$this->isAdjacentToRoomType($x2, $y2, $tailRoom['type']))
            return false; 

        // Forest and anti-forest rules:
        // Only allowed to place a forest room along the outside border
        // and not allowed to place any non-forest along the border.
        // Note: all forest tiles have the forest in the head room.
        $isHeadOnBorder = $x == 0 || $x == FLED_WIDTH - 1 || $y == 0 || $y == FLED_HEIGHT - 1;
        if ($headRoom['type'] == FLED_ROOM_FOREST && !$isHeadOnBorder)
            return false;
        else if ($headRoom['type'] != FLED_ROOM_FOREST && $isHeadOnBorder)
            return false;
            
        // Verify that there are no door-to-window connections for the head tile
        if ($headRoom['type'] != FLED_ROOM_FOREST)
        {
            $egresses = $this->getOrientedEgresses($headRoom, $orientation);
            $adjEgress = [
                $this->getEgressType($x, $y - 1, FLED_DIRECTION_SOUTH),
                $this->getEgressType($x + 1, $y, FLED_DIRECTION_WEST),
                $this->getEgressType($x, $y + 1, FLED_DIRECTION_NORTH),
                $this->getEgressType($x - 1, $y, FLED_DIRECTION_EAST),
            ];
            for ($i = 0; $i < 4; $i++)
            {
                if ($egresses[$i] === FLED_EGRESS_DOOR && $adjEgress[$i] === FLED_EGRESS_WINDOW) return false;
                if ($egresses[$i] === FLED_EGRESS_WINDOW && $adjEgress[$i] === FLED_EGRESS_DOOR) return false;
            }
        }

        // Verify that there are no door-to-window connections for the tail tile
        $egresses = $this->getOrientedEgresses($tailRoom, $orientation);
        $adjEgress = [
            $this->getEgressType($x2, $y2 - 1, FLED_DIRECTION_SOUTH),
            $this->getEgressType($x2 + 1, $y2, FLED_DIRECTION_WEST),
            $this->getEgressType($x2, $y2 + 1, FLED_DIRECTION_NORTH),
            $this->getEgressType($x2 - 1, $y2, FLED_DIRECTION_EAST),
        ];

        for ($i = 0; $i < 4; $i++)
        {
            if ($egresses[$i] === FLED_EGRESS_DOOR && $adjEgress[$i] === FLED_EGRESS_WINDOW) return false;
            if ($egresses[$i] === FLED_EGRESS_WINDOW && $adjEgress[$i] === FLED_EGRESS_DOOR) return false;
        }
        
        return true;
    }

    public function isAdjacentToRoomType($x, $y, $roomType)
    {
        $directions = [ [ 1, 0 ], [ -1, 0 ], [ 0, 1 ], [ 0, -1 ] ];
        foreach ($directions as $dir)
        {
            $adjTileId = $this->getTileAt($x + $dir[0], $y + $dir[1]);
            if (!$adjTileId) continue;
            $adjHeadPos = $this->getTileHeadPos($x + $dir[0], $y + $dir[1]);
            $adjRoomIndex = ($adjHeadPos[0] === $x + $dir[0] && $adjHeadPos[1] === $y + $dir[1]) ? 0 : 1;
            $adjTile = FledLogic::$FledTiles[$adjTileId % 100];
            $adjRoom = $adjTile['rooms'][$adjRoomIndex];
            if ($adjRoom['type'] === $roomType)
                return true;
        }
        return false;
    }

    //
    // Given cell coordinates and a direction, return
    // the egress type for the room at that position.
    //
    public function getEgressType($x, $y, $dir)
    {
        $tileIdAndOrientation = $this->getTileAt($x, $y);
        if (!$tileIdAndOrientation)
            return FLED_EGRESS_NONE;

        $tileId = $tileIdAndOrientation % 100;
        $orientation = $tileIdAndOrientation - $tileId;

        $headPos = $this->getTileHeadPos($x, $y);
        $isHead = $headPos[0] == $x && $headPos[1] == $y;
        $roomIndex = $isHead ? 0 : 1;
        $room = FledLogic::$FledTiles[$tileId]['rooms'][$roomIndex];
        $egresses = $this->getOrientedEgresses($room, $orientation);
        return $egresses[$dir];
    }

    //
    // Given a room object and an orientation, rotate its
    // egresses so that they match the physical layout
    //
    public function getOrientedEgresses($room, $orientation)
    {
        // Rotate the logical room egresses to match the physical layout
        $egresses = $room['egress'];
        for ($o = 100; $o <= $orientation; $o += 100)
        {
            $e = array_shift($egresses);
            array_push($egresses, $e);
        }
        return $egresses;
    }

    public function isPlayer($playerId)
    {
        return array_key_exists($playerId, $this->data->players);
    }

    public function getGameProgression()
    {
        // Game immediately ends when Hard Labor is called
        if ($this->wasHardLaborCalled())
            return 100;

        // If a player has already escaped, the game progression
        // will be 100 - # of turns remaining (even when not
        // enough tiles to draw)
        $finalTurns = $this->data->finalTurns;
        if ($finalTurns !== null)
            return 100 - $finalTurns;

        // Look at the total number of tiles that may be drawn
        // (including from discard pile and one from the Governor
        // Inventory per player in the game) because the game
        // ends when a player can no longer draw five tiles

        $playerCount = count($this->data->order);
        $drawCount = count($this->data->drawPile);
        $discardCount = count($this->data->discardPile);
        $govCount = min($playerCount, count($this->data->governorInventory));

        // TODO: account for expansions
        // There are 51 non-starter tiles, and 6 extra tiles for 3+ player games
        $totalCount = 51 + ($playerCount >= 3 ? 6 : 0);
        // TODO: should the tiles in hand be subtracted too?

        $percent = 100 * ($totalCount - ($drawCount + $discardCount + $govCount)) / $totalCount;
        return floor($percent);
    }

    public function getOption($optionName)
    {
        return $this->data->options->$optionName;
    }

    public function getHandTilesEligibleForMovement()
    {
        $eligibleTileIds = [];

        $playerId = $this->getNextPlayerId();
        $player = $this->data->players->$playerId;

        $toolsNeeded = $this->calculateToolsNeededToEscapeRoom($player->pos[0], $player->pos[1]);

        foreach ($player->hand as $tileId)
        {
            $tile = FledLogic::$FledTiles[$tileId];
            $item = $tile['contains'];
            if ($item === FLED_WHISTLE || $item === FLED_SHAMROCK)
                $eligibleTileIds[] = $tileId;
            else if (array_search($item, $toolsNeeded) !== false && !$player->inSolitary)
            {
                if (count($this->getLegalMovementMoves($tileId)))
                    $eligibleTileIds[] = $tileId;
            }
        }

        return $eligibleTileIds;
    }

    public function calculateToolsNeededToEscapeRoom($x, $y, $distance = 1)
    {
        // We use recursion to handle "double" tiles where the search
        // goes from one half of the double room to the other half.
        // (This is necessary because none of the double tiles have
        // symmetric egresses among their two halves).
        if ($distance < 0)
            return [];

        $tileIdAndOrientation = $this->getTileAt($x, $y);
        $tileId = $tileIdAndOrientation % 100;
        $orientation = $tileIdAndOrientation - $tileId;
        
        $currentRoom = $this->getRoomAt($x, $y);
        $currentRoomType = $currentRoom['type'];
        $egresses = $this->getOrientedEgresses($currentRoom, $orientation);

        $toolsNeeded = [];

        $hasTunnel =
            $currentRoomType === FLED_ROOM_YARD ||
            $currentRoomType === FLED_ROOM_COURTYARD ||
            $currentRoomType === FLED_ROOM_BUNK;
        if ($hasTunnel)
            $toolsNeeded[] = FLED_TOOL_SPOON;
        
        $directions = [
            FLED_DIRECTION_NORTH => [ 0, -1 ],
            FLED_DIRECTION_EAST => [ 1, 0 ],
            FLED_DIRECTION_SOUTH => [ 0, 1 ],
            FLED_DIRECTION_WEST => [ -1, 0 ],
        ];
        foreach ($directions as $dir => $delta)
        {
            $xx = $x + $delta[0];
            $yy = $y + $delta[1];
            if (!$this->getTileAt($xx, $yy)) continue;

            // Find the egress of the adjacent tile in the opposite direction
            // (e.g. our East wall is the same as the adjacent cell West wall)
            $adjEgress = $this->getEgressType($xx, $yy, ($dir + 2) % 4);

            if ($adjEgress === FLED_EGRESS_DOOR || $egresses[$dir] == FLED_EGRESS_DOOR)
                $toolsNeeded[] = FLED_TOOL_KEY;

            else if ($adjEgress === FLED_EGRESS_WINDOW || $egresses[$dir] === FLED_EGRESS_WINDOW)
                $toolsNeeded[] = FLED_TOOL_FILE;

            else if ($adjEgress === FLED_EGRESS_ARCHWAY && $egresses[$dir] === FLED_EGRESS_ARCHWAY)
                $toolsNeeded[] = FLED_TOOL_BOOT;

            // Evaluate the next room as well (because double tiles do not have symmetric egresses)
            else if ($adjEgress === FLED_EGRESS_OPEN && $egresses[$dir] === FLED_EGRESS_OPEN)
                $toolsNeeded = array_merge($toolsNeeded, $this->calculateToolsNeededToEscapeRoom($xx, $yy, $distance - 1));
        }
        return array_unique($toolsNeeded);
    }

    public function getLegalMovementMoves($tileId)
    {
        $playerId = $this->getNextPlayerId();
        $player = $this->data->players->$playerId;

        $tile = FledLogic::$FledTiles[$tileId];
        $distance = 0;
        switch ($tile['color'])
        {
            case FLED_COLOR_PURPLE: $distance = 1; break;
            case FLED_COLOR_GREEN:  $distance = 1; break;
            case FLED_COLOR_GOLD:   $distance = 2; break;
            default:
                return [];
        }

        $x = $player->pos[0];
        $y = $player->pos[1];
        $room = $this->getRoomAt($x, $y);
        $item = $tile['contains'];

        // Cannot move at all if the player is in the Hole
        if ($this->isPlayerInSolitary($playerId))
            return [];

        $paths = [];
        if (FledLogic::roomHasTunnel($room) && ($item === FLED_TOOL_SPOON || $item === FLED_SHAMROCK)) {
            // Each spoon lets player move 3 rooms
            $paths = array_merge($paths, $this->traverseUnderGround($x, $y, $distance * 3));
        }
        if ($item !== FLED_TOOL_SPOON) {
            $paths = array_merge($paths, $this->traverseAboveGround($item, $x, $y, 1, $distance));
        }
        return $paths;
    }

    public function traverseAboveGround($item, $xStart, $yStart, $minDistance, $maxDistance) {
        $bestPathByIndex = [];
        $traversals = [];
        $visited = [];

        // Start in the current room
        $traversals[] = [
            'path' => [
                [ $xStart, $yStart ],
            ],
            'distance' => 0,
            'type' => FLED_EMPTY,
        ];

        $directions = [
            FLED_DIRECTION_NORTH => [  0, -1 ],
            FLED_DIRECTION_EAST =>  [  1,  0 ],
            FLED_DIRECTION_SOUTH => [  0,  1 ],
            FLED_DIRECTION_WEST =>  [ -1,  0 ],
        ];

        while (count($traversals))
        {
            $traversal = array_shift($traversals);
            $distance = $traversal['distance'];
            $path = $traversal['path'];

            $x = $path[count($path) - 1][0];
            $y = $path[count($path) - 1][1];
            $index = FledLogic::makeIndex($x, $y);
            if (array_key_exists($index, $visited) && $visited[$index] <= $distance) continue;
            $visited[$index] = $distance;

            if ($distance >= $minDistance) {
                $bestPathByIndex[$index] = [
                    'path' => $path,
                    'type' => $traversal['type'],
                ];
            }

            foreach ($directions as $dir => $delta)
            {
                $dx = $delta[0];
                $dy = $delta[1];
                $thisRoomEgress = $this->getEgressType($x, $y, $dir);
                $adjRoomEgress = $this->getEgressType($x + $dx, $y + $dy, ($dir + 2) % 4);
                $traversalCost = $this->getTraversalCost($thisRoomEgress, $adjRoomEgress, $item, $effectiveItem);
                if ($distance + $traversalCost <= $maxDistance) {
                    $traversals[] = [
                        'distance' => $distance + $traversalCost,
                        'path' => array_merge($path, [ [ $x + $dx, $y + $dy ] ]),
                        'type' => $effectiveItem === FLED_EMPTY ? $traversal['type'] : $effectiveItem,
                    ];
                }
            }
        }

        return array_values(array_map(fn($path) => $this->collapseDoubleTilesInTraversal($path), $bestPathByIndex));
    }

    public function collapseDoubleTilesInTraversal($traversal)
    {
        $path = $traversal['path'];
        for ($i = count($path) - 1; $i > 0; $i--)
        {
            $x = $path[$i][0];
            $y = $path[$i][1];
            $thisTileId = $this->getTileAt($x, $y) % 100;
            $prevTileId = $this->getTileAt($path[$i - 1][0], $path[$i - 1][1]) % 100;
            if ($thisTileId === $prevTileId && FledLogic::isDoubleTile($thisTileId))
            {
                // Delete the path segment that corresponds to the tail room of the double tile
                $headPos = $this->getTileHeadPos($x, $y);
                $indexToDelete = $headPos[0] == $x && $headPos[1] == $y ? $i - 1 : $i;
                array_splice($path, $indexToDelete, 1);
                $i--; // We just paired two indices... it can't match a third. So skip the next check
            }
        }
        $traversal['path'] = $path;
        return $traversal;
    }

    public function getTraversalCost($egress1, $egress2, $item, &$effectiveItem)
    {
        $effectiveItem = FLED_EMPTY;
        if ($egress1 === FLED_EGRESS_NONE || $egress2 === FLED_EGRESS_NONE)
            return 99;

        $needKey = $egress1 === FLED_EGRESS_DOOR || $egress2 == FLED_EGRESS_DOOR;
        $needFile = $egress1 === FLED_EGRESS_WINDOW || $egress2 === FLED_EGRESS_WINDOW;
        $needBoot = $egress1 === FLED_EGRESS_ARCHWAY && $egress2 === FLED_EGRESS_ARCHWAY;
        // Escape and Tunnels are handled separately

        if ($needKey && ($item === FLED_TOOL_KEY || $item === FLED_SHAMROCK || $item === FLED_WHISTLE))
        {
            $effectiveItem = FLED_TOOL_KEY;
            return 1;
        }
        else if ($needBoot && ($item === FLED_TOOL_BOOT || $item === FLED_SHAMROCK || $item === FLED_WHISTLE))
        {
            $effectiveItem = FLED_TOOL_BOOT;
            return 1;
        }
        else if ($needFile && ($item === FLED_TOOL_FILE || $item === FLED_SHAMROCK))
        {
            $effectiveItem = FLED_TOOL_FILE;
            return 1;
        }
        else if ($egress1 === FLED_EGRESS_OPEN && $egress2 === FLED_EGRESS_OPEN)
            return 0;

        return 99;
    }

    public function traverseUnderGround($xStart, $yStart, $maxDistance)
    {
        $bestPathByIndex = [];
        $visited = [];
        $queue = [
            [
                'x' => $xStart,
                'y' => $yStart,
                'distance' => 0,
            ],
        ];
        $directions = [ [ 0, -1 ], [ 1, 0 ], [ 0, 1 ], [ -1, 0 ] ];

        while (count($queue))
        {
            $traversal = array_shift($queue);
            $x = $traversal['x'];
            $y = $traversal['y'];
            $distance = $traversal['distance'];
        
            $index = FledLogic::makeIndex($x, $y);
            if (array_key_exists($index, $visited) && $visited[$index] <= $distance) continue;
            $visited[$index] = $distance;

            $room = $this->getRoomAt($x, $y);
            if (FledLogic::roomHasTunnel($room) && $distance > 0) {
                $destination = [ $x, $y ];
                $double = false;
                $isHead = false;

                // Is this tile a double tile? Add the head room only
                // (unless it's already been added)
                $tileId = $this->getTileAt($x, $y) % 100;
                $rooms = FledLogic::$FledTiles[$tileId]['rooms'];
                if ($rooms[0]['type'] === $rooms[1]['type'])
                {
                    $headPos = $this->getTileHeadPos($x, $y);
                    $double = true;
                    $isHead = $headPos[0] === $x && $headPos[1] === $y;
                }

                // Push single rooms and push the head room of double tiles
                if (!$double || $isHead)
                {
                    $bestPathByIndex[$index] = [
                        'path' => [
                            [ $xStart, $yStart ],
                            $destination,
                        ],
                        'type' => FLED_TOOL_SPOON,
                    ];
                }
            }
            $thisTileId = $this->getTileAt($x, $y) % 100;
            $thisTile = FledLogic::$FledTiles[$thisTileId];
            foreach ($directions as $delta)
            {
                $dx = $delta[0];
                $dy = $delta[1];
                $index = FledLogic::makeIndex($x + $dx, $y + $dy);
                if ($index === -1) continue;
                if (array_key_exists($index, $visited)) continue;
                // Cost to move to adjacent "room" is 0 if it's a double tile; otherwise, cost is 1
                $adjTileId = $this->getTileAt($x + $dx, $y + $dy) % 100;
                if (!$adjTileId) continue;
                $adjTile = FledLogic::$FledTiles[$adjTileId];
                $traversalCost = ($thisTile && $adjTile && $thisTileId === $adjTileId && $thisTile['rooms'][0]['type'] === $thisTile['rooms'][1]['type']) ? 0 : 1;
                if ($distance + $traversalCost <= $maxDistance) {
                    $queue[] = [
                        'x' => $x + $dx,
                        'y' => $y + $dy,
                        'distance' => $distance + $traversalCost,
                    ];
                }
            }
        }

        return array_values(array_map(fn($path) => $this->collapseDoubleTilesInTraversal($path), $bestPathByIndex));
    }

    public function getHandTilesEligibleForInventory()
    {
        $eligibleTileIds = [];

        $playerId = $this->getNextPlayerId();
        $player = $this->data->players->$playerId;

        if ($player->inSolitary) return [];

        $contrabandInInventory = count(array_filter($player->inventory, fn($tileId) => FledLogic::$FledTiles[$tileId]['color'] == FLED_COLOR_BLUE));
        $hasShamrock = count(array_filter($player->inventory, fn($tileId) => FledLogic::$FledTiles[$tileId]['contains'] == FLED_SHAMROCK)) > 0;

        // If meeple is in a room that matches rooms shown on the current whistle
        // roll call tile then the player may add the associated contraband.
        $currentRoomType = $this->getRoomTypeAt($player->pos[0], $player->pos[1]);
        $rollCallTileId = $this->data->rollCall[$this->data->whistlePos];
        $rollCallTile = FledLogic::$RollCallTiles[$rollCallTileId];
        if (array_key_exists($currentRoomType, $rollCallTile))
        {
            // Four is the absolute max capacity; three is the max
            // if there is no shamrock in the player's inventory.
            // No contraband can be added if we're at capacity.
            $inventoryCount = count($player->inventory);
            if ($inventoryCount < 3 || ($inventoryCount < 4 && $hasShamrock))
            {
                $contraband = $rollCallTile[$currentRoomType];
                foreach ($player->hand as $tileId)
                    if (FledLogic::$FledTiles[$tileId]['contains'] == $contraband)
                        $eligibleTileIds[] = $tileId;
            }
        }

        // If meeple is in a Warder's Quarters or same room as the Chaplain...
        $chaplainPos = array_key_exists('chaplain', (array)$this->data->npcs) ? $this->data->npcs->chaplain->pos : [ -1, -1 ];
        $withChaplain = $this->isInSameRoom($chaplainPos, $player->pos);
        if ($currentRoomType == FLED_ROOM_QUARTERS || $withChaplain)
        {
            // A purple tool costs one teal contraband item; a gold item
            // and a shamrock each cost two teal contraband items
            if ($contrabandInInventory >= 1)
            {
                foreach ($player->hand as $tileId)
                    if (FledLogic::$FledTiles[$tileId]['color'] == FLED_COLOR_PURPLE)
                        $eligibleTileIds[] = $tileId;
            }
            if ($contrabandInInventory >= 2)
            {
                foreach ($player->hand as $tileId)
                    if (FledLogic::$FledTiles[$tileId]['color'] == FLED_COLOR_GOLD || FledLogic::$FledTiles[$tileId]['color'] == FLED_COLOR_GREEN)
                        $eligibleTileIds[] = $tileId;
            }
        }

        return $eligibleTileIds;
    }

    public function getHandTilesEligibleForSurrender()
    {
        $playerId = $this->getNextPlayerId();
        $player = $this->data->players->$playerId;
        return $player->hand;
    }

    public function getLegalTileMoves()
    {
        $playerId = $this->getNextPlayerId();
        $hand = $this->data->players->$playerId->hand;

        $legalMoves = [];

        // Scan the board for empty cells that are adjacent to already-placed tiles.
        $availableCells = [];
        for ($x = 0; $x < 14; $x++) {
            for ($y = 0; $y < 14; $y++) {
                $index = FledLogic::makeIndex($x, $y);
                $tileId = $this->getTileAt($x, $y);
                if ($tileId) continue;
                $availableCells[$index] = 0;

                // Test each direction from the current cell
                $directions = [ [ 0, -1 ], [ 1, 0 ], [ 0, 1 ], [ -1, 0 ] ];
                foreach ($directions as $dir)
                {
                    $xx = $x + $dir[0];
                    $yy = $y + $dir[1];
                    $adjTileIdAndOrientation = $this->getTileAt($xx, $yy);
                    if (!$adjTileIdAndOrientation) continue;

                    $availableCells[$index] = $availableCells[$index] + 1;
                }

                // If we found a tile in all four directions, that means this cell is
                // a 1x1 space and therefore there is no room to place a 2x1 tile.
                if ($availableCells[$index] == 4)
                    unset($availableCells[$index]);
            }
        }
        $availableCells = array_map(fn($index) => FledLogic::parseIndex($index), array_keys($availableCells));

        $orientations = [
            FLED_ORIENTATION_NORTH_SOUTH,
            FLED_ORIENTATION_EAST_WEST,
            FLED_ORIENTATION_SOUTH_NORTH,
            FLED_ORIENTATION_WEST_EAST,
        ];

        $goldTileIds = array_filter($hand, fn($tileId) => FledLogic::$FledTiles[$tileId]['color'] == FLED_COLOR_GOLD);
        foreach ($goldTileIds as $tileId)
        {
            foreach ($availableCells as $cell)
            {
                foreach ($orientations as $orientation)
                {
                    if ($this->isLegalTilePlacement($tileId, $cell[0], $cell[1], $orientation, false))
                        $legalMoves[] = [ $tileId, $cell[0], $cell[1], $orientation ];
                }
            }
        }        

        // A player must place a Gold tile if possible 
        if (count($legalMoves))
            return $legalMoves;

        $otherTileIds = array_filter($hand, fn($tileId) => FledLogic::$FledTiles[$tileId]['color'] != FLED_COLOR_GOLD);
        foreach ($otherTileIds as $tileId)
        {
            foreach ($availableCells as $cell)
            {
                foreach ($orientations as $orientation)
                {
                    if ($this->isLegalTilePlacement($tileId, $cell[0], $cell[1], $orientation, false))
                        $legalMoves[] = [ $tileId, $cell[0], $cell[1], $orientation ];
                }
            }
        }
    
        return $legalMoves;
    }

    // Rules are different for starting tiles... the corridor room must be adjacent
    // to the starting yard tile. Note: we don't need to check egress types because
    // every possible placement of the starting bunk tiles automatically follows the
    // egress matching rules.
    public function getLegalStartingTileMoves()
    {
        $playerId = $this->getNextPlayerId();
        $tileId = $this->getStartingBunkTileId($playerId);

        // Starting tile is at (6, 6)... there are six adjacent cells.
        // The tail room (the corridor) must be in one of these cells.
        $tailCells = [
                      [ 6, 5 ], [ 7, 5 ],
            [ 5, 6 ],                     [ 8, 6 ],
                      [ 6, 7 ], [ 7, 7 ],
        ];

        // Remove cells that are already occupied
        $tailCells = array_filter($tailCells, fn($cell) => !$this->getTileAt($cell[0], $cell[1]));

        // Calculate all moves based on the tail cells
        $moves = [];
        foreach ($tailCells as $tailCell)
        {
            $orientations = [
                FLED_ORIENTATION_NORTH_SOUTH => [  0, -1 ],
                FLED_ORIENTATION_EAST_WEST   => [  1,  0 ],
                FLED_ORIENTATION_SOUTH_NORTH => [  0,  1 ],
                FLED_ORIENTATION_WEST_EAST   => [ -1,  0 ],
            ];
            foreach ($orientations as $orientation => $headDir)
            {
                $headX = $tailCell[0] + $headDir[0];
                $headY = $tailCell[1] + $headDir[1];
                if (!$this->getTileAt($headX, $headY))
                    $moves[] = [ $tileId, $headX, $headY, $orientation ];
            }
        }

        return $moves;
    }

    public function canPlayerEscape($playerId)
    {
        $player = $this->data->players->$playerId;
        $x = $player->pos[0];
        $y = $player->pos[1];
        if ($x != 1 && $x != FLED_WIDTH - 2 && $y != 1 && $y != FLED_HEIGHT - 2)
            throw new Exception('out of bounds');

        // Make sure that the head room of the tile the player is on is a forest 
        $tileId = $this->getTileAt($x, $y) % 100;
        $tile = FledLogic::$FledTiles[$tileId];
        if ($tile['rooms'][0]['type'] != FLED_ROOM_FOREST)
            throw new Exception('not an escape tile');

        $toolNeeded = $tile['rooms'][1]['escape'];
        $isNight = $this->data->whistlePos == $this->data->openWindow;
        $toolsRemaining = $isNight ? 1 : 2;

        foreach ($player->inventory as $tileId)
        {
            $item = $this->getTileItem($tileId);
            if ($item == FLED_SHAMROCK)
                $toolsRemaining--;
            else if ($item != $toolNeeded)
                continue;
            else if ($this->getTileColor($tileId) == FLED_COLOR_GOLD)
                $toolsRemaining -= 2;
            else
                $toolsRemaining--;
        }

        return $toolsRemaining <= 0;
    }

    public function getTileItem($tileId)
    {
        $tile = FledLogic::$FledTiles[$tileId % 100];
        return $tile['contains'];
    }

    public function countGovernorInventory()
    {
        return count($this->data->governorInventory);
    }

    public function wasTilePlaced()
    {
        $playerId = $this->getNextPlayerId();
        return $this->data->players->$playerId->placedTile;
    }

    public function countActionsPlayed()
    {
        $playerId = $this->getNextPlayerId();
        return $this->data->players->$playerId->actionsPlayed;
    }

    public function getBoard()
    {
        return $this->data->board;
    }

    public function getWhistlePosition()
    {
        return $this->data->whistlePos;
    }

    public function getPlayerScore($playerId)
    {
        $player = $this->data->players->$playerId;

        $sum = 0;
        foreach ($player->inventory as $tileId)
        {
            // Increase the player's score based on the colour of the tile
            $tile = FledLogic::$FledTiles[$tileId];
            $sum += $tile['color'];
        }
        if ($player->shackleTile)
            $sum -= 1;
        if ($player->escaped)
            $sum += 5;

        return $sum;
    }

    public function getPlayerAuxScore($playerId)
    {
        // Tie breaker is the highest value tile in inventory (not in hand) -- includes flipped prisoner tile & shackle tile
        $player = $this->data->players->$playerId;
        if ($player->escaped)
            return 5;
        $initial = $player->shackleTile ? -1 : 0;
        return array_reduce($player->inventory, fn($max, $tileId) => max($max, FledLogic::getTileScore($tileId)), $initial);
    }

    public function getScores()
    {
        $scores = [];
        foreach ($this->data->order as $playerId)
            $scores[$playerId] = $this->getPlayerScore($playerId);
        return $scores;
    }

    public function wasHardLaborCalled()
    {
        return $this->data->hardLabor;
    }

    public function getPlayerIds()
    {
        return $this->data->order;
    }

    public function getPlayerPositions()
    {
        return array_map(fn($player) => $player->pos, (array)$this->data->players);
    }

    public function isPlayerInSolitary($playerId)
    {
        return $this->data->players->$playerId->inSolitary;
    }

    public function getMoveCount()
    {
        return $this->data->moves;
    }

    // Return only the public data and the data private to the given player 
    public function getPlayerData($playerId)
    {
        $data = json_decode(json_encode($this->data));
        foreach ($this->data->players as $id => $player)
        {
            // Only return array counts instead of the tile IDs for private player data
            if ($id != $playerId)
                $data->players->$id->hand = count($player->hand);
        }

        // Remove private information about the tiles in the deck and discard
        $data->drawPile = count($data->drawPile);
        $data->discardPile = count($data->discardPile);

        return $data;
    }

    function toJson()
    {
        return json_encode($this->data);
    }
}
