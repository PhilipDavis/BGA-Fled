<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;

require_once('FledLogic.php');


//
// From https://github.com/sebastianbergmann/phpunit/issues/4026#issuecomment-1418205424
// Thanks to https://github.com/dragosprotung
//
//use function array_column;
//use function count;
trait PHPUnitHelper
{
    /**
     * @param array<mixed> $firstCallArguments
     * @param array<mixed> ...$consecutiveCallsArguments
     *
     * @return iterable<Callback<mixed>>
     */
    public static function withConsecutive(array $firstCallArguments, array ...$consecutiveCallsArguments): iterable
    {
        foreach ($consecutiveCallsArguments as $consecutiveCallArguments) {
            self::assertSameSize($firstCallArguments, $consecutiveCallArguments, 'Each expected arguments list need to have the same size.');
        }

        $allConsecutiveCallsArguments = [$firstCallArguments, ...$consecutiveCallsArguments];

        $numberOfArguments = count($firstCallArguments);
        $argumentList      = [];
        for ($argumentPosition = 0; $argumentPosition < $numberOfArguments; $argumentPosition++) {
            $argumentList[$argumentPosition] = array_column($allConsecutiveCallsArguments, $argumentPosition);
        }

        $mockedMethodCall = 0;
        $callbackCall     = 0;
        foreach ($argumentList as $index => $argument) {
            yield new Callback(
                static function (mixed $actualArgument) use ($argumentList, &$mockedMethodCall, &$callbackCall, $index, $numberOfArguments): bool {
                    $expected = $argumentList[$index][$mockedMethodCall] ?? null;

                    $callbackCall++;
                    $mockedMethodCall = (int) ($callbackCall / $numberOfArguments);

                    if ($expected instanceof Constraint) {
                        self::assertThat($actualArgument, $expected);
                    } else {
                        self::assertEquals($expected, $actualArgument);
                    }

                    return true;
                },
            );
        }
    }
}



final class FledLogicTest extends TestCase
{
    protected FledEvents $events;

    static $baseJson = '{
        "version": 1,
        "options": {},
        "nextPlayer": 0,
        "order": [1, 2],
        "players": {
            "1": {
                "inventory": [],
                "hand": [],
                "shackleTile": 0,
                "inSolitary": false,
                "color": 0,
                "pos": null,
                "placedTile": false,
                "actionsPlayed":  0,
                "escaped": false
            },
            "2": {
                "inventory": [],
                "hand": [],
                "shackleTile": 0,
                "inSolitary": false,
                "color": 1,
                "pos": null,
                "placedTile": false,
                "actionsPlayed":  0,
                "escaped": false
            }
        },
        "npcs": {
            "warder1": {
                "pos": [ 6, 6 ]
            },
            "warder2": {},
            "warder3": {},
            "chaplain": {},
            "specter": {},
            "hound": {}
        },
        "governorInventory": {},
        "whistlePos": 4,
        "rollCall": [ 0, 1, 2, 3 ],
        "openWindow": 3,
        "board": [],
        "drawPile": [],
        "discardPile": [],
        "hardLabor": 0,
        "finalTurns": null,
        "setup": 0,
        "moves": 0
    }';

    public function setUp(): void
    {
        # Turn on error reporting
        error_reporting(E_ALL);
    }

    private function createMockEvents()
    {
        $reflection = new \ReflectionClass(FledEvents::class);

        $methods = [];
        foreach($reflection->getMethods() as $method) {
            $methods[] = $method->name;
        }
        return $this->getMockBuilder(FledEvents::class)
            ->onlyMethods($methods)
            ->getMock();
    }

    private function loadFromJson(string $json)
    {
        $this->events = $this->createMockEvents();
        return FledLogic::fromJson($json, $this->events);
    }

    public function testTileOnBoard()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(FledLogicTest::$baseJson, $mockEvents);

        $fled->setTileAt(FLED_TILE_BLUE_BUNK, 3, 3, FLED_ORIENTATION_NORTH_SOUTH);

        $this->assertFalse($fled->isTileOnBoard(FLED_TILE_YELLOW_BUNK));
        $this->assertTrue($fled->isTileOnBoard(FLED_TILE_BLUE_BUNK));
    }

    public function testIsGameSetup()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::newGame([ 0 => 0, 1 => 1 ], [], $mockEvents);

        $fled->placeTile(FLED_TILE_YELLOW_BUNK, 6, 5, FLED_ORIENTATION_WEST_EAST);

        $this->assertFalse($fled->isGameSetup(), 'Game should not be setup yet');

        $fled->advanceNextPlayer();
        $fled->placeTile(FLED_TILE_BLUE_BUNK, 7, 7, FLED_ORIENTATION_EAST_WEST);

        $this->assertTrue($fled->isGameSetup(), 'Game should be setup now');
    }

    public function testLegalMoves()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::newGame([ 10 => FLED_PLAYER_YELLOW, 20 => FLED_PLAYER_BLUE ], [], $mockEvents);

        $fled->placeTile(FLED_TILE_YELLOW_BUNK, 6, 5, FLED_ORIENTATION_WEST_EAST);
        $fled->advanceNextPlayer();
        $fled->placeTile(FLED_TILE_BLUE_BUNK, 7, 7, FLED_ORIENTATION_EAST_WEST);

        $moves = $fled->getLegalTileMoves();

        $this->assertNotEquals(0, count($moves));
    }

    public function testLegalMoves2()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::newGame([ 10 => FLED_PLAYER_YELLOW, 20 => FLED_PLAYER_BLUE ], [], $mockEvents);

        $fled->placeTile(FLED_TILE_YELLOW_BUNK, 6, 5, FLED_ORIENTATION_WEST_EAST);
        $fled->advanceNextPlayer();
        $fled->placeTile(FLED_TILE_BLUE_BUNK, 7, 7, FLED_ORIENTATION_EAST_WEST);

        $result = $fled->isLegalTilePlacement(45, 9, 6, FLED_ORIENTATION_EAST_WEST);

        $this->assertTrue($result, 'Should be able to place tile');
    }

    public function testLegalMoves3()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::newGame([ 10 => FLED_PLAYER_YELLOW, 20 => FLED_PLAYER_BLUE ], [], $mockEvents);

        $fled->placeTile(FLED_TILE_YELLOW_BUNK, 6, 5, FLED_ORIENTATION_WEST_EAST);
        $fled->advanceNextPlayer();
        $fled->placeTile(FLED_TILE_BLUE_BUNK, 7, 7, FLED_ORIENTATION_EAST_WEST);

        $result = $fled->isLegalTilePlacement(4, 6, 4, FLED_ORIENTATION_SOUTH_NORTH);

        $this->assertTrue($result, 'Should be able to place tile');
    }

    public function testLegalMoves4()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::newGame([ 10 => FLED_PLAYER_YELLOW, 20 => FLED_PLAYER_BLUE ], [], $mockEvents);

        $fled->placeTile(FLED_TILE_YELLOW_BUNK, 6, 5, FLED_ORIENTATION_WEST_EAST);
        $fled->advanceNextPlayer();
        $fled->placeTile(FLED_TILE_BLUE_BUNK, 9, 6, FLED_ORIENTATION_EAST_WEST);

        $result = $fled->isLegalTilePlacement(4, 10, 6, FLED_ORIENTATION_WEST_EAST);

        $this->assertTrue($result, 'Should be able to place tile');
    }

    public function testLegalMoves5()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson('{
            "version": 1,
            "options": {},
            "nextPlayer": 0,
            "order": [1, 2],
            "players": {
                "1": {
                    "inventory": [],
                    "hand": [ 41 ],
                    "shackleTile": 0,
                    "color": 0,
                    "pos": null,
                    "placedTile": false,
                    "actionsPlayed":  0,
                    "escaped": false
                },
                "2": {
                    "inventory": [],
                    "hand": [],
                    "shackleTile": 0,
                    "color": 1,
                    "pos": null,
                    "placedTile": false,
                    "actionsPlayed":  0,
                    "escaped": false
                }
            },
            "npcs": {
                "warder1": {
                    "pos": [ 6, 6 ]
                },
                "warder2": {},
                "warder3": {},
                "chaplain": {},
                "specter": {},
                "hound": {}
            },
            "governorInventory": {},
            "whistlePos": 4,
            "rollCall": [ 0, 1, 2, 3 ],
            "openWindow": 3,
            "board": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,248,0,0,0,0,0,0,0,0,0,0,0,31,37,248,0,0,0,0,0,0,0,0,0,0,0,31,37,235,1,102,102,0,0,0,0,0,0,0,0,0,0,235,1,146,146,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
            "drawPile": [],
            "discardPile": [],
            "moves": 5
        }', $mockEvents);

        // 41 is a double corridor
        $result = $fled->isLegalTilePlacement(41, 3, 6, FLED_ORIENTATION_NORTH_SOUTH);
        $this->assertTrue($result, 'Should be able to place tile in N>S');

        $result = $fled->isLegalTilePlacement(41, 3, 7, FLED_ORIENTATION_SOUTH_NORTH);
        $this->assertTrue($result, 'Should be able to place tile in S>N');

        $legalMoves = $fled->getLegalTileMoves();
        $result = array_values(array_filter($legalMoves, fn($m) => $m[0] == 41 && $m[1] == 3));

        $this->assertEquals($result, [
            [ 41, 3, 6, FLED_ORIENTATION_NORTH_SOUTH ],
            [ 41, 3, 6, FLED_ORIENTATION_EAST_WEST ],
            [ 41, 3, 7, FLED_ORIENTATION_SOUTH_NORTH ],
            [ 41, 3, 7, FLED_ORIENTATION_WEST_EAST ],
        ]);
    }

    public function testIsAdjacentToRoomType()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::newGame([ 10 => FLED_PLAYER_YELLOW, 20 => FLED_PLAYER_BLUE ], [], $mockEvents);

        $fled->placeTile(FLED_TILE_YELLOW_BUNK, 6, 5, FLED_ORIENTATION_WEST_EAST);
        $fled->advanceNextPlayer();
        $fled->placeTile(FLED_TILE_BLUE_BUNK, 9, 6, FLED_ORIENTATION_EAST_WEST);

        $result = $fled->isAdjacentToRoomType(10, 6, FLED_ROOM_BUNK);

        $this->assertTrue($result, 'Should be adjacent to a bunk');
    }

    public function testGetOrientedEgresses_SouthNorth()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::newGame([ 10 => FLED_PLAYER_YELLOW, 20 => FLED_PLAYER_BLUE ], [], $mockEvents);

        $headRoom = FledLogic::$FledTiles[4]['rooms'][0]; // A Bunk room
        $result = $fled->getOrientedEgresses($headRoom, FLED_ORIENTATION_SOUTH_NORTH);

        $this->assertEquals([ 4, 3, 3, 3 ], $result, 'Door should be at the top');
    }

    public function testRemoveTileFromHand()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson('{
            "players": {
                "10": {
                    "hand": [ 14, 11, 12, 13, 10 ]
                }
            },
            "discardPile": [],
            "moves": 0
        }', $mockEvents);

        $result = $fled->removeTileFromHand(12, 10);

        $this->assertEquals([ 14, 11, 13, 10 ], $result, 'Tile should be removed from hand');
    }

    /*
    public function testLegalMovementMoves()
    {
        // yellow player at (7, 2)
        $tileId = 45; // (shamrock)
        $board = '[0,0,0,0,0,18,20,0,0,15,13,16,0,0,0,0,0,0,0,18,20,321,321,15,13,16,0,0,0,0,0,0,0,306,306,111,111,231,0,0,0,0,0,0,0,0,310,310,109,109,0,231,0,0,0,0,0,0,0,126,126,44,2,222,325,325,137,137,0,0,0,0,0,0,0,44,2,222,0,0,0,0,0,0,0,0,0,0,101,101,146,146,0,0,0,0,0,0,0,0,0,0,0,352,352,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]';
        // TODO: should not be able to move to (6, 1) -- tile 20 / room 1
        // TODO: should be able to move to (9, 4) tile 25 / room 0

        // Note: when I reloaded the page, it was no longer showing (6, 1) as an option...
    }
    */

    public function testSolitaryConfinement()
    {
        $mockEvents = $this->createMockEvents();

        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{
                "warder1":{"pos":[8,6]},
                "warder2":{"pos":[7,2]}
            },
            "board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,36,0,0,0,0,0,0,0,0,0,0,0,23,0,36,0,0,0,0,0,0,0,0,0,0,0,23,0,48,0,0,0,0,0,0,0,0,0,126,126,150,150,48,1,0,0,0,0,0,0,0,0,0,0,0,146,146,1,0,0,0,0,0,0,0,0,0,0,0,102,102,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
            "moves":17,"order":[2393716,2393715],"setup":1,
            "options":{"houndExpansion":false,"specterExpansion":false},
            "players":{
                "2393715":{"pos":[8,5],"hand":[51,33,25,41,52],"color":1,"escaped":false,"inventory":[],"inSolitary":false,"placedTile":true,"shackleTile":16,"actionsPlayed":2},
                "2393716":{"pos":[8,6],"hand":[27,14,44,18],"color":0,"escaped":false,"inventory":[],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":0}
            },"version":1,"drawPile":[38,10,40,35,42,37,53,4,15,45,21,12,47,49,20,31,17,11,39,29,22,54,28,30,34,5,6,9,56],"rollCall":[1,3,0,2,4],"nextPlayer":0,"openWindow":2,"whistlePos":2,"discardPile":[32,43,24,3,19,13],"finalTurns":null,"governorInventory":[55]}',
            $mockEvents
        );

        $playerId = 2393716;
        $tileId = 44;
        $x = 8;
        $y = 5;
        $p = 'blue'; // = 1
        $targetPlayerId = $fled->getPlayerIdByColorName($p);

        $this->assertFalse($fled->isSafeForRollCall($x, $y), 'Target room should not be considered safe');

        $mockEvents->expects($this->once())->method('onTilePlayedToMoveNpcs')
            ->with($playerId, $tileId, [ 'warder1' ]);

        $mockEvents->expects($this->once())->method('onPlayerMovedNpc')
            ->with($playerId, $targetPlayerId, $x, $y, 'warder1', [ [8,6], [8,5] ], null);

        $mockEvents->expects($this->once())->method('onPlayerSentToSolitary')
            ->with($targetPlayerId);

        $mockEvents->expects($this->never())->method('onPlayerSentToBunk');
        $mockEvents->expects($this->never())->method('onPlayerShackled');
        $mockEvents->expects($this->never())->method('onPlayerUnshackled');


        $fled->discardTileToMoveNpcs($tileId, [ 'npc' => 'warder1', 'x' => $x, 'y' => $y, 'c' => $p ]);

        $this->assertTrue(true);
    }

    public function testDoubleMoveFromDoubleTile()
    {
        // I saw this during a game... yellow player was at 6,6 and tried to move north twice with a gold boot
        // movement was rejected by the server.

        $mockEvents = $this->createMockEvents();

        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{"warder1":{"pos":[5,6]},"warder2":{"pos":[3,7]},"chaplain":{"pos":[9,4]}},"board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,141,141,129,129,131,131,0,0,0,0,0,0,224,9,101,101,302,302,0,0,0,0,0,0,354,354,224,9,34,146,146,122,122,0,0,0,0,0,0,0,136,136,34,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"moves":33,"order":[2393716,2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":false},"players":{"2393715":{"pos":[5,6],"hand":[19,17,4,38,23],"color":1,"escaped":false,"inventory":[5],"inSolitary":false,"placedTile":true,"shackleTile":13,"actionsPlayed":2},"2393716":{"pos":[6,6],"hand":[14,50,45,15],"color":0,"escaped":false,"inventory":[37,11],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":0}},"version":1,"drawPile":[49,56,28,48,10,16,6,18,32,30,21,25,26,40,20,47,33,44,52,53],"rollCall":[1,3,2,0,4],"nextPlayer":0,"openWindow":1,"whistlePos":3,"discardPile":[39,42,35,3,51,27,43],"finalTurns":null,"governorInventory":[]}',
            $mockEvents
        );
        $playerId = 2393716;
        $tile = 15;
        $x = 7;
        $y = 4;

        $mockEvents->expects($this->once())->method('onTilePlayedToMove')
            ->with($playerId, $tile, $x, $y, FLED_TOOL_BOOT, [ [ 6, 6 ], [ 7, 5 ], [ 7, 4 ] ]);

        $fled->discardTileToMove($tile, $x, $y);
    }

    public function testCanEscape()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{"warder1":{"pos":[7,7]},"warder2":{"pos":[11,4]},"warder3":{"pos":[1,5]},"chaplain":{"pos":[11,8]}},"board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,120,120,0,0,0,0,0,0,0,0,0,0,0,0,119,119,0,0,0,0,0,0,0,0,0,0,0,0,114,114,54,0,0,338,338,0,0,0,0,0,0,0,118,118,54,255,311,311,144,144,0,0,0,51,313,313,0,325,325,255,247,2,0,301,301,10,222,51,39,0,0,36,335,335,247,2,146,146,0,10,222,21,39,0,0,36,0,0,0,0,0,350,350,326,326,21,316,316,0,0,0,0,0,0,0,40,0,129,129,31,32,0,0,0,0,0,0,341,341,40,0,0,0,31,32,0,0,0,0,0,0,0,0,0,0,0,337,337,0,0,0,0,0,0,0,0,0,0,0,0,148,148,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"moves":115,"order":[2393716,2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":false},"players":{"2393715":{"pos":[7,7],"hand":[23,28,30,45,49],"color":1,"escaped":false,"inventory":[27,24,42,12],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2},"2393716":{"pos":[12,7],"hand":[43,4],"color":0,"escaped":false,"inventory":[5,15],"inSolitary":false,"placedTile":true,"shackleTile":17,"actionsPlayed":2}},"version":1,"drawPile":[53],"rollCall":[1,3,2,0,4],"hardLabor":0,"nextPlayer":0,"openWindow":0,"whistlePos":0,"discardPile":[34,56,3],"finalTurns":1,"governorInventory":[9,6,33,52]}',
            $mockEvents
        );
        $playerId = 2393716;

        $result = $fled->canPlayerEscape($playerId);
        $this->assertTrue($result, "Player should be able to escape");
    }

    public function testDiscardToEscape()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{"warder1":{"pos":[7,7]},"warder2":{"pos":[11,4]},"warder3":{"pos":[1,5]},"chaplain":{"pos":[11,8]}},"board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,120,120,0,0,0,0,0,0,0,0,0,0,0,0,119,119,0,0,0,0,0,0,0,0,0,0,0,0,114,114,54,0,0,338,338,0,0,0,0,0,0,0,118,118,54,255,311,311,144,144,0,0,0,51,313,313,0,325,325,255,247,2,0,301,301,10,222,51,39,0,0,36,335,335,247,2,146,146,0,10,222,21,39,0,0,36,0,0,0,0,0,350,350,326,326,21,316,316,0,0,0,0,0,0,0,40,0,129,129,31,32,0,0,0,0,0,0,341,341,40,0,0,0,31,32,0,0,0,0,0,0,0,0,0,0,0,337,337,0,0,0,0,0,0,0,0,0,0,0,0,148,148,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"moves":115,"order":[2393716,2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":false},"players":{"2393715":{"pos":[7,7],"hand":[23,28,30,45,49],"color":1,"escaped":false,"inventory":[27,24,42,12],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2},"2393716":{"pos":[12,7],"hand":[43,4],"color":0,"escaped":false,"inventory":[5,15],"inSolitary":false,"placedTile":true,"shackleTile":17,"actionsPlayed":2}},"version":1,"drawPile":[53],"rollCall":[1,3,2,0,4],"hardLabor":0,"nextPlayer":0,"openWindow":0,"whistlePos":0,"discardPile":[34,56,3],"finalTurns":1,"governorInventory":[9,6,33,52]}',
            $mockEvents
        );
        $discards = [ 5 ];

        $mockEvents->expects($this->once())->method('onPlayerEscaped')
            ->with(2393716, 8, 5);

        $fled->discardTilesToEscape($discards);

        $this->assertTrue(true);
    }

    public function testBootDoubleMovementFromDoubleTile()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{"warder1":{"pos":[5,6]},"warder2":{"pos":[3,7]},"chaplain":{"pos":[9,4]}},"board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,141,141,129,129,131,131,0,0,0,0,0,0,224,9,101,101,302,302,0,0,0,0,0,0,354,354,224,9,34,146,146,122,122,0,0,0,0,0,0,0,136,136,34,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"moves":33,"order":[2393716,2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":false},"players":{"2393715":{"pos":[5,6],"hand":[19,17,4,38,23],"color":1,"escaped":false,"inventory":[5],"inSolitary":false,"placedTile":true,"shackleTile":13,"actionsPlayed":2},"2393716":{"pos":[6,6],"hand":[14,50,45,15],"color":0,"escaped":false,"inventory":[37,11],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":0}},"version":1,"drawPile":[49,56,28,48,10,16,6,18,32,30,21,25,26,40,20,47,33,44,52,53],"rollCall":[1,3,2,0,4],"nextPlayer":0,"openWindow":1,"whistlePos":3,"discardPile":[39,42,35,3,51,27,43],"finalTurns":null,"governorInventory":[]}',
            $mockEvents
        );
        $playerId = 2393716;
        $tileId = 15;
        $x = 7;
        $y = 4;

        $result = $fled->getLegalMovementMoves($tileId);
        $traversalsTo_7_4 = array_values(array_filter($result, fn($t) => $t['path'][count($t['path']) - 1] == [ $x, $y ]));

        $this->assertCount(1, $traversalsTo_7_4, "Should have one path to (7, 4)");
    }

    public function testSpoonDoubleMovementFromDoubleTile()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{"warder1":{"pos":[8,3]},"warder2":{"pos":[12,1]}},"board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,324,324,154,154,51,0,0,0,0,0,0,0,0,0,75,28,0,0,51,0,0,0,0,0,0,0,3,0,75,28,0,0,270,0,0,0,0,0,0,0,3,129,129,0,0,0,270,0,0,0,0,0,0,7,102,102,1,0,0,0,0,0,0,0,0,0,0,7,146,146,1,0,0,0,0,0,0,0,0,0,0,0,108,108,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"moves":29,"order":[2393716,2393715,2393718,2393717],"setup":1,"options":{"houndExpansion":false,"specterExpansion":false},"players":{"2393715":{"pos":[6,6],"hand":[15,22,72,52,44],"color":1,"escaped":false,"inventory":[74],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2},"2393716":{"pos":[6,6],"hand":[45,73,48,36,25],"color":0,"escaped":false,"inventory":[],"inSolitary":false,"placedTile":true,"shackleTile":34,"actionsPlayed":2},"2393717":{"pos":[6,6],"hand":[16,19,14,30],"color":3,"escaped":false,"inventory":[],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":0},"2393718":{"pos":[8,5],"hand":[40,41,23,6,47],"color":2,"escaped":false,"inventory":[55],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2}},"version":1,"drawPile":[31,37,9,10,50,56,32,21,71,11,38,5,4,33,35,12],"rollCall":[1,3,0,2,4],"hardLabor":0,"nextPlayer":3,"openWindow":2,"whistlePos":2,"discardPile":[13,17,20,27,26,39,53,43,18,42,49],"finalTurns":null,"governorInventory":[]}',
            $mockEvents
        );
        $playerId = 2393717;
        $tileId = 16; // Tile with gold spoon

        // Green player is at (6, 6) and should be able to reach (9, 2) via tunnel
        $result = $fled->getLegalMovementMoves($tileId);
        $traversalsTo_9_2 = array_values(array_filter($result, fn($t) => $t['path'][1] == [ 9, 2 ]));

        $this->assertCount(9, $result, "Should have nine possible destinations"); // 9, assuming can't end up where player started
        $this->assertCount(1, $traversalsTo_9_2, "Should have one path to (9, 2)");
    }

    public function testPlayerCanEscape()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{"warder1":{"pos":[8,6]},"warder2":{"pos":[3,1]},"chaplain":{"pos":[4,5]}},"board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,351,351,0,26,32,0,0,0,0,0,0,0,120,120,40,254,0,26,32,0,0,0,0,0,0,0,114,114,40,254,38,323,323,43,0,0,0,0,0,0,115,115,324,324,38,347,347,43,0,0,0,0,0,0,0,131,131,304,304,102,102,301,301,0,0,0,0,0,0,0,0,0,328,328,146,146,152,152,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"moves":67,"order":[2393715,2393716],"setup":1,"options":{"houndExpansion":false,"specterExpansion":false},"players":{"2393715":{"pos":[5,5],"hand":[9,10,27,48,16],"color":0,"escaped":false,"inventory":[6,33],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2},"2393716":{"pos":[1,2],"hand":[21,37],"color":1,"escaped":false,"inventory":[13,41,11],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2}},"version":1,"drawPile":[49,56,25,5,22,44,17,39,50,30,42,19,18,35,12,53],"rollCall":[0,1,2,3,4],"hardLabor":0,"nextPlayer":1,"openWindow":1,"whistlePos":1,"discardPile":[3,34,45],"finalTurns":1,"governorInventory":[29,36,55]}',
            $mockEvents
        );
        $playerId = 2393716;
        $discards = [13];

        $mockEvents->expects($this->once())->method('onPlayerEscaped')
            ->with($playerId);

        $fled->discardTilesToEscape($discards);
    }

    public function testPlaceTileAfterEscape()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{"move":10,"npcs":{"warder1":{"pos":[8,5]},"warder2":{"pos":[8,9]}},"board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,52,0,0,0,0,0,0,0,0,0,109,109,0,48,52,0,0,0,0,0,0,0,302,302,1,340,340,48,313,313,0,0,0,0,0,0,146,146,1,341,341,22,314,314,0,0,0,0,0,0,0,350,350,126,126,22,316,316,0,0,0,0,0,0,0,345,345,355,355,229,315,315,0,0,0,0,0,0,0,0,151,151,211,229,0,0,0,0,0,0,0,0,0,0,0,0,211,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"moves":71,"order":[2393716,2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":false},"players":{"2393715":{"pos":[12,7],"hand":[32,35],"color":1,"escaped":true,"inventory":[],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2},"2393716":{"pos":[12,7],"hand":[4,10,36,38,31],"color":0,"escaped":false,"addedTile":true,"inventory":[39],"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":2}},"version":1,"drawPile":[5,44,3,30,24,42,33,27,20,34,12,18,37,6,43,28,23,47],"rollCall":[2,3,1,0,4],"hardLabor":0,"finalTurns":1,"nextPlayer":1,"openWindow":2,"whistlePos":4,"discardPile":[25,54,53,49,21],"governorInventory":[56,19,17]}',
            $mockEvents
        );

        $playerId = 2393716;
        $tileId = 36;
        $x = 10;
        $y = 3;
        $o = 100;

        $mockEvents->expects($this->once())->method('onTilePlaced')
            ->with($playerId, $tileId, $x, $y, $o);

        $fled->advanceNextPlayer(); // Note: the state above was incorrect... the game logic hadn't advanced the player. So do that now.

        $fled->placeTile($tileId, $x, $y, $o);

        $this->assertTrue(true);
    }

    public function testTile26Movement()
    {
        // Player chose tile 26 but and should have 2 legal moves but shows 3

        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{"move":3,"npcs":{"warder1":{"pos":[6,6]}},
                "board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,235,0,0,0,0,0,0,0,0,0,0,146,146,1,235,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                "order":[2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":true},
                "players":{
                    "2393715":{"pos":[9,5],"hand":[26,17,42],"color":0,"escaped":false,"inventory":[],"needMove2":null,"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":1}
                },"version":1,
                "drawPile":[5,50,6,49,14,39,33,9,29,11,32,4,28,30,24,43,54,13,18,52,22,10,16,23,34,51,12,36,41,15,40,55,44,27,20,38,25,45,21,19,90,48,37,3,53,56],
                "rollCall":[3,0,2,1,4],"hardLabor":0,"finalTurns":null,"nextPlayer":0,"openWindow":3,"whistlePos":4,"discardPile":[47],"specterHand":[],"governorInventory":[]
            }',
            $mockEvents
        );
    
        $tileId = 26;
        $result = $fled->getLegalMovementMoves($tileId);

        $this->assertCount(2, $result, "Should have two possible destinations");
        $this->assertEquals([
            [ 'path' => [ [ 9, 5 ], [ 9, 6 ] ], 'type' => 20 ],
            [ 'path' => [ [ 9, 5 ], [ 9, 6 ], [ 8, 6 ] ], 'type' => 20 ],
        ], $result, "Should have one path to (9, 2)");
    }

    public function testSpecterMovesOneRoomInSoloMode()
    {
        // Specter moved all the way to player (two rooms away) but should have only moved one room

        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{
                "version":1,"move":6,
                "npcs":{"specter":{"pos":[7,5]},"warder1":{"pos":[6,6]}},
                "board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,190,190,0,0,0,0,0,0,0,0,0,0,0,146,146,301,301,104,104,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                "order":[2393715],"setup":1,
                "options":{"houndExpansion":false,"specterExpansion":true},
                "players":{
                    "2393715":{"pos":[9,6],"hand":[49,51,3,54,56],"color":0,"escaped":false,"inventory":[],"needMove2":null,"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":0}
                },
                "drawPile":[50,19,44,11,45,6,55,26,53,17,9,38,35,27,48,43,41,16,13,12,14,24,47,28,37,33,36,10,32,52,42,21,25,23,20,29,5,34,40,18],
                "rollCall":[2,3,1,0,4],"hardLabor":0,"finalTurns":null,"nextPlayer":0,"openWindow":3,"whistlePos":4,"discardPile":[],
                "specterHand":[30,39],
                "governorInventory":[15,22]
            }',
            $mockEvents
        );
    
        $playerId = 2393715;
        $tileId = 30;
        $isSpecter = true;


        $mockEvents->expects($this->exactly(2))->method('onSpecterMovedNpc')
            ->with(
                ...self::withConsecutive(
                    [ true,  9, 6, 'warder1', [ [ 6, 6 ], [ 8, 6 ], [ 9, 6 ] ] ],
                    [ false, 8, 6, 'specter', [ [ 7, 5 ], [ 8, 6 ] ] ],
                )
            );

        $fled->discardTile($tileId, $isSpecter);

        $this->assertTrue(true);
    }

    // should have 4 placement options
    // {"move":35,"npcs":{"warder1":{"pos":[7,4]}},"board":[0,0,0,0,0,0,0,14,20,0,0,0,0,0,0,0,0,0,0,341,341,14,20,0,0,0,0,0,0,0,0,0,0,28,0,240,0,0,0,0,0,0,0,0,0,0,0,28,32,240,0,0,0,0,0,0,0,0,0,0,0,227,32,350,350,326,326,0,0,0,0,0,0,0,0,227,352,352,342,342,0,0,0,0,0,0,0,0,0,0,146,146,301,301,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"order":[2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":true},"players":{"2393715":{"pos":[6,4],"hand":[9,18,39,19,17],"color":0,"escaped":false,"inventory":[53],"needMove2":null,"inSolitary":false,"placedTile":false,"shackleTile":48,"actionsPlayed":0}},"version":1,"drawPile":[56,44,24,6,51,45,34,12,47,29,22,15,13,4],"rollCall":[1,0,2,3,4],"hardLabor":0,"finalTurns":null,"nextPlayer":0,"openWindow":3,"whistlePos":2,"discardPile":[21,16,5,35,49,38,3,30,23,55,33,11,43],"specterHand":[90,10,37],"spectersTurn":true,"governorInventory":[54,25,36]}

    public function testSoloModeWarderInSameRoomAsPlayer()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{
                "move":18,
                "npcs":{"warder1":{"pos":[9,6]}},
                "board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,124,124,0,0,0,0,0,0,0,0,146,146,1,135,135,32,0,0,0,0,0,0,0,0,0,0,0,133,133,32,0,0,0,0,0,0,0,0,0,0,0,0,128,128,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                "order":[2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":true},
                "players":{"2393715":{"pos":[9,6],"hand":[16,17,37,53,30],"color":0,"escaped":false,"addedTile":true,"inventory":[],"needMove2":null,"inSolitary":false,"placedTile":true,"shackleTile":48,"actionsPlayed":0}},
                "version":1,"drawPile":[3,38,36,47,50,43,49,13,55,40,20,29,4,54,19,26,10,22,18,39,52,56,15,9,27,90,41,42],
                "rollCall":[3,1,2,0,4],"hardLabor":0,"finalTurns":null,"nextPlayer":0,"openWindow":3,"whistlePos":2,
                "discardPile":[45,21,34,5],"specterHand":[6,44],"spectersTurn":true,"governorInventory":[51,25,23,12,14,11]
            }',
            $mockEvents
        );
    
        $playerId = 2393715;
        $tileId = 44;
        $isSpecter = true;

        $mockEvents->expects($this->once())->method('onSpecterMovedNpc')
            ->with(true,  9, 6, 'warder1', [ [ 9, 6 ], [ 9, 6 ] ]);

        $fled->discardTile($tileId, $isSpecter);

        $this->assertTrue(true);
    }

    public function testSoloModeWarderCannotTraverseWindow()
    {
        $mockEvents = $this->createMockEvents();
        $fled = FledLogic::fromJson(
            '{
                "version":1,"move":6,
                "npcs":{"warder1":{"pos":[6,6]}},
                "board":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,144,144,111,111,0,0,0,0,0,0,0,0,0,146,146,301,301,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                "order":[2393715],"setup":1,"options":{"houndExpansion":false,"specterExpansion":true},
                "players":{"2393715":{"pos":[10,5],"hand":[43,40,20,27,4],"color":0,"escaped":false,"inventory":[],"needMove2":null,"inSolitary":false,"placedTile":true,"shackleTile":0,"actionsPlayed":0}},
                "drawPile":[38,10,52,33,55,56,39,51,6,15,3,34,12,53,42,37,50,21,14,36,35,54,90,5,17,48,16,30,19,22,32,9,26,24,13,41,45,29,18,49],
                "rollCall":[3,0,1,2,4],"hardLabor":0,"finalTurns":null,"nextPlayer":0,"openWindow":3,"whistlePos":4,"discardPile":[23,28],"specterHand":[25,47],"spectersTurn":true,"governorInventory":[]
            }',
            $mockEvents
        );
    
        $playerId = 2393715;
        $tileId = 25;
        $isSpecter = true;

        $mockEvents->expects($this->once())->method('onSpecterMovedNpc')
            ->with(false, 8, 6, 'warder1', [ [ 6, 6 ], [ 8, 6 ] ]);

        $fled->discardTile($tileId, $isSpecter);

        $this->assertTrue(true);
    }
}
