/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fled implementation : © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

define([
    "dojo","dojo/_base/declare",
    "bgagame/modules/PhilsUtils/PhilsUtils.core.v1",
    "bgagame/modules/FledLogic",
    "bgagame/modules/BounceFactory",
    "ebg/core/gamegui",
    "ebg/counter",
],
function (dojo, declare,
    { install, formatBlock, mapValues, applyMarkup, createFromTemplate, stringFromTemplate, invokeServerActionAsync },
    FledLogicModule,
    { animateDropAsync, bounceFactory }
) {
    const BgaGameId = `fled`;
    const BasePath = `${BgaGameId}/${BgaGameId}`;

    let fled; //: FledLogic

    const {
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
        isBoneTile,
        unpackCell,
        RollCallTiles,
        Tiles,
        MeepleNames,
        FledLogic,
    } = FledLogicModule;

    // How many milliseconds for different types of animations
    const ReactionDuration = 200; // Animation of a user's direct action that causes immediate change
    const ShortDuration = 400; // Animation of a user's direct action that causes less-immediate change
    const LongDuration = 800; // Animation of other events e.g. triggered by the system; or a game element moving across a large area

    // How many milliseconds to hover before showing a tooltip
    const ToolTipDelay = 500;

    // Max dimensions of the play surface

    const emTileWidth = 10;
    const emTileHeight = 10;
    const emRoomSize = 10;

    const pxBoardLeftPadding = 10;
    const pxBoardTopPadding = 11.5;
    const pxPerCellWidth = 116;
    const pxPerCellHeight = 116;
    const pxPerEm = 16;

    const DegFromOrientation = {
        [Orientation.NorthSouth]: 0,
        [Orientation.WestEast]: -90,
        [Orientation.SouthNorth]: 180,
        [Orientation.EastWest]: 90,
    };

    // Positioning of cards in the player hand by card count
    const PlayerHandSlots = {
        6: [
            { x:  5, y:  0.75, deg: -11 },
            { x: 15, y: -0.75, deg:  -6 },
            { x: 25, y: -1.5,  deg:  -2 },
            { x: 35, y: -1.5,  deg:   2 },
            { x: 45, y: -0.75, deg:   6 },
            { x: 55, y:  0.75, deg:  11 },
        ],
        5: [
            { x: 10, y:    0, deg: -11 },
            { x: 20, y: -1.5, deg:  -5 },
            { x: 30, y: -2.0, deg:   0 },
            { x: 40, y: -1.5, deg:   5 },
            { x: 50, y:    0, deg:  11 },
        ],
        4: [
            { x: 15, y: -0.75, deg: -6 },
            { x: 25, y: -1.5,  deg: -2 },
            { x: 35, y: -1.5,  deg:  2 },
            { x: 45, y: -0.75, deg:  6 },
        ],
        3: [
            { x: 20, y: -1.5, deg:  -5 },
            { x: 30, y: -2.0, deg:   0 },
            { x: 40, y: -1.5, deg:   5 },
        ],
        2: [
            { x: 25, y: -1.5,  deg: -2 },
            { x: 35, y: -1.5,  deg:  2 },
        ],
        1: [
            { x: 30, y: -2.0, deg:   0 },
        ],
    };

    const LeftSideHandSlots = {
        5: [
            { x:   5, y:  0.5, deg: -24 },
            { x: 9.1, y: -0.8, deg: -16 },
            { x:  14, y: -1.7, deg: -10 },
            { x:  19, y: -2.2, deg:  -5 },
            { x:  24, y: -2.2, deg:   0 },
        ],
        4: [
            { x: 9.1, y: -0.8, deg: -16 },
            { x:  14, y: -1.7, deg: -10 },
            { x:  19, y: -2.2, deg:  -5 },
            { x:  24, y: -2.2, deg:   0 },
        ],
    };

    const SoloHandSlots = {
        3: [
            { x:  0, y: -1.5, deg:  -5 },
            { x: 10, y: -2.0, deg:   0 },
            { x: 20, y: -1.5, deg:   5 },
        ],
        2: [
            { x:  5, y: -1.5,  deg: -2 },
            { x: 15, y: -1.5,  deg:  2 },
        ],
        1: [
            { x: 10, y: -2.0, deg:   0 },
        ],
    };


    function calculateTilePosition(xCell, yCell, orientation) {
        // For east->west and south->north, (xCell, yCell) is the bottom/right
        // so we need to offset our box up/left by one cell.
        xCell = xCell + (orientation === Orientation.EastWest ? -1 : 0);
        yCell = yCell + (orientation === Orientation.SouthNorth ? -1 : 0);

        // Account for the image border
        const emBaseOffsetX = 2;
        const emBaseOffsetY = 2;

        // Account for the tile rotation -- we rotate at the center
        // so need to translate a rotated tile back into position
        const isHorizontal = orientation === Orientation.EastWest || orientation == Orientation.WestEast;
        const emRotationOffsetX = isHorizontal ? emTileWidth / 2 : 0;
        const emRotationOffsetY = isHorizontal ? -emTileWidth / 2 : 0;

        return {
            xEm: emBaseOffsetX + emRotationOffsetX + xCell * emTileWidth,
            yEm: emBaseOffsetY + emRotationOffsetY + yCell * emTileHeight,
            wEm: isHorizontal ? 20 : 10,
            hEm: isHorizontal ? 10 : 20,
            deg: DegFromOrientation[orientation],
        };
    }

    const Preference = {
        ConfirmWhenMoving: 300,
        ConfirmWhenMovingNpc: 301,
        ConfirmWhenAddingToInventory: 302,
        ConfirmWhenSurrenderingTile: 303,
        ConfirmWhenDiscarding: 304,
        ConfirmWhenTakingFromGovernor: 305,
        ConfirmWhenEscaping: 306,
    };

    const ConfirmType = {
        Disabled: 1,
        Timer: 2,
        Enabled: 3,
    };

    return declare(`bgagame.${BgaGameId}`, ebg.core.gamegui, {
        constructor() {
            console.log(`${BgaGameId} constructor`);
            this.clientStateArgs = {
            };

            this.scoreCounter = {};

            Object.defineProperties(this, {
                amIActive: {
                    get() {
                        return parseInt(this.gamedatas.gamestate.active_player, 10);
                        // This is not a multiactiveplayer game
                        //return this.gamedatas.gamestate.multiactive.some(id => id == this.myPlayerId);
                    },
                },
            });
        },
        
        setup(gamedata) {
            console.log('Starting game setup', gamedata);

            // Hook into this object and overwrite default BGA functions with enhanced functions
            // Note: on Studio, the complete server state is sent to the client
            install(dojo, this, 'fled_surface', { debug: false /*!!gamedata.state*/ });

            const { data, scores, state } = gamedata;
            fled = new FledLogic(data, this.player_id);

            // State is only sent when hosted from Studio
            if (state) {
                window.fled = fled; // For convenience during debugging
                //addDebugUI(); // TODO

                // KILL: move into core js
                const menuDiv = document.getElementById('upperrightmenu');
                if (menuDiv) {
                    menuDiv.insertAdjacentHTML('afterbegin', '<div id="fled_debug-menu-button" class="upperrightmenu_item"><div class="fa fa-cloud-upload fa-lg" style="padding: 1em; cursor: pointer; margin-left: -1.25em;"></div></div>');
                    const debugButtonDiv = document.getElementById('fled_debug-menu-button');
                    debugButtonDiv?.addEventListener('click', () => {
                        const pageDiv = document.getElementById('page-content');
                        pageDiv.insertAdjacentHTML('beforeend', '<div id="fled_debug-set-state" style="position: fixed; padding: 1em; top: 15vh; left: 50%; width: 60vw; height: 70vh; display: grid; grid-template-rows: auto 1fr; border: solid .5em rgba(0, 0, 0, .75); transform: translate(-50%, 0%); background: rgba(196, 196, 169, .95); box-shadow: 0.5em 0.5em 1em rgba(0, 0, 0, .5); z-index: 1000;"><h2 style="margin-top: 0; font-size: 1.5em;">Set State</h2><form style="display: grid; width: 100%; grid-template-rows: 1fr auto;"><textarea style="resize: none; width: 100%; font-size: 1.25em; font-family: monospace;"></textarea><div style="margin-top: 1em; display: flex; gap: 1em;"><button type="submit" style="border: solid 1px black; width: auto; padding: .6em 1em; display: inline-block; cursor: pointer; border-radius: .3em;">Set State</button><button type="button" style="border: solid 1px black; width: auto; padding: .6em 1em; display: inline-block; cursor: pointer; border-radius: .3em;">Cancel</button></div></form></div>');
                        const setStateDiv = document.getElementById('fled_debug-set-state');
                        function close() {
                            setStateDiv.parentElement.removeChild(setStateDiv);
                        }
                        const form = setStateDiv.querySelector('form');
                        const textArea = form.querySelector('textarea');
                        textArea.value = state;
                        const cancelButton = form.querySelector('button[type="button"]');
                        form.addEventListener('submit', e => {
                            void (async () => {
                                try {
                                    await invokeServerActionAsync('debugSetState', fled.moveNumber, {
                                        s: btoa(textArea.value.trim()),
                                    });
                                    close();
                                }
                                catch (err) {
                                    console.error(err);
                                }
                            })();
                            e.preventDefault();
                            return false;
                        });
                        cancelButton.addEventListener('click', close);
                        textArea.focus();
                    });
                }
            }

            this.toolTipText = mapValues({
                'refZone-0': _('A *key* lets you move your pawn from one room to the next through any door, or doors, connecting them. /*Note:* Some “rooms” occupy the entire tile and are considered one room./'),
                'refZone-1': _('A *shoe* lets you move your pawn from one room to the next through *two* adjacent archways. A single archway connected to a door or window would require a key or file, respectively.'),
                'refZone-2': _('A *file* lets you move your pawn from one room to the next through a window, or windows, connecting them'),
                'refZone-3': _('A *spoon* lets you move your pawn from one room with a tunnel to any other room with a tunnel up to three (six if doubled) orthogonal rooms away.'),
                'refZone-4': _('A *shamrock* is a good-luck charm and gives you special privileges! First, it is “*wild*” and may count as any single-use tool.'),
                'refZone-5': _('A tool on a *purple scroll* costs 1 contraband item'),
                'refZone-6': _('A tool on a *gold scroll* doubles the symbol’s effect.'),
                'refZone-7': _('A *shamrock* or a tool on a *gold scroll* costs 2 contraband items.'),
                'refZone-8': _('A *whistle* lets you activate and move one warder 0-3 rooms and then target any prisoner occupying his same room. Warders may move through archways or doors, but not tunnels or windows!'),
                'refZone-9': _('If your meeple is in a *warder’s quarters*, or in the same room with the *chaplain*, you may transfer a tool or shamrock from your hand to your inventory by paying the cost in contraband items from your inventory.  /Thematically, you are sneakily trading contraband for escape tools or a good-luck charm!/'),
                'prisZone-0': _('*Step 1: Add 1 Tile to the Prison* || At the beginning of your turn, you _must_ *add a tile* from your hand to the prison |||| *Placement Rules* || First, you _must_ lay a tile with a *Gold Scroll* if possible. |||| Second, you must connect at least one-half of your tile to a matching room type (yard to a yard), and you may never place a tile in a way that connects a *door* to a *window* |||| Third, the Forest must make up the perimeter of the prison, which is exactly six squares from the starting tile, and never closer. No other room type may occupy the sixth square, nor may you add rooms beyond the sixth square. |||| If none of the tiles in your hand can be added to the prison, you must discard one tile to the *Governor’s inventory*'),
                'prisZone-1': _('*Step 2: Play 2 More Tiles* || *2a. Discard a tile to move your pawn or a warder* || Tile items depicted on the purple and gold scrolls are *tools*. When discarded from your hand, tools allow you to move your pawn (or a warder if playing a whistle) orthogonally from room to room. A tool on a *gold scroll* doubles the symbol’s effect. A *shamrock* is *“wild”* and may count as any single-use tool'),
                'prisZone-2': _('*Step 2: Play 2 More Tiles* || *2b. Surrender a tile into the Governor’s inventory* || If you have unplayable tiles, or hope to draw better tiles, surrender a tile to the Governor! Simply place it face up to the right of Governor in his inventory. /Tip: While tiles in the Governor’s inventory may be drawn by other players, they can also be drawn by you later/'),
                'prisZone-3': _('*Step 2: Play 2 More Tiles* || *2c. Add a tile to your inventory* || Tile items depicted on the teal scrolls are *contraband*. If your meeple is in a room matching either of the *room posters* on the roll call tile with the whistle charm, you may transfer the matching contraband item from your hand face up into your inventory at no cost. /Thematically, you are finding contraband in that room!/ |||| If your meeple is in a *warder’s quarters*, or in the same room with the *chaplain*, you may transfer a tool or shamrock from your hand to your inventory by paying the cost in contraband items from your inventory. /Thematically, you are sneakily trading contraband for escape tools or a good-luck charm!/'),
                'prisZone-4': _('*Step 3: Replenish Your Hand* || Replenish your hand back up to 5 tiles by drawing from the draw stacks. If you wish, _one_ of the tiles drawn _may_ come from the *Governor’s inventory*. '),
                'prisZone-5': _('Your inventory is to the right of your prisoner tile and can hold up to 3 tiles (4 if you have one or more *shamrocks* in your inventory)'),
                'cbZone-0': _('Stamps in The Bunks'),
                'cbZone-1': _('Combs in The Washroom'),
                'cbZone-2': _('Buttons in The Yard'),
                'cbZone-3': _('Plum Cake in The Mess Hall'),
            }, applyMarkup);

            // Only load expansion graphics if needed
            if (fled.getOption('houndExpansion') || fled.getOption('specterExpansion')) {
                this.ensureSpecificGameImageLoading([
                    'extras/fled_tiles3.png',
                ]);
            }

            createFromTemplate('fled_Templates.piles', {
                DRAW: _('Draw Pile'),
                DISCARD: _('Discard Pile'),
            }, 'fled_surface', { placement: 'afterbegin' });

            this.updateDrawPile();
            this.updateDiscardPile();

            document.getElementById('fled_board-button-collapse')?.addEventListener('click', () => this.onClickCollapseMap());
            document.getElementById('fled_board-button-expand')?.addEventListener('click', () => this.onClickExpandMap());            

            this.createMiniMap();
            this.instantZoomTo(0, 0, 1);
            this.animateSmartZoomAsync();
            
            // Layout tiles on the board and minimap
            this.createBoard();

            //
            // Create the Governor's Area
            //
            for (let i = 0; i < 4; i++) {
                this.createRollCallTile(fled.rollCall[i], i === fled.openWindow);
            }
            this.createRollCallTile('governor', true);
            this.createWhistle(fled.whistlePos);

            for (const tileId of fled.governorInventory) {
                this.createTileInGovernorInventory(tileId);
            }

            this.myPlayerId = this.player_id;
            const playerIds = Object.keys(fled.players).map(s => parseInt(s, 10));
            this.otherPlayerId = playerIds.filter(id => id !== this.myPlayerId).shift();

            const sortMeFirst = (a, b) => {
                if (a == this.myPlayerId) return -1;
                if (b == this.myPlayerId) return 1;
                // For spectator mode, the order doesn't really matter
                return Number(a) - Number(b);
            }

            // Note: in case of a spectator, myPlayerId is going to
            // be the observer rather than one of the actual players.
            for (const playerId of [ ...playerIds ].sort(sortMeFirst)) {
                const { color, hand, inventory, shackleTile } = fled.players[playerId]
                const { name } = gameui.gamedatas.players[playerId];
                this.createPlayerArea(playerId, name, color, hand, inventory, shackleTile);

                if (playerIds.length === 1) {
                    const handDivId = `fled_player-${playerId}-hand`;
                    createFromTemplate('fled_Templates.soloHand', {}, handDivId, { placement: 'afterend' });
                }

                this.scoreCounter[playerId] = new ebg.counter();
                this.scoreCounter[playerId].create(`player_score_${playerId}`);
                setTimeout(() => {
                    this.scoreCounter[playerId].setValue(scores[playerId]);
                }, 0);
            }

            if (fled.isSoloGame) {
                if (fled.data.specterHand?.length) {
                    this.moveHandToTheLeft();
                    fled.data.specterHand.forEach((tileId, index) => {
                        this.createTileInHand(index, fled.data.specterHand.length, tileId, false, true); 
                    });
                }
            }

            // Set the bottom sticky CSS rule so the player hand is always at least partially visible
            const playerAreasDiv = document.getElementById('fled_player-areas');
            const [ , pxString ] = /^(\d+(?:\.\d+)?)px$/.exec(getComputedStyle(playerAreasDiv).height);
            const bottomSticky = 7 * pxPerEm - parseFloat(pxString);
            playerAreasDiv.style.bottom = `${bottomSticky}px`;

            // Add some tool tips
            for (let i = 0; i < 10; i++) {
                const html = this.format_block('fled_Templates.tooltip', {
                    TYPE: `ref-zone-${i}`,
                    TEXT: this.toolTipText[`refZone-${i}`],
                });
                this.addTooltipHtmlToClass(`fled_ref-tile-zone-${i}`, html, ToolTipDelay);
            }

            for (let i = 0; i < 6; i++) {
                const html = this.format_block('fled_Templates.tooltip', {
                    TYPE: `prisoner-zone-${i}`,
                    TEXT: this.toolTipText[`prisZone-${i}`],
                });
                this.addTooltipHtmlToClass(`fled_prisoner-tile-zone-${i}`, html, ToolTipDelay);
            }

            if (fled.needMove2) {
                this.setClientStateForSecondMove();
            }
        },


        ///////////////////////////////////////////////////
        //// UI Helpers

        calculatePileHeight(nTiles) {
            if (nTiles > 45) return 15;
            if (nTiles > 40) return 14;
            if (nTiles > 35) return 13;
            if (nTiles > 30) return 12;
            if (nTiles > 25) return 11;
            if (nTiles > 20) return 10;
            if (nTiles > 15) return 9;
            if (nTiles > 10) return 8;
            if (nTiles > 7) return 7;
            if (nTiles > 5) return 6;
            if (nTiles > 4) return 5;
            if (nTiles > 3) return 4;
            if (nTiles > 2) return 3;
            if (nTiles > 1) return 2;
            if (nTiles > 0) return 1;
            return 0;
        },

        updateDrawPile() {
            this.updatePile('fled_draw-pile', fled.data.drawPile);
        },
        
        updateDiscardPile() {
            this.updatePile('fled_discard-pile', fled.data.discardPile);
        },
        
        updatePile(pileDivId, n) {
            const desiredPileSize = this.calculatePileHeight(n);
            const deckDiv = document.getElementById(pileDivId);
            let actualPileSize = deckDiv.childElementCount - 2;
            while (actualPileSize < desiredPileSize) {
                createFromTemplate('fled_Templates.tile', {
                    DIV_ID: `${pileDivId}-${actualPileSize}`,
                    CLASS: 'fled_back',
                    X_EM: 5,
                    Y_EM: -5,
                    DEG: 90,
                    Y_DEG: 180,
                }, deckDiv, { placement: 'afterbegin' });
                actualPileSize++;
            }
            while (actualPileSize > desiredPileSize) {
                deckDiv.removeChild(deckDiv.firstElementChild);
                actualPileSize--;
            }

            const countDiv = document.getElementById(`${pileDivId}-count`);
            countDiv.dataset.count = n;
        },

        createRollCallTile(rollCallTileIndex, isOpen) {
            const divId = `fled_tile-rc-${rollCallTileIndex}`;
            dojo.place(this.format_block('fled_Templates.rollCallTile', {
                DIV_ID: divId,
                CLASS: isOpen ? '' : 'fled_back',
                X_EM: 0,
                Y_EM: 0,
                DEG: 0,
            }), 'fled_governors-area');
        },

        createWhistle(pos) {
            dojo.place(this.format_block('fled_Templates.whistle', {
                POS: pos.toString(),
            }), 'fled_governors-area');
            const div = document.getElementById('fled_whistle-host');
            return div;
        },

        createTileOnDrawPile(tileId) {
            const divId = `fled_tile-${tileId}`;
            createFromTemplate('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: `${divId} fled_back`,
                X_EM: 5,
                Y_EM: -5,
                DEG: 90,
                Y_DEG: 180,
            }, 'fled_draw-pile', { placement: 'beforeend' });
            return document.getElementById(divId);
        },

        createTileInGovernorInventory(tileId, isHidden = false) {
            const divId = `fled_tile-${tileId}`;
            const className = `${divId} ${isHidden ? 'fled_hidden' : ''}`;
            dojo.place(this.format_block('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: className,
                X_EM: 0,
                Y_EM: 0,
                DEG: 0,
                Y_DEG: 0,
            }), 'fled_governors-area');

            this.adjustGovernorsInventoryMargin();

            const div = document.getElementById(divId);
            div.addEventListener('click', () => this.onClickSelectableTile(tileId));
            return div;
        },

        createTileOnPlayerBoard(playerId, tileId) {
            const divId = `fled_tile-${tileId}`;
            dojo.place(this.format_block('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: `${divId} fled_hidden`,
                X_EM: 0,
                Y_EM: 0,
                DEG: 0,
                Y_DEG: 180,
            }), `player_board_${playerId}`);
            const tileDiv = document.getElementById(divId);
            tileDiv.addEventListener('click', () => this.onClickSelectableTile(tileId));
            return tileDiv;
        },

        createBoard() {
            // Look through each cell of the board...
            // Find the 'head' of each tile and render it.
            const done = {};
            for (let x = 0; x < FledWidth; x++) {
                for (let y = 0; y < FledHeight; y++) {                    
                    // Get the tile at the current location; skip to next if nothing here
                    const tileIdAndOrientation = fled.getTileAt(x, y);
                    if (!tileIdAndOrientation) continue;

                    // Unpack the cell; skip to next if we've already added this tile
                    const { tileId, orientation } = unpackCell(tileIdAndOrientation);
                    if (done[tileId]) continue;

                    const headPos = fled.getTileHeadPos(x, y);
                    this.createTileOnBoard(tileId, headPos.x, headPos.y, orientation);
                    this.createTileOnMiniMap(tileId, headPos.x, headPos.y, orientation);

                    done[tileId] = true;
                }
            }

            // Place player meeples on the board (but don't place
            // any until after the game setup has been finished)
            if (!Object.values(fled.players).some(p => !p.pos)) {
                for (const player of Object.values(fled.players)) {
                    this.createMeeple(MeepleNames[player.color], player);
                }
            }

            // Place non-player charaters on the board
            for (const [ name, npc ] of Object.entries(fled.npcs)) {
                if (!npc) continue;
                this.createMeeple(name, npc);
            }
        },

        createMeeple(name, { pos }, hidden = false) {
            const divId = `fled_meeple-${name}`;
            const { xEm, yEm, z } = this.calculateMeeplePosition(name, pos[0], pos[1]);
            dojo.place(this.format_block('fled_Templates.meeple', {
                DIV_ID: divId,
                TYPE: name.replace(/[^a-z]/ig, ''),
                X_EM: xEm,
                Y_EM: yEm,
                Z: z,
            }), 'fled_board');
            const meepleDiv = document.getElementById(divId);
            if (!hidden) {
                meepleDiv.classList.remove('fled_hidden');
            }
            meepleDiv.addEventListener('click', () => this.onClickMeeple(name));
            return meepleDiv;
        },

        createTileOnBoard(tileId, x, y, orientation, hidden = false) {
            if (!tileId) return;
            const divId = `fled_tile-${tileId}`;
            const className = divId + (hidden ? ' fled_hidden' : '');
            const { xEm, yEm, deg } = calculateTilePosition(x, y, orientation);
            dojo.place(this.format_block('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: className,
                X_EM: xEm,
                Y_EM: yEm,
                DEG: deg,
                Y_DEG: 0,
            }), 'fled_board');
            const tileDiv = document.getElementById(divId);
            tileDiv.addEventListener('click', () => this.onClickBoardTile(tileId));
            this.createTileTooltip(tileId, deg);
            return tileDiv;
        },

        createTilePlaceholderOnBoard(x, y, orientation) {
            const divId = `fled_tile-placeholder-${x}-${y}`;
            const { xEm, yEm, deg } = calculateTilePosition(x, y, orientation);
            dojo.place(this.format_block('fled_Templates.tilePlaceholder', {
                DIV_ID: divId,
                X_EM: xEm,
                Y_EM: yEm,
                DEG: deg,
            }), 'fled_board');
            return document.getElementById(divId);
        },

        createTileOnMiniMap(tileId, x, y, orientation) {
            if (!tileId) return;
            const divId = `fled_tile-${tileId}-mini`;
            const className = `fled_tile-${tileId}`;
            const { xEm, yEm, deg } = calculateTilePosition(x, y, orientation);
            dojo.place(this.format_block('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: className,
                X_EM: xEm,
                Y_EM: yEm,
                DEG: deg,
                Y_DEG: 0,
            }), 'fled_minimap');
        },

        createMiniMap() {
            dojo.place(this.format_block('fled_Templates.miniMap', {
            }), 'fled_table');
            const miniMapDiv = document.getElementById('fled_minimap');
            miniMapDiv.addEventListener('click', e => this.onClickMiniMap(e));
        },

        createPlayerArea(playerId, playerName, color, hand, inventory, shackleTile) {
            dojo.place(this.format_block('fled_Templates.playerArea', {
                PID: parseInt(playerId, 10),
                NAME: playerName,
                COLOR: color,
            }), 'fled_player-areas');

            // Only need a hand area for the owning playing
            if (playerId != this.myPlayerId) {
                const div = document.getElementById(`fled_player-${playerId}-hand`);
                div?.parentElement.removeChild(div);
            }

            // Shackle tile is only known for this player
            if (shackleTile) {
                this.createTileAsPlayerShackle(playerId, shackleTile);
            }
            
            for (let i = 0; i < 4; i++) {
                dojo.place(this.format_block('fled_Templates.inventorySlot', {
                    PID: parseInt(playerId, 10),
                    INDEX: i,
                }), `fled_inventory-${playerId}`);
            }

            // Hide the fourth slot if the player doesn't have a shamrock in inventory
            if (!fled.playerHasShamrockInInventory(playerId)) {
                document.getElementById(`fled_inventory-${playerId}-slot-3`).classList.add('fled_hidden');
            }
            for (let i = 0; i < inventory.length; i++) {
                const tileId = inventory[i];
                this.createTileInInventory(playerId, tileId, i);
            }

            // Only show hand tiles for this player
            if (playerId == this.myPlayerId) {
                for (let i = 0; i < hand.length; i++) {
                    const tileId = hand[i];
                    this.createTileInHand(i, hand.length, tileId);
                }
            }

            // Add the tool tips for each of the zones on the player reference card
            for (let i = 0; i < 10; i++) {
                dojo.place(this.format_block('fled_Templates.refTileZone', {
                    PID: parseInt(playerId, 10),
                    INDEX: i,
                }), `fled_reference-tile-${playerId}`);
            }

            // Add the tool tips for each of the zones on the player prisoner card
            for (let i = 0; i < 6; i++) {
                dojo.place(this.format_block('fled_Templates.prisonerTileZone', {
                    PID: parseInt(playerId, 10),
                    INDEX: i,
                }), `fled_prisoner-tile-${playerId}`);
            }
        },

        createInventorySlot(playerId, index) {
            dojo.place(this.format_block('fled_Templates.inventorySlot', {
                PID: parseInt(playerId, 10),
                INDEX: index,
            }), `fled_inventory-${PID}`);
        },

        createTileInHand(index, count, tileId, hidden, solo = false) {
            const divId = `fled_tile-${tileId}`;
            const className = `${divId} ${hidden ? 'fled_hidden' : ''}`;
            const handSlots = (solo ? SoloHandSlots : PlayerHandSlots)[count];
            const { x, y, deg } = handSlots[index];
            dojo.place(this.format_block('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: className,
                X_EM: x,
                Y_EM: y,
                DEG: deg,
                Y_DEG: 0,
            }), solo ? `fled_solo-hand` : `fled_player-${this.myPlayerId}-hand`);
            const tileDiv = document.getElementById(divId);
            tileDiv.addEventListener('click', () => this.onClickSelectableTile(tileId));
            return tileDiv;
        },

        createTileTooltip(tileId, deg) {
            if (isDoubleTile(tileId)) {
                const isHorizontal = Math.abs(deg) === 90;
                const html = this.format_block('fled_Templates.doubleRoomTooltip', {
                    TILE_ID: tileId,
                    CLASS: isHorizontal ? 'fled_tile-horizontal' : 'fled_tile-vertical',
                    X_EM: isHorizontal ? 10 : 2.5,
                    Y_EM: isHorizontal ? -2.5 : 5,
                    DEG: deg,
                });
                this.removeTooltip(`fled_tile-${tileId}`);
                this.addTooltipHtml(`fled_tile-${tileId}`, html, ToolTipDelay);
            }
            else {
                this.createRoomTooltip(tileId, 0, deg);
                this.createRoomTooltip(tileId, 1, deg);
            }
        },

        createRoomTooltip(tileId, index, deg) {
            const tileDiv = this.getTileDiv(tileId);
            const divId = `fled_room-${tileId}-${index}`;
            if (!tileDiv.querySelector(`#${divId}`)) {
                tileDiv.insertAdjacentHTML('beforeend', `<div id="${divId}" class="fled_room fled_room-${index}"></div>`);
            }
            const html = this.format_block('fled_Templates.roomTooltip', {
                TILE_ID: tileId,
                ROOM: index,
                X_EM: 2.5,
                Y_EM: 2.5,
                DEG: deg,
            });
            this.removeTooltip(divId);
            this.addTooltipHtml(divId, html, ToolTipDelay);
        },

        createTileAsPlayerShackle(playerId, tileId) {
            // Note: tileId only given for us; opponents show as undefined
            const divId = tileId ? `fled_tile-${tileId}` : `fled_tile-shackle-${playerId}`;
            const className = `${tileId ? divId : ''} fled_back`;
            dojo.place(this.format_block('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: className,
                X_EM: 0,
                Y_EM: 0,
                DEG: 0,
                Y_DEG: 0,
            }), `fled_shackle-slot-${playerId}`);
            const div = document.getElementById(divId);
            div.addEventListener('click', () => this.onClickSelectableTile(tileId));
        },

        createTileInInventory(playerId, tileId, slotIndex) {
            const divId = `fled_tile-${tileId}`;
            dojo.place(this.format_block('fled_Templates.tile', {
                DIV_ID: divId,
                CLASS: divId,
                X_EM: 0,
                Y_EM: 0,
                DEG: 0,
                Y_DEG: 0,
            }), `fled_inventory-${playerId}-slot-${slotIndex}`);
            const div = document.getElementById(divId);
            div.addEventListener('click', () => this.onClickSelectableTile(tileId));
            return div;
        },

        createTileSlot(x, y, o) {
            // Create slot for the head cell
            this.createSlot(x, y);

            // Create slot for the tail cell
            switch (o) {
                case Orientation.NorthSouth: this.createSlot(x, y + 1); break;
                case Orientation.EastWest: this.createSlot(x - 1, y); break;
                case Orientation.SouthNorth: this.createSlot(x, y - 1); break;
                case Orientation.WestEast: this.createSlot(x + 1, y); break;
            }
        },

        createSlot(x, y) {
            const divId = `fled_slot-${x}-${y}`;
            const existing = document.getElementById(divId);
            if (existing) return;
            const baseOffsetX = 2;
            const baseOffsetY = 2;
            dojo.place(this.format_block('fled_Templates.slot', {
                DIV_ID: divId,
                X_EM: baseOffsetX + x * emTileWidth + 1,
                Y_EM: baseOffsetY + y * emTileHeight + 1,
            }), 'fled_board');
            const slotDiv = document.getElementById(divId);
            slotDiv.addEventListener('click', () => this.onClickSlot(x, y));
        },

        createSlotsForLegalMoves(tileId) {
            const moves = this.clientStateArgs.legalTileMoves;
            const tileMoves = moves.filter(move => move[0] === tileId);
            for (const [ , x, y, o ] of tileMoves) {
                this.createTileSlot(x, y, o);
            }
        },

        createDestinationSlot(x, y) {
            let dx = 1, dy = 1;
            const { tileId, orientation } = unpackCell(fled.getTileAt(x, y));
            if (isDoubleTile(tileId)) {
                if (orientation === Orientation.EastWest) { x--; dx++; }
                if (orientation === Orientation.WestEast) dx++;
                if (orientation === Orientation.NorthSouth) dy++;
                if (orientation === Orientation.SouthNorth) { y--; dy++; }
            }
            const divId = `fled_slot-${x}-${y}`;
            const existing = document.getElementById(divId);
            if (existing) return existing;
            const baseOffsetX = 2;
            const baseOffsetY = 2;
            dojo.place(this.format_block('fled_Templates.slot', {
                DIV_ID: divId,
                X_EM: baseOffsetX + x * emTileWidth + .2,
                Y_EM: baseOffsetY + y * emTileHeight + .2,
            }), 'fled_board');
            const slotDiv = document.getElementById(divId);
            slotDiv.classList.add('fled_destination');
            if (dy > 1) slotDiv.classList.add('fled_vertical');
            if (dx > 1) slotDiv.classList.add('fled_horizontal');
            slotDiv.addEventListener('click', () => this.onClickSlot(x, y));
            return slotDiv;
        },

        createRotateButton(xUnit, yUnit, moveCount) {
            let buttonDiv = document.getElementById('fled_rotate-button');
            if (!buttonDiv) {
                dojo.place(this.format_block('fled_Templates.rotateButton', {
                    TEXT: _('Rotate'),
                }), 'fled_board');
                buttonDiv = document.getElementById('fled_rotate-button');
                buttonDiv.addEventListener('click', () => this.onClickRotateButton());
            }

            // Center of the selected board cell
            const { xEm, yEm } = calculateTilePosition(xUnit, yUnit, Orientation.NorthSouth);
            buttonDiv.style.left = `${xEm + emTileWidth / 2}rem`;
            buttonDiv.style.top = `${yEm + emTileHeight / 2}rem`;
            buttonDiv.classList.remove('fled_no-transition');
            this.reflow(buttonDiv);
            buttonDiv.classList.remove('fled_hidden');

            if (moveCount > 1) {
                buttonDiv.classList.remove('fled_disabled');
            }
            else {
                buttonDiv.classList.add('fled_disabled');
            }
        },

        destroyRotateButton() {
            const rotateButtonDiv = document.getElementById('fled_rotate-button');
            rotateButtonDiv?.parentElement.removeChild(rotateButtonDiv);
        },

        destroyAllSlots() {
            const slotDivs = [ ...document.getElementsByClassName('fled_slot') ];
            for (const element of slotDivs) {
                element.parentElement.removeChild(element);
            }
        },

        deselectAll(baseDivId = 'fled_body') {
            const baseDiv = document.getElementById(baseDivId);
            const selectedDivs = Array.from(baseDiv.querySelectorAll('.fled_selected'));
            for (const div of selectedDivs) {
                div.classList.remove('fled_selected');
            }
        },


        ///////////////////////////////////////////////////
        //// Game & client states

        async onEnteringState(stateName, state) {
            if (!this.isCurrentPlayerActive()) {
                fled.resetActionsPlayed();
                const lastTurnDiv = document.getElementById('fled_last-turn');
                lastTurnDiv?.parentElement.removeChild(lastTurnDiv);
                return;
            }

            console.log(`Entering state: ${stateName}`, state);

            document.getElementById('fled_body').classList.add(`fled_state-${stateName}`);
            
            switch (stateName) {
                case 'addStarterTile': {
                    this.clientStateArgs.legalTileMoves = fled.getLegalStartingTileMoves();
                    const tileId = fled.getStartingBunkTileId(this.myPlayerId);
                    this.clientStateArgs.alreadyPlaced = false;
                    this.clientStateArgs.selectedTileId = tileId;
                    if (!this.clientStateArgs.starterTileAdded) {
                        this.clientStateArgs.starterTileAdded = true;
                        await this.animateDrawTileAsync(this.myPlayerId, tileId);
                    }
                    const tileDiv = this.getTileDiv(tileId);
                    tileDiv.classList.add('fled_selectable');
                    tileDiv.classList.add('fled_selected');
                    this.createSlotsForLegalMoves(tileId);
                    await this.animateSmartZoomAsync();
                    break;
                }
                case 'addTile': {
                    const moves = fled.getLegalTileMoves();
                    this.clientStateArgs.alreadyPlaced = false;
                    this.clientStateArgs.legalTileMoves = moves;
                    if (moves.length) {
                        await this.animateSmartZoomAsync();
                        const eligibleTiles = Object.keys(moves.reduce((obj, move) => ({ ...obj, [move[0]]: true}), {})).map(s => Number(s));
                        this.makeTilesSelectable(eligibleTiles);
                    }
                    else {
                        this.destroyAllSlots();
                        this.setClientState('client_discardTile', {
                            descriptionmyturn: _('None of your tiles can be added to the prison. ${you} must select one to discard'),
                        });
                    }
                    break;
                }
                case 'addGhostTile': {
                    const moves = fled.getLegalSpecterTileMoves();
                    this.clientStateArgs.alreadyPlaced = false;
                    this.clientStateArgs.legalTileMoves = moves;
                    if (moves.length) {
                        await this.animateSmartZoomAsync();
                        const eligibleTiles = Object.keys(moves.reduce((obj, move) => ({ ...obj, [move[0]]: true}), {})).map(s => Number(s));
                        this.makeTilesSelectable(eligibleTiles, 'fled_solo-hand');
                    }
                    else {
                        this.destroyAllSlots();
                        this.setClientState('client_surrenderGhostTile', {
                            descriptionmyturn: _('None of the tiles can be added to the prison. ${you} must select one to surrender'),
                        });
                    }
                    break;
                }
                case 'client_selectSlot':
                case 'client_selectSpecterSlot':
                    this.makeTilesNonSelectable();
                    break;

                case 'client_discardTile':
                    this.makeTilesSelectable(fled.myHand);
                    break;

                case 'discardGhostTile':
                    this.makeTilesSelectable(fled.getLegalSpecterDiscards());
                    break;

                case 'client_surrenderGhostTile':
                    this.makeTilesSelectable(fled.getLegalSpecterSurrenders());
                    break;

                case 'playTiles': {
                    await this.animateSmartZoomAsync();
                    if (fled.actionsPlayed < 1) {
                        this.setClientState('client_playFirstAction', {
                            descriptionmyturn: applyMarkup(_('${you} must perform the /first/ of two actions')),
                        });
                    }
                    else {
                        this.setClientState('client_playSecondAction', {
                            descriptionmyturn: applyMarkup(_('${you} must perform the /second/ of two actions')),
                        });
                    }
                    break;
                }
                case 'client_playFirstAction':
                case 'client_playSecondAction':
                    delete this.clientStateArgs.selectedTileId;
                    delete this.clientStateArgs.selectedInventoryTileIds;
                    delete this.clientStateArgs.selectedCoords;
                    delete this.clientStateArgs.selectedNpc;
                    delete this.clientStateArgs.selectedPlayer;
                    delete this.clientStateArgs.npcMoves;
                    this.destroyAllSlots();
                    this.makeMeeplesNonSelectable();
                    this.makeTilesNonSelectable();
                    this.deselectAll();
                    break;

                case 'client_selectTileForMovement': {
                    const playerHand = document.getElementById(`fled_player-${this.myPlayerId}-hand`);
                    playerHand.scrollIntoView({ block: 'end', inline: 'center', behavior: 'smooth' });
                    break;
                }
                case 'client_selectPlayerDestination':
                    const moves = fled.getLegalMovementMoves(this.clientStateArgs.selectedTileId);
                    for (const { path } of moves) {
                        const [ x, y ] = path[path.length - 1];
                        this.createDestinationSlot(x, y);
                    }
                    break;

                case 'client_selectHoundDestination':
                    const houndMoves = fled.getLegalHoundMoves();
                    for (const { path } of houndMoves) {
                        const [ x, y ] = path[path.length - 1];
                        this.createDestinationSlot(x, y);
                    }
                    break;

                case 'client_selectSelfOrNpcToMove':
                    this.makeSelfSelectable();
                    this.makeHoundSelectable();
                case 'client_selectWarderOrSpecterToMove':
                    this.makeSpecterSelectable();
                case 'client_selectWarderToMove':
                    this.makeWardersSelectable();
                    break;

                case 'client_selectWarderDestination':
                    const warderMoves = fled.getLegalWarderMoves(this.clientStateArgs.selectedNpc);
                    for (const { path } of warderMoves) {
                        const [ x, y ] = path[path.length - 1];
                        this.createDestinationSlot(x, y);
                    }
                    break;

                case 'client_selectSpecterDestination':
                    const specterMoves = fled.getLegalSpecterMoves();
                    for (const { path } of specterMoves) {
                        const [ x, y ] = path[path.length - 1];
                        this.createDestinationSlot(x, y);
                    }
                    break;

                case 'client_selectPlayerToTarget': {
                    const { x, y } = this.clientStateArgs.selectedCoords;
                    const playersInRoom = fled.getPlayersAt(x, y);
                    this.makeMeeplesSelectable(playersInRoom);
                    break;
                }
                case 'client_confirmNpcMovement': {
                    const { npcMoves } = this.clientStateArgs;
                    for (const [ , x, y, targetPlayerColor ] of npcMoves) {
                        const { tileId } = unpackCell(fled.getTileAt(x, y));
                        const headPos = fled.getTileHeadPos(x, y);
                        const pos = isDoubleTile(tileId) ? headPos : { x, y };
                        if (targetPlayerColor) {
                            const meepleDiv = document.getElementById(`fled_meeple-${targetPlayerColor}`);
                            meepleDiv.classList.add('fled_selected');
                        }
                        else {
                            const slotDiv = this.createDestinationSlot(pos.x, pos.y); 
                            slotDiv.classList.add('fled_selected');
                        }
                    }
                    break;
                }
                case 'client_selectTileForInventory': {
                    // TODO: sticky on playerArea breaks this. try putting scroll anchor below the player area
                    // TODO: the solution is scroll-margin-top/-left/-bottom?
                    const playerArea = document.getElementById(`fled_player-area-${this.myPlayerId}`);
                    const tilesDiv = playerArea.querySelector('.fled_player-area-tiles');
                    tilesDiv.scrollIntoView({ block: 'end', inline: 'center', behavior: 'smooth' });
                    break;
                }
                case 'client_selectInventoryTiles':
                    const contrabandTileIds = fled.myInventory.filter(tileId => Tiles[tileId].color === ScrollColor.Blue);
                    this.makeTilesSelectable(contrabandTileIds, `fled_inventory-${this.myPlayerId}`);
                    this.clientStateArgs.selectedInventoryTileIds = [];
                    break;

                case 'client_selectTileForSurrender': {
                    const playerHand = document.getElementById(`fled_player-${this.myPlayerId}-hand`);
                    playerHand.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
                    break;
                }
                case 'client_selectTilesForEscape':
                    const playerArea = document.getElementById(`fled_player-area-${this.myPlayerId}`);
                    const tilesDiv = playerArea.querySelector('.fled_player-area-tiles');
                    tilesDiv.scrollIntoView({ block: 'end', inline: 'center', behavior: 'smooth' });

                    this.clientStateArgs.selectedInventoryTileIds = [];
                    this.makeTilesNonSelectable();
                    this.makeTilesSelectable(fled.getInventoryTilesEligibleForEscape(), `fled_inventory-${this.myPlayerId}`);
                    break;

                case 'drawTiles':
                    delete this.clientStateArgs.selectedTileId;
                    delete this.clientStateArgs.selectedInventoryTileIds;
                    delete this.clientStateArgs.selectedCoords;
                    delete this.clientStateArgs.selectedNpc;
                    delete this.clientStateArgs.selectedPlayer;
                    this.destroyAllSlots();
                    this.makeMeeplesNonSelectable();
                    this.makeTilesNonSelectable();
                    this.deselectAll();

                    this.makeTilesSelectable(fled.governorInventory);
                    break;

                case 'client_selectGovernorTile': {
                    const governorsAreaDiv = document.getElementById('fled_governors-area');
                    governorsAreaDiv.scrollIntoView({ block: 'start', inline: 'center', behavior: 'smooth' });
                    this.makeTilesSelectable(fled.governorInventory);
                    break;
                }
            }

            if (fled.isLastTurn) {
                const pageTitleDiv = document.getElementById('page-title');
                pageTitleDiv.insertAdjacentHTML('beforeend', '<div id="fled_last-turn"></div>');
                const lastTurnDiv = document.getElementById('fled_last-turn');
                lastTurnDiv.innerText = _('This is your last turn!');
            }
        },

        onLeavingState(stateName) {
            if (!this.isCurrentPlayerActive()) return;
            console.log(`Leaving state: ${stateName}`);

            document.getElementById('fled_body').classList.remove(`fled_state-${stateName}`);

            switch (stateName) {
                case 'client_selectTileForMovement':
                    this.makeTilesNonSelectable();
                    break;

                case 'client_selectSelfOrNpcToMove': // fall through
                case 'client_selectWarderOrSpecterToMove':
                case 'client_selectWarderToMove':
                    this.makeMeeplesNonSelectable();
                    break;

                case 'client_selectWarderDestination':
                case 'client_selectSpecterDestination':
                    this.destroyAllSlots();
                    break;

                case 'client_selectPlayerToTarget':
                    this.makeMeeplesNonSelectable();
                    break;

                case 'client_confirmNpcMovement':
                    this.makeMeeplesNonSelectable();
                    break;

                case 'client_selectTileForInventory':
                    this.makeTilesNonSelectable();
                    break;

                case 'client_selectInventoryTiles':
                    this.makeTilesNonSelectable(`fled_inventory-${this.myPlayerId}`);
                    break;

                case 'client_selectTilesForEscape':
                    this.makeTilesNonSelectable();
                    break;
            }
        }, 

        onUpdateActionButtons(stateName, args) {
            if (!this.isCurrentPlayerActive()) return;
            
            switch (stateName) {
                case 'addTile':
                    if (fled.canEscape()) {
                        this.addActionButton(`fled_button-escape`, _('Escape (free action)'), () => this.onClickEscape());
                        const escapeButton = document.getElementById('fled_button-escape');
                        escapeButton.insertAdjacentHTML('afterbegin', '<div id="fled_button-escape-icon" class="fled_action-button-icon"></div>');
                    }
                    break;

                case 'client_confirmDiscard':
                    this.addConfirmButton(`fled_button-confirm-discard`, Preference.ConfirmWhenDiscarding, this.onClickConfirmDiscard);
                    this.addActionButton(`fled_button-cancel-discard`, _('Cancel'), () => this.onClickCancelDiscard(), null, false, 'red');
                    break;

                case 'client_confirmTilePlacement':
                case 'client_confirmSpecterTilePlacement':
                    this.addConfirmButton(`fled_button-confirm-placement`, null, this.onClickConfirmTilePlacement); 
                    if (this.last_server_state.name !== 'addStarterTile') {
                        this.addActionButton(`fled_button-cancel-placement`, _('Cancel'), () => this.onClickCancelTilePlacement(), null, false, 'red'); 
                    }
                    break;

                case 'client_selectSlot':
                case 'client_selectSpecterSlot':
                    this.addActionButton(`fled_button-cancel-slot`, _('Cancel'), () => this.onClickCancelTilePlacement(), null, false, 'red'); 
                    break;

                case 'client_playFirstAction':
                case 'client_playSecondAction': {
                    this.addActionButton(`fled_button-move`, _('Move'), () => this.onClickMoveButton());
                    this.addActionButton(`fled_button-add`, _('Add to Inventory'), () => this.onClickAddToInventoryButton());
                    this.addActionButton(`fled_button-surrender`, _('Surrender'), () => this.onClickSurrenderButton());

                    this.clientStateArgs.tilesForMovement = fled.getHandTilesEligibleForMovement();
                    const moveButton = document.getElementById('fled_button-move');
                    moveButton.insertAdjacentHTML('afterbegin', '<div id="fled_button-move-icon" class="fled_action-button-icon"></div>');
                    if (!this.clientStateArgs.tilesForMovement.length) {
                        moveButton.classList.add('disabled');
                    }

                    this.clientStateArgs.tilesForInventory = fled.getHandTilesEligibleForInventory();
                    const addButton = document.getElementById('fled_button-add');
                    addButton.insertAdjacentHTML('afterbegin', '<div id="fled_button-add-icon" class="fled_action-button-icon"></div>');
                    if (!this.clientStateArgs.tilesForInventory.length) {
                        addButton.classList.add('disabled');
                    }

                    const surrenderButton = document.getElementById('fled_button-surrender');
                    surrenderButton.insertAdjacentHTML('afterbegin', '<div id="fled_button-surrender-icon" class="fled_action-button-icon"></div>');

                    if (fled.canEscape()) {
                        this.addActionButton(`fled_button-escape2`, _('Escape (free action)'), () => this.onClickEscape());
                        const escapeButton = document.getElementById('fled_button-escape2');
                        escapeButton.insertAdjacentHTML('afterbegin', '<div id="fled_button-escape-icon" class="fled_action-button-icon"></div>');
                    }
                    break;
                }
                case 'client_selectTileForMovement':
                    this.addActionButton(`fled_button-cancel-movement`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    break;

                case 'client_selectPlayerDestination':
                    this.addActionButton(`fled_button-cancel-movement2`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    break;

                case 'client_selectHoundDestination':
                    this.addActionButton(`fled_button-cancel-hound-movement`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    break;

                case 'client_confirmMovement':
                    this.addConfirmButton(`fled_button-confirm-movement`, Preference.ConfirmWhenMoving, () => this.onClickConfirmMovement());
                    this.addActionButton(`fled_button-cancel-movement3`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    break;

                case 'client_selectSelfOrNpcToMove':
                    this.addActionButton(`fled_button-cancel-self-or-warder-movement`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    break;

                case 'client_selectWarderOrSpecterToMove':
                case 'client_selectWarderToMove':
                    if (!fled.needMove2) {
                        this.addActionButton(`fled_button-cancel-warder-movement`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    }
                    break;
                    
                case 'client_selectWarderDestination':
                    if (!fled.needMove2) {
                        this.addActionButton(`fled_button-cancel-warder-movement2`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    }
                    break;

                case 'client_selectSpecterDestination':
                    if (!fled.needMove2) {
                        this.addActionButton(`fled_button-cancel-specter-movement`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    }
                    break;

                case 'client_selectPlayerToTarget':
                    this.addActionButton(`fled_button-cancel-warder-movement3`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    break;

                case 'client_confirmNpcMovement':
                    this.addConfirmButton(`fled_button-confirm-warder-movement`, Preference.ConfirmWhenMovingNpc, this.onClickConfirmNpcMovement);
                    this.addActionButton(`fled_button-cancel-warder-movement4`, _('Cancel'), () => this.onClickCancelMove(), null, false, 'red');
                    break;

                case 'client_selectTileForInventory':
                    this.addActionButton(`fled_button-cancel-add`, _('Cancel'), () => this.onClickCancelAddToInventory(), null, false, 'red');
                    break;

                case 'client_selectInventoryTiles':
                    this.addActionButton(`fled_button-cancel-add2`, _('Cancel'), () => this.onClickCancelAddToInventory(), null, false, 'red');
                    break;

                case 'client_confirmAddToInventory':
                    this.addConfirmButton(`fled_button-confirm-add`, Preference.ConfirmWhenAddingToInventory, this.onClickConfirmAddToInventory);
                    this.addActionButton(`fled_button-cancel-add`, _('Cancel'), () => this.onClickCancelAddToInventory(), null, false, 'red');
                    break;
    
                case 'client_selectTileForSurrender':
                    this.addActionButton(`fled_button-cancel-surrender`, _('Cancel'), () => this.onClickCancelSurrender(), null, false, 'red');
                    break;

                case 'client_confirmSurrender':
                    this.addConfirmButton(`fled_button-confirm-surrender`, Preference.ConfirmWhenSurrenderingTile, this.onClickConfirmSurrender);
                    this.addActionButton(`fled_button-cancel-surrender`, _('Cancel'), () => this.onClickCancelSurrender(), null, false, 'red');
                    break;

                case 'client_selectTilesForEscape':
                    this.addConfirmButton(`fled_button-confirm-escape`, Preference.ConfirmWhenEscaping, this.onClickConfirmEscape, true);
                    this.addActionButton(`fled_button-cancel-escape`, _('Cancel'), () => this.onClickCancelEscape(), null, false, 'red');
                    break;

                case 'drawTiles':
                    const cardsNeeded = 5 - fled.myHand.length;
                    const canTakeAndDraw = fled.governorInventory.length && fled.data.drawPile + fled.data.discardPile + 1 >= cardsNeeded;
                    const canDrawAll = fled.data.drawPile + fled.data.discardPile >= cardsNeeded;

                    const takeLabel = this.format_string(_('Take 1 from Governor and Draw ${n}'), { n: cardsNeeded - 1 });
                    this.addActionButton(`fled_button-draw-take1-draw-rest`, takeLabel, () => this.onClickTake1Draw2());

                    if (!canTakeAndDraw) {
                        document.getElementById(`fled_button-draw-take1-draw-rest`).classList.add('disabled');
                    }

                    const drawLabel = this.format_string(_('Draw all ${n}'), { n: cardsNeeded });
                    this.addActionButton(`fled_button-draw-draw-all`, drawLabel, () => this.onClickDrawAll());

                    if (!canDrawAll) {
                        document.getElementById(`fled_button-draw-draw-all`).classList.add('disabled');
                    }

                    if (!canTakeAndDraw && !canDrawAll) {
                        this.addActionButton(`fled_button-draw-none`, _('Draw none'), () => this.onClickDrawAll());
                    }

                    if (fled.canEscape()) {
                        this.addActionButton(`fled_button-escape3`, _('Escape!'), () => this.onClickEscape());
                        const escapeButton = document.getElementById('fled_button-escape3');
                        escapeButton.insertAdjacentHTML('afterbegin', '<div id="fled_button-escape-icon" class="fled_action-button-icon"></div>');
                    }
                    break;
                
                case 'client_selectGovernorTile':
                    this.addActionButton(`fled_button-cancel-take`, _('Cancel'), () => this.onClickCancelTakeFromGovernor(), null, false, 'red');
                    break;

                case 'client_confirmGovernorTile':
                    this.addConfirmButton(`fled_button-confirm-take`, Preference.ConfirmWhenTakingFromGovernor, this.onClickConfirmTakeFromGovernor);
                    this.addActionButton(`fled_button-cancel-take`, _('Cancel'), () => this.onClickCancelTakeFromGovernor(), null, false, 'red');
                    break;
            }
        },        


        ///////////////////////////////////////////////////
        //// Utility methods

        reflow(element = document.documentElement) {
            void(element.offsetHeight);
        },

        raiseElement(divOrId) {
            const div = typeof divOrId === 'string' ? document.getElementById(divOrId) : divOrId;
            let cur = div;
            let totalLeft = 0;
            let totalTop = 0;
            const destDiv = document.getElementById('fled_body');
            while (cur !== destDiv) {
                totalLeft += cur.offsetLeft;
                totalTop += cur.offsetTop;
                cur = cur.offsetParent;
            }
            div.style.left = `${totalLeft}px`;
            div.style.top = `${totalTop}px`;
            div.style.right = '';
            div.style.bottom = '';
            div.style.position = 'absolute';
            div.style.zIndex = '1';
            destDiv.appendChild(div);
        },
        
        addConfirmButton(divId, prefId, fnConfirm, disabled) {
            // Reset the timer in case there was already one running
            // and e.g. player chose a different tile.
            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            let confirmButton = document.getElementById(divId);
            if (confirmButton) {
                const countdownSvg = confirmButton.querySelector('svg');
                countdownSvg?.parentElement.removeChild(countdownSvg);
            }
            else {
                this.addActionButton(divId, _('Confirm'), () => {
                    clearInterval(this.clientStateArgs.confirmCountdownIntervalId);
                    fnConfirm.call(this);
                });
            }

            confirmButton = document.getElementById(divId);
            if (disabled) {
                confirmButton.classList.add('disabled');
                return;
            }
            else {
                confirmButton.classList.remove('disabled');
            }

            if (prefId && this.prefs[prefId].value == ConfirmType.Timer) {
                const buttonDiv = document.getElementById(divId);
                const html = this.format_block('fled_Templates.confirmCountdown', {});
                buttonDiv.insertAdjacentHTML('beforeend', html);
                const svgPath = buttonDiv.querySelector('path');
                if (svgPath) {
                    const totalTicks = 12;
                    let ticksRemaining = totalTicks;
                    this.clientStateArgs.confirmCountdownIntervalId = setInterval(() => {
                        if (--ticksRemaining <= 0) {
                            svgPath.parentElement.removeChild(svgPath);
                            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);
                            this.clientStateArgs.confirmCountdownIntervalId = 0;
                            fnConfirm.call(this);
                            return;
                        }
                        const theta = -Math.PI / 2 - 2 * Math.PI * ticksRemaining / totalTicks;
                        const x = Math.round(50 * Math.cos(theta)) + (ticksRemaining === 12 ? 50.1 : 50); // HACK: add .1 so we start will a full circle
                        const y = Math.round(50 * Math.sin(theta)) + 50;
                        const s = ticksRemaining >= totalTicks / 2 ? 1 : 0;
                        const d = `M50 50 L50,0 A50,50 0 ${s} 0 ${x},${y}Z`;
                        svgPath.setAttribute('d', d);
                    }, 1000);
                }
            }
        },

        adjustGovernorsInventoryMargin() {
            // Adjust the margin if necessary (because the Governor's Area is scaled down
            // by 0.8... but only the appearance is shrunken, not the actual DOM elements)
            // This ensures that the apparent gap between the Governor's area row and the
            // game board stays consistent regardless of how many tiles are added. 
            const goverorsAreaDiv = document.getElementById('fled_governors-area');
            const rowCount = Math.ceil((Array.from(goverorsAreaDiv.children).length - 1) / 8);

            // Tile height is 20em; 20% of 20em is 4em.
            goverorsAreaDiv.style.marginBottom = `-${4 * rowCount}em`;
        },

        calculateRoomPosition(xCell, yCell) {
            const { tileId, orientation } = unpackCell(fled.getTileAt(xCell, yCell));
            const tile = Tiles[tileId];
            const headPos = fled.getTileHeadPos(xCell, yCell);
            let wEm = emRoomSize;
            let hEm = emRoomSize;

            if (isDoubleTile(tileId)) {
                // For east->west and south->north, (xCell, yCell) is the bottom/right
                // so we need to offset our box up/left by one cell.
                xCell = headPos.x + (orientation === Orientation.EastWest ? -1 : 0);
                yCell = headPos.y + (orientation === Orientation.SouthNorth ? -1 : 0);
                if (orientation === Orientation.EastWest || orientation == Orientation.WestEast) {
                    wEm *= 2;
                }
                else {
                    hEm *= 2;
                }
            }

            // Account for the image border
            const emBaseOffsetX = 2;
            const emBaseOffsetY = 2;

            return {
                xEm: emBaseOffsetX + xCell * emRoomSize,
                yEm: emBaseOffsetY + yCell * emRoomSize,
                wEm,
                hEm,
            };
        },

        makeSelfSelectable() {
            const { color, inSolitary } = fled.players[this.myPlayerId];
            if (!inSolitary) {
                const name = MeepleNames[color];
                this.makeMeeplesSelectable([ name ]);
            }
        },

        makeWardersSelectable() {
            this.makeMeeplesSelectable([ 'warder1', 'warder2', 'warder3', 'chaplain' ]);
        },

        makeHoundSelectable() {
            this.makeMeeplesSelectable([ 'hound' ]);
        },

        makeSpecterSelectable() {
            this.makeMeeplesSelectable([ 'specter' ]);
        },

        makeMeeplesSelectable(names) {
            const baseDiv = document.getElementById('fled_board');
            const divs = Array.from(baseDiv.querySelectorAll(names.map(n => `#fled_meeple-${n}`).join()));
            for (const div of divs) {
                div.classList.add('fled_selectable');
            }
        },

        makeMeeplesNonSelectable() {
            const baseDiv = document.getElementById('fled_board');
            const meepleDivs = Array.from(baseDiv.querySelectorAll('.fled_meeple'));
            for (const div of meepleDivs) {
                div.classList.remove('fled_selectable');
            }
        },

        makeTilesSelectable(tileIds, baseDivId = 'fled_body') {
            const baseDiv = document.getElementById(baseDivId);
            for (const tileId of tileIds) {
                const tileDiv = baseDiv.querySelector(`#fled_tile-${tileId}`);
                if (!tileDiv) continue;
                tileDiv.classList.add('fled_selectable');
            }
        },

        makeTilesNonSelectable(baseDivId = 'fled_body') {
            const baseDiv = document.getElementById(baseDivId);
            const tileDivs = Array.from(baseDiv.querySelectorAll('.fled_selectable'));
            for (const tileDiv of tileDivs) {
                tileDiv.classList.remove('fled_selectable');
            }
        },

        deselectAllTiles() {
            const fledBodyDiv = document.getElementById(`fled_body`);
            const tileDivs = Array.from(fledBodyDiv.querySelectorAll('.fled_selected'));
            for (const tileDiv of tileDivs) {
                tileDiv.classList.remove('fled_selected');
            }
        },
        
        /* DEBUG: This is a helper method to visualize the placement of various numbers of Meeples in a room
        setMeeples(n, x = 6, y = 6, inSolitary = false) {
            const divs = document.querySelectorAll('.fled_meeple');
            for (const div of divs) {
                div.parentElement.removeChild(div);
            }
            const names = [
                'yellow',
                'blue',
                'orange',
                'green',
                'warder1',
                'warder2',
                'warder3',
                'chaplain',
            ];
            for (const p of Object.values(fled.npcs)) p.pos = [ 0, 0 ];
            for (const p of Object.values(fled.players)) p.pos = [ 0, 0 ];

            const pos = [ x, y ];
            for (let i = 0; i < n; i++) {
                const index = MeepleNames.indexOf(names[i]);
                if (index >= 0) {
                    let player = Object.values(fled.players).filter(p => p.color == index).shift();
                    if (!player) {
                        player = {
                            pos,
                            color: index,
                            inSolitary,
                        };
                        fled.players[index] = player;
                    }
                    else {
                        player.pos = pos;
                        player.inSolitary = inSolitary;
                    }
                }
                else {
                    fled.npcs[names[i]] = { pos };
                }
            }
            for (let i = 0; i < n; i++) {
                this.createMeeple(names[i], { pos });
            }
        },
        */

        calculateMeeplePosition(name, xCell, yCell) {
            const { tileId, orientation } = unpackCell(fled.getTileAt(xCell, yCell));
            const isDouble = isDoubleTile(tileId);
            const isHorizontal = isDouble && (orientation == Orientation.EastWest || orientation == Orientation.WestEast);
            const { xEm, yEm } = this.calculateRoomPosition(xCell, yCell, orientation);

            // Single Room offsets per number of meeples in the room
            const singleRoomOffsets = {
                1: [
                    { x: 3.6, y: -2.0, z: 1 },
                ],
                2: [
                    { x: 3.6, y: -2.9, z: 1 },
                    { x: 1.6, y: 1.0,  z: 2 },
                ],
                3: [
                    { x: 4.8, y: -2.9, z: 1 },
                    { x: 3.3, y:  1.2, z: 3 },
                    { x: 0.8, y: -1.9, z: 2 },
                ],
                4: [
                    { x: 4.8, y: -2.9, z: 2 },
                    { x: 4.3, y:  1.2, z: 4 },
                    { x: 1,   y: -1.4, z: 1 },
                    { x: 0.7, y:  1.6, z: 3 },
                ],
                5: [
                    { x: 4.9, y: -3.4, z: 1 },
                    { x: 0.8, y: -2.5, z: 2 },
                    { x: 4.7, y:  1.9, z: 5 },
                    { x: 0.6, y:  1.9, z: 4 },
                    { x: 3.3, y:  0,   z: 3 },
                ],
                6: [
                    { x:  4.9, y: -3.4, z: 1 },
                    { x:  0.8, y: -2.5, z: 2 },
                    { x:  4.7, y:  1.9, z: 6 },
                    { x:  0.6, y:  1.9, z: 5 },
                    { x:  3.4, y:  0,   z: 4 },
                    { x: -0.9, y:  0,   z: 3 },
                ],
                7: [
                    { x:  4.9, y: -3.4, z: 1 },
                    { x:  0.8, y: -2.5, z: 2 },
                    { x:  4.7, y:  1.9, z: 7 },
                    { x:  0.6, y:  1.9, z: 6 },
                    { x:  3.3, y:  0,   z: 5 },
                    { x: -1.0, y:  0,   z: 4 },
                    { x:  6.7, y:  0,   z: 3 },
                ],
                8: [
                    { x:  4.9, y: -3.4, z: 1 },
                    { x:  0.8, y: -2.5, z: 2 },
                    { x:  4.7, y:  1.9, z: 7 },
                    { x:  0.6, y:  1.9, z: 6 },
                    { x:  3.3, y:  0,   z: 5 },
                    { x: -1.0, y:  0,   z: 4 },
                    { x:  6.7, y:  0,   z: 3 },
                    { x:  2.3, y:  3.3, z: 8 },
                ],
            }
            
            // Double Room (Horizontal)
            const horizontalRoomOffsets = {
                1: [
                    { x: 7.6, y: -2.0, z: 1 },
                ],
                2: [
                    { x: 7.6, y: -2.0, z: 1 },
                    { x: 3.0, y:  0.0, z: 2 },
                ],
                3: [
                    { x:  7.6, y: -2.0, z: 1 },
                    { x:  3.0, y:  0.0, z: 2 },
                    { x: 12.0, y:  0.2, z: 3 },
                ],
                4: [
                    { x:  6.9, y: -2.0, z: 1 },
                    { x:  3.0, y:  0.0, z: 3 },
                    { x: 10.5, y:  1.0, z: 4 },
                    { x: 13.0, y: -3.0, z: 2 },
                ],
                5: [
                    { x:  3.9, y: -3.0, z: 1 },
                    { x:  2.0, y:  1.0, z: 3 },
                    { x: 12.1, y:  1.0, z: 5 },
                    { x: 14.0, y: -3.0, z: 4 },
                    { x:  8.0, y: -1.0, z: 2 },
                ],
                6: [
                    { x:  3.1, y: -3.0, z: 1 },
                    { x:  1.0, y:  1.0, z: 3 },
                    { x: 12.1, y:  1.0, z: 6 },
                    { x: 14.0, y: -3.0, z: 4 },
                    { x:  8.5, y: -3.0, z: 2 },
                    { x:  6.5, y:  1.3, z: 5 },
                ],
                7: [
                    { x:  3.1, y: -3.0, z: 1 },
                    { x:  0.5, y:  1.0, z: 3 },
                    { x: 15.1, y:  1.0, z: 7 },
                    { x: 14.0, y: -3.0, z: 4 },
                    { x:  8.5, y: -3.0, z: 2 },
                    { x:  5.2, y:  1.3, z: 5 },
                    { x: 10.3, y:  1.3, z: 6 },
                ],
                8: [
                    { x:  1.1, y: -4.0, z: 1 },
                    { x:  0.5, y:  2.0, z: 6 },
                    { x: 15.1, y:  2.0, z: 8 },
                    { x: 15.0, y: -4.0, z: 3 },
                    { x:  8.0, y: -4.0, z: 2 },
                    { x:  4.8, y: -1.3, z: 4 },
                    { x: 11.3, y: -1.3, z: 5 },
                    { x:  7.8, y:  2.2, z: 7 },
                ],
            };

            // Double Room (Vertical)
            const verticalRoomOffsets = {
                1: [
                    { x: 2.6, y: 5.0, z: 1 },
                ],
                2: [
                    { x: 2.6, y: 2.0, z: 1 },
                    { x: 2.6, y: 8.0, z: 2 },
                ],
                3: [
                    { x: 2.6, y: -1.0, z: 1 },
                    { x: 2.6, y: 11.0, z: 3 },
                    { x: 2.6, y:  5.0, z: 2 },
                ],
                4: [
                    { x: 3.6, y: -1.0, z: 1 },
                    { x: 1.6, y: 11.0, z: 4 },
                    { x: 1.6, y:  3.0, z: 2 },
                    { x: 3.6, y:  7.0, z: 3 },
                ],
                5: [
                    { x: 2.6, y: -1.0, z: 1 },
                    { x: 0.4, y: 11.0, z: 5 },
                    { x: 0.4, y:  3.0, z: 3 },
                    { x: 2.6, y:  7.0, z: 4 },
                    { x: 4.8, y:  3.0, z: 2 },
                ],
                6: [
                    { x: 2.6, y: -1.0, z: 1 },
                    { x: 0.4, y: 11.0, z: 5 },
                    { x: 0.4, y:  3.0, z: 3 },
                    { x: 2.6, y:  7.0, z: 4 },
                    { x: 4.8, y:  3.0, z: 2 },
                    { x: 4.8, y: 11.5, z: 6 },
                ],
                7: [
                    { x: 2.6, y: -1.0, z: 1 },
                    { x: 0.4, y: 11.0, z: 6 },
                    { x: 0.4, y:  3.0, z: 3 },
                    { x: 4.8, y:  7.0, z: 5 },
                    { x: 4.8, y:  3.0, z: 2 },
                    { x: 4.8, y: 11.5, z: 7 },
                    { x: 0.4, y:  7.0, z: 4 },
                ],
                8: [
                    { x: 4.8, y: -1.0, z: 1 },
                    { x: 0.4, y: 11.0, z: 7 },
                    { x: 0.4, y:  3.0, z: 4 },
                    { x: 4.8, y:  7.0, z: 6 },
                    { x: 4.8, y:  3.0, z: 3 },
                    { x: 4.8, y: 11.5, z: 8 },
                    { x: 0.4, y:  7.0, z: 5 },
                    { x: 0.4, y: -1.0, z: 2 },
                ],
            };

            // Solitary Confinement - In the Hole
            const horizontalInSolitaryOffsets = {
                1: [
                    { x: 7.6, y: -0.5, z: 1 },
                ],
                2: [
                    { x: 8.0, y: -1.5, z: 1 },
                    { x: 7.2, y:  0.5, z: 2 },
                ],
            };

            // Solitary Confinement (horizontal) - Passing through
            const horizontalSolitaryConfinementRoomOffsets = {
                1: [
                    { x: 13.6, y: -1.0, z: 1 },
                ],
                2: [
                    { x: 13.6, y: -1.0, z: 2 },
                    { x:  2.0, y: -1.0, z: 1 },
                ],
                3: [
                    { x: 13.6, y: -1.0, z: 2 },
                    { x:  2.0, y: -3.0, z: 1 },
                    { x:  1.0, y:  1.5, z: 3 },
                ],
                4: [
                    { x: 14.6, y: -3.0, z: 2 },
                    { x:  2.0, y: -3.0, z: 1 },
                    { x:  1.0, y:  1.5, z: 3 },
                    { x: 13.6, y:  1.5, z: 4 },
                ],
                5: [
                    { x: 14.6, y: -3.0, z: 4 },
                    { x:  2.5, y: -3.5, z: 1 },
                    { x:  2.0, y:  2.0, z: 3 },
                    { x: 13.6, y:  1.5, z: 5 },
                    { x: -0.2, y: -0.5, z: 2 },
                ],
                6: [
                    { x: 13.6, y: -3.0, z: 4 },
                    { x:  2.5, y: -3.5, z: 1 },
                    { x:  2.0, y:  2.0, z: 3 },
                    { x: 13.1, y:  2.0, z: 6 },
                    { x: -0.2, y: -0.5, z: 2 },
                    { x: 16.1, y: -0.5, z: 5 },
                ],
                7: [
                    { x: 13.6, y: -3.0, z: 4 },
                    { x:  2.5, y: -3.5, z: 1 },
                    { x:  2.0, y:  2.0, z: 3 },
                    { x: 13.1, y:  2.0, z: 6 },
                    { x: -0.2, y: -0.5, z: 2 },
                    { x: 16.1, y: -0.5, z: 5 },
                    { x:  8.1, y: -4.5, z: 7 },
                ],
                8: [
                    { x: 13.6, y: -3.0, z: 4 },
                    { x:  2.5, y: -3.5, z: 1 },
                    { x:  2.0, y:  2.0, z: 3 },
                    { x: 13.1, y:  2.0, z: 6 },
                    { x: -0.2, y: -0.5, z: 2 },
                    { x: 16.1, y: -0.5, z: 5 },
                    { x:  8.1, y: -4.5, z: 7 },
                    { x:  7.5, y:  2.5, z: 8 },
                ],
            };

            // Solitary Confinement (vertical) - Passing through
            const verticalSolitaryConfinementRoomOffsets = {
                1: [
                    { x: 2.8, y: -2.0, z: 1 },
                ],
                2: [
                    { x: 2.8, y: -2.0, z: 1 },
                    { x: 2.8, y: 10.0, z: 2 },
                ],
                3: [
                    { x: 2.8, y: -2.0, z: 1 },
                    { x: 4.8, y:  9.5, z: 2 },
                    { x: 0.8, y: 11.0, z: 3 },
                ],
                4: [
                    { x: 4.8, y: -2.5, z: 1 },
                    { x: 4.8, y:  9.5, z: 3 },
                    { x: 0.8, y: 11.0, z: 4 },
                    { x: 0.8, y: -1.0, z: 2 },
                ],
                5: [
                    { x: 4.8, y: -2.5, z: 1 },
                    { x: 5.4, y:  9.5, z: 3 },
                    { x: 0.0, y: 10.0, z: 4 },
                    { x: 0.8, y: -1.0, z: 2 },
                    { x: 3.0, y: 12.0, z: 5 },
                ],
                6: [
                    { x: 5.2, y: -1.5, z: 2 },
                    { x: 5.4, y:  9.5, z: 4 },
                    { x: 0.0, y: 10.0, z: 5 },
                    { x: 0.0, y: -1.5, z: 3 },
                    { x: 3.0, y: 12.0, z: 6 },
                    { x: 3.0, y: -3.5, z: 1 },
                ],
                7: [
                    { x: 5.2, y: -1.5, z: 2 },
                    { x: 5.4, y:  9.5, z: 5 },
                    { x: 0.0, y: 10.0, z: 6 },
                    { x: 0.0, y: -1.5, z: 3 },
                    { x: 3.0, y: 12.0, z: 7 },
                    { x: 3.0, y: -3.5, z: 1 },
                    { x: 6.8, y:  3.0, z: 4 },
                ],
                8: [
                    { x:  5.2, y: -1.5, z: 2 },
                    { x:  5.4, y:  9.5, z: 6 },
                    { x:  0.0, y: 10.0, z: 7 },
                    { x:  0.0, y: -1.5, z: 3 },
                    { x:  3.0, y: 12.0, z: 8 },
                    { x:  3.0, y: -3.5, z: 1 },
                    { x:  6.8, y:  3.0, z: 4 },
                    { x: -1.6, y:  3.0, z: 5 },
                ],
            };
            
            // Solitary Confinement (vertical) - In the Hole (note: can only have 2 players max here in 4 player game)
            const verticalInSolitaryOffsets = {
                1: [
                    { x: 2.6, y: 4.0, z: 1 },
                ],
                2: [
                    { x: 3.6, y: 3.0, z: 1 },
                    { x: 1.6, y: 5.5, z: 2 },
                ],
            };

            const inSolitary = this.isInSolitary(tileId, name);
            const meeplesInRoom = fled.getMeeplesAt(xCell, yCell, inSolitary);
            const offsetsArray =
                tileId === SpecialTile.SolitaryConfinement
                    ? isHorizontal
                        ? inSolitary
                            ? horizontalInSolitaryOffsets
                            : horizontalSolitaryConfinementRoomOffsets
                        : inSolitary
                            ? verticalInSolitaryOffsets
                            : verticalSolitaryConfinementRoomOffsets
                    : isDouble
                        ? isHorizontal
                            ? horizontalRoomOffsets
                            : verticalRoomOffsets
                        : singleRoomOffsets;
            const offsets = offsetsArray[meeplesInRoom.length || 1];
            if (!offsets) throw new Error('Invalid count');
            const index = meeplesInRoom.length ? meeplesInRoom.indexOf(name) : 0;
            if (index < 0) throw new Error('Meeple not found');
            
            // Meeple position is the base room position plus the meeple offset
            const { x, y, z } = offsets[index];
            return {
                xEm: xEm + x,
                yEm: yEm + y,
                z,
            };
        },

        isInSolitary(tileId, name) {
            if (tileId % 100 !== SpecialTile.SolitaryConfinement) return false;
            const color = MeepleNames.indexOf(name);
            if (color === -1) return false;
            return !!fled.getPlayerByColor(color)?.inSolitary;
        },

        //
        // Calculate the smallest rectangle that contains all played tiles
        // and then add a configurable-sized border around them.
        //
        calculateSmartZoomCells(padding = 0) {
            // Note: if the game is still in the setup phase, we only want to show
            // a two-cell padding around the starting cell at (6, 6) - (7, 6)
            // because no tiles can be placed outside of that two-cell padding.
            let x1 = 6, y1 = 6;
            let x2 = 7, y2 = 6;

            if (fled.isSetup) {
                for (let x = 0; x < FledWidth; x++) {
                    for (let y = 0; y < FledHeight; y++) {
                        if (!fled.getTileAt(x, y)) continue; 
                        x1 = Math.min(x, x1);
                        x2 = Math.max(x2, x);
                        y1 = Math.min(y, y1);
                        y2 = Math.max(y2, y);
                    }
                }
            }

            // Calculate padding cells around the current bounds
            x1 = Math.max(0, x1 - padding);
            y1 = Math.max(0, y1 - padding);
            x2 = Math.min(x2 + padding, FledWidth - 1);
            y2 = Math.min(y2 + padding, FledHeight - 1);

            return { x1, y1, x2, y2 };
        },

        //
        // Calculate scale factor and board offsets
        //
        calculateZoom(cells, { noExpand = false } = {}) {
            const containerDiv = document.getElementById('fled_board-container');
            const containerRect = containerDiv.getBoundingClientRect();

            const boardRect = {
                width: 1644,
                height: 1531,
            };

            // Calculate the minimum area we want to show (at 1:1 scale)
            console.log(`Smart zoom on cells: ${JSON.stringify(cells)}`);

            const extraPadding = 0.1;
            let left =   Math.round(Math.max((cells.x1     - extraPadding) * pxPerCellWidth + pxBoardLeftPadding, 0));
            let right =  Math.round(Math.min((cells.x2 + 1 + extraPadding) * pxPerCellWidth + pxBoardLeftPadding, boardRect.width - 1));
            let top =    Math.round(Math.max((cells.y1     - extraPadding) * pxPerCellHeight + pxBoardTopPadding, 0));
            let bottom = Math.round(Math.min((cells.y2 + 1 + extraPadding) * pxPerCellHeight + pxBoardTopPadding, boardRect.height - 1));

            console.log(`Smart zoom: ${JSON.stringify({ left, top, right, bottom })}`);


            const pxMinWidth = right - left + 1;
            const pxMinHeight = bottom - top + 1;

            // Choose the scale that covers the minimum visible cells for both dimensions
            const hScale = containerRect.width / pxMinWidth;
            const vScale = containerRect.height / pxMinHeight;
            const visibleScale = Math.min(hScale, vScale);

            const cssEmWidth = 144; // This is how big the board is defined as in the CSS file (#fled_board)
            const multiplier = boardRect.width / pxPerEm / cssEmWidth;
            let scale = visibleScale * multiplier; // Scale needs to be multiplied to be in the scale relative to the CSS size

            // If the scale is so small that the board will take up less space
            // than the container, increase the scale so that the board fits
            // perfectly in the container (on the constrained dimension)
            const effectiveBoardWidth = cssEmWidth * pxPerEm * scale;
            if (effectiveBoardWidth < containerRect.width) {
                scale *= containerRect.width / effectiveBoardWidth;

                // Also trigger the board container to grow in height
                if (!noExpand) {
                    this.expandBoardContainer();
                }
            }

            const xUnit = ((cells.x2 - cells.x1 + 1) / 2 + cells.x1) * 2 / FledWidth - 1;
            const yUnit = ((cells.y2 - cells.y1 + 1) / 2 + cells.y1) * 2 / FledHeight - 1;

            console.log(`Zoom on point (${xUnit}, ${yUnit}) at x${scale}`);

            return this.calculateMap(xUnit, yUnit, scale);
        },

        expandBoardContainer() {
            // TODO: only expand by as much as necessary
            console.log('Expanding board container');
            this.clientStateArgs.boardContainerExpanded = true;
            const containerDiv = document.getElementById('fled_board-container');
            containerDiv.style.maxHeight = 'unset';
            const width = containerDiv.getBoundingClientRect().width;
            const height = width * FledHeight / FledWidth;
            containerDiv.style.height = `${height}px`;

            document.getElementById('fled_board-button-expand').classList.add('fled_hidden');
            document.getElementById('fled_board-button-collapse').classList.remove('fled_hidden');
            this.showHideMiniMap();
        },

        showHideMiniMap() {
            const containerDiv = document.getElementById('fled_board-container');
            const width = containerDiv.getBoundingClientRect().width;

            // Hide the minimap if we're looking at the full board already
            const miniMapDiv = document.getElementById('fled_minimap');
            const boardDiv = document.getElementById('fled_board');
            const boardWidth = boardDiv.getBoundingClientRect().width;
            if (Math.abs(width - boardWidth) < 10) {
                miniMapDiv.classList.add('fled_hidden');
            }
            else {
                miniMapDiv.classList.remove('fled_hidden');
            }
        },

        calculateMap(xUnit, yUnit, zoom) {
            const containerDiv = document.getElementById('fled_board-container');
            const containerRect = containerDiv.getBoundingClientRect();

            // Find center of visible area (containerDiv)
            const visibleOriginX = Math.round(containerRect.width / 2);
            const visibleOriginY = Math.round(containerRect.height / 2);

            const boardRect = {
                width: Math.round(2304 * zoom), // This is 144 em * 16 px/em,
                height: Math.round(2144 * zoom), // This is ~13/14 of the width
            };

            // Find the equivalent pixel in the board dimensions
            const xBoard = (xUnit + 1) / 2 * boardRect.width;
            const yBoard = (yUnit + 1) / 2 * boardRect.height;

            const xOffsetMax = Math.min(containerRect.width - boardRect.width, 0);
            const yOffsetMax = Math.min(containerRect.height - boardRect.height, 0); 

            // Center the board pixel in the visible area but then clamp
            // the value so we're not showing outside the image bounds.
            const xOffset = Math.round(Math.min(0, Math.max(visibleOriginX - xBoard, xOffsetMax)));
            const yOffset = Math.round(Math.min(0, Math.max(visibleOriginY - yBoard, yOffsetMax)));

            // Set the HUD window
            const hScale = 1 / zoom;
            const vScale = 1 / zoom;
            const leftBorder = Math.round(-xOffset * hScale);
            const topBorder = Math.round(-yOffset * vScale);
            const rightBorder = Math.round((boardRect.width - containerRect.width + xOffset) * hScale);
            const bottomBorder = Math.round((boardRect.height - containerRect.height + yOffset) * vScale);

            return { xOffset, yOffset, zoom, topBorder, rightBorder, bottomBorder, leftBorder };
        },

        instantZoomTo(xUnit, yUnit, zoom) {
            const boardDiv = document.getElementById('fled_board');
            const focusDiv = document.getElementById('fled_minimap-focus');

            const { xOffset, yOffset, topBorder, rightBorder, bottomBorder, leftBorder } = this.calculateMap(xUnit, yUnit, zoom);

            boardDiv.classList.add('fled_no-transition');
            focusDiv.classList.add('fled_no-transition');
            this.reflow();

            boardDiv.style.transform = `translate(${xOffset}px, ${yOffset}px) scale(${zoom})`;
            focusDiv.style.borderWidth = `${topBorder}px ${rightBorder}px ${bottomBorder}px ${leftBorder}px`;

            boardDiv.classList.remove('fled_no-transition');
            focusDiv.classList.remove('fled_no-transition');
        },

        //
        // Move the map and minimap to center on (x, y)
        // where the map bounds are from (-1, -1) to (1, 1)
        //
        async animateZoomToAsync(xUnit, yUnit, zoom) {
            const boardDiv = document.getElementById('fled_board');
            const focusDiv = document.getElementById('fled_minimap-focus');

            const { xOffset, yOffset, topBorder, rightBorder, bottomBorder, leftBorder } = this.calculateMap(xUnit, yUnit, zoom);

            const boardPromise = new Promise(resolve => {
                const timeoutId = setTimeout(done, 1000);
                function done() {
                    clearTimeout(timeoutId);
                    boardDiv.removeEventListener('transitionend', done);
                    boardDiv.removeEventListener('transitioncancel', done);
                    resolve();
                }
                boardDiv.addEventListener('transitionend', done, { once: true });
                boardDiv.addEventListener('transitioncancel', done, { once: true });
                
                // Shift the board to center on (x, y) at the appropriate scale
                boardDiv.style.transform = `translate(${xOffset}px, ${yOffset}px) scale(${zoom})`;
            });

            const miniMapPromise = new Promise(resolve => {
                const timeoutId = setTimeout(done, 1000);
                function done() {
                    clearTimeout(timeoutId);
                    focusDiv.removeEventListener('transitionend', done);
                    focusDiv.removeEventListener('transitioncancel', done);
                    resolve();
                }
                focusDiv.addEventListener('transitionend', done, { once: true });
                focusDiv.addEventListener('transitioncancel', done, { once: true });
                
                focusDiv.style.borderWidth = `${topBorder}px ${rightBorder}px ${bottomBorder}px ${leftBorder}px`;
            });

            await Promise.all([
                boardPromise,
                miniMapPromise,
            ]);
        },

        hasSmartZoomChanged(smartZoom) {
            const { currentSmartZoom } = this.clientStateArgs;
            if (!currentSmartZoom) {
                return true;
            }
            for (const prop of Object.getOwnPropertyNames(smartZoom)) {
                if (smartZoom[prop] !== currentSmartZoom[prop]) {
                    return true;
                }
            }
            return false;
        },

        async animateSmartZoomAsync({ force = false, noExpand = false } = {}) {
            const isAddingTiles =
                this.isCurrentPlayerActive() &&
                /(add.*Tile|client_confirm.*TilePlacement)/.test(this.currentState) &&
                !this.clientStateArgs.alreadyPlaced
            ;

            const cells = this.calculateSmartZoomCells(isAddingTiles ? 2 : 1);
            const smartZoom = this.calculateZoom(cells, { noExpand });
            if (!force && !this.hasSmartZoomChanged(smartZoom)) {
                return;
            }
            
            this.clientStateArgs.currentSmartZoom = smartZoom;

            const { xOffset, yOffset, zoom: scale, leftBorder, topBorder, rightBorder, bottomBorder } = smartZoom;
            this.clientStateArgs.currentScale = scale;

            const boardDiv = document.getElementById('fled_board');
            const focusDiv = document.getElementById('fled_minimap-focus');

            const boardPromise = new Promise(resolve => {
                const timeoutId = setTimeout(done, 1000);
                function done() {
                    clearTimeout(timeoutId);
                    boardDiv.removeEventListener('transitionend', done);
                    boardDiv.removeEventListener('transitioncancel', done);
                    resolve();
                }
                boardDiv.addEventListener('transitionend', done, { once: true });
                boardDiv.addEventListener('transitioncancel', done, { once: true });
                
                // Shift the board to center on (x, y) at the appropriate scale
                boardDiv.style.transform = `translate(${xOffset}px, ${yOffset}px) scale(${scale})`;
            });

            const miniMapPromise = new Promise(resolve => {
                const timeoutId = setTimeout(done, 1000);
                function done() {
                    clearTimeout(timeoutId);
                    focusDiv.removeEventListener('transitionend', done);
                    focusDiv.removeEventListener('transitioncancel', done);
                    resolve();
                }
                focusDiv.addEventListener('transitionend', done, { once: true });
                focusDiv.addEventListener('transitioncancel', done, { once: true });
                
                focusDiv.style.borderWidth = `${topBorder}px ${rightBorder}px ${bottomBorder}px ${leftBorder}px`;
            });

            await Promise.all([
                boardPromise,
                miniMapPromise,
            ]);

            this.showHideMiniMap();
        },

        async animateDropMeeplesOnBoardAsync() {
            const promises = fled.order.map(async (playerId, index) => {
                const player = fled.players[playerId];
                const meepleDiv = this.createMeeple(MeepleNames[player.color], player, 'player', true);
                
                // Raise the meeple up for the coming drop
                const dropHeight = 5; // ems
                const [ , x, y ] = /^translate\(([0-9.]+)em,\s?([0-9.]+)em\)$/.exec(meepleDiv.style.transform);
                meepleDiv.style.transform = `translate(${x}em, ${y - dropHeight}em)`;

                await this.delayAsync(index * 100); // Stagger each meeple by player order

                // Fade in
                meepleDiv.classList.remove('fled_hidden');
                await this.delayAsync(100); // opacity transition is 200ms... start dropping when half visible

                await animateDropAsync(meepleDiv, y - dropHeight, y, ShortDuration, bounceFactory(2, 0.02));
            });

            await Promise.all(promises);
        },

        async animateDropNpcOnBoardAsync(name) {
            const npc = fled.npcs[name];
            const npcDiv = this.createMeeple(name, npc, true);
            
            // Raise the meeple up for the coming drop
            const dropHeight = 5; // ems
            const [ , x, y ] = /^translate\(([0-9.]+)em,\s?([0-9.]+)em\)$/.exec(npcDiv.style.transform);
            npcDiv.style.transform = `translate(${x}em, ${y - dropHeight}em)`;

            // Fade in
            npcDiv.classList.remove('fled_hidden');
            await this.delayAsync(100); // opacity transition is 200ms... start dropping when half visible

            await animateDropAsync(npcDiv, y - dropHeight, y, ShortDuration, bounceFactory(2, 0.02));
        },

        async animateRollCallWindowChangeAsync(oldIndex, newIndex) {
            // TODO: if the whistle is on either the old index or the new index, 
            //       need to animate the whistle up (but leave the drop shadow)
            //       flip the two rc tiles, and then drop the whistle back down
            //       -- ideally using the bounce animation!

            const governorDiv = document.getElementById('fled_governors-area');
            const rcDivs = Array.from(governorDiv.querySelectorAll('.fled_tile-rc'));
            const closeDiv = rcDivs[oldIndex];
            const openDiv = rcDivs[newIndex];

            const whistleDiv = document.getElementById('fled_whistle');
            const isWhistleThere = fled.whistlePos === oldIndex || fled.whistlePos === newIndex;
            if (isWhistleThere) {
                await this.transitionInAsync(whistleDiv, 'fled_raised');
            }

            const closePromise = this.transitionInAsync(closeDiv, 'fled_back');
            const openPromise = (async () => {
                await this.delayAsync(200); // Let the close animation start first
                await this.transitionOutAsync(openDiv, 'fled_back');
            })();

            await Promise.all([
                closePromise,
                openPromise,
            ]);

            if (isWhistleThere) {
                await this.transitionOutAsync(whistleDiv, 'fled_raised');
            }
        },

        async animateWhistleToPosition(index) {
            const srcDiv = document.getElementById('fled_whistle-host');

            return new Promise(resolve => {
                const timeoutId = setTimeout(done, 500); // Transition is 400ms
                function done() {
                    clearTimeout(timeoutId);
                    srcDiv.removeEventListener('transitionend', done);
                    srcDiv.removeEventListener('transitioncancel', done);
                    resolve();
                }
                srcDiv.addEventListener('transitionend', done, { once: true });
                srcDiv.addEventListener('transitioncancel', done, { once: true });
                const oldIndex = (index + 1) % 5;
                srcDiv.classList.remove(`fled_whistle-pos-${oldIndex}`);
                srcDiv.classList.add(`fled_whistle-pos-${index}`);
            });

        },

        async animateDrawTileAsync(playerId, tileId, solo) {
            if (playerId != this.myPlayerId) return;

            await Promise.all([
                this.animateHandToMakeRoomForNewTileAsync(99, solo),
                this.animateTileFromDrawPileToMyHandAsync(tileId, solo),
            ]);
        },

        // Optionally takes an index of where the new card will go
        // (defaults to the last position, left to right)
        async animateHandToMakeRoomForNewTileAsync(index = 99, solo = false) {
            const handDiv = document.getElementById(solo ? `fled_solo-hand` : `fled_player-${this.myPlayerId}-hand`);
            const tileDivs = Array.from(handDiv.children);
            await Promise.all(
                tileDivs.map(async (tileDiv, i) => {
                    await this.animateHandTileToNewPositionAsync(tileDiv, i < index ? i : i + 1, tileDivs.length + 1, solo);
                }),
            );
        },

        //
        // This is used for dealing the starting bunk tile
        //
        async animateTileFromPlayerBoardToMyHandAsync(tileId) {
            const tileDiv = this.createTileOnPlayerBoard(this.myPlayerId, tileId);
            const destDiv = document.getElementById(`fled_player-${this.myPlayerId}-hand`);

            const handSlots = Array.from(destDiv.children);
            const handSlotIndex = handSlots.length;
            const { x, y, deg } = PlayerHandSlots[handSlots.length + 1][handSlotIndex];

            const srcDiv = tileDiv.cloneNode(true);
            srcDiv.id = `fled_tile-temp-${tileId}`;
            srcDiv.classList.add('fled_no-transition');
            srcDiv.style.left = '50%';
            srcDiv.style.top = '50%';
            srcDiv.style.transform = `translate(-50%, -50%) rotateZ(0deg) scale(.5)`;

            tileDiv.replaceWith(srcDiv);
            this.reflow();

            const srcRect = srcDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            const destRect = destDiv.getBoundingClientRect();
            const destLeftX = Math.round(destRect.x);
            const destTopY = Math.round(destRect.y);

            const deltaX = destLeftX - srcMidX;
            const deltaY = destTopY - srcMidY;

            const intermediateAngle = deltaY ? -Math.atan(deltaX / deltaY) / Math.PI * 180 : 0;

            // Drop the Governor's Area z-index so that the tiles appear
            // to fly over it rather than under it. (don't want to change
            // the z-index of the tile itself because it needs to slide
            // between two tiles in the player's hand)
            const goverorsAreaDiv = document.getElementById('fled_governors-area');
            goverorsAreaDiv.style.zIndex = -1;

            srcDiv.style.transform = `translate(-50%, -50%) rotateZ(${intermediateAngle}deg) scale(.5)`;
            srcDiv.classList.remove('fled_no-transition');
            srcDiv.classList.remove('fled_hidden');
            this.reflow();
           
            await srcDiv.animate({
                transform: [
                    srcDiv.style.transform,
                    `translate(calc(${x}em + ${deltaX}px), calc(${y}em + ${deltaY}px)) rotateZ(${deg}deg) scale(1)`,
                ],
            }, {
                delay: 100,
                duration: 800,
                easing: 'ease-out',
                fill: 'forwards',
            }).finished;

            this.placeInElement(tileDiv, destDiv);
            tileDiv.style.transform = `translate(${x}em, ${y}em) rotateZ(${deg}deg)`,
            tileDiv.classList.remove('fled_hidden');

            goverorsAreaDiv.style.zIndex = 1;

            // Delete the clone
            srcDiv.parentElement.removeChild(srcDiv);
        },

        async animateTileFromDrawPileToMyHandAsync(tileId, solo) {
            this.updateDrawPile();

            const srcDiv = this.createTileOnDrawPile(tileId);
            srcDiv.id = `fled_temp-tile-${tileId}`;

            const handDiv = document.getElementById(solo ? `fled_solo-hand` : `fled_player-${this.myPlayerId}-hand`);
            const handSlots = Array.from(handDiv.children);
            const handSlotIndex = handSlots.length;
            const { deg } = (solo ? SoloHandSlots : PlayerHandSlots)[handSlots.length + 1][handSlotIndex];

            const tileDiv = this.createTileInHand(handSlotIndex, handSlots.length + 1, tileId, true, solo);

            const srcRect = srcDiv.getBoundingClientRect();
            const destRect = tileDiv.getBoundingClientRect();

            const deltaX = Math.round((destRect.x + destRect.width / 2) - (srcRect.x + srcRect.width / 2));
            const deltaY = Math.round((destRect.y + destRect.height / 2) - (srcRect.y + srcRect.height / 2));

            // Drop the Governor's Area z-index so that the tiles appear
            // to fly over it rather than under it. (don't want to change
            // the z-index of the tile itself because it needs to slide
            // between two tiles in the player's hand)
            const goverorsAreaDiv = document.getElementById('fled_governors-area');
            goverorsAreaDiv.style.zIndex = -1;
            const boardContainerDiv = document.getElementById('fled_board-container');
            boardContainerDiv.style.zIndex = -1;
            
            if (!this.instantaneousMode) {
                // Flip the tile over
                await srcDiv.animate({
                    transform: [
                        `translate(5em, -5em) rotateZ(90deg) rotateY(0deg)`,
                    ],
                }, {
                    duration: 400,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;

                // Send it to player's hand
                await srcDiv.animate({
                    transform: [
                        `translate(5em, -5em) rotateZ(90deg) rotateY(0deg)`,
                        `translate(calc(5em + ${deltaX}px), calc(-5em + ${deltaY}px)) rotateZ(${deg}deg)`,
                    ],
                }, {
                    delay: 100,
                    duration: 800,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }
            
            tileDiv.classList.remove('fled_hidden');
            srcDiv.parentElement.removeChild(srcDiv);

            goverorsAreaDiv.style.zIndex = 1;
            boardContainerDiv.style.zIndex = 0;
        },

        async animateDiscardHandTileAsync(tileId, isSpecter) {
            const tileDiv = this.getTileDiv(tileId);
            if (!tileDiv) return;

            const handSlots = Array.from(tileDiv.parentElement.children);
            const handSlotIndex = handSlots.findIndex(div => div === tileDiv);
            const { x, y } = (isSpecter ? SoloHandSlots : PlayerHandSlots)[handSlots.length][handSlotIndex];

            // There was no discard pile when this was first written.
            // But rather than change this code, will just create a
            // new tile on the discard pile.

            if (!this.instantaneousMode) {
                await tileDiv.animate({
                    opacity: [ 0 ],
                    transform: [ `translate(${x}em, ${y - 10}em) rotateZ(0deg)` ],
                }, {
                    duration: ShortDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }

            tileDiv.parentElement.removeChild(tileDiv);

            this.updateDiscardPile();

            await this.animateCloseGapInHandAsync(isSpecter);
        },

        async animateDiscardInventoryTileAsync(playerId, tileId) {
            const tileDiv = this.getTileDiv(tileId);
            if (!tileDiv) return;

            // There was no discard pile when this was first written.
            // But rather than change this code, will just create a
            // new tile on the discard pile.

            if (!this.instantaneousMode) {
                await tileDiv.animate({
                    opacity: [ 0 ],
                    // TODO: transform: [ `translate(${x}em, ${y - 10}em) rotateZ(0deg)` ],
                }, {
                    duration: ShortDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }

            tileDiv.parentElement.removeChild(tileDiv);

            this.updateDiscardPile();
        },

        async animateTileFromBoardBackToHandAsync(solo = false) {
            const { movesIndex, movesAtSelectedCoords } = this.clientStateArgs;
            const [ tileId, , , orientation ] = movesAtSelectedCoords[movesIndex];

            const srcDiv = this.getTileDiv(tileId);
            srcDiv.id = `fled_temp-tile-${tileId}`;

            const handDiv = document.getElementById(solo ? `fled_solo-hand` : `fled_player-${this.myPlayerId}-hand`);
            const handSlots = Array.from(handDiv.children);
            const handSlotIndex = (solo ? fled.data.specterHand : fled.myHand).findIndex(t => t == tileId);
            const handSlot = (solo ? SoloHandSlots : PlayerHandSlots)[handSlots.length + 1][handSlotIndex];

            await this.animateHandToMakeRoomForNewTileAsync(handSlotIndex, solo);
            
            const srcRect = srcDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            const destDiv = this.createTileInHand(handSlotIndex, handSlots.length + 1, tileId, true, solo);
            const destRect = destDiv.getBoundingClientRect();
            const destMidX = Math.round(destRect.x + destRect.width / 2);
            const destMidY = Math.round(destRect.y + destRect.height / 2);

            const deltaX = destMidX - srcMidX;
            const deltaY = destMidY - srcMidY;

            const intermediateAngle = deltaY ? Math.atan(deltaX / deltaY) / Math.PI * 180 : 0;
            const deg = DegFromOrientation[orientation];

            destDiv.style.transform = `translate(calc(${handSlot.x}em - ${deltaX}px), calc(${handSlot.y}em - ${deltaY}px)) rotateZ(${deg}deg) scale(${this.clientStateArgs.currentScale})`;
            destDiv.classList.remove('fled_hidden');

            srcDiv.parentElement.removeChild(srcDiv);

            await destDiv.animate({
                transform: [
                    destDiv.style.transform,
                    `translate(${handSlot.x}em, ${handSlot.y}em) rotateZ(${intermediateAngle}deg) scale(1)`,
                    `translate(${handSlot.x}em, ${handSlot.y}em) rotateZ(${handSlot.deg}deg) scale(1)`,
                ],
            }, {
                duration: ShortDuration,
                easing: 'ease-out',
                fill: 'forwards',
            }).finished;

            // Don't trust fill: forwards because it seems a little too permanent / difficult to remove
            // and .commitStyles() didn't seem to do anything.
            destDiv.style.transform = `translate(${handSlot.x}em, ${handSlot.y}em) rotateZ(${handSlot.deg}deg)`;
        },

        async animateTileToMyShackleAsync(tileId) {
            const tileDiv = this.getTileDiv(tileId);

            const handSlots = Array.from(tileDiv.parentElement.children);
            const handSlotIndex = handSlots.findIndex(div => div === tileDiv);
            const { x, y } = PlayerHandSlots[handSlots.length][handSlotIndex];
            
            // Create a clone to animate into position
            const srcDiv = tileDiv.cloneNode(true);
            srcDiv.id = `fled_temp-tile-${tileId}`;
            tileDiv.replaceWith(srcDiv);
            
            const srcRect = srcDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            const destDiv = document.getElementById(`fled_shackle-slot-${this.myPlayerId}`);
            const destRect = destDiv.getBoundingClientRect();
            const destMidX = Math.round(destRect.x + destRect.width / 2);
            const destMidY = Math.round(destRect.y + destRect.height / 2);

            const deltaX = destMidX - srcMidX;
            const deltaY = destMidY - srcMidY;

            const intermediateAngle = deltaY ? -Math.atan(deltaX / deltaY) / Math.PI * 180 : 0;

            if (!this.instantaneousMode) {
                await srcDiv.animate({
                    transform: [
                        srcDiv.style.transform,
                        `${srcDiv.style.transform} rotateY(-180deg)`,
                    ],
                }, {
                    duration: 800,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;

                await srcDiv.animate({
                    transform: [
                        `${srcDiv.style.transform} rotateY(-180deg)`,
                        `translate(${x}em, ${y}em) rotateZ(${intermediateAngle}deg) rotateY(-180deg)`,
                        `translate(calc(${x}em + ${deltaX}px), calc(${y}em + ${deltaY}px)) rotateZ(0deg) rotateY(-180deg)`,
                    ],
                }, {
                    duration: 800,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }

            tileDiv.classList.add('fled_back');
            tileDiv.id = `fled_tile-shackle-${this.myPlayerId}`;
            this.placeInElement(tileDiv, destDiv);

            // Delete the clone
            srcDiv.parentElement.removeChild(srcDiv);

            await this.animateCloseGapInHandAsync();
        },

        async animateShackleTileToGovernorsInventory(playerId, tileId) {
            const srcDiv = this.getTileDiv(tileId) || document.getElementById(`fled_tile-shackle-${playerId}`);
            srcDiv.classList.add(`fled_tile-${tileId}`);

            const srcRect = srcDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            const destDiv = this.createTileInGovernorInventory(tileId, true);
            const destRect = destDiv.getBoundingClientRect();
            const destMidX = Math.round(destRect.x + destRect.width / 2);
            const destMidY = Math.round(destRect.y + destRect.height / 2);

            const deltaX = destMidX - srcMidX;
            const deltaY = destMidY - srcMidY;

            const intermediateAngle = deltaY ? -Math.atan(deltaX / deltaY) / Math.PI * 180 : 0;

            this.raiseElement(srcDiv);

            if (!this.instantaneousMode) {
                // Flip the tile over first
                await srcDiv.animate({
                    transform: [
                        `${srcDiv.style.transform} rotateY(-180deg)`,
                        `${srcDiv.style.transform} rotateY(0deg)`,
                    ],
                }, {
                    duration: ShortDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;

                await srcDiv.animate({
                    transform: [
                        `${srcDiv.style.transform} rotateY(0deg)`,
                        `${srcDiv.style.transform} rotateY(0deg) rotateZ(${intermediateAngle}deg)`,
                        `translate(${deltaX}px, ${deltaY}px) rotateY(0deg) rotateZ(0deg)`,
                    ],
                }, {
                    duration: LongDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }
            
            // Show the new tile and delete the original
            destDiv.classList.remove('fled_hidden');
            srcDiv.parentElement.removeChild(srcDiv);

            this.adjustGovernorsInventoryMargin();
        },

        async animateTileToMyInventoryAsync(tileId, inventoryIndex) {
            const tileDiv = this.getTileDiv(tileId);
            tileDiv.id = `fled_temp-tile-${tileId}`;

            const handSlots = Array.from(tileDiv.parentElement.children);
            const handSlotIndex = handSlots.findIndex(div => div === tileDiv);
            const { x, y } = PlayerHandSlots[handSlots.length][handSlotIndex];
            
            this.raiseElement(tileDiv);
            
            const srcRect = tileDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            const destDiv = document.getElementById(`fled_inventory-${this.myPlayerId}-slot-${inventoryIndex}`);
            const destRect = destDiv.getBoundingClientRect();
            const destMidX = Math.round(destRect.x + destRect.width / 2);
            const destMidY = Math.round(destRect.y + destRect.height / 2);

            const deltaX = destMidX - srcMidX;
            const deltaY = destMidY - srcMidY;

            const intermediateAngle = deltaY ? -Math.atan(deltaX / deltaY) / Math.PI * 180 : 0;

            const slidePromise = (async () => {
                await tileDiv.animate({
                    transform: [
                        tileDiv.style.transform,
                        `translate(${x}em, ${y}em) rotateZ(${intermediateAngle}deg)`,
                        `translate(calc(${x}em + ${deltaX}px), calc(${y}em + ${deltaY}px)) rotateZ(0deg)`,
                    ],
                }, {
                    duration: ShortDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;

                this.createTileInInventory(this.myPlayerId, tileId, inventoryIndex);

                tileDiv.parentElement.removeChild(tileDiv);
            })();

            const adjustHandPromise = (async () => {
                await this.delayAsync(200);
                await this.animateCloseGapInHandAsync();
            })();

            await Promise.all([
                slidePromise,
                adjustHandPromise,
            ]);
        },

        async animateTileFromHandToGovernorInventoryAsync(tileId, solo) {
            const tileDiv = this.getTileDiv(tileId);
            if (!tileDiv) {
                return;
            }

            const handSlots = Array.from(tileDiv.parentElement.children);
            const handSlotIndex = handSlots.findIndex(div => div === tileDiv);
            const { x, y } = (solo ? SoloHandSlots : PlayerHandSlots)[handSlots.length][handSlotIndex];

            tileDiv.id = `fled_temp-tile-${tileId}`;
            this.raiseElement(tileDiv);
            
            const srcRect = tileDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            const destDiv = this.createTileInGovernorInventory(tileId, true);
            const destRect = destDiv.getBoundingClientRect();
            const destMidX = Math.round(destRect.x + destRect.width / 2);
            const destMidY = Math.round(destRect.y + destRect.height / 2);

            const deltaX = destMidX - srcMidX;
            const deltaY = destMidY - srcMidY;

            const intermediateAngle = deltaY ? -Math.atan(deltaX / deltaY) / Math.PI * 180 : 0;

            if (!this.instantaneousMode) {
                const slidePromise = tileDiv.animate({
                    transform: [
                        tileDiv.style.transform,
                        `translate(${x}em, ${y}em) rotateZ(${intermediateAngle}deg)`,
                        `translate(calc(${x}em + ${deltaX}px), calc(${y}em + ${deltaY}px)) rotateZ(0deg)`,
                    ],
                }, {
                    duration: LongDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;

                const closeGapPromise = (async () => {
                    await this.delayAsync(ShortDuration);
                    await this.animateCloseGapInHandAsync(solo);
                })();

                await Promise.all([
                    slidePromise,
                    closeGapPromise,
                ]);
            }

            this.adjustGovernorsInventoryMargin();

            // Show the new tile and delete the old
            destDiv.classList.remove('fled_hidden');
            tileDiv.parentElement.removeChild(tileDiv);
        },

        async animateCloseGapInHandAsync(solo = false) {
            const handDiv = document.getElementById(solo ? `fled_solo-hand` : `fled_player-${this.myPlayerId}-hand`);
            const tileDivs = Array.from(handDiv.children);
            await Promise.all(
                tileDivs.map(async (tileDiv, i) => {
                    await this.animateHandTileToNewPositionAsync(tileDiv, i, tileDivs.length, solo);
                }),
            );
        },

        async animateHandTileToNewPositionAsync(tileDiv, tileIndex, tileCount, solo = false) {
            const { x, y, deg } = (solo ? SoloHandSlots : PlayerHandSlots)[tileCount][tileIndex];

            const newTransform = `translate(${x}em, ${y}em) rotateZ(${deg}deg)`;
            if (!this.instantaneousMode) {
                await tileDiv.animate({
                    transform: [
                        tileDiv.style.transform,
                        newTransform,
                    ],
                }, {
                    duration: ShortDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }
            tileDiv.style.transform = newTransform;
        },

        moveHandToTheLeft() {
            const handDiv = document.getElementById(`fled_player-${this.myPlayerId}-hand`);
            const tileDivs = Array.from(handDiv.children);
            tileDivs.map((tileDiv, i) => {
                const leftSlot = LeftSideHandSlots[tileDivs.length][i];
                this.moveHandTileToSlot(tileDiv, leftSlot);
            });
        },

        moveHandTileToSlot(tileDiv, { x, y, deg }) {
            const newTransform = `translate(${x}em, ${y}em) rotateZ(${deg}deg)`;
            tileDiv.style.transform = newTransform;
        },

        async animateHandToTheLeftAsync(speed = 'fast') {
            const handDiv = document.getElementById(`fled_player-${this.myPlayerId}-hand`);
            const tileDivs = Array.from(handDiv.children);
            await Promise.all(
                tileDivs.map(async (tileDiv, i) => {
                    const leftSlot = LeftSideHandSlots[tileDivs.length][i];
                    if (speed != 'instant') {
                        await this.delayAsync(i * 10);
                    }
                    await this.animateHandTileToSlotAsync(tileDiv, leftSlot, speed);
                }),
            );
        },

        async animateHandToTheRightAsync(speed = 'fast') {
            const handDiv = document.getElementById(`fled_player-${this.myPlayerId}-hand`);
            const tileDivs = Array.from(handDiv.children);
            await Promise.all(
                tileDivs.map(async (tileDiv, i) => {
                    const slot = PlayerHandSlots[tileDivs.length][i];
                    await this.delayAsync((tileDivs.length - i - 1) * 10);
                    await this.animateHandTileToSlotAsync(tileDiv, slot, speed);
                }),
            );
        },

        async animateHandTileToSlotAsync(tileDiv, { x, y, deg }, speed) {
            const newTransform = `translate(${x}em, ${y}em) rotateZ(${deg}deg)`;
            if (!this.instantaneousMode && speed != 'instant') {
                await tileDiv.animate({
                    transform: [
                        tileDiv.style.transform,
                        newTransform,
                    ],
                }, {
                    duration: speed == 'fast' ? ReactionDuration : ShortDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }
            tileDiv.style.transform = newTransform;
        },

        async animateCloseGapInInventoryAsync(playerId) {
            const inventoryDiv = document.getElementById(`fled_inventory-${playerId}`);
            const tileDivs = Array.from(inventoryDiv.querySelectorAll('.fled_tile'));
            await Promise.all(
                tileDivs.map(async (tileDiv, i) => {
                    await this.animateInventoryTileToNewPositionAsync(playerId, tileDiv, i, tileDivs.length)
                }),
            );
        },

        async animateInventoryTileToNewPositionAsync(playerId, tileDiv, slotIndex) {
            const srcRect = tileDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);

            const destDiv = document.getElementById(`fled_inventory-${playerId}-slot-${slotIndex}`);

            // Can bail out if the tile is already in the desired inventory slot
            if (tileDiv.parentElement === destDiv) {
                return;
            }

            const destRect = destDiv.getBoundingClientRect();
            const destMidX = Math.round(destRect.x + destRect.width / 2);

            const deltaX = destMidX - srcMidX;

            this.raiseElement(tileDiv);

            if (!this.instantaneousMode) {
                await tileDiv.animate({
                    transform: [
                        `translate(${deltaX}px, 0)`,
                    ],
                }, {
                    duration: ShortDuration,
                    easing: 'ease-out',
                }).finished;
            }

            this.placeInElement(tileDiv, destDiv);
        },

        async animateTileFromHandToBoardAsync(tileId, x, y, orientation, isSpecter) {
            const tileDiv = this.getTileDiv(tileId);

            const handSlots = Array.from(tileDiv.parentElement.children);
            const handSlotIndex = handSlots.findIndex(div => div === tileDiv);
            const handSlot = (isSpecter ? SoloHandSlots : PlayerHandSlots)[handSlots.length][handSlotIndex];
            
            const srcDiv = tileDiv;
            this.raiseElement(srcDiv);
            srcDiv.id = `fled_temp-tile-${tileId}`;
            
            const srcRect = srcDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            // Create a placeholder on the board to animate to
            const destDiv = this.createTilePlaceholderOnBoard(x, y, orientation);
            const destRect = destDiv.getBoundingClientRect();
            const destMidX = Math.round(destRect.x + destRect.width / 2);
            const destMidY = Math.round(destRect.y + destRect.height / 2);

            const deltaX = destMidX - srcMidX;
            const deltaY = destMidY - srcMidY;

            const intermediateAngle = deltaY ? -Math.atan(deltaX / deltaY) / Math.PI * 180 : 0;
            const deg = DegFromOrientation[orientation];

            if (!this.instantaneousMode) {
                const slideAnimation = srcDiv.animate({
                    transform: [
                        `translate(${handSlot.x}em, ${handSlot.y}em) rotateZ(${handSlot.deg}deg) scale(1)`,
                        `translate(${handSlot.x}em, ${handSlot.y}em) rotateZ(${intermediateAngle}deg) scale(1)`,
                        `translate(calc(${handSlot.x}em + ${deltaX}px), calc(${handSlot.y}em + ${deltaY}px)) rotateZ(${deg}deg) scale(${this.clientStateArgs.currentScale})`,
                    ],
                }, {
                    duration: LongDuration,
                    easing: 'ease-out',
                });

                try { await slideAnimation.finished; } catch (err) {}
                slideAnimation.commitStyles();
            }

            // Rather than fiddle with the original tile, create a new one on the board
            this.createTileOnBoard(tileId, x, y, orientation);

            // Get rid of the placeholder
            destDiv.parentElement.removeChild(destDiv);

            // Delete the original
            srcDiv.parentElement.removeChild(srcDiv);

            await this.animateCloseGapInHandAsync(isSpecter);
        },

        async animateOpponentTileToBoardAsync(playerId, tileId, x, y, orientation) {
            await this.animateSmartZoomAsync();

            const tileDiv = this.createTileOnBoard(tileId, x, y, orientation, true);

            if (!this.instantaneousMode) {
                await tileDiv.animate({
                    transform: [
                        tileDiv.style.transform + ' scale(1.5)',
                        tileDiv.style.transform + ' scale(1)',
                    ],
                    opacity: [ 0, 1 ],
                }, {
                    delay: 200,
                    duration: ShortDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }

            tileDiv.classList.remove('fled_hidden');
        },

        async animateTileFromGovernorInventoryToHandAsync(tileId) {
            const tileDiv = this.getTileDiv(tileId);
            tileDiv.id = `fled_temp-tile-${tileId}`;

            const handDiv = document.getElementById(`fled_player-${this.myPlayerId}-hand`);
            const handSlots = Array.from(handDiv.children);
            const handSlotIndex = handSlots.length; // Should be 2
            const { x, y, deg } = PlayerHandSlots[handSlots.length + 1][handSlotIndex];

            this.raiseElement(tileDiv);
            this.adjustGovernorsInventoryMargin();
            
            const srcRect = tileDiv.getBoundingClientRect();
            const srcMidX = Math.round(srcRect.x + srcRect.width / 2);
            const srcMidY = Math.round(srcRect.y + srcRect.height / 2);

            const destDiv = document.getElementById(`fled_player-${this.myPlayerId}-hand`);
            const destRect = destDiv.getBoundingClientRect();
            const destLeftX = Math.round(destRect.x);
            const destTopY = Math.round(destRect.y);

            const deltaX = destLeftX - srcMidX;
            const deltaY = destTopY - srcMidY;

            if (!this.instantaneousMode) {
                const slidePromise = tileDiv.animate({
                    transform: [
                        `translate(0, 0) rotateZ(0deg) scale(1)`,
                        `translate(calc(${x}em + ${deltaX}px), calc(${y}em + ${deltaY}px)) rotateZ(${deg}deg) scale(1)`,
                    ],
                }, {
                    duration: LongDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;

                const adjustHandPromise = this.animateHandToMakeRoomForNewTileAsync();

                await Promise.all([
                    slidePromise,
                    adjustHandPromise,
                ]);
            }

            this.createTileInHand(handSlotIndex, handSlots.length + 1, tileId); 

            tileDiv.parentElement.removeChild(tileDiv);
        },

        async animatePlayerEscape(playerId) {
            //// Mark wants the player's meeple to remain visible in the forest
            // const player = fled.players[playerId];
            // const name = MeepleNames[player.color];
            // const meepleDiv = document.getElementById(`fled_meeple-${name}`);
            //
            // await meepleDiv.animate({
            //     opacity: [ 0 ],
            // }, {
            //     duration: LongDuration,
            //     easing: 'ease-in',
            //     fill: 'forwards',
            // }).finished;

            // Flip the prisoner tile
            const prisonerTileDiv = document.getElementById(`fled_prisoner-tile-${playerId}`);
            await this.transitionInAsync(prisonerTileDiv, 'fled_back');
        },

        async animatePlayerMoveAsync(playerId, x, y, inSolitary = false) {
            const player = fled.players[playerId];
            const name = MeepleNames[player.color];
            await this.animateOtherMeeplesToMakeRoomForNewMeepleAsync(name, x, y, inSolitary);
            await this.animateMeepleMoveAsync(name, x, y);
        },

        async animateNpcMoveAsync(npcName, x, y) {
            await this.animateOtherMeeplesToMakeRoomForNewMeepleAsync(npcName, x, y);
            await this.animateMeepleMoveAsync(npcName, x, y);
        },

        async animateMeepleMoveAsync(name, x, y, isFast = false) { // TODO: refactor and combine with animatePlayer
            const meepleDiv = document.getElementById(`fled_meeple-${name}`);
            const { xEm, yEm, z } = this.calculateMeeplePosition(name, x, y);

            meepleDiv.style.zIndex = y * FledWidth + z; // Don't need to consider x here because no overlap of cells horizontally

            if (!this.instantaneousMode) {
                const animation = meepleDiv.animate({
                    transform: [
                        `translate(${xEm}em, ${yEm}em)`,
                    ],
                }, {
                    duration: isFast ? ShortDuration : LongDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                });
                
                await animation.finished;
                animation.commitStyles();
            }

            meepleDiv.style.transform = `translate(${xEm}em, ${yEm}em)`;
            meepleDiv.style.zIndex = z;
        },

        // Optionally takes an index of where the new meeple will go
        // (defaults to the last position, left to right, top to bottom)
        async animateOtherMeeplesToMakeRoomForNewMeepleAsync(exceptName, x, y, inSolitary = false) {
            const meeples = fled.getMeeplesAt(x, y, inSolitary);
            await Promise.all(
                meeples.map(async name => {
                    if (name === exceptName) return;
                    await this.animateMeepleMoveAsync(name, x, y, true);
                }),
            );
        },

        delayAsync(duration) {
            if (this.instantaneousMode) return Promise.resolve();
            return new Promise(resolve => setTimeout(resolve, duration));
        },

        async animateTransitionAsync(div, fnStyle) {
            if (this.instantaneousMode) {
                fnStyle(div.style);
            }
            return new Promise(resolve => {
                const timeoutId = setTimeout(done, LongDuration + 100);
                function done() {
                    clearTimeout(timeoutId);
                    div.removeEventListener('transitionend', done);
                    div.removeEventListener('transitioncancel', done);
                    resolve();
                }
                div.addEventListener('transitionend', done, { once: true });
                div.addEventListener('transitioncancel', done, { once: true });
                fnStyle(div.style);
            });
        },

        async transitionInAsync(div, className, timeout = 1000) {
            if (div.classList.contains(className)) return;
            if (this.instantaneousMode) {
                div.classList.add(className);
            }
            await new Promise(resolve => {
                const timeoutId = setTimeout(done, timeout);
                function done() {
                    clearTimeout(timeoutId);
                    div.removeEventListener('transitionend', done);
                    div.removeEventListener('transitioncancel', done);
                    resolve();
                }
                div.addEventListener('transitionend', done, { once: true });
                div.addEventListener('transitioncancel', done, { once: true });
                div.classList.add(className);
            });
        },

        async transitionOutAsync(div, className, timeout = 1000) {
            if (!div.classList.contains(className)) return;
            if (this.instantaneousMode) {
                div.classList.remove(className);
            }
            await new Promise(resolve => {
                const timeoutId = setTimeout(done, timeout);
                function done() {
                    clearTimeout(timeoutId);
                    div.removeEventListener('transitionend', done);
                    div.removeEventListener('transitioncancel', done);
                    resolve();
                }
                div.addEventListener('transitionend', done, { once: true });
                div.addEventListener('transitioncancel', done, { once: true });
                div.classList.remove(className);
            });
        },

        getTileDiv(tileId) {
            return document.getElementById(`fled_tile-${tileId}`);
        },

        // Similar to placeOnObject, except it sets the child of
        // the parent instead of just setting the coordinates.
        placeInElement(childIdOrElement, parentIdOrElement) {
            const child = typeof childIdOrElement === 'string' ? document.getElementById(childIdOrElement) : childIdOrElement;
            const parent = typeof parentIdOrElement=== 'string' ? document.getElementById(parentIdOrElement) : parentIdOrElement;
            child.style.position = '';
            child.style.left = '';
            child.style.top = '';
            child.style.bottom = '';
            child.style.right = '';
            child.style.zIndex = '';
            child.style.transform = '';
            parent.appendChild(child);
        },

        resetPosition(div) {
            div.style = '';
        },


        ///////////////////////////////////////////////////
        //// Player's action

        async onClickMiniMap(event) {
            // Get the mouse click and convert to (-1, -1) - (1, 1) coordinate space
            const { offsetX, offsetY, currentTarget } = event;
            const x = 2 * offsetX / currentTarget.clientWidth - 1;
            const y = 2 * offsetY / currentTarget.clientHeight - 1;
            await this.animateZoomToAsync(x, y, this.clientStateArgs.currentScale);
        },

        async onClickExpandMap() {
            document.getElementById('fled_board-button-expand').classList.add('fled_hidden');
            const container = document.getElementById('fled_board-container');
            container.style.height = '69.17em';
            this.reflow(container);
            const { zoom } = this.calculateZoom({ x1: -1, y1: -1, x2: FledWidth + 1, y2: FledHeight + 1 });
            await this.animateZoomToAsync(0, 0, zoom);
            this.showHideMiniMap();
            this.clientStateArgs.boardContainerExpanded = true;
            document.getElementById('fled_board-button-collapse').classList.remove('fled_hidden');
        },

        async onClickCollapseMap() {
            document.getElementById('fled_board-button-collapse').classList.add('fled_hidden');
            const container = document.getElementById('fled_board-container');
            container.style.height = '32em';
            this.reflow(container);
            await this.animateSmartZoomAsync({ force: true, noExpand: true });
            this.clientStateArgs.boardContainerExpanded = false;
            this.showHideMiniMap();
            document.getElementById('fled_board-button-expand').classList.remove('fled_hidden');
        },

        async onClickBoardTile(tileId) {
            console.log(`onClickBoardTile(${tileId})`);
        },

        async onClickSelectableTile(tileId) {
            if (!this.isCurrentPlayerActive()) return;

            const fledBodyDiv = document.getElementById(`fled_body`);
            const tileDiv = fledBodyDiv.querySelector(`#fled_tile-${tileId}`);
            if (!tileDiv.classList.contains('fled_selectable')) return;

            console.log(`onClickSelectableTile(${tileId})`);

            // Special case for selecting inventory tiles... we may need one or two to be selected
            if (this.currentState === 'client_selectInventoryTiles') {
                const { selectedInventoryTileIds, selectionsNeeded } = this.clientStateArgs;
                const tileIndex = selectedInventoryTileIds.indexOf(tileId);

                // Deselect if already selected
                if (tileIndex >= 0) {
                    selectedInventoryTileIds.splice(tileIndex, 1);
                    const tileDiv = this.getTileDiv(tileId);
                    tileDiv.classList.remove('fled_selected');
                }
                else {
                    // Select the clicked tile
                    selectedInventoryTileIds.push(tileId);
                    const tileDiv = this.getTileDiv(tileId);
                    tileDiv.classList.add('fled_selected');

                    // Deselect the first selected tile if we have too many selected now
                    while (selectedInventoryTileIds.length > selectionsNeeded) {
                        const firstTileId = selectedInventoryTileIds.shift();
                        const tileDiv = this.getTileDiv(firstTileId);
                        tileDiv.classList.remove('fled_selected');
                    }
                }
            }
            // Special case for escape... also here we may need either one or two to be selected
            else if (this.currentState === 'client_selectTilesForEscape') {
                const { selectedInventoryTileIds } = this.clientStateArgs;
                const tileIndex = selectedInventoryTileIds.indexOf(tileId);

                // Deselect if already selected
                if (tileIndex >= 0) {
                    selectedInventoryTileIds.splice(tileIndex, 1);
                    const tileDiv = this.getTileDiv(tileId);
                    tileDiv.classList.remove('fled_selected');
                }
                else {
                    // Select the clicked tile
                    selectedInventoryTileIds.push(tileId);
                    const tileDiv = this.getTileDiv(tileId);
                    tileDiv.classList.add('fled_selected');
                }

                const selectedEnoughToEscape = fled.canEscapeWith(selectedInventoryTileIds);
                if (selectedEnoughToEscape) {
                    this.makeTilesNonSelectable();
                }
                else {
                    this.makeTilesSelectable(fled.getInventoryTilesEligibleForEscape());
                }
                this.addConfirmButton(`fled_button-confirm-escape`, Preference.ConfirmWhenEscaping, this.onClickConfirmEscape, !selectedEnoughToEscape);
            }
            // Standard case where we only need one tile selected
            else {
                const selectedDivs = fledBodyDiv.querySelectorAll('.fled_selected');
                for (const selectedDiv of selectedDivs) {
                    if (selectedDiv === tileDiv) continue;
                    selectedDiv.classList.remove('fled_selected');
                }
                this.destroyAllSlots();

                this.clientStateArgs.selectedTileId = tileId;
                tileDiv.classList.add('fled_selected');
            }

            switch (this.currentState) {
                case 'addTile':
                case 'addStarterTile':
                    this.animateSmartZoomAsync({ force: true });
                    this.createSlotsForLegalMoves(tileId);
                    this.setClientState('client_selectSlot', {
                        descriptionmyturn: _('${you} must place the tile on the board'),
                    });
                    break;

                case 'addGhostTile':
                    this.animateSmartZoomAsync({ force: true });
                    this.createSlotsForLegalMoves(tileId);
                    this.setClientState('client_selectSpecterSlot', {
                        descriptionmyturn: _('${you} must place the tile on the board'),
                    });
                    break;

                case 'client_selectSlot':
                case 'client_selectSpecterSlot':
                    this.createSlotsForLegalMoves(tileId);
                    break;

                case 'client_discardTile':
                    this.askConfirmationOrInvoke({
                        state: 'client_confirmDiscard',
                        prompt: _('${you} will discard this tile'),
                        pref: Preference.ConfirmWhenDiscarding,
                        bypass: this.onClickConfirmDiscard,
                    });
                    break;

                case 'discardGhostTile':
                    this.askConfirmationOrInvoke({
                        state: 'client_confirmDiscard',
                        prompt: _('${you} will discard this tile'),
                        pref: Preference.ConfirmWhenDiscarding,
                        bypass: this.onClickConfirmDiscard,
                    });
                    break;
    
                case 'client_surrenderGhostTile':
                    this.askConfirmationOrInvoke({
                        state: 'client_confirmSurrender',
                        prompt: _('${you} will surrender this tile'),
                        pref: Preference.ConfirmWhenSurrenderingTile,
                        bypass: this.onClickConfirmSurrender,
                    });
                    break;
    
                case 'client_selectTileForMovement':
                    if (isWhistleTile(tileId)) {
                        if (fled.getOption('specterExpansion') && fled.isSpecterInPlay) {
                            this.setClientState('client_selectWarderOrSpecterToMove', {
                                descriptionmyturn: _('${you} must select the specter or a warder to move'),
                            });
                        }
                        else {
                            if (fled.warderCount === 1) {
                                this.clientStateArgs.selectedNpc = 'warder1';
                                this.setClientState('client_selectWarderDestination', {
                                    descriptionmyturn: _('${you} must select a destination for the warder'),
                                });
                            }
                            else {
                                this.setClientState('client_selectWarderToMove', {
                                    descriptionmyturn: _('${you} must select a warder to move'),
                                });
                            }
                        }
                    }
                    else if (isShamrockTile(tileId)) {
                        this.setClientState('client_selectSelfOrNpcToMove', {
                            descriptionmyturn: _('${you} must select who to move'),
                        });
                    }
                    else if (isBoneTile(tileId)) {
                        this.clientStateArgs.selectedNpc = 'hound';
                        this.setClientState('client_selectHoundDestination', {
                            descriptionmyturn: _('${you} must select a destination for the hound'),
                        });
                    }
                    else {
                        this.setClientState('client_selectPlayerDestination', {
                            descriptionmyturn: _('${you} must select your destination'),
                        });
                    }
                    break;

                case 'client_selectTileForInventory':
                    this.onSelectTileToAddToInventory();
                    break;

                case 'client_selectInventoryTiles':
                    if (this.clientStateArgs.selectedInventoryTileIds.length === this.clientStateArgs.selectionsNeeded) {
                        this.askConfirmationOrInvoke({
                            state: 'client_confirmAddToInventory',
                            prompt: _('${you} will trade these tiles'),
                            pref: Preference.ConfirmWhenAddingToInventory,
                            bypass: this.onClickConfirmAddToInventory,
                        });
                    }
                    break;

                case 'client_confirmAddToInventory':
                    // If the player deselected a tile, go back to the previous state
                    if (this.clientStateArgs.selectedInventoryTileIds.length < this.clientStateArgs.selectionsNeeded) {
                        this.onSelectTileToAddToInventory();
                    }
                    break;

                case 'client_selectTileForSurrender':
                    this.askConfirmationOrInvoke({
                        state: 'client_confirmSurrender',
                        prompt: _('${you} will surrender this tile'),
                        pref: Preference.ConfirmWhenSurrenderingTile,
                        bypass: this.onClickConfirmSurrender,
                    });
                    break;

                case 'drawTiles':
                case 'client_selectGovernorTile':
                    this.askConfirmationOrInvoke({
                        state: 'client_confirmGovernorTile',
                        prompt: _('${you} will take this tile'),
                        pref: Preference.ConfirmWhenTakingFromGovernor,
                        bypass: this.onClickConfirmTakeFromGovernor,
                    });
                    break;
            }
        },

        async onClickMeeple(name) {
            console.log(`onClickMeeple(${name})`);

            switch (name) {
                case 'hound':
                    if (fled.getOption('houndExpansion') && this.currentState === 'client_selectSelfOrNpcToMove') {
                        this.clientStateArgs.selectedNpc = name;
                        this.setClientState('client_selectHoundDestination', {
                            descriptionmyturn: _('${you} must select a destination for the hound'),
                        });
                    }
                    break;

                case 'specter':
                    if (fled.getOption('specterExpansion') && fled.isSpecterInPlay && (
                        this.currentState === 'client_selectSelfOrNpcToMove' ||
                        this.currentState === 'client_selectWarderOrSpecterToMove'
                    )) {
                        this.clientStateArgs.selectedNpc = name;
                        this.setClientState('client_selectSpecterDestination', {
                            descriptionmyturn: _('${you} must select a destination for the specter'),
                        });
                    }
                    break;

                case 'warder1':
                case 'warder2':
                case 'warder3':
                case 'chaplain':
                    if (this.currentState === 'client_selectSelfOrNpcToMove' ||
                        this.currentState === 'client_selectWarderOrSpecterToMove' ||
                        this.currentState === 'client_selectWarderToMove'
                    ) {
                        this.clientStateArgs.selectedNpc = name;
                        this.setClientState('client_selectWarderDestination', {
                            descriptionmyturn:
                                name === 'chaplain'
                                    ? _('${you} must select a destination for the chaplain')
                                    : _('${you} must select a destination for the warder')
                        });
                    }
                    break;

                default:
                    const color = MeepleNames.indexOf(name);
                    if (color < 0) return;
                    this.clientStateArgs.selectedPlayer = name;

                    switch (this.currentState) {
                        case 'client_selectSelfOrNpcToMove':
                            this.setClientState('client_selectPlayerDestination', {
                                descriptionmyturn: _('${you} must select your destination'),
                            });
                            break;

                        case 'client_selectPlayerToTarget':
                            this.finalizeNpcMovement();
                            break;
                    }
                    break;
            }
        },

        async onSelectTileToAddToInventory() {
            const tile = Tiles[this.clientStateArgs.selectedTileId];
            switch (tile.color) {
                case ScrollColor.Blue: // Blue can be added for no cost
                    this.askConfirmationOrInvoke({
                        state: 'client_confirmAddToInventory',
                        prompt: _('${you} will add this tile to your inventory'),
                        pref: Preference.ConfirmWhenAddingToInventory,
                        bypass: this.onClickConfirmAddToInventory,
                    });
                    break;

                case ScrollColor.Purple:
                    this.clientStateArgs.selectionsNeeded = 1;
                    this.setClientState('client_selectInventoryTiles', {
                        descriptionmyturn: _('${you} must select an inventory tile to pay the cost'),
                    });
                    break;

                case ScrollColor.Gold:
                case ScrollColor.Green:
                    this.clientStateArgs.selectionsNeeded = 2;
                    this.setClientState('client_selectInventoryTiles', {
                        descriptionmyturn: applyMarkup(_('${you} must select /two/ inventory tiles to pay the cost')),
                    });
                    break;
            }
        },

        async onClickSlot(x, y) {
            if (!this.amIActive) return;
            console.log(`onClickSlot(${x}, ${y})`);

            // Select the slot that the player clicked
            const slotDiv = document.getElementById(`fled_slot-${x}-${y}`);
            slotDiv.classList.add('fled_selected');

            // TODO: clean up this function

            if (this.currentState === 'client_selectPlayerDestination' || this.currentState === 'client_confirmMovement') {
                this.deselectAll('fled_board');
                this.clientStateArgs.selectedCoords = { x, y };
                const destDiv = document.getElementById(`fled_slot-${x}-${y}`);
                destDiv.classList.add('fled_selected');

                this.askConfirmationOrInvoke({
                    state: 'client_confirmMovement',
                    prompt: _('${you} must confirm the movement'),
                    pref: Preference.ConfirmWhenMoving,
                    bypass: this.onClickConfirmMovement,
                });
                return;
            }
            else if (this.currentState === 'client_selectHoundDestination') {
                this.deselectAll('fled_board');
                const destDiv = document.getElementById(`fled_slot-${x}-${y}`);
                destDiv.classList.add('fled_selected');

                this.clientStateArgs.npcMoves = [
                    [ 'hound', x, y, '' ],
                ];

                this.askConfirmationOrInvoke({
                    state: 'client_confirmNpcMovement',
                    prompt: _('${you} must confirm the hound movement'),
                    pref: Preference.ConfirmWhenMovingNpc,
                    bypass: this.onClickConfirmNpcMovement,
                });
                return;
            }
            else if (this.currentState === 'client_selectWarderDestination' || this.currentState === 'client_selectSpecterDestination') {
                this.deselectAll('fled_board'); // TODO: only deselect if multiple meeples in room
                this.clientStateArgs.selectedCoords = { x, y };
                const destDiv = document.getElementById(`fled_slot-${x}-${y}`);
                destDiv.classList.add('fled_selected');

                const meeples = fled.getPlayersAt(x, y);
                if (meeples.length > 1) {
                    this.setClientState('client_selectPlayerToTarget', {
                        descriptionmyturn: _('${you} must select a player to target'),
                    });
                }
                else {
                    this.clientStateArgs.selectedPlayer = meeples[0];
                    this.finalizeNpcMovement();
                }
                return;
            }

            // For the given slot, choose one of the possible moves
            const moves = this.clientStateArgs.legalTileMoves;
            const tileId = this.clientStateArgs.selectedTileId;
            const matchingMoves = moves.filter(move => {
                if (move[0] !== tileId) return false;
                if (move[1] === x && move[2] === y) return true; // Head cell match
                switch (move[3]) { // Test for tail cell match
                    case Orientation.NorthSouth: return move[1] === x && move[2] === y - 1;
                    case Orientation.EastWest:   return move[1] === x + 1 && move[2] === y;
                    case Orientation.SouthNorth: return move[1] === x && move[2] === y + 1;
                    case Orientation.WestEast:   return move[1] === x - 1 && move[2] === y;
                }
                throw new Error('Invalid move');
            });
            if (!matchingMoves.length) {
                throw new Error('No matching moves'); // Hmm... uh oh
            }

            // If a tile has already been placed this turn,
            // try to maintain the same orientation if the
            // player selects a different slot. Otherwise,
            // default to the NorthSouth orientation.
            const idealOrientation = this.clientStateArgs.orientation || Orientation.NorthSouth;

            const bestMatchingMoves = matchingMoves.sort((a, b) => {
                if (a[1] === x && a[2] === y && a[3] === idealOrientation) return -1;
                if (b[1] === x && b[2] === y && b[3] === idealOrientation) return 1;
                if (a[1] === x && a[2] === y) return -1;
                if (b[1] === x && b[2] === y) return 1;
                if (a[3] === idealOrientation) return -1;
                if (b[3] === idealOrientation) return 1;
                return a[3] - b[3];
            });            

            const solo = this.currentState === 'client_selectSpecterSlot';

            const bestMove = bestMatchingMoves[0];
            this.clientStateArgs.orientation = bestMove[3];

            if (this.clientStateArgs.alreadyPlaced) {
                // move from where it currently is
                const orientation = bestMove[3];
                const xx = bestMove[1];
                const yy = bestMove[2];
                const tileDiv = this.getTileDiv(tileId);
                const { xEm, yEm, deg } = calculateTilePosition(xx, yy, orientation);

                this.createRotateButton(x, y, bestMatchingMoves.length);

                await tileDiv.animate({
                    transform: [ `translate(${xEm}em, ${yEm}em) rotateZ(${deg}deg)` ],
                }, {
                    duration: 400,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;

                // Recreate the tooltip because the rotation may have changed
                this.createTileTooltip(tileId, deg);
            }
            else {
                this.makeTilesNonSelectable();
                this.clientStateArgs.alreadyPlaced = true;
                await this.animateTileFromHandToBoardAsync(tileId, bestMove[1], bestMove[2], bestMove[3], solo);
                const tileDiv = this.getTileDiv(tileId);
                tileDiv.classList.add('fled_tentative');
                this.deselectAllTiles();
                this.createRotateButton(x, y, bestMatchingMoves.length);
            }

            this.clientStateArgs.movesAtSelectedCoords = bestMatchingMoves;
            this.clientStateArgs.movesIndex = 0;
            this.clientStateArgs.selectedCoords = { x, y };

            // TODO: allow timed confirmation?
            this.setClientState(solo ? 'client_confirmSpecterTilePlacement' : 'client_confirmTilePlacement', {
                descriptionmyturn: _('${you} must confirm the tile placement'),
            });
        },

        async onClickRotateButton() {
            console.log('onClickRotateButton()');

            let {
                movesAtSelectedCoords,
                movesIndex,
            } = this.clientStateArgs;
            
            const currentMove = movesAtSelectedCoords[movesIndex];
            const [ , xxCur, yyCur, oCur ] = currentMove;
            const curPos = calculateTilePosition(xxCur, yyCur, oCur);

            movesIndex = (movesIndex + 1) % movesAtSelectedCoords.length;
            this.clientStateArgs.movesIndex = movesIndex;
            
            // move from where it currently is
            const nextMove = movesAtSelectedCoords[movesIndex];
            const [ tileId, xx, yy, orientation ] = nextMove;
            const tileDiv = this.getTileDiv(tileId);
            const { xEm, yEm, deg } = calculateTilePosition(xx, yy, orientation);

            if (Math.abs(deg - curPos.deg) > 180) {
                // Adjust the rotation so that we take the shorter rotation angle
                // (i.e. rotate -90 degrees instead of 270)
                await tileDiv.animate({
                    transform: [
                        `translate(${curPos.xEm}em, ${curPos.yEm}em) rotateZ(${(curPos.deg + 360) % 360}deg)`,
                        `translate(${xEm}em, ${yEm}em) rotateZ(${(deg + 360) % 360}deg)`,
                    ],
                }, {
                    duration: 400,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }
            else {
                await tileDiv.animate({
                    transform: [
                        `translate(${curPos.xEm}em, ${curPos.yEm}em) rotateZ(${curPos.deg}deg)`,
                        `translate(${xEm}em, ${yEm}em) rotateZ(${deg}deg)`
                    ],
                }, {
                    duration: 400,
                    easing: 'ease-out',
                    fill: 'forwards',
                }).finished;
            }

            // Recreate the tooltip because the rotation changed
            this.createTileTooltip(tileId, deg);
        },

        async onClickConfirmDiscard() {
            console.log('onClickConfirmDiscard()');
            const t = this.clientStateArgs.selectedTileId;
            const s = this.currentState === 'discardGhostTile' ? 1 : undefined;
            await invokeServerActionAsync('discard', fled.moveNumber, { t, s });
            // TODO: lock the UI while the above call is happening
        },

        async onClickCancelDiscard() {
            console.log('onClickCancelDiscard()');
            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            this.deselectAllTiles();
            this.makeTilesNonSelectable();

            delete this.clientStateArgs.selectedTileId;

            this.restoreServerGameState();
        },

        async onClickConfirmTilePlacement() {
            console.log('onClickConfirmTilePlacement()');
            const {
                movesAtSelectedCoords,
                movesIndex,
            } = this.clientStateArgs;

            const [ t, x, y, o ] = movesAtSelectedCoords[movesIndex];

            try {
                this.destroyRotateButton();
                this.destroyAllSlots();
                this.makeTilesNonSelectable();

                await invokeServerActionAsync('placeTile', fled.moveNumber, { t, x, y, o });

                const tileDiv = this.getTileDiv(t);
                tileDiv?.classList.remove('fled_tentative');
            }
            catch (err) {
                const moves = this.clientStateArgs.legalTileMoves;
                const tileDiv = this.getTileDiv(t);
                tileDiv.classList.add('fled_selectable');
                tileDiv.classList.add('fled_selected');
                this.createSlotsForLegalMoves(t);
                this.createRotateButton(x, y, moves.length);
            }
        },

        async onClickCancelTilePlacement() {
            console.log('onClickCancelTilePlacement()');
            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            this.destroyRotateButton();
            this.destroyAllSlots();
            this.deselectAllTiles();

            if (this.clientStateArgs.alreadyPlaced) {
                const solo = this.currentState === 'client_confirmSpecterTilePlacement';
                await this.animateTileFromBoardBackToHandAsync(solo);
                this.clientStateArgs.alreadyPlaced = false;
            }

            delete this.clientStateArgs.selectedTileId;
            delete this.clientStateArgs.selectedCoords;
            delete this.clientStateArgs.movesAtSelectedCoords;
            delete this.clientStateArgs.movesIndex;

            this.restoreServerGameState();
        },

        async onClickMoveButton() {
            if (!this.isCurrentPlayerActive()) return;
            console.log('onClickMoveButton()');

            const { tilesForMovement } = this.clientStateArgs;
            this.makeTilesSelectable(tilesForMovement);

            this.setClientState('client_selectTileForMovement', {
                descriptionmyturn: _('${you} must select a tile to discard for movement'),
            });
        },

        async onClickConfirmMovement() {
            console.log('onClickConfirmMovement()');
            const t = this.clientStateArgs.selectedTileId;
            let { x, y } = this.clientStateArgs.selectedCoords;

            this.makeTilesNonSelectable();
            this.destroyAllSlots();

            // Always choose the head room of a double tile
            const { tileId } = unpackCell(fled.getTileAt(x, y));
            if (isDoubleTile(tileId)) {
                const headPos = fled.getTileHeadPos(x, y);
                x = headPos.x;
                y = headPos.y;
            }

            await invokeServerActionAsync('move', fled.moveNumber, { t, x, y });
            // TODO: lock the UI while the above call is happening

            delete this.clientStateArgs.selectedTileId;
            delete this.clientStateArgs.selectedCoords;
        },

        // Note: Not used for moving the hound... just the warders and specter
        finalizeNpcMovement() {
            let { x, y } = this.clientStateArgs.selectedCoords;
            const w = this.clientStateArgs.selectedNpc;
            const p = this.clientStateArgs.selectedPlayer;

            this.clientStateArgs.npcMoves = [
                ...(this.clientStateArgs.npcMoves || []),
                [ w, x, y, p ],
            ];

            delete this.clientStateArgs.selectedCoords;
            delete this.clientStateArgs.selectedNpc;
            delete this.clientStateArgs.selectedPlayer;

            this.askConfirmationOrInvoke({
                state: 'client_confirmNpcMovement',
                prompt: _('${you} must confirm the movement'),
                pref: Preference.ConfirmWhenMovingNpc,
                bypass: this.onClickConfirmNpcMovement,
            });
        },

        async onClickConfirmNpcMovement() {
            if (this.currentState !== 'client_confirmNpcMovement' &&
                this.currentState !== 'client_selectWarderDestination' &&
                this.currentState !== 'client_selectHoundDestination' &&
                this.currentState !== 'client_selectSpecterDestination' &&
                this.currentState !== 'client_selectPlayerToTarget') return;

            this.destroyAllSlots();
            this.makeMeeplesNonSelectable();

            const t = this.clientStateArgs.selectedTileId;
            const moves = this.clientStateArgs.npcMoves?.flat().join('_');

            await invokeServerActionAsync('moveNpc', fled.moveNumber, { t, moves });
            // TODO: lock the UI while the above call is happening

            delete this.clientStateArgs.selectedTileId;
            delete this.clientStateArgs.npcMoves;
        },

        async onClickCancelMove() {
            console.log('onClickCancelMove()');
            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            this.deselectAllTiles();
            this.makeTilesNonSelectable();
            this.destroyAllSlots();

            delete this.clientStateArgs.selectedTileId;
            delete this.clientStateArgs.selectedCoords;

            this.restoreServerGameState();
        },

        async onClickAddToInventoryButton() {
            if (!this.isCurrentPlayerActive()) return;
            console.log('onClickAddToInventoryButton()');

            const { tilesForInventory } = this.clientStateArgs;
            this.makeTilesSelectable(tilesForInventory);

            this.setClientState('client_selectTileForInventory', {
                descriptionmyturn: _("${you} must select a tile to add to your inventory"),
            });
        },

        async onClickConfirmAddToInventory() {
            if (!this.isCurrentPlayerActive()) return;
            console.log('onClickConfirmAddToInventory()');

            this.makeTilesNonSelectable();

            const {
                selectedTileId: t,
                selectedInventoryTileIds,
            } =  this.clientStateArgs;

            const d = selectedInventoryTileIds?.map(tileId => `${tileId}`).join() || undefined;
            await invokeServerActionAsync('add', fled.moveNumber, { t, d });
            // TODO: lock the UI while the above call is happening
        },

        async onClickCancelAddToInventory() {
            console.log('onClickCancelAddToInventory()');
            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            this.deselectAllTiles();
            this.makeTilesNonSelectable();

            delete this.clientStateArgs.selectedTileId;
            delete this.clientStateArgs.selectedInventoryTileIds;

            this.restoreServerGameState();
        },

        async onClickSurrenderButton() {
            if (!this.isCurrentPlayerActive()) return;
            console.log('onClickSurrenderButton()');

            this.makeTilesSelectable(fled.myHand);

            this.setClientState('client_selectTileForSurrender', {
                descriptionmyturn: _("${you} must select a tile to surrender to the Governor"),
            });
        },

        async onClickConfirmSurrender() {
            console.log('onClickConfirmSurrender()');
            const t = this.clientStateArgs.selectedTileId;

            this.makeTilesNonSelectable();

            const s = this.currentState === 'client_surrenderGhostTile' ? 1 : undefined;
            await invokeServerActionAsync('surrender', fled.moveNumber, { t, s });
            // TODO: lock the UI while the above call is happening

            delete this.clientStateArgs.selectedTileId;
        },

        async onClickCancelSurrender() {
            console.log('onClickCancelSurrender()');
            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            this.deselectAllTiles();
            this.makeTilesNonSelectable();

            delete this.clientStateArgs.selectedTileId;

            this.restoreServerGameState();
        },

        async onClickEscape() {
            if (!fled.canEscape()) return;
            console.log('onClickEscape()');

            const tools = fled.calculateToolsNeededToEscape();

            this.setClientState('client_selectTilesForEscape', {
                descriptionmyturn: _("${you} must select ${RT} to use for your escape"),
                args: {
                    RT: tools.reduce((html, _, i) => {
                        return html + this.format_block('fled_Templates.actionBarResourceType', { TYPE: tools[i] })
                    }, ''),
                },
            });
        },

        async onClickCancelEscape() {
            if (this.currentState !== 'client_selectTilesForEscape') return;
            console.log('onClickCancelEscape()');
            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            this.deselectAllTiles();
            this.makeTilesNonSelectable();

            delete this.clientStateArgs.selectedInventoryTileIds;

            this.restoreServerGameState();
        },

        async onClickConfirmEscape() {
            if (this.currentState !== 'client_selectTilesForEscape') return;
            console.log('onClickConfirmEscape()');

            this.makeTilesNonSelectable();

            // Format discards into a comma-separated list
            const d = this.clientStateArgs.selectedInventoryTileIds?.map(tileId => `${tileId}`).join() || undefined;
            await invokeServerActionAsync('escape', fled.moveNumber, { d });

            delete this.clientStateArgs.selectedInventoryTileIds;
        },

        async onClickTake1Draw2() {
            if (!this.isCurrentPlayerActive()) return;
            if (this.last_server_state.name !== 'drawTiles') return;
            console.log('onClickTake1Draw2()');

            this.setClientState('client_selectGovernorTile', {
                descriptionmyturn: _("${you} must select a tile from the Governor's inventory"),
            });
        },

        async onClickDrawAll() {
            if (!this.isCurrentPlayerActive()) return;
            if (this.last_server_state.name !== 'drawTiles') return;
            console.log('onClickDrawAll()');

            this.makeTilesNonSelectable();

            await invokeServerActionAsync('drawTiles', fled.moveNumber, { t: 0 });
        },

        async onClickCancelTakeFromGovernor() {
            if (!this.isCurrentPlayerActive()) return;
            if (this.last_server_state.name !== 'drawTiles') return;
            console.log('onClickCancelTakeFromGovernor()');

            clearInterval(this.clientStateArgs.confirmCountdownIntervalId);

            this.deselectAllTiles();
            this.makeTilesNonSelectable();

            delete this.clientStateArgs.selectedTileId;

            this.restoreServerGameState();
        },

        async onClickConfirmTakeFromGovernor() {
            console.log('onClickConfirmTakeFromGovernor()');
            const t = this.clientStateArgs.selectedTileId;

            this.makeTilesNonSelectable();

            await invokeServerActionAsync('drawTiles', fled.moveNumber, { t });
            // TODO: lock the UI while the above call is happening

            delete this.clientStateArgs.selectedTileId;
        },

        //
        // Helper function to either present an action confirmation
        // or to perform an action directly, depending on the
        // player's preferences
        //
        askConfirmationOrInvoke({ state, prompt, pref, bypass }) {
            if (this.prefs[pref].value == ConfirmType.Disabled) {
                bypass.call(this);
            }
            else {
                this.setClientState(state, {
                    descriptionmyturn: prompt,
                });
            }
        },

        setClientStateForSecondMove() {
            this.clientStateArgs.selectedTileId = 0;
            const needMove2 = fled.needMove2;
            if (fled.isWarder(needMove2)) {
                if (fled.warderCount === 1) {
                    this.clientStateArgs.selectedNpc = 'warder1';
                    this.setClientState('client_selectWarderDestination', {
                        descriptionmyturn: _('${you} must also select a destination for the warder'),
                    });
                }
                else {
                    this.setClientState('client_selectWarderToMove', {
                        descriptionmyturn: _('${you} must also select a warder to move'),
                    });
                    this.makeWardersSelectable();
                }
            }
            else if (needMove2 == 'specter') {
                this.clientStateArgs.selectedNpc = 'specter';
                this.setClientState('client_selectSpecterDestination', {
                    descriptionmyturn: _('${you} must also select a destination for the specter'),
                });
            }
        },


        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        async notify_tileDiscarded({ playerId, tile: tileId, gov }) {
            // Note: tileId is only sent when this player discards a tile
            // or when a player is unable to play a tile (gov == true)
            fled.removeTileFromHand(playerId, tileId);
            if (tileId) {
                if (gov) {
                    if (playerId == this.myPlayerId) {
                        await this.animateTileFromHandToGovernorInventoryAsync(tileId);
                    }
                    else {
                        this.createTileInGovernorInventory(tileId);
                    }
                }
                else {
                    await this.animateDiscardHandTileAsync(tileId);
                }
            }
        },

        async notify_specterTileDiscarded({ tile: tileId }) {
            fled.removeTileFromHand(null, tileId);
            await this.animateDiscardHandTileAsync(tileId, true);
        },

        async notify_inventoryDiscarded({ playerId, tileIds, score }) {
            for (const tileId of tileIds) {
                fled.removeTileFromInventory(playerId, tileId);
                fled.addTileToDiscardPile(tileId);
                await this.animateDiscardInventoryTileAsync(playerId, tileId);
                await this.animateCloseGapInInventoryAsync(playerId);
            }

            if (!fled.playerHasShamrockInInventory(playerId)) {
                document.getElementById(`fled_inventory-${playerId}-slot-3`).classList.add('fled_hidden');
            }
            else {
                document.getElementById(`fled_inventory-${playerId}-slot-3`).classList.remove('fled_hidden');
            }

            this.scoreCounter[playerId].setValue(score);
        },

        async notify_tilePlaced({ playerId, tile: tileId, x, y, o: orientation }) {
            // Update the internal game state
            fled.removeTileFromHand(playerId, tileId);
            fled.setTileAt(tileId, x, y, orientation);
            
            this.createTileOnMiniMap(tileId, x, y, orientation);

            // Current player has already had the tile animated to the board
            // (unless we're in archive mode!)
            if (playerId == this.myPlayerId || !playerId) {
                this.destroyRotateButton();

                if (g_archive_mode) {
                    await this.animateTileFromHandToBoardAsync(tileId, x, y, orientation, !!playerId);
                }
            }
            else {
                await this.animateOpponentTileToBoardAsync(playerId, tileId, x, y, orientation);
            }
        },

        async notify_setupComplete({ players }) {
            // Update internal game state
            fled.isSetup = true;
            for (const [ playerId, [ x, y ] ] of Object.entries(players)) {
                fled.setPlayerPosition(playerId, x, y);
            }

            this.updatePageTitle({
                name: 'client_readyToBegin',
                descriptionmyturn: _('We are ready to begin'),
                description: _('We are ready to begin'),
            });

            await Promise.all([
                this.animateSmartZoomAsync(),
                this.animateDropMeeplesOnBoardAsync(),
                this.delayAsync(1000),
            ]);
        },

        async notify_tileAddedToInventory({ playerId, tile: tileId, score }) {
            const player = fled.players[playerId];
            const slotIndex = player.inventory.length;

            fled.addTileToInventory(playerId, tileId);
            if (fled.playerHasShamrockInInventory(playerId)) {
                document.getElementById(`fled_inventory-${playerId}-slot-3`).classList.remove('fled_hidden');
            }

            if (playerId == this.myPlayerId) {
                await this.animateTileToMyInventoryAsync(tileId, slotIndex);
            }
            else {
                this.createTileInInventory(playerId, tileId, slotIndex);
            }
            this.scoreCounter[playerId].setValue(score);
        },

        async notify_tileSurrendered({ playerId, tile: tileId }) {
            fled.surrenderTile(playerId, tileId);

            if (playerId == this.myPlayerId) {
                await this.animateTileFromHandToGovernorInventoryAsync(tileId);
            }
            else if (!playerId) {
                await this.animateTileFromHandToGovernorInventoryAsync(tileId, true);
            }
            else {
                // TODO: put a small animation here... fade in, perhaps?
                this.createTileInGovernorInventory(tileId);
            }
        },

        async notify_tookFromGovernor({ playerId, tile: tileId }) {
            fled.removeTileFromGovernorInventory(tileId);
            fled.addTileToHand(playerId, tileId);

            if (playerId == this.myPlayerId) {
                await this.animateTileFromGovernorInventoryToHandAsync(tileId);
            }
            else {
                // TODO: make the tile fade out
                const tileDiv = this.getTileDiv(tileId);
                tileDiv.parentElement.removeChild(tileDiv);
            }
        },

        async notify_tilesDrawn({ playerId, n }) {
            // If another player or the Specter draw cards,
            // just do the accounting but no animations
            if (playerId && playerId != this.myPlayerId) {
                for (let i = 0; i < n; i++) {
                    fled.removeFromDrawPile();
                    fled.addTileToHand(playerId, 0);
                }
                this.updateDrawPile();
            }
        },

        async notify_tilesReceived({ tileIds, s: specter }) {
            for (const tileId of tileIds) {
                await this.animateDrawTileAsync(this.myPlayerId, tileId, specter);
                await this.delayAsync(100);
                fled.drawTile(specter ? null : this.myPlayerId, tileId);
            }
        },

        async notify_shuffled({ n }) {
            fled.shuffle(n);
            this.updateDiscardPile();
            this.updateDrawPile();
        },

        async notify_tilePlayedToMove({ playerId, tile: tileId, x, y }) {
            fled.movePlayer(playerId, tileId, x, y);

            await this.animateDiscardHandTileAsync(tileId);

            this.updateDiscardPile();

            // TODO: animate the entire path
            await this.animatePlayerMoveAsync(playerId, x, y);
        },

        async notify_tilePlayedToMoveNpcs({ playerId, tile: tileId }) {
            fled.removeTileFromHand(playerId, tileId);

            this.makeMeeplesNonSelectable();
            this.deselectAll();

            await this.animateDiscardHandTileAsync(tileId);

            fled.addTileToDiscardPile();
            this.updateDiscardPile();
        },

        async notify_playerMovedNpc({ playerId, x, y, npc: npcName, needMove2 }) {
            fled.moveNpc(npcName, x, y);

            this.makeMeeplesNonSelectable();
            this.deselectAll();

            await this.animateNpcMoveAsync(npcName, x, y);

            if (needMove2 && this.myPlayerId == playerId && !this.g_archive_mode) {
                fled.needMove2 = needMove2;
                this.setClientStateForSecondMove();
            }
        },

        async notify_specterMovedNpc({ x, y, npc: npcName }) {
            fled.moveNpc(npcName, x, y);

            this.makeMeeplesNonSelectable();
            this.deselectAll();

            await this.animateNpcMoveAsync(npcName, x, y);
        },

        async notify_missedTurn({ playerId }) {
            if (playerId == this.myPlayerId) {
                document.getElementById('pagemaintitletext').innerHTML = _('You miss your turn');
                await this.delayAsync(2000);
            }
        },

        async notify_playerSentToBunk({ playerId }) {
            const { x, y } = fled.getTilePosition(fled.getStartingBunkTileId(playerId));
            fled.setPlayerPosition(playerId, x, y);
            fled.players[playerId].inSolitary = false;

            await this.animatePlayerMoveAsync(playerId, x, y);
        },

        async notify_playerSentToSolitary({ playerId }) {
            fled.sendToSolitaryConfinement(playerId);

            const [ x, y ] = fled.players[playerId].pos;

            await this.animatePlayerMoveAsync(playerId, x, y);
        },

        async notify_shackled({ playerId, tileId, score }) {
            fled.players[playerId].shackleTile = tileId || true;
            fled.removeTileFromHand(playerId, tileId);

            // tileId is only sent to the player who owns that tile
            if (tileId && playerId == this.myPlayerId) {
                await this.animateTileToMyShackleAsync(tileId);
            }
            else {
                this.createTileAsPlayerShackle(playerId);
            }

            this.scoreCounter[playerId].setValue(score);
        },

        async notify_unshackled({ playerId, tile: tileId, score }) {
            // Update internal state
            fled.unshacklePlayer(playerId, tileId);
            
            // Update the UI
            await this.animateShackleTileToGovernorsInventory(playerId, tileId);
            this.scoreCounter[playerId].setValue(score);
        },

        async notify_whistleMoved({ playerId }) {
            const index = fled.advanceWhistlePos();
            await this.animateWhistleToPosition(index);
        },

        async notify_npcAdded({ npc: name, x, y }) {
            fled.addNpc(name, x, y);
            this.animateDropNpcOnBoardAsync(name, x, y);

            // TODO: maybe do this when tile added rather than NPC added? (because of expansion content)
            if (fled.isWarder(name)) {
                const oldIndex = fled.openWindow;
                const newIndex = fled.advanceOpenWindow();
                await this.animateRollCallWindowChangeAsync(oldIndex, newIndex);
            }
        },

        async notify_prisonerEscaped({ playerId, score }) {
            const player = fled.players[playerId];
            const [ x, y ] = player.pos;
            const headPos = fled.getTileHeadPos(x, y);

            if (!this.instantaneousMode) {
                // Scroll the board into view
                const containerDiv = document.getElementById('fled_board-container');
                containerDiv.scrollIntoView({ block: 'center', inline: 'center', behaviour: 'smooth' });
            
                await this.delayAsync(500);

                // Zoom in on the escaping player
                const { zoom } = this.calculateZoom({ x1: headPos.x - 2, y1: headPos.y - 2, x2: headPos.x + 2, y2: headPos.y + 2 });

                const xUnit = (headPos.x * 2) / FledWidth - 1;
                const yUnit = (headPos.y * 2) / FledHeight - 1;
                await this.animateZoomToAsync(xUnit, yUnit, zoom);
                
                await this.delayAsync(500);
            }
            
            // Move to the forest
            await this.animatePlayerMoveAsync(playerId, headPos.x, headPos.y);
            await this.animatePlayerEscape(playerId);

            // Update internal game state
            fled.escape(playerId);

            this.scoreCounter[playerId].setValue(score);

            // Zoom out to full board
            await this.delayAsync(1000);
            const { zoom } = this.calculateZoom({ x1: 0, y1: 0, x2: FledWidth, y2: FledHeight });
            await this.animateZoomToAsync(0, 0, zoom);
        },

        async notify_hardLabor() {
            fled.startHardLabor();
            
            // Move warder1 to the starting yard tile
            const promises = [
                this.animateMeepleMoveAsync('warder1', 6, 6, this.instantaneousMode),
            ];

            // Move the player meeples to the starting yard tile
            promises.push(
                ...Object.values(fled.players).map(async (player, i) => {
                    if (player.escaped) return;
                    await this.delayAsync(500 + i * 50);
                    const name = MeepleNames[player.color];
                    await this.animateMeepleMoveAsync(name, 6, 6, this.instantaneousMode);
                })
            );

            // Zoom in on the yard tile
            promises.push((async () => {
                await this.delayAsync(500);
                await this.animateZoomToAsync(0, 0, 1);
            })());

            await Promise.all(promises);
        },

        // Only called for the current player
        async notify_actionComplete() {
            fled.actionComplete();

            if (fled.actionsPlayed !== 1) return;
            this.setClientState('client_playSecondAction', {
                descriptionmyturn: applyMarkup(_('${you} must perform the /second/ of two actions')),
            });
        },

        async notify_turnEnded() {
            fled.endTurn();
        },

        async notify_spectersTurn() {
            fled.spectersTurn = true;
            this.setClientState('client_spectersTurn', {
                descriptionmyturn: _("It's the Specter's turn"),
            });
            await Promise.all([
                this.delayAsync(800),
                this.animateHandToTheLeftAsync('normal'),
            ]);
        },

        async notify_endSpectersTurn() {
            fled.spectersTurn = false;
            this.setClientState('client_endSpectersTurn', {
                descriptionmyturn: _("It's your turn"),
            });
            await Promise.all([
                this.delayAsync(800),
                this.animateHandToTheRightAsync('normal'),
            ]);
        },

        async notify_lostSoloGame() {
            this.scoreCounter[this.myPlayerId].setValue(0);
        },
    });
});
