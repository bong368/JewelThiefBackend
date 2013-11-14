<?php

class Achievement extends CouchbaseHelper
{
	protected $data;
	protected $bLoaded = false;
	protected $allowed_fields = array();
	
	public function __construct()
	{
		$this->allowed_fields = array(
	    	"game_id",
	        "achievement_id",
	        "title",
	        "desc",
	        "game_folder_name"
	    );
	}

	

	static function HandleAchievementUpdates($player_id, $game_id, $credits, $four_pay_achievement_str, $five_pay_achievement_str, $scatter_achievement_str, $special_achievement_str, &$cb, &$cbPlayer, &$cbGameStats)
	{
		$bUpdatedProgress = false;
		$achievementStr = '0000000000000000000000000';
		$progress1 = 0;
		$progress2 = 0;
		$progress3 = 0;
		$progress4 = 0;
		$progress5 = 0;
		$lifetime_spins = 0;
		$current_win_streak = 0;
		$current_loss_streak = 0;
		$total_credits_won = 0;
		
		$achievementStr = $cbGameStats->achievement_str;
		$progress1 = (int)$cbGameStats->progress_1;
		$progress2 = (int)$cbGameStats->progress_2;
		$progress3 = (int)$cbGameStats->progress_3;
		$progress4 = (int)$cbGameStats->progress_4;
		$progress5 = (int)$cbGameStats->progress_5;
		$lifetime_spins = (int)$cbGameStats->lifetime_spins;
		$current_win_streak = (int)$cbGameStats->current_win_streak;
		$current_loss_streak = (int)$cbGameStats->current_loss_streak;
		$total_credits_won = (int)$cbGameStats->total_credits_won;				
		
		$just_completed_achievements = array();
		
		for ($i = 0; $i < 25; $i++)
		{
			$just_completed_achievements[$i] = 0;
		}
		
		//the first 16 achievements for every game are the same... 4 pays for the top 8 symbols and 5 pays for the top 8 symbols
		for ($i = 0; $i < 8; $i++)
		{
			if ($four_pay_achievement_str[$i] == '1')
			{
				if ($achievementStr[$i] == '0')
				{
					$achievementStr[$i] = '1';
					$just_completed_achievements[$i] = 1;
				}
			}
		}
		
		for ($i = 0; $i < 8; $i++)
		{
			if ($five_pay_achievement_str[$i] == '1')
			{
				if ($achievementStr[($i+8)] == '0')
				{
					$achievementStr[($i+8)] = '1';
					$just_completed_achievements[($i+8)] = 1;
				}
			}
		}
		
		//the next 4 are the same for all games		

		//500 spins
		if ($lifetime_spins == 500)
			$just_completed_achievements[16] = self::UpdateSingleAchievement($player_id, $game_id, 16, $achievementStr);

		//1500 spins
		if ($lifetime_spins == 1500)
			$just_completed_achievements[17] = self::UpdateSingleAchievement($player_id, $game_id, 17, $achievementStr);
		
		//5 winning spins in a row
		if ($current_win_streak >= 5)
			$just_completed_achievements[18] = self::UpdateSingleAchievement($player_id, $game_id, 18, $achievementStr);
		
		//$1 Million credits
		if ($total_credits_won >= 100000000) //a million pennies
			$just_completed_achievements[19] = self::UpdateSingleAchievement($player_id, $game_id, 19, $achievementStr);

		
		//the remaining 5 could be from the following list; win streak, spins played, credits won, 4 scatter, 5 scatter, Win X credits in a single bonus, Win X Bonus Rounds, Special Achievement X, Special Achievement Y
		
		switch ($game_id)
		{
		case 1: //Brazilian Beauty
			//15 bonus 1
			if (isset($_REQUEST['bonus_1_value']) && $_REQUEST['bonus_1_value'] > 0)
			{
				$bUpdatedProgress = true;
				$just_completed_achievements[20] = self::UpdateSingleAchievementWithProgress($player_id, $game_id, 20, 1, 15, $achievementStr, $progress1);
			}

			//30  bonus 1
			if (isset($_REQUEST['bonus_1_value']) && $_REQUEST['bonus_1_value'] > 0)
			{
				$bUpdatedProgress = true;
				$just_completed_achievements[21] = self::UpdateSingleAchievementWithProgress($player_id, $game_id, 21, 1, 30, $achievementStr, $progress2);
			}
			
			//$10,000 in single bonus
			if (isset($_REQUEST['bonus_1_value']) && $_REQUEST['bonus_1_value'] >= 1000000) //$10,000 in pennies
				$just_completed_achievements[22] = self::UpdateSingleAchievement($player_id, $game_id, 22, $achievementStr);
			
			//Get all 5 bonus triggers
			if (isset($_REQUEST['special_1_value']) && $_REQUEST['special_1_value'] >= 5)
				$just_completed_achievements[23] = self::UpdateSingleAchievement($player_id, $game_id, 23, $achievementStr);

			//collect 20 or more beauties
			if (isset($_REQUEST['special_2_value']) && $_REQUEST['special_2_value'] >= 20)
				$just_completed_achievements[24] = self::UpdateSingleAchievement($player_id, $game_id, 24, $achievementStr);
				
			break;			
		}
	
		
		$globalAchievementCountIncrease = 0;
		$achievementBonusSpins = 0;
		
		$newly_won_achievements = '0000000000000000000000000';
		
		for ($i = 0; $i < 25; $i++)
		{
			if ($just_completed_achievements[$i] == 1)
			{
				$newly_won_achievements[$i] = '1';
				$globalAchievementCountIncrease++;
			}
		}
		
		if ($bUpdatedProgress == true || $newly_won_achievements != '0000000000000000000000000')
		{
			$cbGameStats->achievement_str = $achievementStr;
			$cbGameStats->progress_1 = $progress1;
			$cbGameStats->progress_2 = $progress2;
			$cbGameStats->progress_3 = $progress3;
			$cbGameStats->progress_4 = $progress4;
			$cbGameStats->progress_5 = $progress5;

			$cbGameStats->SaveStats($cb);
		}
		
		if ($globalAchievementCountIncrease > 0)
		{
			$cbPlayer->global_achievement_count += $globalAchievementCountIncrease;
		}
		
		return $newly_won_achievements;
	}

	public function Load($gid, $aid, $cb)
    {
    	
        $jsonStr = $this->getDocument("achievement_info_" . $gid . '_' . $aid, $cb);
        
        if ($jsonStr == COUCHBASE_KEY_ENOENT || $jsonStr == null || strlen($jsonStr) == 0)
        {
            error_log("Failed to load achievement info for game " . $gid . "and achievement " . $aid);
        }
        
        else
        {
            $this->data = json_decode($jsonStr);
        
            if ($this->data)
            {
                $this->bLoaded = true;
                return $this->data;
            }
            
            else
            {
            }
        }
    }
    
    public function Save($cb)
    {
        if ($this->bLoaded)
        {
            $this->setDocument("achievement_info_" . $this->data->game_id . '_' . $this->data->achievement_id, json_encode($this->data), $cb);
        	
        }
    } 
	
	static function UpdateSingleAchievement($player_id, $game_id, $achievement_id, &$achievementStr)
	{
		if ($achievementStr[$achievement_id] == '0')
		{
			$achievementStr[$achievement_id] = '1';
			return 1;
		}
		
		else
		{
			return 0;
		}
	}

	public function CreateAchievementInfo($gid, $aid)
    {
        $this->data->game_id = $gid;
        $this->data->achievement_id = $aid;
        $this->bLoaded = true;
    }
	
	static function UpdateSingleAchievementWithProgress($player_id, $game_id, $achievement_id, $increment, $goal, &$achievementStr, &$progress)
	{
		if ($achievementStr[$achievement_id] == '0')
		{
			$progress += $increment;
			
			if ($progress >= $goal)
			{
				$achievementStr[$achievement_id] = '1';
				return 1;
			}
			else
			{
				return 0;
			}
		}
		else
		{
			return 0;
		}
	}	
}
?>