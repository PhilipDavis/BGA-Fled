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
  	    if (self::isArg('notifwindow'))
  	    {
            $this->view = 'common_notifwindow';
  	        $this->viewArgs['table'] = self::getArg('table', AT_posint, true);
  	    }
  	    else
  	    {
            $this->view = 'fled_fled';
            self::trace('Complete reinitialization of board game');
        }
  	}
  	
    public function placeTile()
    {
        self::setAjaxMode();
        
        $tileId = intval(self::getArg('t', AT_posint, true));
        $x = intval(self::getArg('x', AT_posint, true));
        $y = intval(self::getArg('y', AT_posint, true));
        $orientation = intval(self::getArg('o', AT_posint, true));
        $this->game->action_placeTile($tileId, $x, $y, $orientation);

        self::ajaxResponse();
    }

    public function discard()
    {
        self::setAjaxMode();
        
        $tileId = intval(self::getArg('t', AT_posint, true));
        $this->game->action_discard($tileId);

        self::ajaxResponse();
    }

    public function move()
    {
        self::setAjaxMode();
        
        $tileId = intval(self::getArg('t', AT_posint, true));
        $x = intval(self::getArg('x', AT_posint, true));
        $y = intval(self::getArg('y', AT_posint, true));
        $this->game->action_move($tileId, $x, $y);

        self::ajaxResponse();
    }

    public function moveWarder()
    {
        self::setAjaxMode();
        
        $tileId = intval(self::getArg('t', AT_posint, true));
        $x = intval(self::getArg('x', AT_posint, true));
        $y = intval(self::getArg('y', AT_posint, true));
        $w = self::getArg('w', AT_alphanum, true);
        $p = self::getArg('p', AT_alphanum, false, null);
        $this->game->action_moveWarder($tileId, $x, $y, $w, $p);

        self::ajaxResponse();
    }

    public function add()
    {
        self::setAjaxMode();
        
        $tileId = intval(self::getArg('t', AT_posint, true));
        $d = trim(self::getArg('d', AT_numberlist, false, ''));
        $discardTileIds = strlen($d) ? array_map(fn($s) => intval($s), explode(',', self::getArg('d', AT_numberlist, false, ''))) : [];
        $this->game->action_add($tileId, $discardTileIds);

        self::ajaxResponse();
    }

    public function surrender()
    {
        self::setAjaxMode();
        
        $tileId = intval(self::getArg('t', AT_posint, true));
        $this->game->action_surrender($tileId);

        self::ajaxResponse();
    }

    public function escape()
    {
        self::setAjaxMode();
        
        $d = trim(self::getArg('d', AT_numberlist, false, ''));
        $discardTileIds = strlen($d) ? array_map(fn($s) => intval($s), explode(',', self::getArg('d', AT_numberlist, false, ''))) : [];
        $this->game->action_escape($discardTileIds);

        self::ajaxResponse();
    }

    public function drawTiles()
    {
        self::setAjaxMode();
        
        $governorTileId = intval(self::getArg('t', AT_posint, true));
        $this->game->action_drawTiles($governorTileId);

        self::ajaxResponse();
    }
}
