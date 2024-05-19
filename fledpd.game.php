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

require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');
require_once('modules/FledLogic.php');


class FledPD extends Table
{
	function __construct()
	{
        parent::__construct();
        
        self::initGameStateLabels([
            // Game Options
            //"houndExpansion" => 100, // TODO
            //"specterExpansion" => 101,
        ]);
	}
	
    protected function getGameName()
    {
		// Used for translations and stuff. Please do not modify.
        return "fledpd";
    }	

    //
    // This method is called only once, when a new game is launched.
    // In this method, you must setup the game according to the game
    // rules, so that the game is ready to be played.
    //
    protected function setupNewGame($players, $options = [])
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfo = $this->getGameinfos();
        $defaultColors = $gameinfo['player_colors'];
 
        // Create players
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];
        foreach($players as $playerId => $player)
        {
            $color = array_shift($defaultColors);
            $values[] = "(" .
                "'" . $playerId . "'" .
                ", '$color'" .
                ",'" . $player['player_canal'] ."'" .
                ",'" . addslashes($player['player_name']) . "'" .
                ",'" . addslashes($player['player_avatar']) . "'" .
            ")";
        }
        $sql .= implode(',', $values);
        $this->DbQuery($sql);
        $this->reattributeColorsBasedOnPreferences($players, $gameinfo['player_colors']);
        $this->reloadPlayersBasicInfos();
        
        $playerIds = array_keys($players);
        $playerCount = count($playerIds);
        $playerColors = $this->getCollectionFromDB("SELECT player_id, player_color FROM player", true);
        $playerColorIndices = array_map(fn($color) => array_search($color, $gameinfo['player_colors']), $playerColors);


        /************ Start the game initialization *****/

        //
        // Init game statistics
        //
        $this->initStat('table', 'prison_size', 0);
        $this->initStat('table', 'warder_count', 1);
        $this->initStat('table', 'whistles_blown', 0);
        $this->initStat('table', 'warder_distance', 0);
        $this->initStat('table', 'chaplain_distance', 0);
        $this->initStat('table', 'hound_distance', 0);
        $this->initStat('table', 'specter_distance', 0);
        $this->initStat('table', 'governors_inventory_max', 0);
        $this->initStat('table', 'confiscations', 0);
        $this->initStat('table', 'items_surrendered', 0);
        $this->initStat('table', 'items_from_governor', 0);
        $this->initStat('table', 'items_from_chaplain', 0);
        $this->initStat('table', 'escaped', 0);
        $this->initStat('table', 'hard_labour', 0);

        $this->initStat('player', 'added_to_prison', 0);
        $this->initStat('player', 'whistles_blown', 0);
        $this->initStat('player', 'shackled', 0);
        $this->initStat('player', 'shackled_opponents', 0);
        $this->initStat('player', 'solitary', 0);
        $this->initStat('player', 'contraband_found', 0);
        $this->initStat('player', 'tools_acquired', 0);
        $this->initStat('player', 'shamrocks_acquired', 0);
        $this->initStat('player', 'shamrocks_played', 0);
        $this->initStat('player', 'tools_used_single', 0);
        $this->initStat('player', 'tools_used_double', 0);
        $this->initStat('player', 'escaped', 0);
        $this->initStat('player', 'opponent_escaped', 0);
        $this->initStat('player', 'traversed_window', 0);
        $this->initStat('player', 'traversed_door', 0);
        $this->initStat('player', 'traversed_archway', 0);
        $this->initStat('player', 'traversed_tunnels', 0);
        $this->initStat('player', 'distance_traveled', 0);
        $this->initStat('player', 'items_surrendered', 0);
        $this->initStat('player', 'items_from_governor', 0);
 

        $fledOptions = [
            'specterExpansion' => false, // TODO
            'houndExpansion' => false, // TODO
        ];
        
        $fled = FledLogic::newGame($playerColorIndices, $fledOptions);
        $this->initializeGameState($fled);

        // Must set the first active player
        $this->activeNextPlayer();
    }

    //
    // Gather all informations about current game situation (visible by the current player).
    // The method is called each time the game interface is displayed to a player,
    // i.e. when the game starts and when a player refreshes the game page (F5).
    //
    protected function getAllDatas()
    {
        $currentPlayerId = $this->getCurrentPlayerId();
        $fled = $this->loadGameState();        
        return [
            'data' => $fled->getPlayerData($currentPlayerId),
            'scores' => $fled->getScores(),
        ];
    }

    //
    // Compute and return the current game progression. The number returned must be
    // an integer beween 0 (the game just started) and 100 (the game is finished).
    //
    function getGameProgression()
    {
        $fled = $this->loadGameState();
        return $fled->getGameProgression();
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Database functions
////////////

    protected function initializeGameState($fled)
    {
        $json = $fled->toJson();
        $this->DbQuery("INSERT INTO game_state (doc) VALUES ('$json')");
    }

    protected function loadGameState()
    {
        $json = $this->getObjectFromDB("SELECT id, doc FROM game_state LIMIT 1")['doc'];
        return FledLogic::fromJson($json);
    }

    protected function saveGameState($fled)
    {
        $json = $fled->toJson();
        $this->DbQuery("UPDATE game_state SET doc = '$json'");
    }

    protected function getPlayerScores()
    {
        return array_map(fn($s) => intval($s), $this->getCollectionFromDB('SELECT player_id, player_score FROM player', true));
    }

    protected function setPlayerScore($playerId, $score, $scoreAux)
    {
        $this->DbQuery(<<<SQL
            UPDATE player
            SET player_score = '$score',
                player_score_aux = '$scoreAux'
            WHERE player_id = '$playerId'
        SQL);
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    protected function validateCaller()
    {
        // Get the function name of the caller -- https://stackoverflow.com/a/11238046
        $fnName = debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        $actionName = explode('_', $fnName)[1];
        self::checkAction($actionName);

        // Active player is whose turn it is
        $activePlayerId = self::getActivePlayerId();

        // Current player is who made the AJAX call to us
        $currentPlayerId = self::getCurrentPlayerId();

        // Bail out if the current player is not the active player
        if ($activePlayerId != $currentPlayerId)
            throw new BgaVisibleSystemException(self::_("It is not your turn"));

        return $activePlayerId;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    //
    // When Player has no legal tiles to add to the prison, they must discard.
    //
    function action_discard($tileId)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        if (!$fled->discardTile($tileId))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': discard failed',
                'player: ' . $playerId,
                'tileId: ' . $tileId,
                'state: ' . $fled->toJson()
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->afterDiscardTile($fled, $playerId, $tileId);
    }

    function afterDiscardTile(FledLogic $fled, $activePlayerId, $tileId)
    {
        // TODO: update internal game state

        //
        // Update the player stats
        //
        // TODO: increment tiles discarded (maybe differentiate due to being unplayable)

        //
        // Send notifications to players
        //
        $this->notifyAllPlayers('unableToAdd', clienttranslate('${playerName} is unable to add a tile to the prison'), [
            'playerName' => $this->getPlayerNameById($activePlayerId),
        ]);
        foreach ($fled->getPlayerIds() as $playerId)
        {
            if ($playerId == $activePlayerId)
            {
                $this->notifyPlayer($playerId, 'tileDiscarded', clienttranslate('${playerName} discards a tile'), [
                    'playerName' => $this->getPlayerNameById($playerId),
                    'playerId' => $activePlayerId,
                    'tileId' => $tileId,
                    'preserve' => [ 'tileId' ],
                ]);
            }
            else
            {
                $this->notifyPlayer($playerId, 'tileDiscarded', clienttranslate('${playerName} discards a tile'), [
                    'playerName' => $this->getPlayerNameById($playerId),
                    'playerId' => $activePlayerId,
                    'preserve' => [ 'playerId' ],
                ]);
            }
        }

        $this->gamestate->nextState('nextPhase');
    }

    function action_placeTile($tileId, $x, $y, $orientation)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        if (!$fled->placeTile($tileId, $x, $y, $orientation, $moon, $newNpcs))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': placeTile failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $x, $y, $orientation ]),
                'state: ' . $fled->toJson()
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->afterPlaceTile($fled, $playerId, $tileId, $x, $y, $orientation, $moon, $newNpcs);
    }

    //
    // This functionality is the same whether called by a real player
    // or a zombie player. The logic is extracted into a shared function
    // to ensure same behaviour for both cases.
    //
    function afterPlaceTile(FledLogic $fled, $activePlayerId, $tileId, $x, $y, $orientation, $moon, $newNpcs)
    {
        //
        // Update the player stats
        //
        $this->incStat(1, 'prison_size');
        $this->incStat(1, 'added_to_prison', $activePlayerId);

        //
        // Send notifications to players
        //
        $this->notifyAllPlayers('tilePlayed', clienttranslate('${playerName} adds ${_tile} to the prison'), [
            'i18n' => [ '_tile' ],
            '_tile' => clienttranslate('a tile'),
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            'tile' => $tileId,
            'x' => $x,
            'y' => $y,
            'o' => $orientation,
            'preserve' => [ 'playerId', 'x', 'y', 'o', 'tile' ],
        ]);

        // NPC Display name for the replay log
        $npcDisplayLabel = [
            'warder2' => clienttranslate('A new Warder'),
            'warder3' => clienttranslate('A new Warder'),
            'chaplain' => clienttranslate('The Chaplain'),
        ];

        foreach ($newNpcs as $name => $npc)
        {
            $this->incStat(1, 'warder_count');

            $this->notifyAllPlayers('npcAdded', clienttranslate('${_npc} has been added to the prison at (${x}, ${y})'), [
                'i18n' => [ '_npc' ],
                '_npc' => $npcDisplayLabel[$name],
                'npc' => $name,
                'x' => $npc->pos[0],
                'y' => $npc->pos[1],
                'preserve' => [ 'npc' ],
            ]);
        }

        // Check if the game is now ready to start
        if ($fled->isGameSetup() && $fled->getMoveCount() == 0)
        {
            $this->notifyAllPlayers('setupComplete', clienttranslate('<b>WHISTLE!</b> All prisoners go to their bunk room'), [
                'players' => $fled->getPlayerPositions(),
                'preserve' => [ 'players' ],
            ]);
        }

        $this->gamestate->nextState('');
    }

    function action_move($tileId, $x, $y)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        if (!$fled->discardTileToMove($tileId, $x, $y, $tool, $path))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': move failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $x, $y ]),
                'state: ' . $fled->toJson(),
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->afterMovePlayer($fled, $playerId, $tileId, $x, $y, $tool, $path);
    }

    function afterMovePlayer(FledLogic $fled, $activePlayerId, $tileId, $x, $y, $tool, $path)
    {
        switch (FledLogic::getTileColor($tileId))
        {
            case FLED_COLOR_GREEN:  $this->incStat(1, 'shamrocks_played', $activePlayerId); break;
            case FLED_COLOR_PURPLE: $this->incStat(1, 'tools_used_single', $activePlayerId); break;
            case FLED_COLOR_GOLD:   $this->incStat(1, 'tools_used_double', $activePlayerId); break;
        }
        switch ($tool)
        {
            case FLED_TOOL_FILE:  $this->incStat(1, 'traversed_window', $activePlayerId); break;
            case FLED_TOOL_KEY:   $this->incStat(1, 'traversed_door', $activePlayerId); break;
            case FLED_TOOL_BOOT:  $this->incStat(1, 'traversed_archway', $activePlayerId); break;
            case FLED_TOOL_SPOON: $this->incStat(1, 'traversed_tunnels', $activePlayerId); break;
        }
        $distance = count($path) - 1;
        $this->incStat($distance, 'distance_traveled', $activePlayerId);

        //
        // Send notifications to players
        //
        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('tilePlayedToMove', clienttranslate('${playerName} plays ${_item} to move to (${x}, ${y})'), [
            'i18n' => [ '_item' ],
            '_item' => $this->Items[$itemId]['one'],
            'item' => $itemId,
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            'tile' => $tileId,
            'x' => $x,
            'y' => $y,
            'preserve' => [ 'playerId', 'tile', 'item' ],
        ]);

        $this->afterAction($fled);
    }

    function action_moveWarder($tileId, $x, $y, $w, $p)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $targetPlayerId = $p ? $fled->getPlayerIdByColorName($p) : null;
        if (!$fled->discardTileToMoveWarder($tileId, $x, $y, $w, $targetPlayerId, $shackleTile, $unshackleTile, $toBunk, $toSolitary, $path, $targetIsSafe))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': moveWarder failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $x, $y, $w, $p ]),
                'state: ' . $fled->toJson(),
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->afterMoveWarder($fled, $playerId, $tileId, $x, $y, $w, $targetPlayerId, $shackleTile, $unshackleTile, $toBunk, $toSolitary, $path, $targetIsSafe);
    }

    function afterMoveWarder(FledLogic $fled, $activePlayerId, $tileId, $x, $y, $w, $targetPlayerId, $shackleTile, $unshackleTile, $toBunk, $toSolitary, $path, $targetIsSafe)
    {
        //
        // Update stats
        //
        $this->incStat(1, 'whistles_blown');

        $distance = count($path) - 1;
        $this->incStat($distance, 'warder_distance');

        $inventoryMax = max($fled->countGovernorInventory(), $this->getStat('governors_inventory_max'));
        $this->setStat($inventoryMax, 'governors_inventory_max');

        if ($toBunk)
            $this->incStat(1, 'confiscations');

        $this->incStat(1, 'whistles_blown', $activePlayerId);

        if ($shackleTile)
        {
            $this->incStat(1, 'shackled', $targetPlayerId);
            $this->incStat(1, 'shackled_opponents', $activePlayerId);
        }

        if ($toSolitary)
            $this->incStat(1, 'solitary', $targetPlayerId);

        //
        // Notify Players
        //
        $this->notifyAllPlayers('tilePlayedToMoveWarder', clienttranslate('${playerName} plays ${_tile} to move ${_npc} to (${x}, ${y})'), [
            'i18n' => [ '_tile', '_npc' ],
            '_tile' => _('a tile'),
            '_npc' =>
                $w == 'chaplain'
                    ? _('the chaplain')
                    : _('a warder'),
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            'tile' => $tileId,
            'npc' => $w,
            'x' => $x,
            'y' => $y,
            'preserve' => [ 'playerId', 'tile', 'npc' ],
        ]);

        if ($toBunk)
        {
            $this->notifyAllPlayers('playerSentToBunk', clienttranslate('${playerName} is sent back to bunk'), [
                'playerName' => $this->getPlayerNameById($targetPlayerId),
                'playerId' => $targetPlayerId,
                'preserve' => [ 'playerId' ],
            ]);
        }

        if ($shackleTile)
        {
            foreach ($fled->getPlayerIds() as $playerId)
            {
                if ($playerId == $targetPlayerId)
                {
                    $this->notifyPlayer($playerId, 'shackled', clienttranslate('${playerName} is <b>shackled!</b>'), [
                        'playerName' => $this->getPlayerNameById($targetPlayerId),
                        'playerId' => $targetPlayerId,
                        'tileId' => $shackleTile,
                        'score' => $fled->getPlayerScore($targetPlayerId),
                        'preserve' => [ 'playerId', 'tileId', 'score' ],
                    ]);
                }
                else
                {
                    $this->notifyPlayer($playerId, 'shackled', clienttranslate('${playerName} is <b>shackled!</b>'), [
                        'playerName' => $this->getPlayerNameById($targetPlayerId),
                        'playerId' => $targetPlayerId,
                        'score' => $fled->getPlayerScore($targetPlayerId),
                        'preserve' => [ 'playerId', 'score' ],
                    ]);
                }
            }
        }

        if ($unshackleTile)
        {
            $this->notifyAllPlayers('unshackled', clienttranslate('${playerName} is <b>unshackled</b>'), [
                'playerName' => $this->getPlayerNameById($targetPlayerId),
                'playerId' => $targetPlayerId,
                'tileId' => $unshackleTile,
                'score' => $fled->getPlayerScore($targetPlayerId),
                'preserve' => [ 'playerId', 'tileId', 'score' ],
            ]);
        }

        if ($toSolitary)
        {
            $this->notifyAllPlayers('playerSentToSolitary', clienttranslate('${playerName} is sent to <b>solitary confinement</b>'), [
                'playerName' => $this->getPlayerNameById($targetPlayerId),
                'playerId' => $targetPlayerId,
                'preserve' => [ 'playerId' ],
            ]);
        }

        if ($targetIsSafe)
        {
            $roomType = $fled->getRoomTypeAt($x, $y);
            $this->notifyAllPlayers('playerSafe', clienttranslate('${playerName} is <b>safe</b> in the ${_room}'), [
                'i18n' => [ '_room' ],
                '_room' => $this->RoomTypeLabels[$roomType],
                'room' => $roomType,
                'preserve' => [ 'room' ],
            ]);
        }

        $safeRoomTypes = $fled->getSafeRollCallRooms();
        if (count($safeRoomTypes) == 2)
        {
            $this->notifyAllPlayers('whistleMoved', clienttranslate('Prisoners caught outside of ${_room0} and ${_room1} will be in trouble!'), [
                'i18n' => [ '_room0', '_room1' ],
                '_room0' => $this->RoomTypeLabels[$safeRoomTypes[0]],
                '_room1' => $this->RoomTypeLabels[$safeRoomTypes[1]],
                'room0' => $safeRoomTypes[0],
                'room1' => $safeRoomTypes[1],
                'playerId' => $activePlayerId,
                'preserve' => [ 'room0', 'room1', 'playerId' ],
            ]);
        }
        else
        {
            $this->notifyAllPlayers('whistleMoved', clienttranslate('Prisoners caught outside of ${_room} will be in trouble!'), [
                'i18n' => [ '_room' ],
                '_room' => $this->RoomTypeLabels[$safeRoomTypes[0]],
                'room' => $safeRoomTypes[0],
                'playerId' => $activePlayerId,
                'preserve' => [ 'room', 'playerId' ],
            ]);
        }

        $this->afterAction($fled);
    }

    function action_add($tileId, $discards)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        if (!$fled->addTileToInventory($tileId, $discards))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': add failed',
                'player: ' . $playerId,
                'tileId: ' . $tileId,
                'state: ' . $fled->toJson(),
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->afterAddTileToInventory($fled, $playerId, $tileId, $discards);
    }

    function afterAddTileToInventory(FledLogic $fled, $activePlayerId, $tileId, $discards)
    {
        // Update stats
        switch (FledLogic::getTileColor($tileId))
        {
            case FLED_COLOR_BLUE:  $this->incStat(1, 'contraband_found', $activePlayerId); break;
            case FLED_COLOR_PURPLE: // fall through
            case FLED_COLOR_GOLD:  $this->incStat(1, 'tools_acquired', $activePlayerId); break;
            case FLED_COLOR_GREEN: $this->incStat(1, 'shamrocks_acquired', $activePlayerId); break;
        }

        // Update player score in database
        $score = $fled->getPlayerScore($activePlayerId);
        $this->setPlayerScore($activePlayerId, $score, 0);

        $this->notify_inventoryDiscarded($fled, $activePlayerId, $discards);

        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('tileAddedToInventory', clienttranslate('${playerName} gains ${_item}'), [
            'i18n' => [ '_item' ],
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            '_item' => $this->Items[$itemId]['one'],
            'item' => $itemId,
            'tileId' => $tileId,
            'score' => $score,
            'preserve' => [ 'playerId', 'item', 'tileId', 'score' ],
        ]);

        $this->afterAction($fled);
    }

    function action_surrender($tileId)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        if (!$fled->surrenderTile($tileId))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': surrender failed',
                'player: ' . $playerId,
                'tileId: ' . $tileId,
                'state: ' . $fled->toJson(),
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->afterSurrenderTile($fled, $playerId, $tileId);
    }

    //
    // This functionality is the same whether called by a real player
    // or a zombie player. The logic is extracted into a shared function
    // to ensure same behaviour for both cases.
    //
    function afterSurrenderTile(FledLogic $fled, $activePlayerId, $tileId)
    {
        //
        // Update the table and player stats
        //
        $inventoryMax = max($fled->countGovernorInventory(), $this->getStat('governors_inventory_max'));
        $this->setStat($inventoryMax, 'governors_inventory_max');

        $this->incStat(1, 'items_surrendered');
        $this->incStat(1, 'items_surrendered', $activePlayerId);

        //
        // Send notifications to players
        //
        $this->notifyAllPlayers('tileSurrendered', clienttranslate('${playerName} surrenders ${_tile} to the Governor'), [
            'i18n' => [ '_tile' ],
            '_tile' => clienttranslate('a tile'),
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            'tile' => $tileId,
            'preserve' => [ 'playerId', 'tile' ],
        ]);

        $this->afterAction($fled);
    }

    function afterAction(FledLogic $fled)
    {
        if ($fled->countActionsPlayed() == 2)
            $this->gamestate->nextState('nextPhase');
    }

    function action_escape($discards)
    {
        $activePlayerId = $this->validateCaller();

        $fled = $this->loadGameState();
        if (!$fled->discardTilesToEscape($discards))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': escape failed',
                'player: ' . $activePlayerId,
                'state: ' . $fled->toJson(),
                'input: ' . json_encode([ $discards ]),
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        //
        // Update the player stats
        //
        $this->incStat(1, 'escaped');
        $this->setStat(1, 'escaped', $activePlayerId);
        foreach ($fled->getPlayerIds() as $playerId)
            if ($playerId != $activePlayerId)
                $this->incStat(1, 'opponent_escaped', $playerId);

        //
        // Update player score in database
        //
        $score = $fled->getPlayerScore($playerId);
        $this->setPlayerScore($playerId, $score, 0);

        //
        // Send notifications to players
        //
        $this->notify_inventoryDiscarded($fled, $activePlayerId, $discards);

        $this->notifyAllPlayers('prisonerEscaped', clienttranslate('*${playerName} escapes!*'), [
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            'score' => $score,
            'discards' => $discards,
            'preserve' => [ 'playerId', 'score', 'discards' ],
        ]);

        // Skip the draw phase and immediately go to the next player.
        // Everyone else gets one more turn.
        $this->gamestate->nextState('nextTurn');
    }

    function action_drawTiles($governorTileId)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        if (!$fled->drawTiles($governorTileId, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileSize))
        {
            $refId = uniqid();
            self::trace(implode(', ', [
                'Ref #' . $refId . ': drawTiles failed',
                'player: ' . $playerId,
                'tileId: ' . $governorTileId,
                'state: ' . $fled->toJson(),
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->afterDrawTiles($fled, $playerId, $governorTileId, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileSize);
    }

    function afterDrawTiles(FledLogic $fled, $activePlayerId, $governorTileId, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileSize)
    {
        //
        // Update the player stats
        //
        if ($governorTileId)
        {
            $this->incStat(1, 'items_from_governor');
            $this->incStat(1, 'items_from_governor', $activePlayerId);
        }

        //
        // Send notifications to players
        //
        if ($governorTileId)
        {
            $this->notifyAllPlayers('tookFromGovernor', clienttranslate('${playerName} takes a tile from the Governor\'s inventory'), [
                'playerName' => $this->getPlayerNameById($activePlayerId),
                'playerId' => $activePlayerId,
                'tileId' => $governorTileId,
                'preserve' => [ 'playerId', 'tileId' ],
            ]);
        }

        if (count($drawnBeforeShuffle))
        {
            $logMessage =
                count($drawnBeforeShuffle) === 1
                    ? clienttranslate('${playerName} draws 1 tile')
                    : clienttranslate('${playerName} draws ${n} tiles');

            foreach ($fled->getPlayerIds() as $playerId)
            {
                if ($playerId == $activePlayerId)
                {
                    $this->notifyPlayer($playerId, 'tilesDrawn', $logMessage, [
                        'playerName' => $this->getPlayerNameById($activePlayerId),
                        'playerId' => $activePlayerId,
                        'n' => count($drawnBeforeShuffle),
                        'tileIds' => $drawnBeforeShuffle,
                        'preserve' => [ 'tileIds', 'r', 'playerId' ],
                    ]);
                }
                else
                {
                    $this->notifyPlayer($playerId, 'tilesDrawn', $logMessage, [
                        'playerName' => $this->getPlayerNameById($activePlayerId),
                        'playerId' => $activePlayerId,
                        'n' => count($drawnBeforeShuffle),
                        'preserve' => [ 'playerId' ],
                    ]);
                }
            }
        }

        if (count($drawnAfterShuffle))
        {
            $this->notifyAllPlayers('shuffled', clienttranslate('Discards shuffled into new draw pile (<b>${n} tiles remain</b>)'), [
                'n' => $drawPileSize + count($drawnAfterShuffle),
            ]);

            $logMessage =
                count($drawnBeforeShuffle) === 0
                    ? clienttranslate('${playerName} draws ${n} tiles')
                    : (count($drawnAfterShuffle) === 1
                        ? clienttranslate('${playerName} draws 1 more tile')
                        : clienttranslate('${playerName} draws ${n} more tiles')
                    );

            foreach ($fled->getPlayerIds() as $playerId)
            {
                if ($playerId == $activePlayerId)
                {
                    $this->notifyPlayer($playerId, 'tilesDrawn', $logMessage, [
                        'playerName' => $this->getPlayerNameById($activePlayerId),
                        'playerId' => $activePlayerId,
                        'n' => count($drawnAfterShuffle),
                        'tileIds' => $drawnAfterShuffle,
                        'preserve' => [ 'tileIds', 'playerId' ],
                    ]);
                }
                else
                {
                    $this->notifyPlayer($playerId, 'tilesDrawn', $logMessage, [
                        'playerName' => $this->getPlayerNameById($activePlayerId),
                        'playerId' => $activePlayerId,
                        'n' => count($drawnAfterShuffle),
                        'preserve' => [ 'playerId' ],
                    ]);
                }
            }
        }

        $this->gamestate->nextState('nextTurn');
    }

    function notify_inventoryDiscarded(FledLogic $fled, $playerId, $discards)
    {
        //
        // Send notifications to players
        //
        if ($discards)
        {
            $tile1 = FledLogic::$FledTiles[$discards[0]];
            $itemId1 = $tile1['contains'];

            $itemsParams = [];
            if ($discards == 2)
            {
                $tile2 = FledLogic::$FledTiles[$discards[1]];
                $itemId2 = $tile2['contains'];

                if ($itemId1 == $itemId2)
                {
                    $itemsParams = [
                        'log' => '${_item1}${_item2}',
                        'args' => [
                            'i18n' => [ '_item1', '_item2' ],
                            '_item1' => $this->Items[$itemId1]['two'],
                            '_item2' => '',
                            'item1' => $itemId1,
                            'item2' => $itemId2,
                        ],
                    ];
                }
                else
                {
                    $itemsParams = [
                        'log' => '${_item1}${_item2}',
                        'args' => [
                            'i18n' => [ '_item1', '_item2' ],
                            '_item1' => $this->Items[$itemId1]['one'],
                            '_item2' => $this->Items[$itemId1]['one'],
                            'item1' => $itemId1,
                            'item2' => $itemId2,
                        ],
                    ];
                }
            }
            else
            {
                $itemsParams = [
                    'log' => '${_item1}',
                    'args' => [
                        'i18n' => [ '_item1' ],
                        '_item1' => $this->Items[$itemId1]['one'],
                        'item1' => $itemId1,
                    ],
                ];
            }

            $this->notifyAllPlayers('inventoryDiscarded', clienttranslate('${playerName} discards ${items} from inventory'), [
                'items' => $itemsParams,
                'playerName' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'tileIds' => $discards,
                'score' => $fled->getPlayerScore($playerId),
                'preserve' => [ 'playerId', 'tileIds', 'score' ],
            ]);
        }
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////


//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    function stNextTurn()
    {
        $fled = $this->loadGameState();

        if ($fled->wasHardLaborCalled())
        {
            $this->setStat(1, 'hard_labour');
            $this->notifyAllPlayers('hardLabor', clienttranslate('<b>The Governor summons everyone to hard labour in the quarry!</b>'), []);
        }

        if ($fled->getGameProgression() >= 100)
        {
            $this->gamestate->nextState('gameOver');
            return;
        }

        $playerId = $fled->getNextPlayerId();

        // Determine if the next player should lose a turn from being in Solitary Confinement
        $fledChanged = false;
        while ($fled->isPlayerInSolitary($playerId))
        {
            $fled->releasePlayerFromSolitary($playerId, $unshackleTile);
            $fledChanged = true;

            $this->notifyAllPlayers('missedTurn', clienttranslate('${playerName} <b>misses a turn</b> in solitary confinement'), [
                'playerName' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'preserve' => [ 'playerId' ],
            ]);

            $this->notifyAllPlayers('playerSentToBunk', clienttranslate('${playerName} is sent back to bunk'), [
                'playerName' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'preserve' => [ 'playerId' ],
            ]);

            $this->notifyAllPlayers('unshackled', clienttranslate('${playerName} is <b>unshackled</b>'), [
                'playerName' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'tileId' => $unshackleTile,
                'score' => $fled->getPlayerScore($playerId),
                'preserve' => [ 'playerId', 'tileId', 'score' ],
            ]);

            $playerId = $fled->getNextPlayerId();
        }

        if ($fledChanged)
            $this->saveGameState($fled);

        // Is the game over now? (now that we've potentially skipped over other players)
        if ($fled->getGameProgression() >= 100)
        {
            $this->gamestate->nextState('gameOver');
            return;
        }
        
        $this->giveExtraTime($playerId);
        $this->gamestate->changeActivePlayer($playerId);

        if ($fled->isGameSetup())
            $this->gamestate->nextState('nextTurn');
        else
            $this->gamestate->nextState('nextStarterTurn');
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    function zombieTurn($state, $zombiePlayerId)
    {
    	$stateName = $state['name'];

        if ($state['type'] !== "activeplayer")
            throw new feException("Zombie mode not supported at this game state: " . $stateName); // NOI18N

        $fled = $this->loadGameState();

        switch ($stateName)
        {
            case STATE_ADD_STARTER_TILE:
                // Randomly choose a tile and a legal move
                $legalMoves = $fled->getLegalStartingTileMoves();
                shuffle($legalMoves);
                $move = array_pop($legalMoves);
                if (!$fled->placeTile($move[0], $move[1], $move[2], $move[3], $moon, $newNpcs))
                {
                    $refId = uniqid();
                    self::trace(implode(', ', [
                        'Ref #' . $refId . ': placeTile failed',
                        'zombie player: ' . $zombiePlayerId,
                        'tileId: ' . $move[0],
                        'x: ' . $move[1],
                        'y: ' . $move[2],
                        'o: ' . $move[3],
                        'state: ' . $fled->toJson()
                    ]));
                    throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
                }
                $this->saveGameState($fled);
        
                $this->afterPlaceTile($fled, $zombiePlayerId, $move[0], $move[1], $move[2], $move[3], $moon, $newNpcs);
                return;

            case STATE_ADD_TILE:
                // Randomly choose a tile and a legal move
                $legalMoves = $fled->getLegalTileMoves();
                shuffle($legalMoves);
                $move = array_pop($legalMoves);
                if (!$fled->placeTile($move[0], $move[1], $move[2], $move[3], $moon, $newNpcs))
                {
                    $refId = uniqid();
                    self::trace(implode(', ', [
                        'Ref #' . $refId . ': placeTile failed',
                        'zombie player: ' . $zombiePlayerId,
                        'tileId: ' . $move[0],
                        'x: ' . $move[1],
                        'y: ' . $move[2],
                        'o: ' . $move[3],
                        'state: ' . $fled->toJson()
                    ]));
                    throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
                }
                $this->saveGameState($fled);
        
                $this->afterPlaceTile($fled, $zombiePlayerId, $move[0], $move[1], $move[2], $move[3], $moon, $newNpcs);
                return;

            case STATE_PLAY_TILES:
                // Just surrender random tiles
                $handTiles = $fled->getHandTilesEligibleForSurrender();
                $index = rand(0, count($handTiles) - 1);
                $surrenderTile = $handTiles[$index];
                $fled->surrenderTile($surrenderTile);
                $this->saveGameState($fled);
                $this->afterSurrenderTile($fled, $zombiePlayerId, $surrenderTile);
                return;

            case STATE_DRAW_TILES:
                $fled->drawTiles(0, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileCount, $hardLabor);
                $this->saveGameState($fled);
                $this->afterDrawTiles($fled, $zombiePlayerId, 0, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileCount, $hardLabor);
                return;
        }
    }

    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
    }    
}
