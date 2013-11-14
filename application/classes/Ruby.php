<?php
class Ruby
{
	protected static $rubyLevels = array(
		array('level' => 0, 'max' => 20),
		array('level' => 1, 'max' => 40),
		array('level' => 2, 'max' => 130),
		array('level' => 3, 'max' => 150),
		array('level' => 4, 'max' => 160),
		array('level' => 5, 'max' => 180),
		array('level' => 6, 'max' => 190),
		array('level' => 7, 'max' => 210),
		array('level' => 8, 'max' => 230),
		array('level' => 9, 'max' => 240),
		array('level' => 10, 'max' => 250),
		array('level' => 11, 'max' => 260),
		array('level' => 12, 'max' => 270),
		array('level' => 13, 'max' => 280),
		array('level' => 14, 'max' => 300),
		array('level' => 15, 'max' => 310),
		array('level' => 16, 'max' => 330),
		array('level' => 17, 'max' => 350),
		array('level' => 18, 'max' => 360),
		array('level' => 19, 'max' => 380),
		array('level' => 20, 'max' => 390),
		array('level' => 21, 'max' => 400),
		array('level' => 22, 'max' => 420),
		array('level' => 23, 'max' => 430),
		array('level' => 24, 'max' => 450),
		array('level' => 25, 'max' => 460),
		array('level' => 26, 'max' => 480),
		array('level' => 27, 'max' => 490),
		array('level' => 28, 'max' => 500),
		array('level' => 29, 'max' => 520),
		array('level' => 30, 'max' => 530),
		array('level' => 31, 'max' => 550),
		array('level' => 32, 'max' => 570),
		array('level' => 33, 'max' => 580),
		array('level' => 34, 'max' => 600),
		array('level' => 35, 'max' => 620),
		array('level' => 36, 'max' => 630),
		array('level' => 37, 'max' => 650),
		array('level' => 38, 'max' => 660),
		array('level' => 39, 'max' => 680),
		array('level' => 40, 'max' => 690),
		array('level' => 41, 'max' => 710),
		array('level' => 42, 'max' => 730),
		array('level' => 43, 'max' => 740),
		array('level' => 44, 'max' => 760),
		array('level' => 45, 'max' => 780),
		array('level' => 46, 'max' => 800),
		array('level' => 47, 'max' => 810),
		array('level' => 48, 'max' => 820),
		array('level' => 49, 'max' => 840),
		array('level' => 50, 'max' => 850)
	);

	static function getRubyAward($playerID=null, $creditsBet=0, $creditsWon=0, $level)
	{

		//now we'll want to add into this a way to add promotions
		//we can do our own logic for setting the number of rubies returned
		$rubies = 0;
		$divider = .5;

		$creditsBet = $creditsBet * -1;

		$rubyReward = 0;

		log_message('debug', 'getRubyAward received the params - playerID: ' . $playerID . ' bet: ' . $creditsBet . ' won: ' . $creditsWon . ' level: ' . $level);

		//ruby progression.
		//Beginners will be rewared more during the initial levels (5 for now after that then we slowly decrease it.)

		$multiplier = self::getMultiplier($playerID);
		//$multiplier = 1;

		log_message('debug', 'the multiplier was ' . $multiplier);



		if($creditsWon > ($creditsBet * 3) && $creditsWon < ($creditsBet * 10) )
		{
			//$rubies += (($rubyReward * 3)  * $multiplier);
			log_message('debug', 'Won 3 rubies for 3 x bet');
			$rubies += 5;
		}
		else if ($creditsWon > ($creditsBet * 10) && $creditsWon < ($creditsBet * 25))
		{
			//$rubies += (($rubyReward * 9))
			log_message('debug', 'Won 10 rubies for 10 x bet');
			$rubies += 15;
		}
		else if($creditsWon > ($creditsBet * 25) && $creditsWon < ($creditsWon * 100))
		{
			//$rubies += (($rubyReward * 10)* $multiplier);
			log_message('debug', 'Won 35 rubies for 25 x bet');
			$rubies += 35;
		}
		else if($creditsWon > ($creditsBet * 100) && $creditsBet < ($creditsWon * 250))
		{
			//$rubies += (($rubyReward * 10)* $multiplier);
			log_message('debug', 'Won 50 rubies for 100 x bet');
			$rubies += 50;
		}
		else if($creditsWon > ($creditsBet * 250))
		{
			log_message('debug', 'Won 100 rubies for 250 x bet');
			//$rubies += (($rubyReward * 10)* $multiplier);
			$rubies += 100;
		}
		else
		{
			$rubies = 0;
		}

		return $rubies;

	}


	static function getMaxRubies($playerID, $level)
	{

		return self::getRubyMaxForLevel($level);

		log_message('debug', 'The max rubies returned is: ' . $max);
		return $max;
	}

	static function getRubyMaxForLevel($level)
	{
		foreach (self::$rubyLevels as $info)
		{
			if ($level == $info['level'])
			{
				
				return $info['max'];
			}
		}

		return null;		
	}

	static function getMultiplier($playerID=null)
	{
		//check to see if there are any promotions going on
		//We might run a weekend promo 
		//or maybe this player has purchased before and the prmomo only affects players that pay.

		return 2;

	}

	static function resetPlayerRubies($playerID)
	{
		//we'll be resetting the players rubies to zero
		//in addition we'll be also setting the time_unlocked to the current timestamp.
		$playerID = (int)$playerID;

		$cb = CouchbaseHelper::connect(); //create couchbase instance

		$p = new PlayerHelper();
		$p->loadPlayer($playerID);

		$p->total_rubies = 0;
		$p->last_ruby_claim_time = date('Y-m-d h:i:s');

		$p->savePlayer($cb);

		return 0;

		log_message('debug', 'reset rubies for player ' . $playerID);

	}

	static function setRubyTimer($playerID)
	{
		$playerID = (int)$playerID;

		$cb = CouchbaseHelper::connect(); //create couchbase instance

		$p = new PlayerHelper();
		$p->loadPlayer($playerID);
		$p->last_ruby_claim_time = date('Y-m-d h:i:s');
		$p->savePlayer($cb);


		log_message('debug', 'set ruby timer for player ' . $playerID);

		return 0;
	}

}