// Fled implementation : © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)
"use strict"
define([], () => {
    const PlayerColor = {
        Yellow: 0,
        Blue: 1,
        Orange: 2,
        Green: 3,
    };

    // Only used internally; not used for display
    const MeepleNames = [
        'yellow',
        'blue',
        'orange',
        'green',
    ];

    const RoomType = {
        Yard: 1,
        Corridor: 2,
        Bunk: 3,
        MessHall: 4,
        Washroom: 5,
        Quarters: 6,
        Courtyard: 7,
        Forest: 8,
    };

    const EgressType = {
        None: 0,// Special-case return value for when there is no tile there
        Open: 1,// Used for double tiles where there is no interior wall between the two halves of the one room
        Archway: 2,
        Window: 3,
        Door: 4,
        Escape: 5,
    }

    const Empty = 0;

    const ScrollColor = {
        None: 0,
        Blue: 1,
        Purple: 2,
        Green: 3,
        Gold: 4,
    };

    const ContrabandType = {
        Button: 10,
        Stamp: 11,
        Comb: 12,
        Cake: 13,
    };

    const ToolType = {
        Key: 20,
        File: 21,
        Boot: 22,
        Spoon: 23,
    };

    const ItemType = {
        ...ContrabandType,
        ...ToolType,
        Shamrock: 30,
        Whistle: 40,
    };

    const SpecialTile = {
        YellowBunk: 1,
        BlueBunk: 2,
        OrangeBunk: 7,
        GreenBunk: 8,
        SolitaryConfinement: 26,
        Chapel: 31,
        DoubleWashroom: 36,
        DoubleCorridor: 41,
        DoubleYard: 46,
        DoubleMessHall: 51,
    };

    const Orientation = {
        NorthSouth: 0,
        WestEast: 100,
        SouthNorth: 200,
        EastWest: 300,
    };

    const Direction = {
        North: 0,
        East: 1,
        South: 2,
        West: 3,
    };

    const FledWidth = 14;
    const FledHeight = 13;

    const RollCallTiles = [
        {
            [RoomType.MessHall]: ContrabandType.Cake,
            [RoomType.Yard]: ContrabandType.Button,
        },
        {
            [RoomType.Washroom]: ContrabandType.Comb,
            [RoomType.MessHall]: ContrabandType.Cake,
        },
        {
            [RoomType.Washroom]: ContrabandType.Comb,
            [RoomType.Bunk]: ContrabandType.Stamp,
        },
        {
            [RoomType.Bunk]: ContrabandType.Stamp,
            [RoomType.Yard]: ContrabandType.Button,
        },
        { // The Governor tile
            [RoomType.Bunk]: ContrabandType.Stamp,
        },
    ];

    // Note: All bunk, yard, and courtyard tiles have a tunnel

    // These are the playable tiles only.
    const Tiles = {
        [SpecialTile.YellowBunk]: {
            color: ScrollColor.None,
            contains: Empty,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.BlueBunk]: {
            color: ScrollColor.None,
            contains: Empty,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        3: {
            color: ScrollColor.Purple,
            contains: ItemType.Whistle,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        4: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Comb,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        5: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        6: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Stamp,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.OrangeBunk]: {
            color: ScrollColor.None,
            contains: Empty,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.GreenBunk]: {
            color: ScrollColor.None,
            contains: Empty,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        9: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Comb,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        10: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Cake,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        11: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Button,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        12: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Stamp,
            rooms: [
                {
                    type: RoomType.Bunk,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        13: {
            color: ScrollColor.Gold,
            contains: ToolType.Key,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Escape, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                    escape: ToolType.File,
                },
            ],
            minPlayers: 1,
        },
        14: {
            color: ScrollColor.Gold,
            contains: ToolType.File,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Escape, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                    escape: ToolType.Boot,
                },
            ],
            minPlayers: 1,
        },
        15: {
            color: ScrollColor.Gold,
            contains: ToolType.Boot,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Escape, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                    escape: ToolType.Spoon,
                },
            ],
            minPlayers: 1,
        },
        16: {
            color: ScrollColor.Gold,
            contains: ToolType.Spoon,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Escape, EgressType.Window, EgressType.Window, EgressType.Door ],
                    escape: ToolType.Key,
                },
            ],
            minPlayers: 1,
        },
        17: {
            color: ScrollColor.Purple,
            contains: ToolType.Boot,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Escape, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                    escape: ToolType.File,
                },
            ],
            minPlayers: 1,
        },
        18: {
            color: ScrollColor.Purple,
            contains: ToolType.Spoon,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Escape, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                    escape: ToolType.Boot,
                },
            ],
            minPlayers: 1,
        },
        19: {
            color: ScrollColor.Purple,
            contains: ToolType.Key,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Escape, EgressType.Archway, EgressType.Archway, EgressType.Archway ],
                    escape: ToolType.Spoon,
                },
            ],
            minPlayers: 1,
        },
        20: {
            color: ScrollColor.Purple,
            contains: ToolType.File,
            rooms: [
                {
                    type: RoomType.Forest,
                    egress: [ EgressType.Open, EgressType.Open, EgressType.Escape, EgressType.Open ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Escape, EgressType.Window, EgressType.Window, EgressType.Door ],
                    escape: ToolType.Key,
                },
            ],
            minPlayers: 1,
        },
        21: {
            color: ScrollColor.Purple,
            contains: ToolType.Key,
            rooms: [
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Door, EgressType.Door ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Window, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        22: {
            color: ScrollColor.Purple,
            contains: ToolType.Boot,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Door, EgressType.Door ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Window, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        23: {
            color: ScrollColor.Purple,
            contains: ToolType.File,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Door, EgressType.Door ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Window, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        24: {
            color: ScrollColor.Purple,
            contains: ToolType.Key,
            rooms: [
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Window, EgressType.Window ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Window, EgressType.Door, EgressType.Door, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        25: {
            color: ScrollColor.Purple,
            contains: ItemType.Whistle,
            rooms: [
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Window, EgressType.Window ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Window, EgressType.Door, EgressType.Door, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.SolitaryConfinement]: {
            color: ScrollColor.Gold,
            contains: ToolType.Key,
            rooms: [
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Door, EgressType.Open, EgressType.Window ],
                },
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Open, EgressType.Door, EgressType.Door, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        27: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        28: {
            color: ScrollColor.Purple,
            contains: ToolType.Key,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        29: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Cake,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        30: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.Chapel]: {
            color: ScrollColor.Gold,
            contains: ToolType.Boot,
            rooms: [
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Open, EgressType.Archway ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Open, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        32: {
            color: ScrollColor.Purple,
            contains: ItemType.Whistle,
            rooms: [
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Window, EgressType.Door, EgressType.Window, EgressType.Door ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        33: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Stamp,
            rooms: [
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Archway, EgressType.Door, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        34: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Archway, EgressType.Door, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        35: {
            color: ScrollColor.Purple,
            contains: ToolType.Spoon,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Archway, EgressType.Door, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.DoubleWashroom]: {
            color: ScrollColor.Gold,
            contains: ToolType.File,
            rooms: [
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Archway, EgressType.Window, EgressType.Open, EgressType.Archway ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Open, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        37: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Button,
            rooms: [
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Archway, EgressType.Window, EgressType.Window, EgressType.Archway ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        38: {
            color: ScrollColor.Purple,
            contains: ToolType.Boot,
            rooms: [
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Window, EgressType.Door ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        39: {
            color: ScrollColor.Purple,
            contains: ToolType.Spoon,
            rooms: [
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Window, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Window, EgressType.Door, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        40: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Cake,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Window, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Window, EgressType.Door, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        41: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Stamp,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Open, EgressType.Window ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Open, EgressType.Door, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        42: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Door, EgressType.Door ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        43: {
            color: ScrollColor.Purple,
            contains: ToolType.File,
            rooms: [
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Door, EgressType.Door ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        44: {
            color: ScrollColor.Purple,
            contains: ItemType.Whistle,
            rooms: [
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Door, EgressType.Door ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        45: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.DoubleYard]: {
            color: ScrollColor.None,
            contains: Empty,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Open, EgressType.Archway ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Open, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        47: {
            color: ScrollColor.Purple,
            contains: ToolType.File,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        48: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Stamp,
            rooms: [
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        49: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        50: {
            color: ScrollColor.Purple,
            contains: ItemType.Whistle,
            rooms: [
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Window, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Window, EgressType.Archway ],
                },
            ],
            minPlayers: 1,
        },
        [SpecialTile.DoubleMessHall]: {
            color: ScrollColor.Gold,
            contains: ToolType.Spoon,
            rooms: [
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Archway, EgressType.Window, EgressType.Open, EgressType.Archway ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Open, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        52: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Comb,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Archway, EgressType.Window, EgressType.Window, EgressType.Archway ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        53: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Archway, EgressType.Window, EgressType.Window, EgressType.Archway ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Window, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 1,
        },
        54: {
            color: ScrollColor.Purple,
            contains: ToolType.Boot,
            rooms: [
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Archway, EgressType.Door, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        55: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Button,
            rooms: [
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Archway, EgressType.Door, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        56: {
            color: ScrollColor.Purple,
            contains: ToolType.Spoon,
            rooms: [
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Window, EgressType.Door, EgressType.Door, EgressType.Window ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 1,
        },
        70: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Comb,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Archway, EgressType.Window, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.MessHall,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Door ],
                },
            ],
            minPlayers: 3,
        },
        71: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Cake,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Archway, EgressType.Door, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 3,
        },
        72: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Button,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Archway, EgressType.Door, EgressType.Door, EgressType.Archway ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Door, EgressType.Archway, EgressType.Archway, EgressType.Window ],
                },
            ],
            minPlayers: 3,
        },
        73: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Yard,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Window, EgressType.Door ],
                },
                {
                    type: RoomType.Washroom,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 3,
        },
        74: {
            color: ScrollColor.Blue,
            contains: ContrabandType.Stamp,
            rooms: [
                {
                    type: RoomType.Corridor,
                    egress: [ EgressType.Archway, EgressType.Archway, EgressType.Window, EgressType.Door ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Window, EgressType.Window, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 3,
        },
        75: {
            color: ScrollColor.Green,
            contains: ItemType.Shamrock,
            rooms: [
                {
                    type: RoomType.Quarters,
                    egress: [ EgressType.Door, EgressType.Door, EgressType.Window, EgressType.Window ],
                },
                {
                    type: RoomType.Courtyard,
                    egress: [ EgressType.Window, EgressType.Door, EgressType.Archway, EgressType.Archway ],
                },
            ],
            minPlayers: 3,
        },
    };

/*
// Specter Expansion
S1 - [ghost] double Corridor: EgressType.Archway, EgressType.Archway, , EgressType.Archway | , EgressType.Archway, EgressType.Archway, EgressType.Archway / Gold key
S2 - ghost tile

// Governor's House Expansion
six tiles... TODO
*/

    function makeIndex(x, y) {
        if (x < 0 || x >= FledWidth || y < 0 || y >= FledHeight)
            return -1;

        // The top-left forest cell is (0,0)
        return y * FledWidth + x; 
    }

    function parseIndex(index) {
        const x = index % FledWidth;
        const y = (index - x) / FledWidth;
        return [ x, y ]; 
    }

    // Board cells are represented as the tileId and a rotation
    function unpackCell(tileIdAndOrientation) {
        const tileId = tileIdAndOrientation % 100;
        const orientation = tileIdAndOrientation - tileId;
        return {
            tileId,
            orientation,
        };
    }
    
    function getTailLocation(x, y, orientation) {
        let x2 = x;
        let y2 = y;
        if (orientation == Orientation.NorthSouth) y2++;
        if (orientation == Orientation.SouthNorth) y2--;
        if (orientation == Orientation.EastWest) x2--;
        if (orientation == Orientation.WestEast) x2++;
        return { x2, y2 };
    }

    function tileColorIs(color) {
        return tileId => getTileColor(tileId) === color;
    }

    function tileColorIsNot(color) {
        return tileId => getTileColor(tileId) !== color;
    }

    function getTileColor(tileId) {
        return Tiles[tileId % 100].color;
    }

    function tileContains(itemType) {
        return tileId => getTileItem(tileId) === itemType;
    }

    function getTileItem(tileId) {
        return Tiles[tileId % 100].contains;
    }

    function getTilePoints(tileId) {
        return Tiles[tileId % 100].color; // Color index is equal to the points for the tile
    }

    function roomHasTunnel(room) {
        if (!room) return false;
        return room.type === RoomType.Bunk
            || room.type === RoomType.Yard
            || room.type === RoomType.Courtyard
        ;
    }

    function tileHasMoon(tileId) {
        switch (tileId) {
            case SpecialTile.Chapel: return true;
            case SpecialTile.DoubleWashroom: return true;
            case SpecialTile.DoubleYard: return true;
            case SpecialTile.DoubleMessHall: return true;
            default: return false;
        }
    }

    function isDoubleTile(tileId) {
        switch (tileId) {
            case SpecialTile.Chapel: return true;
            case SpecialTile.SolitaryConfinement: return true;
            case SpecialTile.DoubleWashroom: return true;
            case SpecialTile.DoubleYard: return true;
            case SpecialTile.DoubleMessHall: return true;
            case SpecialTile.DoubleCorridor: return true;
            default: return false;
        }
    }

    function isWhistleTile(tileId) {
        const { contains } = Tiles[tileId];
        return contains === ItemType.Whistle;
    }

    function isShamrockTile(tileId) {
        const { contains } = Tiles[tileId];
        return contains === ItemType.Shamrock;
    }

    class FledLogic {
        constructor(data, myPlayerId) {
            this.myPlayerId = myPlayerId;
            this.data = data;
            this.parseBoard();
        }

        // Only intended for unit testing
        setBoard(board) {
            this.data.board = board;
            this.parseBoard();
        }

        //
        // Break down the tiles into individual rooms that are already rotated
        // into the correct position.
        //
        parseBoard() {
            this.rooms = new Array(FledWidth * FledHeight).map(_ => null);
            for (let x = 0; x < FledWidth; x++) {
                for (let y = 0; y < FledHeight; y++) {
                    const tileIdAndOrientation = this.getTileAt(x, y);
                    if (!tileIdAndOrientation) continue;
                    const { tileId, orientation } = unpackCell(tileIdAndOrientation);
                    const tile = Tiles[tileId];
                    const headPos = this.getTileHeadPos(x, y);
                    const isHead = headPos.x == x && headPos.y == y;
                    const room = tile.rooms[isHead ? 0 : 1];
                    this.cacheRoom(x, y, room, orientation);
                }
            }
        }

        cacheRoom(x, y, room, orientation) {
            const egresses = this.getOrientedEgresses(room, orientation);
            const index = makeIndex(x, y);
            this.rooms[index] = {
                type: room.type,
                egresses,
                escape: room.escape,
            };
        }

        getTileAt(x, y) {
            if (x < 0 || x >= FledWidth || y < 0 || y >= FledHeight)
                return 0;
            const index = makeIndex(x, y);
            return this.data.board[index];
        }

        getRoomAt(x, y) {
            if (x < 0 || x >= FledWidth || y < 0 || y >= FledHeight)
                return null;
            const index = makeIndex(x, y);
            return this.rooms[index];
        }

        //
        // Given a cell position, find the position of the head of the tile at (x, y).
        // Precondition: (x, y) is not empty
        //
        getTileHeadPos(x, y) {
            if (x < 0 || y < 0 || x >= FledWidth || y >= FledWidth)
                throw new Error('Invalid location');

            // The borders of the playable surface are all forest locations
            // and the tiles with forest all have forest at the head of the
            // tile. Therefore, any coordinate laying on the border must be
            // the tile head.
            if (x === 0 || y === 0 || x === FledWidth - 1 || y === FledHeight - 1)
                return { x, y };

            const index = makeIndex(x, y);
            const tileIdAndOrientation = this.data.board[index];
            if (!tileIdAndOrientation) throw new Error('precondition failed: empty cell');
            const { tileId, orientation } = unpackCell(tileIdAndOrientation);

            // Based on tile orientation, look at an adjacent cell
            // to determine if it is actually the head of the tile
            let xMod = x;
            let yMod = y;
            switch (orientation) {
                case Orientation.NorthSouth: yMod = y - 1; break;
                case Orientation.WestEast:   xMod = x - 1; break;
                case Orientation.SouthNorth: yMod = y + 1; break;
                case Orientation.EastWest:   xMod = x + 1; break;
                default: throw new Error('invalid orientation');
            }

            return (
                unpackCell(this.data.board[makeIndex(xMod, yMod)]).tileId === tileId
                    ? { x: xMod, y: yMod }
                    : { x, y }
            );
        }

        //
        // Given a cell position, find the position of the tail of the tile at (x, y).
        // Precondition: (x, y) is not empty
        //
        getTileTailPos(x, y) {
            if (x < 0 || y < 0 || x >= FledWidth || y >= FledWidth)
                throw new Error('Invalid location');

            const { tileId, orientation } = unpackCell(this.getTileAt(x, y));

            // Based on tile orientation, look at an adjacent cell
            // to determine if it is actually the head of the tile
            let xMod = x;
            let yMod = y;
            switch (orientation) {
                case Orientation.NorthSouth: yMod = y + 1; break;
                case Orientation.WestEast:   xMod = x + 1; break;
                case Orientation.SouthNorth: yMod = y - 1; break;
                case Orientation.EastWest:   xMod = x - 1; break;
                default: throw new Error('invalid orientation');
            }

            return (
                unpackCell(this.getTileAt(xMod, yMod)).tileId === tileId
                    ? { x: xMod, y: yMod }
                    : { x, y }
            );
        }

        setTileAt(tileId, x, y, orientation) {
            if (x < 0 || x >= FledWidth || y < 0 || y >= FledHeight)
                throw new Error('invalid location');

            // Place the tile head
            const index = makeIndex(x, y);
            this.data.board[index] = tileId + orientation;

            // Place the tile tail
            const { x2, y2 } = getTailLocation(x, y, orientation);
            const index2 = makeIndex(x2, y2);
            this.data.board[index2] = tileId + orientation;

            // Cache the room information for future calculations
            const tile = Tiles[tileId];
            this.cacheRoom(x, y, tile.rooms[0], orientation);
            this.cacheRoom(x2, y2, tile.rooms[1], orientation);
        }

        getNextPlayerId() {
            return this.data.order[this.data.nextPlayer];
        }

        isGameSetup() {
            return !!this.data.setup;
        }

        isTileOnBoard(tileId) {
            return this.data.board.indexOf(tileId) >= 0;
        }

        getTilePosition(tileId) {
            // Precondition is that the tile is known to be on the board
            const index = this.data.board.findIndex(t => t % 100 === tileId);
            const [ x, y ] = parseIndex(index);
            return this.getTileHeadPos(x, y);
        }    
    
        getStartingBunkTileId(playerId) {
            switch (this.data.players[playerId].color) {
                case PlayerColor.Yellow: return SpecialTile.YellowBunk;
                case PlayerColor.Blue:   return SpecialTile.BlueBunk;
                case PlayerColor.Orange: return SpecialTile.OrangeBunk;
                case PlayerColor.Green:  return SpecialTile.GreenBunk;
            }
            throw new Error('Invalid color');
        }

        isTileInPlayersHand(tileId) {
            return this.data.players[this.myPlayerId].hand.indexOf(tileId) >= 0;
        }

        removeTileFromHand(playerId, tileId) {
            if (playerId != this.myPlayerId) {
                this.data.players[playerId].hand--;
                return;
            }

            const player = this.data.players[playerId];
            player.hand = player.hand.filter(t => t != tileId);
            
            // Return value is only used in test code
            return player.hand;
        }

        drawTile(playerId, tileId) {
            this.addTileToHand(playerId, tileId);
            this.data.drawPile--;
        }

        shuffle() {
            this.data.drawPile += this.data.discardPile;
            this.data.discardPile = 0;
        }

        addTileToHand(playerId, tileId) {
            const player = this.data.players[playerId];
            if (playerId == this.myPlayerId) {
                player.hand.push(tileId);
            }
            else {
                player.hand++;
            }
        }

        addTileToGovernorInventory(tileId) {
            this.data.governorInventory.push(tileId);
        }

        removeTileFromGovernorInventory(tileId) {
            this.data.governorInventory = this.data.governorInventory.filter(t => t != tileId);
        }

        addTileToDiscardPile() {
            this.data.discardPile++;
        }

        discardTile(playerId, tileId) {
            // Move tile from player's hand to the discard pile
            this.removeTileFromHand(playerId, tileId);
            this.addTileToDiscardPile();

            // Technically, the player hasn't added a tile...
            // but we're just using this flag to indicate that
            // the addTile phase has been completed.
            this.data.players[playerId].addedTile = true;
            this.data.moves++;

            return true;
        }

        addTileToInventory(playerId, tileId) {
            // Remove the card from the player's hand
            // And add it to the player's inventory
            this.removeTileFromHand(playerId, tileId);
            this.data.players[playerId].inventory.push(tileId);

            if (playerId == this.myPlayerId) {
                this.data.players[playerId].actionsPlayed++;
            }
            this.data.moves++;

            return true;
        }

        removeTileFromInventory(playerId, tileId) {
            const player = this.data.players[playerId];
            player.inventory = player.inventory.filter(t => t != tileId);
        }

        surrenderTile(playerId, tileId) {
            // Remove the card from the player's hand
            // And add it to the Governor's inventory
            this.removeTileFromHand(playerId, tileId);
            this.addTileToGovernorInventory(tileId);

            if (playerId == this.myPlayerId) {
                this.data.players[playerId].actionsPlayed++;
            }
            this.data.moves++;

            return true;
        }

        escape(playerId) {
            const player = this.data.players[playerId];
            player.escaped = true;
            player.pos = this.getTileHeadPos(player.pos[0], player.pos[1]);

            if (this.data.finalTurns === null) {
                this.data.finalTurns = this.data.order.length;
            }
            // Note: the value will be decremented soon via the endTurn event handler
        }

        isLegalTilePlacement(tileId, x, y, orientation, isStarterBunk = false) {
            // Can't place the head room outside the board
            if (x < 0 || x >= FledWidth || y < 0 || y >= FledHeight)
                return false;

            // Bail out if the head cell is occupied
            if (this.getTileAt(x, y))
                return false;

            // Can't place the tail room outside the board
            // (but also can't place the tail cell on the border
            // due to the forest placement rule and the fact
            // that all forest rooms are in the head position)
            const { x2, y2 } = getTailLocation(x, y, orientation);
            if (x2 <= 0 || x2 >= FledWidth - 1 || y2 <= 0 || y2 >= FledHeight - 1)
                return false;

            // Bail out if the tail cell is occupied
            if (this.getTileAt(x2, y2))
                return false;

            // The starter bunk tile must have the Corridor room adjacent to the yard.
            if (isStarterBunk) {
                // (x2, y2) represents the Corridor room for the bunk tiles
                return this.isAdjacentToRoomType(x2, y2, RoomType.Yard);
            }

            const tile = Tiles[tileId];
            const [ headRoom, tailRoom ] = tile.rooms;

            // Either the head or the tail must be placed adjacent to a matching room type 
            if (!this.isAdjacentToRoomType(x, y, headRoom.type) && !this.isAdjacentToRoomType(x2, y2, tailRoom.type))
                return false; 

            // Forest and anti-forest rules:
            // Only allowed to place a forest room along the outside border
            // and not allowed to place any non-forest along the border.
            // Note: all forest tiles have the forest in the head room.
            const isHeadOnBorder = x == 0 || x == FledWidth - 1 || y == 0 || y == FledHeight - 1;
            if (headRoom.type == RoomType.Forest && !isHeadOnBorder)
                return false;
            else if (headRoom.type != RoomType.Forest && isHeadOnBorder)
                return false;
                
            // Verify that there are no Door-to-Window connections for the head tile
            if (headRoom.type != RoomType.Forest) {
                const egresses = this.getOrientedEgresses(headRoom, orientation);
                const adjEgress = [
                    this.getEgressType(x, y - 1, Direction.South),
                    this.getEgressType(x + 1, y, Direction.West),
                    this.getEgressType(x, y + 1, Direction.North),
                    this.getEgressType(x - 1, y, Direction.East),
                ];

                for (i = 0; i < 4; i++) {
                    if (egresses[i] === EgressType.Door && adjEgress[i] === EgressType.Window) return false;
                    if (egresses[i] === EgressType.Window && adjEgress[i] === EgressType.Door) return false;
                }
            }

            // Verify that there are no Door-to-Window connections for the tail tile
            const egresses = this.getOrientedEgresses(tailRoom, orientation);
            const adjEgress = [
                this.getEgressType(x2, y2 - 1, Direction.South),
                this.getEgressType(x2 + 1, y2, Direction.West),
                this.getEgressType(x2, y2 + 1, Direction.North),
                this.getEgressType(x2 - 1, y2, Direction.East),
            ];

            for (i = 0; i < 4; i++) {
                if (egresses[i] === EgressType.Door && adjEgress[i] === EgressType.Window) return false;
                if (egresses[i] === EgressType.Window && adjEgress[i] === EgressType.Door) return false;
            }
            
            return true;
        }

        isAdjacentToRoomType(x, y, roomType) {
            const directions = [ [ 1, 0 ], [ -1, 0 ], [ 0, 1 ], [ 0, -1 ] ];
            for (const dir of directions) {
                const adjRoom = this.getRoomAt(x + dir[0], y + dir[1]);
                if (adjRoom?.type === roomType)
                    return true;
            }
            return false;
        }

        //
        // Given cell coordinates and a direction, return
        // the egress type for the room at that position.
        //
        getEgressType(x, y, dir) {
           const room = this.getRoomAt(x, y);
           if (!room) return EgressType.None;
           return room.egresses[dir];
        }

        //
        // Given a tile-room object and an orientation, rotate its
        // egresses so that they match the physical layout
        //
        getOrientedEgresses(room, orientation) {
            // Rotate the logical room egresses to match the physical layout
            let egresses = [ ...room.egress ];
            for (let o = 100; o <= orientation; o += 100) {
                egresses.push(egresses.shift());
            }
            return egresses;
        }

        isPlayer(playerId) {
            return Object.keys(this.data.players).indexOf(playerId) >= 0;
        }

        getOption(optionName) {
            return this.data.options[optionName];
        }

        getInventoryTilesEligibleForEscape() {
            const player = this.data.players[this.myPlayerId];
            const [ x, y ] = player.pos;
            if (x != 1 && x != FledWidth - 2 && y != 1 && y != FledHeight - 2) {
                return false;
            }
    
            // Make sure that the head room of the tile the player is on is a forest 
            const { tileId } = unpackCell(this.getTileAt(x, y));
            const tile = Tiles[tileId];
            if (tile.rooms[0].type !== RoomType.Forest)
                return false;
    
            const toolNeeded = tile.rooms[1].escape;

            return player.inventory.filter(tileId => {
                const item = getTileItem(tileId);
                return item === toolNeeded || item === ItemType.Shamrock;
            });
        }

        getHandTilesEligibleForMovement() {
            const eligibleTileIds = [];

            const player = this.data.players[this.myPlayerId];
            if (!player.pos) return [];
            const [ x, y ] = player.pos;
            const toolsNeeded = this.calculateToolsNeededToLeaveRoom(x, y);

            for (const tileId of player.hand) {
                const tile = Tiles[tileId];
                const item = tile.contains;
                if (item === ItemType.Whistle || item === ItemType.Shamrock) {
                    eligibleTileIds.push(tileId);
                }
                else if (toolsNeeded.indexOf(item) >= 0 && !player.inSolitary) {
                    const legalMoves = this.getLegalMovementMoves(tileId);
                    if (legalMoves.length) {
                        eligibleTileIds.push(tileId);
                    }
                }
            }

            return eligibleTileIds;
        }

        calculateToolsNeededToLeaveRoom(x, y, distance = 1) {
            // We use recursion to handle "double" tiles where the search
            // goes from one half of the double room to the other half.
            // (This is necessary because none of the double tiles have
            // symmetric egresses among their two halves).
            if (distance < 0)
                return [];
            
            const currentRoom = this.getRoomAt(x, y);
            const currentRoomType = currentRoom.type;
            const egresses = currentRoom.egresses;
            
            const toolsNeeded = [];

            const hasTunnel =
                currentRoomType === RoomType.Yard ||
                currentRoomType === RoomType.Courtyard ||
                currentRoomType === RoomType.Bunk;
            if (hasTunnel)
                toolsNeeded.push(ToolType.Spoon);
            
            const directions = {
                [Direction.North]: [ 0, -1 ],
                [Direction.East]:  [ 1, 0 ],
                [Direction.South]: [ 0, 1 ],
                [Direction.West]: [ -1, 0 ],
            };
            for (const [ dirString, delta ] of Object.entries(directions)) {
                const dir = Number(dirString);
                const xx = x + delta[0];
                const yy = y + delta[1];
                if (!this.getTileAt(xx, yy)) continue;

                // Find the egress of the adjacent tile in the opposite direction
                // (e.g. our East wall is the same as the adjacent cell West wall)
                const adjEgress = this.getEgressType(xx, yy, (dir + 2) % 4);

                if (adjEgress === EgressType.Door || egresses[dir] == EgressType.Door)
                    toolsNeeded.push(ToolType.Key);

                else if (adjEgress === EgressType.Window || egresses[dir] === EgressType.Window)
                    toolsNeeded.push(ToolType.File);

                else if (adjEgress === EgressType.Archway && egresses[dir] === EgressType.Archway)
                    toolsNeeded.push(ToolType.Boot);

                // Evaluate the next room as well (because double tiles do not have symmetric egresses)
                else if (adjEgress === EgressType.Open && egresses[dir] === EgressType.Open)
                    toolsNeeded.push(...this.calculateToolsNeededToLeaveRoom(xx, yy, distance - 1));
            }
            return unique(toolsNeeded);
        }

        getLegalMovementMoves(tileId) {
            const tile = Tiles[tileId];
            let distance = 0;
            switch (tile.color) {
                case ScrollColor.Purple: distance = 1; break;
                case ScrollColor.Green:  distance = 1; break;
                case ScrollColor.Gold:   distance = 2; break;
                default:
                    return [];
            }

            const [ x, y ] = this.data.players[this.myPlayerId].pos;
            const room = this.getRoomAt(x, y);
            const item = tile.contains;

            const paths = [];
            if (roomHasTunnel(room) && (item === ToolType.Spoon || item === ItemType.Shamrock)) {
                // Each spoon lets player move 3 rooms
                paths.push(...this.traverseUnderGround(x, y, distance * 3));
            }
            if (item !== ToolType.Spoon) {
                paths.push(...this.traverseAboveGround(item, x, y, 1, distance));
            }
            return paths;
        }

        traverseAboveGround(item, xStart, yStart, minDistance, maxDistance) {
            const bestPathByIndex = {};
            const traversals = [];
            const visited = {};

            // Start in the current room
            traversals.push({
                path: [
                    [ xStart, yStart ],
                ],
                distance: 0,
            });

            const directions = {
                [Direction.North]: [  0, -1 ],
                [Direction.East]:  [  1,  0 ],
                [Direction.South]: [  0,  1 ],
                [Direction.West]:  [ -1,  0 ],
            };

            while (traversals.length) {
                const { distance, path } = traversals.shift();
                const [ x, y ] = path[path.length - 1];
                const index = makeIndex(x, y);

                if (typeof visited[index] === 'number' && visited[index] <= distance) continue;
                visited[index] = distance;

                if (distance >= minDistance) {
                    let double;
                    let isHead = false;

                    // Is this tile a double tile? Add the head room only
                    // (unless it's already been added)
                    const { tileId } = unpackCell(this.getTileAt(x, y));
                    if (isDoubleTile(tileId)) {
                        const headPos = this.getTileHeadPos(x, y);
                        path.pop();
                        path.push([ headPos.x, headPos.y ]);
                        double = true;
                        isHead = headPos.x === x && headPos.y === y;
                    }

                    if (!double || isHead) {
                        bestPathByIndex[index] = {
                            tunnel: false,
                            path,
                            distance,
                        };
                    }
                }

                for (const [ dirString, [ dx, dy ] ] of Object.entries(directions)) {
                    const dir = Number(dirString);
                    const thisRoomEgress = this.getEgressType(x, y, dir);
                    const adjRoomEgress = this.getEgressType(x + dx, y + dy, (dir + 2) % 4);
                    const traversalCost = this.getTraversalCost(thisRoomEgress, adjRoomEgress, item);
                    if (distance + traversalCost <= maxDistance) {
                        traversals.push({
                            distance: distance + traversalCost,
                            path: [ ...path, [ x + dx, y + dy ] ],
                        });
                    }
                }
            }

            return Object.values(bestPathByIndex).map(t => this.collapseDoubleTilesInTraversal(t));
        }

        collapseDoubleTilesInTraversal(traversal) {
            const { path } = traversal;
            for (let i = path.length - 1; i > 0; i--) {
                const [ x, y ] = path[i];
                const { tileId: thisTileId } = unpackCell(this.getTileAt(x, y));
                const { tileId: prevTileId } = unpackCell(this.getTileAt(path[i - 1][0], path[i - 1][1]));
                if (thisTileId === prevTileId && isDoubleTile(thisTileId))
                {
                    // Delete the path segment that corresponds to the tail room of the double tile
                    const headPos = this.getTileHeadPos(x, y);
                    const indexToDelete = headPos.x == x && headPos.y == y ? i - 1 : i;
                    path.splice(indexToDelete, 1);
                    i--; // We just paired two indices... it can't match a third. So skip the next check
                }
            }
            return traversal;
        }
    
        getTraversalCost(egress1, egress2, item) {
            if (egress1 === EgressType.None || egress2 === EgressType.None)
                return 99;

            const needKey = egress1 === EgressType.Door || egress2 == EgressType.Door;
            const needFile = egress1 === EgressType.Window || egress2 === EgressType.Window;
            const needBoot = egress1 === EgressType.Archway && egress2 === EgressType.Archway;
            // Escape and Tunnels are handled separately

            if (needKey && (item === ItemType.Key || item === ItemType.Shamrock || item === ItemType.Whistle)) {
                return 1;
            }
            else if (needBoot && (item === ItemType.Boot || item === ItemType.Shamrock || item === ItemType.Whistle)) {
                return 1;
            }
            else if (needFile && (item === ItemType.File || item === ItemType.Shamrock)) {
                return 1;
            }
            else if (egress1 === EgressType.Open && egress2 === EgressType.Open) {
                return 0;
            }
            return 99;
        }

        traverseUnderGround(xStart, yStart, maxDistance) {
            const bestPathByIndex = {};
            const visited = {};
            const queue = [
                { x: xStart, y: yStart, distance: 0 },
            ];
            const directions = [ [ 0, -1 ], [ 1, 0 ], [ 0, 1 ], [ -1, 0 ] ];

            while (queue.length) {
                const { x, y, distance } = queue.shift();
                const index = makeIndex(x, y);

                if (typeof visited[index] === 'number' && visited[index] <= distance) continue;
                visited[index] = distance;
                
                const room = this.getRoomAt(x, y);
                if (roomHasTunnel(room) && distance > 0) {
                    let double;
                    let isHead = false;

                    // Is this tile a double tile? Add the head room only
                    // (unless it's already been added)
                    const { tileId } = unpackCell(this.getTileAt(x, y));
                    const { rooms } = Tiles[tileId];
                    if (rooms[0].type === rooms[1].type) {
                        const headPos = this.getTileHeadPos(x, y);
                        double = true;
                        isHead = headPos.x === x && headPos.y === y;
                    }

                    // Push single rooms and push the head room of double tiles
                    if (!double || isHead) {
                        bestPathByIndex[index] = {
                            tunnel: true,
                            path: [
                                [ xStart, yStart ],
                                [ x, y ],
                            ],
                        };
                    }
                }

                const thisTileId = this.getTileAt(x, y) % 100;
                const thisTile = Tiles[thisTileId];
                for (const [ dx, dy ] of directions) {
                    const index = makeIndex(x + dx, y + dy);
                    if (index === -1) continue;
                    if (visited[index]) continue;
                    // Cost to move to adjacent "room" is 0 if it's a double tile; otherwise, cost is 1
                    const adjTileId = this.getTileAt(x + dx, y + dy) % 100;
                    if (!adjTileId) continue;
                    const adjTile = Tiles[adjTileId];
                    const traversalCost = (thisTile && adjTile && thisTileId === adjTileId && thisTile.rooms[0].type === thisTile.rooms[1].type) ? 0 : 1;
                    if (distance + traversalCost <= maxDistance) {
                        queue.push({
                            x: x + dx,
                            y: y + dy,
                            distance: distance + traversalCost,
                        });
                    }
                }
            }
            return Object.values(bestPathByIndex).map(t => this.collapseDoubleTilesInTraversal(t));
        }

        getHandTilesEligibleForInventory() {
            const eligibleTileIds = [];
            const player = this.data.players[this.myPlayerId];

            // Cannot add any tiles while in solitary confinement
            if (player.inSolitary) return [];

            const hasShamrock = this.playerHasShamrockInInventory(this.myPlayerId);

            // If meeple is in a room that matches rooms shown on the current whistle
            // roll call tile then the player may add the associated contraband.
            const currentRoomType = this.getRoomAt(player.pos[0], player.pos[1]).type;
            const rollCallTileId = this.data.rollCall[this.data.whistlePos];
            const rollCallTile = RollCallTiles[rollCallTileId];
            if (rollCallTile[currentRoomType] !== undefined) {
                // Four is the absolute max capacity; three is the max
                // if there is no shamrock in the player's inventory.
                // No contraband can be added if we're at capacity.
                const inventoryCount = player.inventory.length;
                if (inventoryCount < 3 || (inventoryCount < 4 && hasShamrock)) {
                    const contraband = rollCallTile[currentRoomType];
                    for (const tileId of player.hand) {
                        if (Tiles[tileId].contains == contraband)
                            eligibleTileIds.push(tileId);
                    }
                }
            }

            // If meeple is in a Warder's Quarters or same room as the Chaplain...
            const { chaplain } = this.data.npcs;
            const chaplainPos = chaplain?.pos || [ -1, -1 ];
            const inRoomWithChaplain = chaplainPos[0] == player.pos[0] && chaplainPos[1] == player.pos[1];
            if (currentRoomType == RoomType.Quarters || inRoomWithChaplain) {
                // A Purple tool costs one teal contraband item; a Gold item and a
                // shamrock each cost two teal contraband items (or one shamrock!)
                const contrabandInInventory = player.inventory.filter(tileColorIs(ScrollColor.Blue)).length;
                if (contrabandInInventory >= 1) {
                    for (const tileId of player.hand)
                        if (Tiles[tileId].color == ScrollColor.Purple)
                            eligibleTileIds.push(tileId);
                }
                if (contrabandInInventory >= 2) {
                    for (const tileId of player.hand)
                        if (Tiles[tileId].color == ScrollColor.Gold || Tiles[tileId].color == ScrollColor.Green)
                            eligibleTileIds.push(tileId);
                }
            }

            return eligibleTileIds;
        }

        findEmptyCellsAdjacentToTiles() {
            const directions = [ [ 1, 0 ], [ -1, 0 ], [ 0, 1 ], [ 0, -1 ] ];
            const availableCells = [];
            for (let x = 0; x < 14; x++) {
                for (let y = 0; y < 14; y++) {
                    if (this.getTileAt(x, y))
                        continue;

                    const index = makeIndex(x, y);
                    availableCells[index] = 0;

                    // Test each direction from the current cell
                    for (const dir of directions) {
                        const xx = x + dir[0];
                        const yy = y + dir[1];
                        if (this.getTileAt(xx, yy)) {
                            availableCells[index] = availableCells[index] + 1;
                        }
                    }

                    // If we found a tile in all four directions, that means this cell is
                    // a 1x1 space and therefore there is no room to place a 2x1 tile.
                    if (availableCells[index] == 4) {
                        delete availableCells[index];
                    }
                }
            }
            return Object.keys(availableCells).map(parseIndex);
        }

        getLegalTileMoves() {
            const player = this.data.players[this.myPlayerId];
            const legalMoves = [];

            const availableCells = this.findEmptyCellsAdjacentToTiles();

            const orientations = [
                Orientation.NorthSouth,
                Orientation.EastWest,
                Orientation.SouthNorth,
                Orientation.WestEast,
            ];

            const goldTileIds = player.hand.filter(tileColorIs(ScrollColor.Gold));
            for (const tileId of goldTileIds)
                for (const [ x, y ] of availableCells)
                    for (const orientation of orientations)
                        if (this.isLegalTilePlacement(tileId, x, y, orientation, false))
                            legalMoves.push([ tileId, x, y, orientation ]);

            // A player must place a Gold tile if possible 
            if (legalMoves.length)
                return legalMoves;

            const otherTileIds = player.hand.filter(tileColorIsNot(ScrollColor.Gold));
            for (const tileId of otherTileIds)
                for (const [ x, y ] of availableCells)
                    for (const orientation of orientations)
                        if (this.isLegalTilePlacement(tileId, x, y, orientation, false))
                            legalMoves.push([ tileId, x, y, orientation ]);

            return legalMoves;
        }

        // Rules are different for starting tiles... the Corridor room must be adjacent
        // to the starting yard tile. Note: we don't need to check egress types because
        // every possible placement of the starting bunk tiles automatically follows the
        // egress matching rules.
        getLegalStartingTileMoves() {
            const tileId = this.getStartingBunkTileId(this.myPlayerId);

            // Starting tile is at (6, 6)... there are six adjacent cells.
            // The tail room (the Corridor) must be in one of these cells.
            let tailCells = [
                          [ 6, 5 ], [ 7, 5 ],
                [ 5, 6 ],                     [ 8, 6 ],
                          [ 6, 7 ], [ 7, 7 ],
            ];

            // Remove cells that are already occupied
            tailCells = tailCells.filter(([ x, y ]) => !this.getTileAt(x, y));

            const orientations = {
                [Orientation.NorthSouth]: [  0, -1 ],
                [Orientation.EastWest]:   [  1,  0 ],
                [Orientation.SouthNorth]: [  0,  1 ],
                [Orientation.WestEast]:   [ -1,  0 ],
            };

            // Calculate all moves based on the tail cells
            const moves = [];
            for (const [ tailX, tailY ] of tailCells) {
                for (const [ orientationString, [ dx, dy ] ] of Object.entries(orientations)) {
                    const orientation = Number(orientationString);
                    const headX = tailX + dx;
                    const headY = tailY + dy;
                    if (!this.getTileAt(headX, headY)) {
                        moves.push([ tileId, headX, headY, orientation ]);
                    }
                }
            }

            return moves;
        }

        getLegalWarderMoves(name) {
            const [ x, y ] = this.data.npcs[name].pos;
            return this.traverseAboveGround(ItemType.Whistle, x, y, 0, 3);
        }

        moveWarder(playerId, tileId, npcName, x, y) {
            this.discardTile(playerId, tileId);
            if (playerId == this.myPlayerId) {
                this.data.players[playerId].actionsPlayed++;
            }
            this.data.npcs[npcName].pos = [ x, y ];
        }

        movePlayer(playerId, tileId, x, y) {
            this.discardTile(playerId, tileId);
            this.setPlayerPosition(playerId, x, y);
            if (playerId == this.myPlayerId) {
                this.data.players[playerId].actionsPlayed++;
            }
        }

        sendToSolitaryConfinement(playerId) {
            const { x, y } = this.getTilePosition(SpecialTile.SolitaryConfinement);
            this.setPlayerPosition(playerId, x, y);
            this.players[playerId].inSolitary = true;
        }

        unshacklePlayer(playerId, tileId) {
            const player = this.players[playerId];
            player.shackleTile = null;

            this.addTileToGovernorInventory(tileId);
        }

        canEscape() {
            const player = this.data.players[this.myPlayerId];    
            return this.canEscapeWith(player.inventory);
        }
    
        calculateToolsNeededToEscape()
        {
            const player = this.data.players[this.myPlayerId];
            const [ x, y ] = player.pos;
            if (x != 1 && x != FledWidth - 2 && y != 1 && y != FledHeight - 2) {
                return [];
            }
    
            const { tileId } = unpackCell(this.getTileAt(x, y));
            const tile = Tiles[tileId];
            if (tile.rooms[0].type !== RoomType.Forest)
                return [];
            const toolNeeded = tile.rooms[1].escape; // Forest is always in the head room
            const isNight = this.data.whistlePos == this.data.openWindow;
            return isNight
                ? [ toolNeeded ]
                : [ toolNeeded, toolNeeded ];
        }

        canEscapeWith(tileIds) {
            const player = this.data.players[this.myPlayerId];
            const [ x, y ] = player.pos || [ -1, -1 ];
            if (x != 1 && x != FledWidth - 2 && y != 1 && y != FledHeight - 2) {
                return false;
            }
    
            for (const tileId of tileIds) {
                if (player.inventory.indexOf(tileId) === -1) {
                    return false;
                }
            }

            const { tileId } = unpackCell(this.getTileAt(x, y));
            const tile = Tiles[tileId];
            if (tile.rooms[0].type !== RoomType.Forest)
                return false;
            const toolNeeded = tile.rooms[1].escape; // Forest is always in the head room
            const isNight = this.data.whistlePos == this.data.openWindow;
            let toolsRemaining = isNight ? 1 : 2;
    
            for (const tileId of tileIds) {
                const item = getTileItem(tileId);
                if (item === ItemType.Shamrock)
                    toolsRemaining--;
                else if (item !== toolNeeded)
                    continue;
                else if (getTileColor(tileId) === ScrollColor.Gold)
                    toolsRemaining -= 2;
                else
                    toolsRemaining--;
            }
    
            return toolsRemaining <= 0;
        }

        countGovernorInventory() {
            return this.data.governorInventory.length;
        }

        resetActionsPlayed() {
            this.data.players[this.myPlayerId].actionsPlayed = 0;
        }

        getBoard() {
            return this.data.board;
        }

        getPlayerScore(playerId) {
            const player = this.data.players[playerId];

            let sum = player.inventory.reduce((agg, tileId) => agg + getTilePoints(tileId), 0);
            if (player.shackleTile)
                sum -= 1;
            if (player.escaped)
                sum += 5;

            return sum;
        }

        getPlayerByColor(color) {
            return Object.values(this.data.players).find(p => p.color === color);
        }

        getPlayerIds() {
            return this.data.order;
        }

        getPlayerPosition(playerId) {
            return this.data.players[playerId].pos;
        }

        setPlayerPosition(playerId, x, y) {
            this.data.players[playerId].pos = [ x, y ];
        }

        getMeeplesAt(x, y, isInSolitary = false) {
            const result = [];
            for (const { color, pos, inSolitary } of Object.values(this.data.players)) {
                if (!pos) continue; // pos is null until starting bunk is played
                if (pos[0] === x && pos[1] === y && inSolitary == isInSolitary) {
                    result.push(MeepleNames[color]);
                }
            }
            for (const [ name, npc ] of Object.entries(this.data.npcs)) {
                const { pos } = npc;
                if (pos[0] === x && pos[1] === y) {
                    result.push(name);
                }
            }
            return result;
        }

        getPlayersAt(x, y) {
            const result = [];
            for (const { color, pos, inSolitary } of Object.values(this.data.players)) {
                if (!pos) continue; // pos is null until starting bunk is played
                if (pos[0] === x && pos[1] === y && !inSolitary) {
                    result.push(MeepleNames[color]);
                }
            }
            return result;
        }

        getMoveCount() {
            return this.data.moves;
        }

        endTurn() {
            if (this.data.finalTurns !== null) {
                this.data.finalTurns--;
            }
        }

        toJson() {
            return JSON.stringify(data);
        }

        advanceWhistlePos() {
            this.data.whistlePos = (this.data.whistlePos + 4) % 5;
            return this.data.whistlePos;
        }

        get whistlePos() {
            return this.data.whistlePos;
        }

        get openWindow() {
            return this.data.openWindow;
        }

        get actionsPlayed() {
            return this.data.players[this.myPlayerId].actionsPlayed;
        }

        get isLastTurn() {
            return Object.values(this.data.players).some(p => p.escaped) && this.data.finalTurns > 0;
        }

        get isSetup() {
            return this.data.setup;
        }

        set isSetup(value) {
            this.data.setup = value;
        }

        get order() {
            return this.data.order;
        }

        get myHand() {
            return this.data.players[this.myPlayerId].hand;
        }

        get myInventory() {
            return this.data.players[this.myPlayerId].inventory;
        }

        get players() {
            return this.data.players;
        }

        get npcs() {
            return this.data.npcs;
        }

        playerHasShamrockInInventory(playerId) {
            return !!this.data.players[playerId].inventory.find(tileContains(ItemType.Shamrock));
        }

        addNpc(name, x, y) {
            this.data.npcs[name] = {
                pos: [ x, y ]
            };
        }

        advanceOpenWindow() {
            return --this.data.openWindow;
        }

        get openWindow() {
            return this.data.openWindow;
        }

        get rollCall() {
            return this.data.rollCall;
        }

        get governorInventory() {
            return this.data.governorInventory;
        }
    }

    function unique(array) {
        return Object.values(array.reduce((obj, item) => {
            obj[item] = item;
            return obj;
        }, {}));
    }

    return {
        PlayerColor,
        RoomType,
        EgressType,
        Empty,
        ScrollColor,
        ContrabandType,
        ToolType,
        ItemType,
        SpecialTile,
        Orientation,
        Direction,
        FledWidth,
        FledHeight,
        isDoubleTile,
        isWhistleTile,
        isShamrockTile,
        unpackCell,
        RollCallTiles,
        Tiles,
        MeepleNames,
        FledLogic,
    };
});