<?php
class PlayerController extends PhantomController
{
	public function player_info($id=null)
	{
		echo "The Player ID passed was: " . $id . " and var is: " . $_REQUEST['var'];
	}
}