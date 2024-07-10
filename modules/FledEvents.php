<?php
// © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)

interface FledEvents
{
    function onSetupComplete(array $playerPositions);
    function onUnableToAddTile($playerId);
    function onTileDiscarded(string $activePlayerId, int $tileId, bool $toGovernor);
    function onTilePlaced($playerId, $tileId, $x, $y, $orientation);
    function onTilePlayedToMove($playerId, $tileId, $x, $y, $tool, $path);
    function onTilePlayedToMoveWarder($activePlayerId, $targetPlayerId, $tileId, $x, $y, $npcName, $path);
    function onPlayerShackled($activePlayerId, $targetPlayerId, $shackleTile, $score);
    function onPlayerUnshackled($playerId, $unshackleTile, $score);
    function onPlayerSentToBunk($playerId);
    function onPlayerSentToSolitary($playerId);
    function onPlayerIsSafe($playerId, $roomType);
    function onWhistleMoved($playerId, $safeRoomTypes);
    function onNpcAdded(string $name, object $npc);
    function onActionComplete(int $actionsPlayed);
    function onMissedTurn($playerId);
    function onTookFromGovernor($playerId, $tileId);
    function onTilesDrawn($activePlayerId, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileSize);
    function onTileAddedToInventory($playerId, $tileId, $discards, $score, $auxScore);
    function onInventoryDiscarded($playerId, $discards, $score);
    function onTileSurrendered($activePlayerId, $tileId);
    function onPlayerEscaped($playerId, $score, $auxScore);
    function onEndTurn();
}
