<?php
class SpinController extends PhantomController
{

	public function submit($playerID)
	{
		
		require_once APPPATH . 'classes/Couchbase.php';
		require_once APPPATH . 'classes/Player.php';
		require_once APPPATH . 'classes/GameStats.php';
		require_once APPPATH . 'classes/Leaderboard.php';
		require_once APPPATH . 'classes/Achievement.php';
		require_once APPPATH . 'classes/Spin.php';
		require_once APPPATH . 'classes/Level.php';
		require_once APPPATH . 'classes/Ruby.php';

		$secretKey = Config::get('app.key');
		
		$cb = CouchbaseHelper::connect();
		
		//instantiate defaults
		$total_lifetime_spins = 0;
		$leaderboardScore = 0.0;
		$currentLeaderboardVersion = 0;
		$playerLeaderboardVersion = 0;
		$facebook_id = '';
		$credits = 0;
		$xpMultiplier = 1;
		$showInvite10FriendsDialog = false;
		$promoUnlockDateIsValid = false;
		$thisIsAnUnlockedPromoSlot = false;

		$promoUnlockXP = 0;
		$newPromoUnlockXP = -1;
		$promoUnlockXPTarget = 0;
		$promoUnlockGameID = 0;
		$promoSecondsRemaining = 0;

		$p = new Player();
		$gs = new GameStats();

		//set the current leaderboard version
		$lbv = new Leaderboard();
		$lbv->Load($cb);


		$currentLeaderboardVersion = $lbv->version;

		if (!isset($_REQUEST['four_pay_achievement_str']))
			$_REQUEST['four_pay_achievement_str'] = '00000000';
		if (!isset($_REQUEST['five_pay_achievement_str']))
			$_REQUEST['five_pay_achievement_str'] = '00000000';
		if (!isset($_REQUEST['scatter_achievement_str']))
			$_REQUEST['scatter_achievement_str'] = '00';
		if (!isset($_REQUEST['special_achievement_str']))
			$_REQUEST['special_achievement_str'] = '00';

		if (isset($playerID) && isset($_REQUEST['creditsBet']) && isset($_REQUEST['creditsWon']))
		{
			
			$p->LoadPlayer((int)$playerID);
			

			if(!$p) //Error finding player let's kill this.
			{
				die(json_encode(array("status" => "error", "reason" => "no matching player record found")));
			}

			//$leaderRow = $db->from('leaderboard_version')->where('idleaderboard_version', 1)->fetch();

			$currentLeaderboardVersion = (int)$p->version;
			$totalLifetimeSpins = $p->total_lifetime_spins;
			$leaderBoardScore = $p->leaderboard_score;
			$facebookId = $p->facebook_id;
			$credits = $p->credits;
			$playerLeaderboardVersion = $p->current_leaderboard_id;
			$currentDay = $p->currentDay;
			$oldLevel = $p->level;
			$experience = $p->experience;
			$globalAchievementCount = $p->global_achievement_count;
			$xpMultiplierExirationDate = $p->xp_multiplier_expiration_date;
			$adSource = $p->ad_source;

			$gs->LoadStats((int)$playerID, (int)$_REQUEST['gameID'], $cb);

			//if (!$p->IsLoaded())
			//$real_hash = md5($secretKey . $_REQUEST['creditsBet'] . $_REQUEST['creditsWon'] . $playerID . $p->total_lifetime_spins);
			$real_hash = "mikerocks";
		}
		
		else
		{
			//we don't know what this is... set the $real_hash to a bogus value
			$real_hash = "ERROR!!!";
		}
	
		if(true)
		{
			$playerID = (int)$playerID;
			$gameID = (int)$_REQUEST['gameID'];
		
			$creditsBet = 0;
			$creditsWon = 0;
			$achievement_string = "";
			$bLeveledUp = false;
			$levelUpCredits = 0;
			$dailyCreditsWon = 0;
			
			$mobile = null;
			if (isset($_REQUEST["mobile"]))
				$mobile = $_REQUEST["mobile"];
			
			if (!empty($playerID))
			{

				//the credits won for this spin will be passed in a get parameter
				$creditsBet = (int)$_REQUEST['creditsBet'];
				$creditsWon = (int)$_REQUEST['creditsWon'];
				
				//sanity checking to limit cheating
				if ($creditsBet >= 0 && $mobile == null) //temporarily allow 0 bet amounts from mobile since they are sometimes sending them on collapse features, etc.
				{
					error_log("GOLDFISH: Negative credits bet");

					// send the cheat call
					$kt_params = array();
					$kt_params['s'] = $playerID; // player ID
					$kt_params['ts'] = time(); // TimeStamp
					$kt_params['n'] = "negative_bet"; // name
					$kt_params['st1'] = "cheat"; // st1
					$kt_params['st2'] = "spin"; // st2
					$kt_params['v'] = 1; // credits
					$kt_params['l'] = 1; // level
					//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);

					die(json_encode(array('status'=>'failure')));
				}

				else if ((-1*$creditsBet) > $p->credits)
				{
					error_log("GOLDFISH: Bet amount exceeds bankroll");

					// send the cheat call
					$kt_params = array();
					$kt_params['s'] = $playerID; // player ID
					$kt_params['ts'] = time(); // TimeStamp
					$kt_params['n'] = "negative_bet"; // name
					$kt_params['st1'] = "cheat"; // st1
					$kt_params['st2'] = "insufficient_bankroll"; // st2
					$kt_params['v'] = 1; // credits
					$kt_params['l'] = 1; // level
					//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);

					die(json_encode(array('status'=>'failure')));
				}

				
				//make an adjustment to the player's leaderboard score and tell the Facebook scores API about it
				if ($creditsWon > 0 && $creditsBet < 0)
				{
					if ($currentLeaderboardVersion != $p->current_leaderboard_id)
					{
						$p->leaderboard_score = $creditsWon;
						$p->current_leaderboard_id = $currentLeaderboardVersion;
					}
					else
					{
						$p->leaderboard_score += $creditsWon;
					}
				}
				
				if($p->total_lifetime_spins % 100 == 0)
					$showInvite10FriendsDialog = true;					


				//adjust the spin stats for the current game
				$gs->lifetime_spins += 1;
				$gs->lifetime_credits += $creditsBet + $creditsWon;

				if ($creditsWon > $gs->lifetime_biggest_win)
					$gs->lifetime_biggest_win = $creditsWon;
				
				if ($creditsWon > 0)
				{
					$gs->current_win_streak++;
					$gs->current_loss_streak = 0;
				}
				else
				{
					$gs->current_win_streak = 0;
					$gs->current_loss_streak++;				
				}

				$gs->total_credits_won += $creditsWon;

				/*
				| NEED TO ADD GAME STATS UPDATE HERE
				| MOVE THIS TO THE GAMESTATS CLASS
				|
				|
				*/
				
				$achievement_string = Achievement::HandleAchievementUpdates($playerID, $gameID, $creditsWon,
									$_REQUEST['four_pay_achievement_str'],
									$_REQUEST['five_pay_achievement_str'],
									$_REQUEST['scatter_achievement_str'],
									$_REQUEST['special_achievement_str'],
									$cb, $p, $gs);
				

				

				//if(($oldLevel >= 10) && self::IsBigWin($creditsWon, $creditsBet) && SpinHelper::XPMultiplierExpirationDateIsValid($p->xp_multiplier_expiration_date) && $mobile == null)
				if(($oldLevel >= 10) && Spin::IsBigWin($creditsWon, $creditsBet) && $mobile == null)
				{
					//$xpMultiplierExpirationDate = UpdateXPMultiplierExpirationDate($p, 5);
				}
				
				$xpMultiplierSecondsRemaining = Spin::GetXPMultiplierSecondsRemaining($p);
				
				if($xpMultiplierSecondsRemaining > 0)
					$xpMultiplier *= 2;

				if ($oldLevel == 9 && // this group has another 2x experience at level 9
					(($playerID % 6) < 3))
				{
					$xpMultiplier *= 2;
				}

				/* XP INCREASE
				| Move this to a class
				| Aaaaaand something
				*/

				if(($playerID >= 7745000 && $playerID <= 10016000) && (($playerID % 6) < 3))
					$xpMultiplier *= 2;
				else if($oldLevel <= 40)
					$xpMultiplier *= 2; // everyone has double xp until level 40

				if ($creditsBet < 0)
					$xp_increase = -1 * $creditsBet * $xpMultiplier;
				else
					$xp_increase = 0;

				$p->credits = $p->credits +  ($creditsBet + $creditsWon);
				$newCredits = ($creditsBet + $creditsWon);
				$oldTotal = (int)$p->credits;
				echo "Old total: " . $oldTotal . " New total: " . $newCredits ."<br />";
				$p->credits = $newTotal = $oldTotal + $newCredits;
				echo 'the new total should be: ' . $newTotal;

				$p->experience += $xp_increase;
				$p->total_lifetime_spins += 1;
				$p->has_leaderboard_score_changed = 1;	
				$p->promo_unlock_xp = $newPromoUnlockXP;
				$p->last_spin_time = $currentDay;

				if($promoUnlockDateIsValid && $newPromoUnlockXP >= $p->promo_unlock_xp_target)
				{
					$newExpirationDate = UnlockPromoSlotForPlayer($p, 15);
					
					$promoSecondsRemaining = DateHelper::GetSecondsRemaining($nowStr);
					$xpMultiplierSecondsRemaining = DateHelper::GetSecondsRemaining($newExpirationDate);
				}
				
				// adjust money
				$kt_params = array();
				$kt_params['s'] = $playerID; // player ID
				$kt_params['ts'] = time(); // TimeStamp
				$kt_params['n'] = "spin"; // name
				$kt_params['st1'] = "economy"; // st1
				$kt_params['st2'] = "coins"; // st2
				$kt_params['st3'] = "sink"; // st3
				$kt_params['v'] = $creditsBet; // credits
				$kt_params['l'] = $oldLevel; // level
				
				if ($creditsWon > 0)
				{
					$kt_credits = $creditsWon;
					while ($kt_credits > 21000000000)
					{
						// adjust money
						$kt_params = array();
						$kt_params['s'] = $playerID; // player ID
						$kt_params['ts'] = time(); // TimeStamp
						$kt_params['n'] = "spin_win"; // name
						$kt_params['st1'] = "economy"; // st1
						$kt_params['st2'] = "coins"; // st2
						$kt_params['st3'] = "source"; // st3
						$kt_params['v'] = 21000000000; // credits
						$kt_params['l'] = $oldLevel; // level
						
						//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);
						$kt_credits = $kt_credits - 21000000000;
					}
					
					// adjust money
					$kt_params = array();
					$kt_params['s'] = $playerID; // player ID
					$kt_params['ts'] = time(); // TimeStamp
					$kt_params['n'] = "spin_win"; // name
					$kt_params['st1'] = "economy"; // st1
					$kt_params['st2'] = "coins"; // st2
					$kt_params['st3'] = "source"; // st3
					$kt_params['v'] = $kt_credits; // credits
					$kt_params['l'] = $oldLevel; // level
					
					//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);
				}
				
				$info = Level::GetLevelInfoForXP($p->experience);

				if ($info)
				{
					$level = (int)$info['level'];
					$experience = (int)$p->experience - $info['min_xp'];
					$level_max_xp = (int)$info['max_xp'] - $info['min_xp'];
					$levelUpCredits = (int)$info['bonus_credits'];
					
					if (($playerID >= 0 && $playerID <= 925300) || ($playerID >= 925300 && $playerID < 1100000 && ($playerID%2 == 1)))
						$friendBonusCredits = 10000; //megabonus V1 players get $100 per friend
					else
						$friendBonusCredits = (int)$info['friend_bonus_credits']; //newer megabonus players' friend bonus is based on the level_info table
				}
				
				
				//adjust the player's level in the DB if it has changed
				if ($p->level != $level)
				{
					$p->level = $level;
					$bLeveledUp = true;
				}

				$awardedRubies = Ruby::getRubyAward($playerID, $creditsBet, $creditsWon, $p->level);
				$p->total_rubies += $awardedRubies;
				//let's see if the ruby class works. gah
				//$rubyMax = Ruby::getMaxRubies($playerID, $p->level);
				$rubyMax = Ruby::getMaxRubies($playerID, $p->rubies_claimed);

				

				if($p->total_rubies >= $rubyMax)
				{
				
				}
				else
				{

				}
				
				/** End Rubies Call */
				

				//'ruby_collect_time_remaining' => $rubyCollectTimeRemaining, removed not needed right now.
				//note that the returned experience is the experience in the current level, not total experience
				$data = array(
					'status' => 'ok', 
					'credits' => (int)$p->credits, 
					'experience' => (int)$experience,
					'global_achievement_count' => (int)$p->global_achievement_count,
					'level_max_xp' => (int)$level_max_xp,
					'level' => (int)$level,
					'leveled_up' => $bLeveledUp,
					'total_lifetime_spins' => (string)$p->total_lifetime_spins,
					'friend_bonus_credits' => (int)$friendBonusCredits,
					'show_invite_ten_friends_dialog' => $showInvite10FriendsDialog,
					'xp_multiplier_seconds_remaining' => $xpMultiplierSecondsRemaining,
					'promo_slot_xp' => $newPromoUnlockXP,
					'promo_slot_target_xp' => $p->promo_unlock_xp_target,
					'promo_game_id' => $p->promo_unlock_game_id,
					'promo_seconds_remaining' => $promoSecondsRemaining,
					'total_experience' => $p->experience,
					'rubies_total' => $p->total_rubies,
					'rubies_max' => $rubyMax,
					'rubies_awarded' => $awardedRubies
				);


				//get all the info about the current and next level
				if ($bLeveledUp)
				{
					$newLevelInfo = array();
					$nextLevelInfo = array();
									
					$info = Level::GetLevelInfoForLevel($level);
					if ($info)
					{
						$newLevelInfo['level'] = (int)$info['level'];
						$newLevelInfo['min_xp'] = (int)$info['min_xp'];
						$newLevelInfo['max_xp'] = (int)$info['max_xp'];
						$newLevelInfo['bonus_credits'] = (int)$info['bonus_credits'];
						
						if (($playerID >= 0 && $playerID <= 925300) || ($playerID >= 925300 && $playerID < 1100000 && ($playerID%2 == 1)))
							$newLevelInfo['friend_bonus_credits'] = -1; //megabonus v1 players get $100 per friend, but we never show it in the level perks
						else if ((int)$info['unlocks_friend_bonus_increase'] == 1)
							$newLevelInfo['friend_bonus_credits'] = (int)$info['friend_bonus_credits']; //newer megabonus players get a friend bonus based on level
						else
							$newLevelInfo['friend_bonus_credits'] = -1; //newer megabonus players get a friend bonus based on level
						
						$newLevelInfo['slot_group'] = (int)$info['slot_group'];
						$newLevelInfo['slots_unlocked_in_group'] = (int)$info['slots_unlocked_in_group'];
						$newLevelInfo['daily_bonus_credits'] = (int)$info['daily_bonus_credits'];
						$newLevelInfo['max_denom_amt'] = (int)Level::GetMaxDenomAmtForLevel($level);
						$newLevelInfo['unlocks_slot'] = (int)$info['unlocks_slot'];
					}
					
					$info = Level::GetLevelInfoForLevel($level + 1);
					if ($info)
					{
						$nextLevelInfo['level'] = (int)$info['level'];
						$nextLevelInfo['min_xp'] = (int)$info['min_xp'];
						$nextLevelInfo['max_xp'] = (int)$info['max_xp'];
						$nextLevelInfo['bonus_credits'] = (int)$info['bonus_credits'];
						$nextLevelInfo['slot_group'] = (int)$info['slot_group'];
						$nextLevelInfo['slots_unlocked_in_group'] = (int)$info['slots_unlocked_in_group'];
						$nextLevelInfo['unlocks_slot'] = (int)$info['unlocks_slot'];
						
						//only return new information for the next level unlocks
						if ($info['unlocks_daily_bonus_increase'] == 1) //daily bonus increase
							$nextLevelInfo['daily_bonus_credits'] = (int)$info['daily_bonus_credits'];
						else
							$nextLevelInfo['daily_bonus_credits'] = -1;
							
						if ($info['unlocks_denomination_increase'] == 1) //denomination increase
							$nextLevelInfo['max_denom_amt'] = (int)Level::GetMaxDenomAmtForLevel($level+1);
						else
							$nextLevelInfo['max_denom_amt'] = -1;
							
						 //friend bonus increase
						if (($playerID >= 0 && $playerID <= 925300) || ($playerID >= 925300 && $playerID < 1100000 && ($playerID%2 == 1)))
							$nextLevelInfo['friend_bonus_credits'] = -1; //megabonus v1 players get $100 per friend
						else if ((int)$info['unlocks_friend_bonus_increase'] == 1)
							$nextLevelInfo['friend_bonus_credits'] = (int)$info['friend_bonus_credits']; //newer megabonus players get a friend bonus based on level
						else
							$nextLevelInfo['friend_bonus_credits'] = -1; //newer megabonus players get a friend bonus based on level
					}

					
					//award the bonus credits here as well
					$p->credits = $p->credits + $newLevelInfo['bonus_credits'];
					
					//populate the info for the level up so we can show the popup, award the credits, etc.
					$data['level_info'] = array();
					
					$data['level_info']['new_level'] = $newLevelInfo;
					$data['level_info']['next_level'] = $nextLevelInfo;
					
					
					// adjust money
					$kt_params = array();
					$kt_params['s'] = $playerID; // player ID
					$kt_params['ts'] = time(); // TimeStamp
					$kt_params['n'] = "level_reward"; // name
					$kt_params['st1'] = "economy"; // st1
					$kt_params['st2'] = "coins"; // st2
					$kt_params['st3'] = "source"; // st3
					$kt_params['v'] = $newLevelInfo['bonus_credits']; // credits
					$kt_params['l'] = $level; // level
					
					//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);
				}

				//add data for any achievements they might have won on this spin
				$data['achievements'] = array();
				$len = strlen($achievement_string);
				for ($i = 0; $i < $len; $i++)
				{
					if ($achievement_string[$i] == '1')
					{
						
						$ai = new AchievementHelper;
						$doc = $ai->Load($gameID, $i + 1, $cb);
						
							log_message('debug', 'Loaded acheivement file for game: ' . $gameID. ' and ach: ' . $i+1);
							$temp['achievement_id'] = (int)($i + 1);
							$temp['achievement_title'] = $doc->title;
							$temp['achievement_desc'] = $doc->desc;
							$temp['achievement_game_folder_name'] = $doc->game_folder_name;
							$data['achievements'][] = $temp;
					}
				}


			
				if ($bLeveledUp)
				{
					$maxDenomAmt = Level::GetMaxDenomAmtForLevel($level);
					//Log::info("Setting max denom for player: " . $playerID . " Level: " . $level . " To " . $maxDenomAmt);
					$nextSlotAwardLevel = Level::GetNextSlotAwardLevel($level);

					$next_awarded_slot_xp = Level::GetNextSlotAwardByXP($p->experience);
					$last_awarded_slot_xp = Level::GetLastSlotAwardedByXP($p->experience);
					
					$currentSlotGroup = 0;
					$slotsUnlockedInCurrentGroup = 0;

					$info = Level::GetLevelInfoForLevel($level);

					if ($info)
					{
						$currentSlotGroup = $info['slot_group'];
						$slotsUnlockedInCurrentGroup = $info['slots_unlocked_in_group'];
					}

					$data['max_denom_amt'] = $maxDenomAmt;
					$data['next_slot_award_level'] = $nextSlotAwardLevel;
					$data['max_slot_group'] = $currentSlotGroup;
					
					$data['next_awarded_slot_xp'] = $next_awarded_slot_xp;
					$data['last_awarded_slot_xp'] = $last_awarded_slot_xp;
					//now check to see if we have all the slots we should based on our group and owned slots
					$groupSlots = array();
					
					$ownedSlotsInGroup = 0;
					$availableSlotsToUnlock = array();
			
					for ($i = 0; $i < count($groupSlots); $i++)
					{
						if ($groupSlots[$i]['owned'] == 1)
						{
							$ownedSlotsInGroup++;
						}
						else
						{
							$availableSlotsToUnlock[] = $groupSlots[$i];
						}
					}
					
					if ($ownedSlotsInGroup < $slotsUnlockedInCurrentGroup)
					{
						//we need to unlock a slot
						$data['bHasUnlockSlotAvailable'] = true;
						$data['availableUnlockSlots'] = $availableSlotsToUnlock;
					}
					else
					{
						$data['bHasUnlockSlotAvailable'] = false;
					}
				}
				else
				{
					$data['bHasUnlockSlotAvailable'] = false;
				}

				//save the player and game stats record
				$p->savePlayer($cb);
				$updateFields = (array)$p;

				//$query = $db->update('player', $update, 'player_id', $playerID);
				//$db->update('player')->set($updateFields)->where('player_id', $playerID)->execute();
			
				//$gs->SaveStats($cb);
				
				echo(json_encode($data));
			}
			else
			{
				echo(json_encode(array('status'=>' sdsd failure')));
			}
		}
		else
		{
			error_log("GOLDFISH: Key Mismatch");
			echo(json_encode(array('status'=>' fdaa failure')));
		}

	}

	public function submit_db($playerID)
	{
		require_once APPPATH . 'classes/Player.php';
		require_once APPPATH . 'classes/Leaderboard.php';
		require_once APPPATH . 'classes/Spin.php';
		require_once APPPATH . 'classes/Level.php';
		require_once APPPATH . 'classes/Ruby.php';
		require_once APPPATH . 'classes/Date.php';

		//Initialize default variables
		$totalLifetimeSpins = 0;
		$leaderboardScore = 0.0;
		$currentLeaderboardVersion = 0;
		$playerLeaderboardVersion = 0;
		$facebookId = '';
		$credits = 0;
		$xpMultiplier = 1;
		$showInvite10FriendsDialog = false;
			
		$promoUnlockXP = 0;
		$newPromoUnlockXP = -1;
		$promoUnlockXPTarget = 0;
		$promoUnlockGameID = 0;
		$promoSecondsRemaining = 0;
		$now = 0;
		$promoUnlockDateIsValid = false;
		$thisIsAnUnlockedPromoSlot = false;
		$adSource = '';
		$bLeveledUp = false;

		$db = $this->get_dbInstance();

		if (!isset($_REQUEST['four_pay_achievement_str']))
			$_REQUEST['four_pay_achievement_str'] = '00000000';
		if (!isset($_REQUEST['five_pay_achievement_str']))
			$_REQUEST['five_pay_achievement_str'] = '00000000';
		if (!isset($_REQUEST['scatter_achievement_str']))
			$_REQUEST['scatter_achievement_str'] = '00';
		if (!isset($_REQUEST['special_achievement_str']))
			$_REQUEST['special_achievement_str'] = '00';


		$secretKey = Config::get('app.key');

		$query = mysqli_query($db,'SELECT player.*, NOW() AS currentDateTime FROM player WHERE player_id = ' . $playerID);

		$p = (object)mysqli_fetch_array($query);
		
		

		
		if(isset($playerID) && isset($_REQUEST['creditsBet']) && isset($_REQUEST['creditsWon']))
		{
			//setup instance
			$hash = md5($secretKey . $_REQUEST['creditsBet'] . $_REQUEST['creditsWon'] . $p->total_lifetime_spins); //SEECDREETT SHHHHH
			$hash = "mikerocks";
		}
		else
		{
			$hash = "ERROR";
		}

		if($hash == $_REQUEST['key'])
		{
			//Legit user let's start the calculations
			$query = "SELECT * FROM leaderboard_version WHERE idleaderboard_version = 1";
			$results = mysqli_query($db, $query);

			$l = (object)mysqli_fetch_array($results);

			$totalLifetimeSpins = $p->total_lifetime_spins;
			$leaderboardScore = $p->leaderboard_score;
			$facebookID = $p->facebook_id;
			$credits = $p->credits;
			$playerLeaderboardVersion = $p->current_leaderboard_id;
			$currentLeaderboardVersion = $l->version;
			$oldLevel = $p->level;
			$experience = $p->experience;
			$globalAchievementCount = $p->global_achievement_count;
			$xpMultiplierExpirationDate = $p->xp_multiplier_expiration_date;
			$now = $p->currentDateTime;
			$adSource = $p->ad_source;
			
			//$cb = CouchbaseHelper::connect();
			//$gs = new GameStats();
			//$gs->LoadStats((int)$playerID, (int)$_REQUEST['gameID'], $cb);


			if(isset($_REQUEST['mobile']))
				$mobile = $_REQUEST['mobile'];
			else
				$mobile = null;

			if(!empty($playerID))
			{
				$gameID = (int)$_REQUEST['gameID'];
				$creditsBet = (int)$_REQUEST['creditsBet'];
				$creditsWon = (int)$_REQUEST['creditsWon'];

				//get players data.

				$oldLevel = $p->level;

				if($creditsBet >= 0 && $mobile == null)
				{

					//Kontagent Call for hax0rs
				}
				else if((-1*$creditsBet) > $credits)
				{
					//Kontagent Call for hax0rs
				}

				if($creditsWon > 0 && $creditsBet)
				{
					if($currentLeaderboardVersion != $playerLeaderboardVersion)
					{
						$leaderboardScore = $creditsWon;
						$playerLeaderboardVersion = $currentLeaderboardVersion;
					}
					else
					{
						$leaderboardScore += $creditsWon;
					}
				}

				if($totalLifetimeSpins % 100 == 0)
				{
					$showInvite10FriendsDialog = true;
				}

				/* Adjust the spin stats for the current game
				|
				|
				|
				End Adjustment
				*/

				/*
				$promoUnlockDateIsValid = DateHelper::IsCurrentTimeValid($now, $promoUnlockExpirationDate);
				if($promoUnlockDateIsValid && ($promoUnlockXP >= 0))
				{	
						//if the player's unlock expiration time hasn't expired, 
						//allow them to accumulate xp towards unlocking the slot -
						//greater or equal to zero because xp is set to -1 while in an unlocked state
						$newPromoUnlockXP = (int)$creditsWon + (int)$promoUnlockXP;
						//$promoUnlockDateIsValid = true;
				}
				
				if($promoUnlockDateIsValid && $promoUnlockXP == -1 && ($promoUnlockGameID == $gameID))
				{
					//currently in an unlocked state and playing the unlocked slot
					$thisIsAnUnlockedPromoSlot = true;
					$promoSecondsRemaining = PhantomDateUtils::GetSecondsRemaining($promoUnlockExpirationDate);
				}

				
				//see if we just won any achievements and are not playing an unlocked promo slot
				if(!$thisIsAnUnlockedPromoSlot)
				{
					$achievement_string = HandleAchievementUpdates($playerID, $gameID, $creditsWon,
													 mysqli_real_escape_string(JackpotPartyDB::$connection, $_REQUEST['four_pay_achievement_str']),
													 mysqli_real_escape_string(JackpotPartyDB::$connection, $_REQUEST['five_pay_achievement_str']),
													 mysqli_real_escape_string(JackpotPartyDB::$connection, $_REQUEST['scatter_achievement_str']),
													 mysqli_real_escape_string(JackpotPartyDB::$connection, $_REQUEST['special_achievement_str']));
				}
				*/
				
				/* SET MULTIPLIER STUFF
				|
				|
				*/
				/*
				$achievement_string = Achievement::HandleAchievementUpdates($playerID, $gameID, $creditsWon,
									$_REQUEST['four_pay_achievement_str'],
									$_REQUEST['five_pay_achievement_str'],
									$_REQUEST['scatter_achievement_str'],
									$_REQUEST['special_achievement_str'],
									$cb, $p, $gs);
				*/
				$xpMultiplierSecondsRemaining = 0;

				if($xpMultiplierSecondsRemaining > 0)
					$xpMultiplier *= 2;
					
				if ($oldLevel == 9 && // this group has another 2x experience at level 9
					(($playerID % 6) < 3))
				{
					$xpMultiplier *= 2;
				}

				if(($playerID >= 7745000 && $playerID <= 10016000) && (($playerID % 6) < 3))
					$xpMultiplier *= 2;
				else if ($oldLevel <= 40)
					$xpMultiplier *= 2; // everyone has double xp until level 40
				


				if ($creditsBet < 0)
					$xp_increase = -1 * $creditsBet * $xpMultiplier;
				else
					$xp_increase = 0;

				$credits += ($creditsBet + $creditsWon);
				$experience = $experience + $xp_increase;
				$total_experience = $experience;
				$totalLifetimeSpins += 1; //Adding 1 to lifetime spins.

				/*
				'total_lifetime_spins' => 'total_lifetime_spins + 1',
					'last_spin_time' => 'NOW()',
				*/

				

				//updated player
				//let's continue

				//Kontagent Call
				// adjust money
				$kt_params = array();
				$kt_params['s'] = $playerID; // player ID
				$kt_params['ts'] = time(); // TimeStamp
				$kt_params['n'] = "spin"; // name
				$kt_params['st1'] = "economy"; // st1
				$kt_params['st2'] = "coins"; // st2
				$kt_params['st3'] = "sink"; // st3
				$kt_params['v'] = $creditsBet; // credits
				$kt_params['l'] = $oldLevel; // level
				//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);

				if ($creditsWon > 0)
				{
					$kt_credits = $creditsWon;
					while ($kt_credits > 21000000000)
					{
						// adjust money
						$kt_params = array();
						$kt_params['s'] = $playerID; // player ID
						$kt_params['ts'] = time(); // TimeStamp
						$kt_params['n'] = "spin_win"; // name
						$kt_params['st1'] = "economy"; // st1
						$kt_params['st2'] = "coins"; // st2
						$kt_params['st3'] = "source"; // st3
						$kt_params['v'] = 21000000000; // credits
						$kt_params['l'] = $old_level; // level
						
						//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);
						$kt_credits = $kt_credits - 21000000000;
					}
					// adjust money
					$kt_params = array();
					$kt_params['s'] = $playerID; // player ID
					$kt_params['ts'] = time(); // TimeStamp
					$kt_params['n'] = "spin_win"; // name
					$kt_params['st1'] = "economy"; // st1
					$kt_params['st2'] = "coins"; // st2
					$kt_params['st3'] = "source"; // st3
					$kt_params['v'] = $kt_credits; // credits
					$kt_params['l'] = $oldLevel; // level
					//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);
				}


				$info = Level::GetLevelInfoForXP($p->experience);

				if ($info)
				{
					$level = (int)$info['level'];
					$experience = (int)$p->experience - $info['min_xp'];
					$level_max_xp = (int)$info['max_xp'] - $info['min_xp'];
					$levelUpCredits = (int)$info['bonus_credits'];
					
					if (($playerID >= 0 && $playerID <= 925300) || ($playerID >= 925300 && $playerID < 1100000 && ($playerID%2 == 1)))
						$friendBonusCredits = 10000; //megabonus V1 players get $100 per friend
					else
						$friendBonusCredits = (int)$info['friend_bonus_credits']; //newer megabonus players' friend bonus is based on the level_info table
				}
				
				//adjust the player's level in the DB if it has changed
				if ($oldLevel != $level)
				{
					$playerData['level'] = $level;
					$bLeveledUp = true;

					if ($p->ad_source == 'LSM' && $level == 2)
					{
						/*
						JackpotPartyDB::GetConnection();

						$sql = 'SELECT ls_unique_id FROM UpdateSingleAchievementWithProgress WHERE player_id = ' . $playerID;

						$result = mysqli_query(JackpotPartyDB::$connection, $sql);
					
						if ($row = mysqli_fetch_row($result))
						{
							file_get_contents("https://pix.lfstmedia.com/_tracker/455?__noscript=true&propname=%7Cadvertiser_offer_id&propvalue=183040&__sid=" . $row[0]);
						}

						JackpotPartyDB::ReleaseConnection();
						*/
					}
				}

				$awardedRubies = Ruby::getRubyAward($playerID, $creditsBet, $creditsWon, $p->level);
				$p->total_rubies += $awardedRubies;

				//let's see if the ruby class works. gah
				
				//$rubyMax = RubyHelper::getMaxRubies($playerID, $p->level);
				$rubyMax = Ruby::getMaxRubies($playerID, $p->rubies_claimed);

				

				if($p->total_rubies >= $rubyMax)
				{
					//Log::info('max rubies reached lets reset and remaining time issssss' . Config::get('app.fifo_game_unlock_expires_time_secs'));
					//Ruby::resetPlayerRubies($playerID); //reset players rubies and set ruby unlock time.
					//$p->total_rubies = 0;
					//Ruby::setRubyTimer($playerID);
					//$rubyCollectTimeRemaining = Config::get('app.fifo_game_unlock_expires_time_secs');
				}
				else
				{
					//$rubyCollectTimeRemaining = 0;
				}


				$query = "UPDATE player  SET credits = credits + " . $credits . ", experience = experience + " . $xp_increase . 
						 ", leaderboard_score = " . $leaderboardScore .", current_leaderboard_id = " . $playerLeaderboardVersion .", " .
						 " promo_unlock_xp = " . $newPromoUnlockXP . ", total_lifetime_spins = total_lifetime_spins + 1, last_spin_time = NOW()," .
						 " has_leaderboard_score_changed = 1, total_rubies = total_rubies + " . $awardedRubies .
						 " WHERE player_id = " . $playerID;

				mysqli_query($db, $query);


				$data = array(
					'status' => 'ok', 
					'credits' => (int)$p->credits, 
					'experience' => (int)$experience,
					'global_achievement_count' => (int)$p->global_achievement_count,
					'level_max_xp' => (int)$level_max_xp,
					'level' => (int)$level,
					'leveled_up' => $bLeveledUp,
					'total_lifetime_spins' => (string)$p->total_lifetime_spins,
					'friend_bonus_credits' => (int)$friendBonusCredits,
					'show_invite_ten_friends_dialog' => $showInvite10FriendsDialog,
					'xp_multiplier_seconds_remaining' => $xpMultiplierSecondsRemaining,
					'promo_slot_xp' => $newPromoUnlockXP,
					'promo_slot_target_xp' => $p->promo_unlock_xp_target,
					'promo_game_id' => $p->promo_unlock_game_id,
					'promo_seconds_remaining' => $promoSecondsRemaining,
					'total_experience' => $p->experience,
					'rubies_total' => $p->total_rubies,
					'rubies_max' => $rubyMax,
					'rubies_awarded' => $awardedRubies);
					
				//get all the info about the current and next level
				if ($bLeveledUp)
				{
					$newLevelInfo = array();
					$nextLevelInfo = array();
									
					$info = Level::GetLevelInfoForLevel($level);
					if ($info)
					{
						$newLevelInfo['level'] = (int)$info['level'];
						$newLevelInfo['min_xp'] = (int)$info['min_xp'];
						$newLevelInfo['max_xp'] = (int)$info['max_xp'];
						$newLevelInfo['bonus_credits'] = (int)$info['bonus_credits'];
						
						if (($playerID >= 0 && $playerID <= 925300) || ($playerID >= 925300 && $playerID < 1100000 && ($playerID%2 == 1)))
							$newLevelInfo['friend_bonus_credits'] = -1; //megabonus v1 players get $100 per friend, but we never show it in the level perks
						else if ((int)$info['unlocks_friend_bonus_increase'] == 1)
							$newLevelInfo['friend_bonus_credits'] = (int)$info['friend_bonus_credits']; //newer megabonus players get a friend bonus based on level
						else
							$newLevelInfo['friend_bonus_credits'] = -1; //newer megabonus players get a friend bonus based on level
						
						$newLevelInfo['slot_group'] = (int)$info['slot_group'];
						$newLevelInfo['slots_unlocked_in_group'] = (int)$info['slots_unlocked_in_group'];
						$newLevelInfo['daily_bonus_credits'] = (int)$info['daily_bonus_credits'];
						$newLevelInfo['max_denom_amt'] = (int)Level::GetMaxDenomAmtForLevel($level);
						$newLevelInfo['unlocks_slot'] = (int)$info['unlocks_slot'];
					}
					
					$info = Level::GetLevelInfoForLevel($level + 1);
					if ($info)
					{
						$nextLevelInfo['level'] = (int)$info['level'];
						$nextLevelInfo['min_xp'] = (int)$info['min_xp'];
						$nextLevelInfo['max_xp'] = (int)$info['max_xp'];
						$nextLevelInfo['bonus_credits'] = (int)$info['bonus_credits'];
						$nextLevelInfo['slot_group'] = (int)$info['slot_group'];
						$nextLevelInfo['slots_unlocked_in_group'] = (int)$info['slots_unlocked_in_group'];
						$nextLevelInfo['unlocks_slot'] = (int)$info['unlocks_slot'];
						
						//only return new information for the next level unlocks
						if ($info['unlocks_daily_bonus_increase'] == 1) //daily bonus increase
							$nextLevelInfo['daily_bonus_credits'] = (int)$info['daily_bonus_credits'];
						else
							$nextLevelInfo['daily_bonus_credits'] = -1;
							
						if ($info['unlocks_denomination_increase'] == 1) //denomination increase
							$nextLevelInfo['max_denom_amt'] = (int)Level::GetMaxDenomAmtForLevel($level+1);
						else
							$nextLevelInfo['max_denom_amt'] = -1;
							
						 //friend bonus increase
						if (($playerID >= 0 && $playerID <= 925300) || ($playerID >= 925300 && $playerID < 1100000 && ($playerID%2 == 1)))
							$nextLevelInfo['friend_bonus_credits'] = -1; //megabonus v1 players get $100 per friend
						else if ((int)$info['unlocks_friend_bonus_increase'] == 1)
							$nextLevelInfo['friend_bonus_credits'] = (int)$info['friend_bonus_credits']; //newer megabonus players get a friend bonus based on level
						else
							$nextLevelInfo['friend_bonus_credits'] = -1; //newer megabonus players get a friend bonus based on level
					}

					//award the bonus credits here as well
					$p->credits += $newLevelInfo['bonus_credits'];
					
					//populate the info for the level up so we can show the popup, award the credits, etc.
					$data['level_info'] = array();
					
					$data['level_info']['new_level'] = $newLevelInfo;
					$data['level_info']['next_level'] = $nextLevelInfo;
					
					
					// adjust money
					$kt_params = array();
					$kt_params['s'] = $playerID; // player ID
					$kt_params['ts'] = time(); // TimeStamp
					$kt_params['n'] = "level_reward"; // name
					$kt_params['st1'] = "economy"; // st1
					$kt_params['st2'] = "coins"; // st2
					$kt_params['st3'] = "source"; // st3
					$kt_params['v'] = $newLevelInfo['bonus_credits']; // credits
					$kt_params['l'] = $level; // level
					
					//KontagentAPI::SendAPICall($mobile,false,false,"evt",$kt_params);
				}

				$data['achievements'] = array();
				
				/*
				$len = strlen($achievement_string);
				
				for ($i = 0; $i < $len; $i++)
				{
					if ($achievement_string[$i] == '1')
					{
						Log::info('Acheivement string was 1 ');
						//$ai = new AchievementHelper;
						//$doc = $ai->Load($gameID, $i + 1, $cb);
						
							Log::info('Loaded acheivement file for game: ' . $gameID. ' and ach: ' . $i+1);
							$temp['achievement_id'] = (int)($i + 1);
							$temp['achievement_title'] = $doc->title;
							$temp['achievement_desc'] = $doc->desc;
							$temp['achievement_game_folder_name'] = $doc->game_folder_name;
							$data['achievements'][] = $temp;
						
						
					}
				}
				*/

				if ($bLeveledUp)
				{
					$maxDenomAmt = Level::GetMaxDenomAmtForLevel($level);
					//Log::info("Setting max denom for player: " . $playerID . " Level: " . $level . " To " . $maxDenomAmt);
					$nextSlotAwardLevel = Level::GetNextSlotAwardLevel($level);

					$next_awarded_slot_xp = Level::GetNextSlotAwardByXP($p->experience);
					$last_awarded_slot_xp = Level::GetLastSlotAwardedByXP($p->experience);
					
					$currentSlotGroup = 0;
					$slotsUnlockedInCurrentGroup = 0;

					$info = Level::GetLevelInfoForLevel($level);

					if ($info)
					{
						$currentSlotGroup = $info['slot_group'];
						$slotsUnlockedInCurrentGroup = $info['slots_unlocked_in_group'];
					}

					$data['max_denom_amt'] = $maxDenomAmt;
					$data['next_slot_award_level'] = $nextSlotAwardLevel;
					$data['max_slot_group'] = $currentSlotGroup;
					
					$data['next_awarded_slot_xp'] = $next_awarded_slot_xp;
					$data['last_awarded_slot_xp'] = $last_awarded_slot_xp;
					//now check to see if we have all the slots we should based on our group and owned slots
					$groupSlots = array();
					
					$ownedSlotsInGroup = 0;
					$availableSlotsToUnlock = array();
			
					for ($i = 0; $i < count($groupSlots); $i++)
					{
						if ($groupSlots[$i]['owned'] == 1)
						{
							$ownedSlotsInGroup++;
						}
						else
						{
							$availableSlotsToUnlock[] = $groupSlots[$i];
						}
					}
					
					if ($ownedSlotsInGroup < $slotsUnlockedInCurrentGroup)
					{
						//we need to unlock a slot
						$data['bHasUnlockSlotAvailable'] = true;
						$data['availableUnlockSlots'] = $availableSlotsToUnlock;
					}
					else
					{
						$data['bHasUnlockSlotAvailable'] = false;
					}
				}
				else
				{
					$data['bHasUnlockSlotAvailable'] = false;
				}
				//$gs->SaveStats($cb);
			}

		}
		else
		{
			$data['status'] = 'error';
		}

		echo json_encode($data);
		
		

	}

}