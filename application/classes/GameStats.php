<?php
require_once(APPPATH.'classes/Couchbase.php');

class GameStats extends CouchbaseHelper
{
	protected $data;
    protected $bLoaded = false;
    protected $allowed_fields = array();
    
    public function __construct()
    {
        $this->allowed_fields = array(
            "player_id",
            "game_id",
            "lifetime_spins",
            "lifetime_credits",
            "lifetime_biggest_win",
            "time_played_seconds",
            "current_win_streak",
            "current_loss_streak",
            "achievement_str",
            "progress_1",
            "progress_2",
            "progress_3",
            "progress_4",
            "progress_5",
            "total_credits_won"
        );
    }
    
    //magic method to deal with the underlying JSON object
    public function __get($name)
    {
        if (isset($this->data->$name))
        {
            return $this->data->$name;
        }
        
        return null;
    }
    
    public function __set($name, $value)
    {
        if (in_array($name, $this->allowed_fields))
        {
            $this->data->$name = $value;
        }
        else
        {
            error_log("Error trying to set unsupported field " . $name);
        }
    }
    
    public function CreateStatsRecord($pid, $gid)
    {
        if(!is_object($this->data))
            $this->data = new stdClass();
        $this->data->player_id = $pid;
        $this->data->game_id = $gid;
        $this->ResetStatsData();
        $this->bLoaded = true;
    }

    public function ResetStatsData()
    {
        $this->data->lifetime_spins = 0;
        $this->data->lifetime_credits = 0;
        $this->data->lifetime_biggest_win = 0;
        $this->data->time_played_seconds = 0;
        $this->data->current_win_streak = 0;
        $this->data->current_loss_streak = 0;
        $this->data->achievement_str = '0000000000000000000000000';
        $this->data->progress_1 = 0;
        $this->data->progress_2 = 0;
        $this->data->progress_3 = 0;
        $this->data->progress_4 = 0;
        $this->data->progress_5 = 0;
        $this->data->total_credits_won = 0;        
    }
    
    public function LoadStats($pid, $gid, $cb)
    {  
        $jsonStr = $this->getDocument("game_stats_" . $pid . '_' . $gid, $cb);
        
        if ($jsonStr == COUCHBASE_KEY_ENOENT || $jsonStr == null || strlen($jsonStr) == 0)
        {
            //no stats record... just create one
            $this->CreateStatsRecord($pid, $gid);
            $this->bLoaded = true;
        }
        
        else
        {
            $this->data = json_decode($jsonStr);
            
            if ($this->data)
            {
                $this->bLoaded = true;
            }
            
            else
            {
                $this->CreateStatsRecord($pid, $gid);
                $this->bLoaded = true;
            }
        }
    }
    
    public function SaveStats($cb)
    {
        if ($this->bLoaded)
        {
            log_message("debug", "Saving Couchbase Game Status for game_stats_" . $this->data->player_id . '_' . $this->data->game_id);
            $this->setDocument("game_stats_" . $this->data->player_id . '_' . $this->data->game_id, json_encode($this->data), $cb);
        }
        else
        {
            log_message('debug','Tried to set game stats but bLoaded was false');
        }
    } 

    public function IsLoaded()
    {
        return $this->bLoaded;
    }  
}