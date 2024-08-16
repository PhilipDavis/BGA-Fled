<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fled implementation : © Copyright 2024, Philip Davis (mrphilipadavis AT gmail)
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 */

class action_fled extends APP_GameAction
{ 
    // Constructor: please do not modify
   	public function __default()
  	{
  	    if ($this->isArg('notifwindow'))
  	    {
            $this->view = 'common_notifwindow';
  	        $this->viewArgs['table'] = $this->getArg('table', AT_posint, true);
  	    }
  	    else
  	    {
            $this->view = 'fled_fled';
            $this->trace('Complete reinitialization of board game');
        }
  	}

    // Data exchange helper
    private function getMoveNpcsFromArgList($list)
    {
        // Using underscore because it doesn't need to be escaped in the URL
        $items = explode('_', $list);
        $untypedChunks = array_chunk($items, 4);
        return array_map(fn($chunk) => [
            'npc' => strval($chunk[0]),
            'x' => intval($chunk[1]),
            'y' => intval($chunk[2]),
            'c' => strval($chunk[3]), // Target player colour name
        ], $untypedChunks);
    }

    public function debugSetState()
    {
        $this->setAjaxMode();
        
        $json = base64_decode($this->getArg('s', AT_base64, true));
        $this->game->action_debugSetState($json);

        $this->ajaxResponse();
    }
  	
    public function placeTile()
    {
        $this->setAjaxMode();
        
        $tileId = intval($this->getArg('t', AT_posint, true));
        $x = intval($this->getArg('x', AT_posint, true));
        $y = intval($this->getArg('y', AT_posint, true));
        $orientation = intval($this->getArg('o', AT_posint, true));
        $this->game->action_placeTile($tileId, $x, $y, $orientation);

        $this->ajaxResponse();
    }

    public function discard()
    {
        $this->setAjaxMode();
        
        $tileId = intval($this->getArg('t', AT_posint, true));
        $this->game->action_discard($tileId);

        $this->ajaxResponse();
    }

    public function move()
    {
        $this->setAjaxMode();
        
        $tileId = intval($this->getArg('t', AT_posint, true));
        $x = intval($this->getArg('x', AT_posint, true));
        $y = intval($this->getArg('y', AT_posint, true));
        $this->game->action_move($tileId, $x, $y);

        $this->ajaxResponse();
    }

    public function moveNpcs()
    {
        $this->setAjaxMode();
        
        // TODO: moveNumber (in all actions)
        $tileId = intval($this->getArg('t', AT_posint, true));
        $moves = $this->getMoveNpcsFromArgList($this->getArg('moves', AT_alphanum, true, ''));
        $this->game->action_moveNpcs($tileId, $moves);

        $this->ajaxResponse();
    }

    public function add()
    {
        $this->setAjaxMode();
        
        $tileId = intval($this->getArg('t', AT_posint, true));
        $d = trim($this->getArg('d', AT_numberlist, false, ''));
        $discardTileIds = strlen($d) ? array_map(fn($s) => intval($s), explode(',', $this->getArg('d', AT_numberlist, false, ''))) : [];
        $this->game->action_add($tileId, $discardTileIds);

        $this->ajaxResponse();
    }

    public function surrender()
    {
        $this->setAjaxMode();
        
        $tileId = intval($this->getArg('t', AT_posint, true));
        $this->game->action_surrender($tileId);

        $this->ajaxResponse();
    }

    public function escape()
    {
        $this->setAjaxMode();
        
        $d = trim($this->getArg('d', AT_numberlist, false, ''));
        $discardTileIds = strlen($d) ? array_map(fn($s) => intval($s), explode(',', $this->getArg('d', AT_numberlist, false, ''))) : [];
        $this->game->action_escape($discardTileIds);

        $this->ajaxResponse();
    }

    public function drawTiles()
    {
        $this->setAjaxMode();
        
        $governorTileId = intval($this->getArg('t', AT_posint, true));
        $this->game->action_drawTiles($governorTileId);

        $this->ajaxResponse();
    }

    public function jsError()
    {
        $this->setAjaxMode();

        $userAgent = $_POST['ua'];
        $url = $_POST['url'];
        $msg = $_POST['msg'];
        $line = $_POST['line'];
        $this->game->action_jsError($msg, $url, $line, $userAgent);

        $this->ajaxResponse();
    }
}
