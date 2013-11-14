<?php
class Spin
{
	static function GetXPMultiplierSecondsRemaining($cbPlayer)
	{
		$nowTime = new DateTime;
		$xpMultiplierExpirationDate = DateTime::createFromFormat('Y-m-d H:i:s', $cbPlayer->xp_multiplier_expiration_date);

		if ($xpMultiplierExpirationDate)
		{
			$seconds = $xpMultiplierExpirationDate->getTimestamp() - $nowTime->getTimestamp();
			return ($seconds > 0) ? $seconds : 0; 
		}
		else
		{
			return 0;
		}
	}

	static function IsBigWin($creditsWon, $creditsBet)
	{
		//sanity check
		if ($creditsBet >= 0)
			return false;

		if(( $creditsWon / (-1 * $creditsBet)) >= 25)
			return true;
		else
			return false;
	}
}