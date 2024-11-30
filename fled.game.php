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
require_once('modules/FledEvents.php');


class Fled extends Table implements FledEvents
{
    private FledLogic | null $fled;

	function __construct()
	{
        parent::__construct();
        
        self::initGameStateLabels([
            // Game Options
            FLED_OPT_HOUND => FLED_OPT_HOUND,
            FLED_OPT_SPECTER => FLED_OPT_SPECTER,
            FLED_OPT_SPECTER_SOLO => FLED_OPT_SPECTER_SOLO,
        ]);

        $this->fled = null;
	}
	
    protected function getGameName()
    {
		// Used for translations and stuff. Please do not modify.
        return "fled";
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
        $this->initStat('table', 'whistles_blown', 0);
        $this->initStat('table', 'warder_distance', 0);
        $this->initStat('table', 'chaplain_distance', 0);
        $this->initStat('table', 'hound_distance', 0);
        $this->initStat('table', 'specter_distance', 0);
        $this->initStat('table', 'confiscations', 0);
        $this->initStat('table', 'items_surrendered', 0);
        $this->initStat('table', 'items_from_governor', 0);
        $this->initStat('table', 'items_from_chaplain', 0);
        $this->initStat('table', 'escaped', 0);

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
        $this->initStat('player', 'traversed_window', 0);
        $this->initStat('player', 'traversed_door', 0);
        $this->initStat('player', 'traversed_archway', 0);
        $this->initStat('player', 'traversed_tunnels', 0);
        $this->initStat('player', 'distance_traveled', 0);
        $this->initStat('player', 'items_surrendered', 0);
        $this->initStat('player', 'items_from_governor', 0);
 

        $fledOptions = [
            'specterExpansion' =>
                $this->getGameStateValue(FLED_OPT_SPECTER) == FLED_OPT_SPECTER_YES ||
                $this->getGameStateValue(FLED_OPT_SPECTER_SOLO) == FLED_OPT_SPECTER_SOLO_YES,
            'houndExpansion' =>
                $this->getGameStateValue(FLED_OPT_HOUND) == FLED_OPT_HOUND_YES,
        ];
        
        $fled = FledLogic::newGame($playerColorIndices, $fledOptions, $this);
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
        $data = [
            'data' => $fled->getPlayerData($currentPlayerId),
            'scores' => $fled->getScores(),
        ];
        if ($this->getBgaEnvironment() == 'studio')
            $data['state'] = $fled->toJson();
        return $data;
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
        if (!$this->fled)
        {
            try
            {
                $json = $this->getObjectFromDB("SELECT id, doc FROM game_state LIMIT 1")['doc'];
                if (!$json)
                    throw new Exception('Failed to load game state JSON');
                $this->fled = FledLogic::fromJson($json, $this);
            }
            catch (Throwable $e)
            {
                $this->error($e->getTraceAsString());
                throw $e;
            }
        }
        return $this->fled;
    }

    protected function saveGameState(FledLogic $fled)
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

    function action_debugSetState($json)
    {
        if ($this->getBgaEnvironment() != 'studio')
            throw new Exception('Cannot set state in Production');

        $this->warn(`** DEBUG setState: ` . $json);
        $fled = FledLogic::fromJson($json, $this);
        $this->saveGameState($fled);

        $this->gamestate->nextState('debugSetState');
    }

    public function action_jsError($msg, $url, $line, $userAgent)
    {
        $this->error(implode(PHP_EOL, [
            '##### Client Error #####',
            '- error: ' . $msg,
            '- url: ' . $url,
            '- line: ' . $line,
            '- ua: ' . $userAgent,
            '########################',
        ]));
    }

    //
    // When Player has no legal tiles to add to the prison, they must discard.
    // Also used in Solo mode to discard a card from the Specter's hand.
    //
    function action_discard($tileId, bool $specter)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->discardTile($tileId, $specter);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $this->error(implode(', ', [
                'Ref #' . $refId . ': discard failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $specter ]),
                'state: ' . $stateBefore,
                'ex:' . $e,
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }
        $this->saveGameState($fled);

        $this->giveExtraTime($playerId);
        if (!$specter)
            $this->gamestate->nextState('nextPhase');
    }

    function onUnableToAddTile($playerId)
    {
        $this->notifyAllPlayers('unableToAdd', clienttranslate('${playerName} is unable to add a tile to the prison'), [
            'playerName' => $this->getPlayerNameById($playerId),
        ]);
    }

    function onTileDiscarded($activePlayerId, $tileId, $toGovernor)
    {
        if ($toGovernor)
        {
            // This is only used at the beginning of a turn when
            // the player's tile is unable to be added to the prison.
            $tile = FledLogic::$FledTiles[$tileId];
            $itemId = $tile['contains'];
            $this->notifyAllPlayers('tileDiscarded', clienttranslate('${playerName} surrenders ${_tile} to Governor\'s Inventory.'), [
                'i18n' => [ '_tile' ],
                'playerName' => $this->getPlayerNameById($activePlayerId),
                '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
                'playerId' => $activePlayerId,
                'tile' => $tileId,
                'gov' => $toGovernor,
                'preserve' => [ 'tile' ],
            ]);
            return;
        }
    }

    function onSpecterTileDiscarded($tileId)
    {
        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('specterTileDiscarded', clienttranslate('The Specter discards ${_tile}'), [
            'i18n' => [ '_tile' ],
            '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
            'tile' => $tileId,
            'preserve' => [ 'tile' ],
        ]);
    }

    function action_placeTile($tileId, $x, $y, $orientation)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->placeTile($tileId, $x, $y, $orientation);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $this->error(implode(', ', [
                'Ref #' . $refId . ': placeTile failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $x, $y, $orientation ]),
                'state: ' . $stateBefore,
                'ex:' . $e,
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }

        $this->saveGameState($fled);

        $this->giveExtraTime($playerId);
        $this->gamestate->nextState('nextPhase');
    }

    function onNpcAdded(string $name, object $npc)
    {
        // Strings for the replay log
        $npcDisplayLabel = [
            'warder2' => clienttranslate('a new Warder'),
            'warder3' => clienttranslate('a new Warder'),
            'chaplain' => clienttranslate('the Chaplain'),
            'specter' => clienttranslate('the Specter'),
        ];

        $this->notifyAllPlayers('npcAdded', clienttranslate('${_npc} has been added to the prison at (${x}, ${y})'), [
            'i18n' => [ '_npc' ],
            '_npc' => $npcDisplayLabel[$name],
            'npc' => $name,
            'x' => $npc->pos[0],
            'y' => $npc->pos[1],
            'preserve' => [ 'npc' ],
        ]);
    }

    function onTilePlaced($playerId, $tileId, $x, $y, $orientation)
    {
        //
        // Update the player stats
        //
        $this->incStat(1, 'prison_size');
        $this->incStat(1, 'added_to_prison', $playerId);

        //
        // Send notifications to players
        //
        $this->notifyAllPlayers('tilePlaced', clienttranslate('${playerName} adds a tile to the prison'), [
            'playerName' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'tile' => $tileId,
            'x' => $x,
            'y' => $y,
            'o' => $orientation,
        ]);
    }

    function onSpecterTilePlaced($tileId, $x, $y, $orientation)
    {
        $this->incStat(1, 'prison_size');

        //
        // Send notifications to players
        //
        $this->notifyAllPlayers('tilePlaced', clienttranslate('${playerName} adds a tile to the prison'), [
            'playerName' => clienttranslate('The Specter'),
            'tile' => $tileId,
            'x' => $x,
            'y' => $y,
            'o' => $orientation,
        ]);
    }

    function onSetupComplete(array $playerPositions)
    {
        $this->notifyAllPlayers('setupComplete', clienttranslate('<b>WHISTLE!</b> All prisoners go to their bunk room'), [
            'players' => $playerPositions,
        ]);
    }

    function action_move($tileId, $x, $y)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->discardTileToMove($tileId, $x, $y);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $this->error(implode(', ', [
                'Ref #' . $refId . ': move failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $x, $y ]),
                'state: ' . $stateBefore,
                'ex:' . $e,
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }

        $this->saveGameState($fled);
    }

    public function onTilePlayedToMove($playerId, $tileId, $x, $y, $tool, $path)
    {
        switch (FledLogic::getTileColor($tileId))
        {
            case FLED_COLOR_GREEN:  $this->incStat(1, 'shamrocks_played', $playerId); break;
            case FLED_COLOR_PURPLE: $this->incStat(1, 'tools_used_single', $playerId); break;
            case FLED_COLOR_GOLD:   $this->incStat(1, 'tools_used_double', $playerId); break;
        }
        switch ($tool)
        {
            case FLED_TOOL_FILE:  $this->incStat(1, 'traversed_window', $playerId); break;
            case FLED_TOOL_KEY:   $this->incStat(1, 'traversed_door', $playerId); break;
            case FLED_TOOL_BOOT:  $this->incStat(1, 'traversed_archway', $playerId); break;
            case FLED_TOOL_SPOON: $this->incStat(1, 'traversed_tunnels', $playerId); break;
        }
        $distance = count($path) - 1;
        $this->incStat($distance, 'distance_traveled', $playerId);

        //
        // Send notifications to players
        //
        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('tilePlayedToMove', clienttranslate('${playerName} plays ${_tile} to move to (${x}, ${y})'), [
            'i18n' => [ '_tile' ],
            '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
            'playerName' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'tile' => $tileId,
            'x' => $x,
            'y' => $y,
            'preserve' => [ 'tile' ],
        ]);
    }

    function action_moveNpc($tileId, $move)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->discardTileToMoveNpcs($tileId, $move);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $test = implode(' ', [
                '/***** Exception: ' . $e->getMessage() . ' *****/',
                'function testRef' . $refId . '() {',
                '    $fled = $this->loadFromJson(',
                "       '" . $stateBefore . "'",
                '    );',
                '    $playerId = ' . $playerId . ';',
                '    $tileId = ' . $tileId . ';',
                '    $move = json_decode("' . json_encode($move) . '");',
                '',
                '    $fled->discardTileToMoveNpcs($tileId, $move);',
                '',
                '    $this->assertTrue(true);',
                '}',
                '/*' . '**********/',
            ]);
            $this->error($test);
            throw new Exception("Invalid operation - Ref #" . $refId); // NOI18N
        }

        $this->saveGameState($fled);
    }

    function onPlayerSentToBunk($playerId, $wasFrightened)
    {
        if (!$wasFrightened)
            $this->incStat(1, 'confiscations');

        $msg =
            $wasFrightened
                ? clienttranslate('${playerName} is frightened back to bunk')
                : clienttranslate('${playerName} is sent back to bunk')
        ;

        $this->notifyAllPlayers('playerSentToBunk', $msg, [
            'playerName' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
        ]);
    }

    function onTilePlayedToMoveNpcs($activePlayerId, $tileId, $npcNames)
    {
        if (count($npcNames) < 1 || count($npcNames) > 2)
            throw new Exception('Unexpected number of NPCs: ' . json_encode($npcNames));

        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('tilePlayedToMoveNpcs', clienttranslate('${playerName} plays ${_tile} to move ${npcs}'), [
            'i18n' => [ '_tile' ],
            'npcs' => [
                'log' =>
                    count($npcNames) == 1
                        ? '${npc1}'
                        : clienttranslate('${npc1} and ${npc2}'), // Array has either 1 or 2 names
                'args' => [
                    'npc1' => $this->NpcTypes[$npcNames[0]]['the'],
                    'npc2' => count($npcNames) > 1 ? $this->NpcTypes[$npcNames[1]]['the'] : '',
                ],
            ],
            '_tile' => $this->Items[$itemId]['one'],
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            'tile' => $tileId,
            'preserve' => [ 'tile' ],
        ]);
    }

    function onPlayerMovedNpc($activePlayerId, $targetPlayerId, $x, $y, $npcName, $path, $needMove2)
    {
        //
        // Update stats
        //
        $distance = count($path) - 1;
        if ($npcName == FLED_NPC_CHAPLAIN)
            $this->incStat($distance, 'chaplain_distance');
        else if (FledLogic::isWarder($npcName))
            $this->incStat($distance, 'warder_distance');
        else if ($npcName == FLED_NPC_HOUND)
            $this->incStat($distance, 'hound_distance');
        else if ($npcName == FLED_NPC_SPECTER)
            $this->incStat($distance, 'specter_distance');

        //
        // Notify Players
        //
        $msg =
            $targetPlayerId
                ? clienttranslate('${playerName} moves ${_npc} to ${targetName} at (${x}, ${y})')
                : clienttranslate('${playerName} moves ${_npc} to (${x}, ${y})');

        $this->notifyAllPlayers('playerMovedNpc', $msg, [
            'i18n' => [ '_npc' ],
            '_npc' => $this->NpcTypes[$npcName]['the'],
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'targetName' => $targetPlayerId ? $this->getPlayerNameById($targetPlayerId) : null,
            'playerId' => $activePlayerId,
            'npc' => $npcName,
            'x' => $x,
            'y' => $y,
            'needMove2' => $needMove2,
            'preserve' => [ 'npc' ],
        ]);
    }

    function onSpecterMovedNpc(bool $playerTargeted, $x, $y, $npcName, $path)
    {
        //
        // Update stats
        //
        $distance = count($path) - 1;
        if (FledLogic::isWarder($npcName))
            $this->incStat($distance, 'warder_distance');
        else if ($npcName == FLED_NPC_SPECTER)
            $this->incStat($distance, 'specter_distance');

        //
        // Notify Players
        //
        $msg =
            $playerTargeted
                ? clienttranslate('The Specter moves ${_npc} to ${targetName} at (${x}, ${y})')
                : clienttranslate('The Specter moves ${_npc} to (${x}, ${y})');

        $this->notifyAllPlayers('specterMovedNpc', $msg, [
            'i18n' => [ '_npc' ],
            '_npc' => $this->NpcTypes[$npcName]['the'],
            'targetName' => $playerTargeted ? $this->getPlayerNameById($this->fled->getNextPlayerId()) : null,
            'npc' => $npcName,
            'x' => $x,
            'y' => $y,
            'preserve' => [ 'npc' ],
        ]);
    }

    function onPlayerShackled($activePlayerId, $targetPlayerId, $shackleTile, $score, $auxScore)
    {
        $this->incStat(1, 'shackled', $targetPlayerId);
        $this->incStat(1, 'shackled_opponents', $activePlayerId);
        
        // Update player score in database
        $this->setPlayerScore($targetPlayerId, $score, $auxScore);

        foreach ($this->players as $playerId => $player)
        {
            if ($playerId == $targetPlayerId)
            {
                $this->notifyPlayer($playerId, 'shackled', clienttranslate('${playerName} is <b>shackled!</b>'), [
                    'playerName' => $this->getPlayerNameById($targetPlayerId),
                    'playerId' => $targetPlayerId,
                    'tileId' => $shackleTile,
                    'score' => $score,
                ]);
            }
            else
            {
                $this->notifyPlayer($playerId, 'shackled', clienttranslate('${playerName} is <b>shackled!</b>'), [
                    'playerName' => $this->getPlayerNameById($targetPlayerId),
                    'playerId' => $targetPlayerId,
                    'score' => $score,
                ]);
            }
        }
    }

    function onPlayerUnshackled($targetPlayerId, $unshackleTile, $score, $auxScore)
    {
        // Update player score in database
        $this->setPlayerScore($targetPlayerId, $score, $auxScore);

        $tile = FledLogic::$FledTiles[$unshackleTile];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('unshackled', clienttranslate('${playerName} is <b>unshackled</b>; ${_tile} is surrendered to Governor\'s Inventory.'), [
            'i18n' => [ '_tile' ],
            'playerName' => $this->getPlayerNameById($targetPlayerId),
            'playerId' => $targetPlayerId,
            '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
            'tile' => $unshackleTile,
            'score' => $score,
            'preserve' => [ 'tile' ],
        ]);
    }

    function onPlayerSentToSolitary($playerId)
    {
        $this->incStat(1, 'solitary', $playerId);

        $this->notifyAllPlayers('playerSentToSolitary', clienttranslate('${playerName} is sent to <b>solitary confinement</b>'), [
            'playerName' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
        ]);
    }

    function onPlayerIsSafe($playerId, $roomType)
    {
        $this->notifyAllPlayers('playerSafe', clienttranslate('${playerName} is <b>safe</b> in ${_room}'), [
            'i18n' => [ '_room' ],
            'playerName' => $this->getPlayerNameById($playerId),
            '_room' => $this->RoomTypeLabels[$roomType]['the'],
            'room' => $roomType,
            'preserve' => [ 'room' ],
        ]);
    }

    function onWhistleMoved($playerId, $safeRoomTypes)
    {
        // Increase table stat and player stat
        $this->incStat(1, 'whistles_blown');
        $this->incStat(1, 'whistles_blown', $playerId);

        if (count($safeRoomTypes) == 2)
        {
            $this->notifyAllPlayers('whistleMoved', clienttranslate('Prisoners caught outside of ${_room0} and ${_room1} will be in trouble!'), [
                'i18n' => [ '_room0', '_room1' ],
                '_room0' => $this->RoomTypeLabels[$safeRoomTypes[0]]['plural'],
                '_room1' => $this->RoomTypeLabels[$safeRoomTypes[1]]['plural'],
                'room0' => $safeRoomTypes[0],
                'room1' => $safeRoomTypes[1],
                'playerId' => $playerId,
                'preserve' => [ 'room0', 'room1' ],
            ]);
        }
        else
        {
            $this->notifyAllPlayers('whistleMoved', clienttranslate('Prisoners caught outside of ${_room} will be in trouble!'), [
                'i18n' => [ '_room' ],
                '_room' => $this->RoomTypeLabels[$safeRoomTypes[0]]['plural'],
                'room' => $safeRoomTypes[0],
                'playerId' => $playerId,
                'preserve' => [ 'room' ],
            ]);
        }
    }

    function action_add($tileId, $discards)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->addTileToInventory($tileId, $discards);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $this->error(implode(', ', [
                'Ref #' . $refId . ': add failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $discards ]),
                'state: ' . $stateBefore,
                'ex:' . $e,
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }

        $this->saveGameState($fled);
    }

    function onTileAddedToInventory($activePlayerId, $tileId, $discards, $score, $auxScore)
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
        $this->setPlayerScore($activePlayerId, $score, $auxScore);

        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('tileAddedToInventory', clienttranslate('${playerName} gains ${_tile}'), [
            'i18n' => [ '_tile' ],
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
            'tile' => $tileId,
            'score' => $score,
            'preserve' => [ 'tile' ],
        ]);
    }

    function action_surrender($tileId, $specter)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->surrenderTile($tileId, $specter);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $this->error(implode(', ', [
                'Ref #' . $refId . ': surrender failed',
                'player: ' . $playerId,
                'inputs: ' . json_encode([ $tileId, $specter ]),
                'state: ' . $stateBefore,
                'ex:' . $e,
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }

        $this->saveGameState($fled);

        // Solo player was unable to add a tile, so it had to be surrendered.
        // Advance to the Discard phase. Note, this action is not called when
        // the final Specter tile is surrendered. (that happens automatically
        // as part of the discard logic)
        if ($specter)
        {
            $this->giveExtraTime($playerId);
            $this->gamestate->nextState('nextPhase');
        }
    }

    function onTileSurrendered($activePlayerId, $tileId)
    {
        //
        // Update the table and player stats
        //
        $this->incStat(1, 'items_surrendered');
        $this->incStat(1, 'items_surrendered', $activePlayerId);

        //
        // Send notifications to players
        //
        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('tileSurrendered', clienttranslate('${playerName} surrenders ${_tile} to the Governor'), [
            'i18n' => [ '_tile' ],
            '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
            'playerName' => $this->getPlayerNameById($activePlayerId),
            'playerId' => $activePlayerId,
            'tile' => $tileId,
            'preserve' => [ 'tile' ],
        ]);
    }

    function onSpecterTileSurrendered($tileId)
    {
        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
        $this->notifyAllPlayers('tileSurrendered', clienttranslate('${playerName} surrenders ${_tile} to the Governor'), [
            'i18n' => [ '_tile' ],
            '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
            'playerName' => clienttranslate('The Specter'),
            'tile' => $tileId,
            'preserve' => [ 'tile' ],
        ]);
    }

    function onActionComplete($playerId, int $actionsPlayed)
    {
        $this->notifyPlayer($playerId, 'actionComplete', '', [
            'n' => $actionsPlayed,
        ]);

        if ($actionsPlayed == 2)
            $this->gamestate->nextState('nextPhase');
    }

    function onMissedTurn($playerId)
    {
        $this->notifyAllPlayers('missedTurn', clienttranslate('${playerName} <b>misses a turn</b> in solitary confinement'), [
            'playerName' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
        ]);
    }

    function action_escape($discards)
    {
        $activePlayerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->discardTilesToEscape($discards);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $this->error(implode(', ', [
                'Ref #' . $refId . ': escape failed',
                'player: ' . $activePlayerId,
                'input: ' . json_encode([ $discards ]),
                'state: ' . $stateBefore,
                'ex:' . $e,
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }

        $this->saveGameState($fled);
    }

    public function onPlayerEscaped($playerId, $score, $auxScore)
    {
        //
        // Update the player stats
        //
        $this->incStat(1, 'escaped');
        $this->setStat(1, 'escaped', $playerId);

        //
        // Update player score in database
        //
        $this->setPlayerScore($playerId, $score, $auxScore);

        //
        // Send notifications to players
        //
        $this->notifyAllPlayers('prisonerEscaped', clienttranslate('<b>${playerName} escapes!</b>'), [
            'playerName' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'score' => $score,
        ]);

        if ($this->fled->isSoloGame())
        {
            if ($score == 5) // Note: 5 is minimum possible score for escaping
                $soloRating = clienttranslate('5 VP: Awfully average');
            else if ($score <= 8)
                $soloRating = clienttranslate('6 - 8 VP: Dreadfully decent');
            else if ($score <= 11)
                $soloRating = clienttranslate('9 - 11 VP: Ghoulishly great');
            else if ($score <= 14)
                $soloRating = clienttranslate('12 - 14 VP: Frighteningly fantastic');
            else if ($score >= 15)
                $soloRating = clienttranslate('15+ VP: Eerily exalted');

            $this->notifyAllPlayers('wonSoloGame', $soloRating, []);
        }

        // Skip the draw phase and immediately go to the next player.
        // Everyone else gets one more turn.
        $this->gamestate->nextState('nextTurn');
    }

    public function onLostSoloGame(string $reason)
    {
        $messages = [
            FLED_SOLO_LOSS_REPLENISH => clienttranslate('<b>Game over</b>: Unable to draw cards'),
            FLED_SOLO_LOSS_SPECTER => clienttranslate('<b>Game over</b>: The Ghost meeple reached the prisoner'),
            FLED_SOLO_LOSS_WHISTLE => clienttranslate("<b>Game over</b>: The Whistle traveled back to the Governor's roll call tile"),
        ];

        $this->setPlayerScore($this->fled->getNextPlayerId(), 0, 0);

        $this->notifyAllPlayers('lostSoloGame', $messages[$reason], []);

        $this->gamestate->nextState('nextTurn');
    }

    function action_drawTiles($governorTileId)
    {
        $playerId = $this->validateCaller();

        $fled = $this->loadGameState();
        $stateBefore = $fled->toJson();
        try
        {
            $fled->drawTiles($governorTileId);
        }
        catch (Throwable $e)
        {
            $refId = uniqid();
            $this->error(implode(', ', [
                'Ref #' . $refId . ': drawTiles failed',
                'player: ' . $playerId,
                'input: ' . json_encode([ $governorTileId ]),
                'state: ' . $stateBefore,
                'ex:' . $e,
            ]));
            throw new BgaVisibleSystemException("Invalid operation - Ref #" . $refId); // NOI18N
        }

        $this->saveGameState($fled);

        $this->gamestate->nextState('nextTurn');
    }

    function onTookFromGovernor($playerId, $tileId)
    {
        //
        // Update the player stats
        //
        $this->incStat(1, 'items_from_governor');
        $this->incStat(1, 'items_from_governor', $playerId);

        //
        // Send notifications to players
        //
        $tile = FledLogic::$FledTiles[$tileId];
        $itemId = $tile['contains'];
            $this->notifyAllPlayers('tookFromGovernor', clienttranslate('${playerName} takes ${_tile} from the Governor\'s inventory'), [
            'i18n' => [ '_tile' ],
            '_tile' => $tile['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId]['double'] : $this->Items[$itemId]['one'],
            'playerName' => $this->getPlayerNameById($playerId),
            'playerId' => $playerId,
            'tile' => $tileId,
            'preserve' => [ 'tile' ],
        ]);
    }

    function onTilesDrawn($activePlayerId, array $drawnBeforeShuffle, array $drawnAfterShuffle, int $drawPileSize, bool $isSpecter)
    {
        if (count($drawnBeforeShuffle))
        {
            $logMessage =
                count($drawnBeforeShuffle) === 1
                    ? clienttranslate('${playerName} draws 1 tile')
                    : clienttranslate('${playerName} draws ${n} tiles');

            $this->notifyAllPlayers('tilesDrawn', $logMessage, [
                'playerName' =>
                    $isSpecter
                        ? clienttranslate('The Specter')
                        : $this->getPlayerNameById($activePlayerId),
                'playerId' =>
                    $isSpecter
                        ? null
                        : $activePlayerId,
                'n' => count($drawnBeforeShuffle),
            ]);

            $this->notifyPlayer($activePlayerId, 'tilesReceived', '', [
                'tileIds' => $drawnBeforeShuffle,
                's' => $isSpecter,
            ]);
        }

        if (count($drawnAfterShuffle))
        {
            $this->notifyAllPlayers('shuffled', clienttranslate('Shuffling new draw pile (<b>${n} tiles remain</b>)'), [
                'n' => $drawPileSize + count($drawnAfterShuffle),
            ]);

            $logMessage =
                count($drawnBeforeShuffle) === 0
                    ? clienttranslate('${playerName} draws ${n} tiles')
                    : (count($drawnAfterShuffle) === 1
                        ? clienttranslate('${playerName} draws 1 more tile')
                        : clienttranslate('${playerName} draws ${n} more tiles')
                    );

            $this->notifyAllPlayers('tilesDrawn', $logMessage, [
                'playerName' =>
                    $isSpecter
                        ? clienttranslate('The Specter')
                        : $this->getPlayerNameById($activePlayerId),
                'playerId' =>
                    $isSpecter
                        ? null
                        : $activePlayerId,
                'n' => count($drawnAfterShuffle),
            ]);

            $this->notifyPlayer($activePlayerId, 'tilesReceived', '', [
                'tileIds' => $drawnAfterShuffle,
                's' => $isSpecter,
            ]);
        }
    }

    function onInventoryDiscarded($playerId, $discards, $score, $auxScore)
    {
        // Update player score in database
        $this->setPlayerScore($playerId, $score, $auxScore);

        //
        // Send notifications to players
        //
        if ($discards)
        {
            $tile1 = FledLogic::$FledTiles[$discards[0]];
            $itemId1 = $tile1['contains'];
            $color1 = $tile1['color'];

            $itemsParams = [];
            if ($discards == 2)
            {
                $tile2 = FledLogic::$FledTiles[$discards[1]];
                $itemId2 = $tile2['contains'];
                $color2 = $tile2['color'];

                if ($itemId1 == $itemId2 && $color1 === $color2)
                {
                    $itemsParams = [
                        'log' => '${_tile1}${_tile2}',
                        'args' => [
                            'i18n' => [ '_tile1', '_tile2' ],
                            '_tile1' => $tile1['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId1]['two double'] : $this->Items[$itemId1]['two'],
                            '_tile2' => '',
                            'tile1' => $discards[0],
                            'tile2' => $discards[1],
                        ],
                    ];
                }
                else
                {
                    $itemsParams = [
                        'log' => '${_tile1} and ${_tile2}',
                        'args' => [
                            'i18n' => [ '_tile1', '_tile2' ],
                            '_tile1' => $tile1['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId1]['double'] : $this->Items[$itemId1]['one'],
                            '_tile2' => $tile2['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId2]['double'] : $this->Items[$itemId2]['one'],
                            'tile1' => $discards[0],
                            'tile2' => $discards[1],
                        ],
                    ];
                }
            }
            else
            {
                $itemsParams = [
                    'log' => '${_tile1}',
                    'args' => [
                        'i18n' => [ '_tile1' ],
                        '_tile1' => $tile1['color'] === FLED_COLOR_GOLD ? $this->Items[$itemId1]['double'] : $this->Items[$itemId1]['one'],
                        'tile1' => $discards[0],
                    ],
                ];
            }

            $this->notifyAllPlayers('inventoryDiscarded', clienttranslate('${playerName} discards ${items} from inventory'), [
                'items' => $itemsParams,
                'playerName' => $this->getPlayerNameById($playerId),
                'playerId' => $playerId,
                'tileIds' => $discards,
                'score' => $score,
            ]);
        }
    }

    function onEndTurn()
    {
        // The only reason for this is to notify the client to decrement
        // its last turn counter (once a player has escaped)
        $this->notifyAllPlayers('endTurn', '', []);
    }

    function onEndSpectersTurn()
    {
        $this->notifyAllPlayers('endSpectersTurn', '', []);
        $this->gamestate->nextState('nextTurn');
    }

    function onSpectersTurn()
    {
        $this->notifyAllPlayers('spectersTurn', '', []);
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////


//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    function stNextTurn()
    {
        $fled = $this->fled; // Loaded earlier

        if ($fled->wasHardLaborCalled())
            $this->notifyAllPlayers('hardLabor', clienttranslate('<b>The Governor summons everyone to hard labour in the quarry!</b>'), []);

        if ($fled->getGameProgression() >= 100)
        {
            $this->gamestate->nextState('gameOver');
            return;
        }

        $playerId = $fled->advanceNextPlayer();

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
        {
            if ($fled->isSpectersTurn())
                $this->gamestate->nextState('ghostTurn');
            else
                $this->gamestate->nextState('nextTurn');
        }
        else
            $this->gamestate->nextState('nextStarterTurn');
    }

    function stDebugSetState()
    {
        $fled = $this->loadGameState();
        $playerId = $fled->getNextPlayerId();
        $this->gamestate->changeActivePlayer($playerId);

        if (!$fled->wasTilePlaced())
            $this->gamestate->nextState('addTile');
        else if ($fled->countActionsPlayed() >= 2)
            $this->gamestate->nextState('drawTiles');
        else
            $this->gamestate->nextState('playTiles');
    }

    public function loadBugReportSQL(int $reportId, array $studioPlayers): void
    {
        $this->trace('** DEBUG - Loading bug report ' . $reportId);
        
        $prodPlayers = $this->getObjectListFromDb("SELECT `player_id` FROM `player`", true);
        $prodCount = count($prodPlayers);
        $studioCount = count($studioPlayers);
        if ($prodCount != $studioCount) {
            throw new BgaVisibleSystemException("Incorrect player count (bug report has $prodCount players, studio table has $studioCount players)");
        }

        $fled = $this->loadGameState();

        // SQL specific to your game
        // For example, reset the current state if it's already game over
        $sql = [
            "UPDATE `global` SET `global_value` = 10 WHERE `global_id` = 1 AND `global_value` = 99"
        ];
        foreach ($prodPlayers as $index => $prodPlayerId) {
            $studioPlayerId = $studioPlayers[$index];
            // SQL common to all games
            $sql[] = "UPDATE `player` SET `player_id` = $studioPlayerId WHERE `player_id` = $prodPlayerId";
            $sql[] = "UPDATE `global` SET `global_value` = $studioPlayerId WHERE `global_value` = $prodPlayerId";
            $sql[] = "UPDATE `stats` SET `stats_player_id` = $studioPlayerId WHERE `stats_player_id` = $prodPlayerId";
            $fled->debugSwapPlayers($prodPlayerId, $studioPlayerId);
        }
        foreach ($sql as $q) {
            $this->DbQuery($q);
        }

        $this->saveGameState($fled);
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
                $fled->placeTile($move[0], $move[1], $move[2], $move[3]);
                break;

            case STATE_ADD_TILE:
                // Randomly choose a tile and a legal move
                $legalMoves = $fled->getLegalTileMoves();
                shuffle($legalMoves);
                $move = array_pop($legalMoves);
                $fled->placeTile($move[0], $move[1], $move[2], $move[3]);
                break;

            case STATE_PLAY_TILES:
                // Just surrender random tiles
                $handTiles = $fled->getHandTilesEligibleForSurrender();
                $index = rand(0, count($handTiles) - 1);
                $surrenderTile = $handTiles[$index];
                $fled->surrenderTile($surrenderTile, false);
                break;

            case STATE_DRAW_TILES:
                $fled->drawTiles(0);
                break;
        }
        $this->saveGameState($fled);

        $this->gamestate->nextState('nextTurn');
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
