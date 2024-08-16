<?php
// © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)

interface FledEvents
{
    function onSetupComplete(array $playerPositions);
    function onUnableToAddTile($playerId);
    function onTileDiscarded(string $activePlayerId, int $tileId, bool $toGovernor);
    function onTilePlaced($playerId, $tileId, $x, $y, $orientation);
    function onTilePlayedToMove($playerId, $tileId, $x, $y, $tool, $path);
    function onTilePlayedToMoveNpcs($activePlayerId, $tileId, $npcNames);
    function onPlayerMovedNpc($activePlayerId, $targetPlayerId, $x, $y, $npcName, $path);
    function onPlayerShackled($activePlayerId, $targetPlayerId, $shackleTile, $score, $auxScore);
    function onPlayerUnshackled($playerId, $unshackleTile, $score, $auxScore);
    function onPlayerSentToBunk($playerId, $wasFrightened);
    function onPlayerSentToSolitary($playerId);
    function onPlayerIsSafe($playerId, $roomType);
    function onWhistleMoved($playerId, $safeRoomTypes);
    function onNpcAdded(string $name, object $npc);
    function onActionComplete($playerId, int $actionsPlayed);
    function onMissedTurn($playerId);
    function onTookFromGovernor($playerId, $tileId);
    function onTilesDrawn($activePlayerId, $drawnBeforeShuffle, $drawnAfterShuffle, $drawPileSize);
    function onTileAddedToInventory($playerId, $tileId, $discards, $score, $auxScore);
    function onInventoryDiscarded($playerId, $discards, $score, $auxScore);
    function onTileSurrendered($activePlayerId, $tileId);
    function onPlayerEscaped($playerId, $score, $auxScore);
    function onEndTurn();
}
