<?php
require_once(APPPATH.'classes/Couchbase.php');

class Player extends CouchbaseHelper
{
	protected $source;
	protected $bLoaded = false;
    protected $userProfile;
	protected $bErrored = false;
    protected $data;
    protected $allowed_fields;

    public function __construct($uid = null, $source = 'facebook', $userProfile=null)
    {
        $this->allowed_fields = $this->playerFields();
       
        $this->data = new stdClass();

        if(isset($userProfile))
        {
            //we have this object, let's set some default for this person (that way if we're saving we have them set.)
           //Log::info("userProfile was set");

           if(isset($userProfile->gender))
           {
             switch($userProfile->gender)
             {
                case 'male':
                    $gender = 1;
                break;
                case 'female':
                    $gender = 0;
                break;
                default:
                    $gender = 2; //unset
                break;
             }
              $this->data->gender = $gender;
           } 
           else 
           {
            $this->data->gender = 2; //make sure something is set.
           }
            if(isset($userProfile->facebook_id))
            {
                $this->data->facebook_id = $userProfile->facebook_id;
            }
            if(isset($userProfile->first_name))
            {
                $this->data->first_name = $userProfile->first_name;
            }

            if(isset($userProfile->last_name))
            {
                $this->data->last_name = $userProfile->last_name;
            }

            if(isset($userProfile->email))
            {
                $this->data->email = $userProfile->email;
            }
            
            if(isset($userProfile->birthday))
            {
                $this->data->birth_date = $userProfile->birthday;
            }
            
            //Log::info("here are some data values that were set to this->data: " . $this->data->first_name ." " . $this->data->last_name);
        }

        if($uid != null)
        {
            //let's load up this player.
            $this->data->player_id = $this->getPlayerID($uid, $source);
            
            //we have the player now let's load this player into the data object.
            $this->data = $this->loadPlayer($this->data->player_id);

        } else {
            //Since it looks like
           // exit('Error loading player');
        }
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
            //Log::error("Couchbase Error: Trying to set unsupported field " . $name . " UA: " . $_SERVER['HTTP_USER_AGENT'] . " XFWD: " . $_SERVER['HTTP_X_FORWARDED_FOR']);
        }
    }

    public function getPlayerData()
    {
        if($this->bLoaded)
        {
            return $this->data;
        }
        else
        {
            return "Error: player not loaded";
        }
    }



    public function savePlayer($cb)
    {
        //Log::info("savePlayer Called");
        //Log::info("bLoaded's value is: " . $this->bLoaded);
        if ($this->bLoaded)
        {
            if (!isset($this->data->player_id))
            {
                //Log::error("Couchbase Error: trying to do a CB set for a player with no ID.");
                //" . "UA: " . $_SERVER['HTTP_USER_AGENT'] . " XFWD: " . $_SERVER['HTTP_X_FORWARDED_FOR']
               // //Log::error(json_encode($this->data));
            }

            else
            {
                log_message('debug', 'Player->data = ' . $this->data->total_rubies);
                //Log::info("savePlayer:: Made it to setDocument with data: player_id: " . $this->data->player_id ." AND json data: " . json_encode($this->data));
                $this->setDocument("player_" . $this->data->player_id, json_encode($this->data), $cb);
            }
        }
    }

    static function playerLikesApp($playerID)
    {
        //we're going to just return true or false if the player has liked the app :)~
        $facebook = App::make('facebook');
        $user = $facebook->getUser();
        $likesApp = false;
       
        $params = array(
            'method' => 'fql.query',
            'query' => 'SELECT uid FROM page_fan WHERE page_id = ' . Config::get('facebook.page_id') . ' AND uid = ' . $user
        );

        $userLikes = $facebook->api($params);

        if($userLikes)
        {
            $likesApp = true;

            $cb = CouchbaseHelper::connect();

            $p = new PlayerHelper();
            $p = $p->loadPlayer($playerID);

            $p->liked_game = 1;
            $p->savePlayer($cb);

            //log_message('debug', 'Updated player couchbase liked_game = 1 for player ' . $playerID);

        } 
        else
        {
            $likesApp = false;
        }

        return $likesApp;
    }

    static function isNewPlayer($createDate = null, $lastSpinTime=null)
    {
        //compare these 2.
        //we'll break it down to seconds and not days.. since they might have just started playing 30 minutes ago.
        if($createDate == null || $lastSpinTime == null)
        {
            return false;
            //log_message('debug', 'A value was not passed, createDate: ' . $createDate . ' lastSpinTime: ' . $lastSpinTime);
        }
        else
        {
            $secondsBetween = DateHelper::GetSecondsBetween($createDate, $lastSpinTime);

            //log_message('debug', 'calling isNewPlayer, the seconds between is' . $secondsBetween);

            if($secondsBetween)
            {
                return true;
            }
            else
            {
                return false;
            }
        }

    }

    static function isActivePlayer($lastSpinTime = null)
    {
        $days = 14;

        if($lastSpinTime == null)
        {
            return false;
        }
        else
        {
            //check days between 2 dates.
            $days = DateHelper::GetDaysSince($lastSpinTime);
            if($days > 14)
            {
                return false;
            }
            else
            {
                return true;
            }
        }
    }

    static function getPlayerIDFromFacebookID($facebookID = null)
    {
        if($facebookID == null)
        {
            return false;
        }
        else
        {
            //log_message('debug', 'retrieving player id from faebook id: ' . $facebookID);
            $row = DB::table('player')->select('player_id')->where('facebook_id','=',$facebookID)->first();
            if($row)
            {
                return $row->player_id;
            }
            else
            {
                return false;
            }
            
        }

        

    }

	public function loadPLayer($playerID = null)
	{
        //Log::info("loadPlayer Called");
       //we need to get this player id
        $cb = CouchbaseHelper::connect();
		$jsonStr = $this->getDocument("player_" . $playerID, $cb);

        if ($jsonStr == COUCHBASE_KEY_ENOENT)
        {
            //no record was found
            $this->bLoaded = false;
            $this->bErrored = false;

            //no player_id created.. if we have playerID let's create it.
            $this->savePlayer($cb);

            //Log::error("CB Error:  No record found for player " . $playerID);
        }

        else if ($jsonStr == null || strlen($jsonStr) == 0)
        {
            //no record was found
            $this->bLoaded = false;
            $this->bErrored = true;
            //Log::error("CB Error:  internal couchbase problem loading player  " . $playerID);            
        }

        else
        {
            $this->data = json_decode($jsonStr);

            $this->InitUnsetFields();
            
            if ($this->data)
            {
                $this->bLoaded = true;
            }
            
            else
            {
                //Don't create a player because the player should only be created explicitly
                //during the login process.  It shouldn't be possible for a "couchbased" player
                //to get to this point without a couchbase record.
                //Log::error("CB Error:  JSON decode failed for player " . $playerID);
                $this->bLoaded = false;
            }

            return $this->getPlayerData();
        }
	}



	public function getCredits($playerID)
	{

	}

	public function adjustCredits($playerID)
	{

	}

	public function updateSpinStats($playerID)
	{

	}

	public function addGame($playerID)
	{

	}

	public function checkABTest($playerID)
	{

	}

	public function adjustBonusStartTime($playerID)
	{

	}

	private function playerFields()
	{
		$allowed_fields = array(
            "player_id",
            "facebook_id",
            "first_name",
            "last_name",
            "credits",
            "experience",
            "level",
            "global_achievement_count",
            "gender",
            "birth_date",
            "create_date",
            "last_spins_claimed_time",
            "last_coins_claimed_time_mobile",
            "gifts_given",
            "invites_sent",
            "invites_accepted",
            "app_source",
            "was_invited",
            "last_spin_time",
            "email",
            "total_lifetime_spins",
            "sound_vol",
            "music_vol",
            "ambient_vol",
            "current_leaderboard_id",
            "total_rubies",
            "last_ruby_claim_time",
            "rubyies_claimed",
            "leaderboard_score",
            "has_leaderboard_score_changed",
            "mega_bonus_level",
            "mega_bonus_level_mobile",
            "mega_bonus_multiplier",
            "mega_bonus_multiplier_mobile",
            "total_purchase_count",
            "total_fb_credits_spent",
            "last_purchase_date",
            "total_ios_purchase_count",
            "total_ios_spent",
            "last_ios_purchase_date",
            "current_timed_offer_id",
            "timed_offer_expiration_date",
            "num_lifetime_sessions",
            "avg_starting_bankroll",
            "mystery_jackpot_pending_amount",
            "mystery_jackpot_collect_time",
            "xp_multiplier_expiration_date",
            "last_loyalty_login_date",
            "loyalty_bonus_multiplier",
            "avg_starting_bankroll_weight",
            "avg_bet_amount",
            "avg_bet_amount_weight",
            "promo_unlock_xp",
            "promo_unlock_expiration_date",
            "promo_unlock_game_id",
            "promo_unlock_xp_target",
            "promo_unlock_giver_fb_id",
            "gift_vault_pending_amount",
            "gift_vault_collect_date",
            "liked_game",
            "consecutive_days_played",
            "last_consecutive_bonus_collect_date",
            "ad_source"
        );

		return $allowed_fields;
	}

    public function InitUnsetFields()
    {
        if (!isset($this->data->facebook_id))
            $this->data->facebook_id = '';
        if (!isset($this->data->first_name))
            $this->data->first_name = '';
        if (!isset($this->data->last_name))
            $this->data->last_name = '';
        if (!isset($this->data->credits))
            $this->data->credits = 50000;
        if (!isset($this->data->experience))
            $this->data->experience = 0;
        if (!isset($this->data->level))
            $this->data->level = 1;
        if (!isset($this->data->global_achievement_count))
            $this->data->global_achievement_count = 0;
        if (!isset($this->data->gender))
            $this->data->gender = '';
        if (!isset($this->data->birth_date))
            $this->data->birth_date = '';
        if (!isset($this->data->create_date))
            $this->data->create_date = '';
        if (!isset($this->data->last_spins_claimed_time) || (strlen($this->data->last_spins_claimed_time) == 0))
            $this->data->last_spins_claimed_time = '2012-08-01 00:00:00';
        if (!isset($this->data->last_coins_claimed_time_mobile) || (strlen($this->data->last_coins_claimed_time_mobile) == 0))
            $this->data->last_coins_claimed_time_mobile = '2012-08-01 00:00:00';
        if (!isset($this->data->gifts_given))
            $this->data->gifts_given = 0;
        if (!isset($this->data->invites_sent))
            $this->data->invites_sent = 0;
        if (!isset($this->data->invites_accepted))
            $this->data->invites_accepted = 0;
        if (!isset($this->data->app_source))
            $this->data->app_source = 0;
        if (!isset($this->data->was_invited))
            $this->data->was_invited = 0;
        if (!isset($this->data->last_spin_time))
            $this->data->last_spin_time = '';
        if (!isset($this->data->email))
            $this->data->email = '';
        if (!isset($this->data->total_lifetime_spins))
            $this->data->total_lifetime_spins = 0;
        if (!isset($this->data->sound_vol))
            $this->data->sound_vol = 100;
        if (!isset($this->data->music_vol))
            $this->data->music_vol = 100;
        if (!isset($this->data->ambient_vol))
            $this->data->ambient_vol = 100;
        if (!isset($this->data->current_leaderboard_id))
            $this->data->current_leaderboard_id = 0;
        if (!isset($this->data->leaderboard_score))
            $this->data->leaderboard_score = 0;
        if (!isset($this->data->has_leaderboard_score_changed))
            $this->data->has_leaderboard_score_changed = 0;
        if (!isset($this->data->mega_bonus_level))
            $this->data->mega_bonus_level = 1;
        if (!isset($this->data->mega_bonus_level_mobile))
            $this->data->mega_bonus_level_mobile = 1;
        if (!isset($this->data->mega_bonus_multiplier))
            $this->data->mega_bonus_multiplier = 3;
        if (!isset($this->data->mega_bonus_multiplier_mobile))
            $this->data->mega_bonus_multiplier_mobile = 3;
        if (!isset($this->data->total_purchase_count))
            $this->data->total_purchase_count = 0;
        if (!isset($this->data->total_fb_credits_spent))
            $this->data->total_fb_credits_spent = 0;
        if (!isset($this->data->last_purchase_date))
            $this->data->last_purchase_date = '';
        if (!isset($this->data->total_ios_purchase_count))
            $this->data->total_ios_purchase_count = 0;
        if (!isset($this->data->total_ios_spent))
            $this->data->total_ios_spent = 0;
        if (!isset($this->data->last_ios_purchase_date))
            $this->data->last_ios_purchase_date = '';
        if (!isset($this->data->current_timed_offer_id))
            $this->data->current_timed_offer_id = 0;
        if (!isset($this->data->timed_offer_expiration_date))
            $this->data->timed_offer_expiration_date = '';
        if (!isset($this->data->num_lifetime_sessions))
            $this->data->num_lifetime_sessions = 0;
        if (!isset($this->data->avg_starting_bankroll))
            $this->data->avg_starting_bankroll = 0;
        if (!isset($this->data->mystery_jackpot_pending_amount))
            $this->data->mystery_jackpot_pending_amount = 0;
        if (!isset($this->data->mystery_jackpot_collect_time))
            $this->data->mystery_jackpot_collect_time = '';
        if (!isset($this->data->xp_multiplier_expiration_date))
            $this->data->xp_multiplier_expiration_date = '';
        if (!isset($this->data->last_loyalty_login_date))
            $this->data->last_loyalty_login_date = '';
        if (!isset($this->data->loyalty_bonus_multiplier))
            $this->data->loyalty_bonus_multiplier = '';

        if (!isset($this->data->avg_starting_bankroll_weight))
            $this->data->avg_starting_bankroll_weight = 0;
        if (!isset($this->data->avg_bet_amount))
            $this->data->avg_bet_amount = 0;
        if (!isset($this->data->avg_bet_amount_weight))
            $this->data->avg_bet_amount_weight = 0;

        //Promo Unlock fields
        if (!isset($this->data->promo_unlock_xp))
            $this->data->promo_unlock_xp = 0;
        if (!isset($this->data->promo_unlock_expiration_date))
            $this->data->promo_unlock_expiration_date = "";
        if (!isset($this->data->promo_unlock_game_id))
            $this->data->promo_unlock_game_id = 0;
        if (!isset($this->data->promo_unlock_xp_target))
            $this->data->promo_unlock_xp_target = 0;
        if (!isset($this->data->promo_unlock_giver_fb_id))
            $this->data->promo_unlock_giver_fb_id = "";
        

        //Added Ruby Handler
        if(!isset($this->data->total_rubies))
            $this->data->total_rubies = 0;
        //Ruby last claim time - This tracks the last time they spent there rubies to buy a FIFO game.
        if (!isset($this->data->last_ruby_claim_time) || (strlen($this->data->last_ruby_claim_time) == 0))
            $this->data->last_ruby_claim_time = date('Y-m-d h:i:a');
        //Count the number fo times there rubies has been claimed.
        if(!isset($this->data->rubies_claimed))
            $this->data->rubyies_claimed = 0;

        //Gifting 3.0 (vault) fields
        if (!isset($this->data->gift_vault_pending_amount))
            $this->data->gift_vault_pending_amount = 0;
        if (!isset($this->data->gift_vault_collect_date))
        {
            $tomorrow = new DateTime();
            $interval = new DateInterval("PT24H");
            $tomorrow->add($interval);

            //$this->data->gift_vault_collect_date = PhantomDateUtils::ToCouchbaseDateString($tomorrow);
        }
        if (!isset($this->data->liked_game))
            $this->data->liked_game = 0;

        if (!isset($this->data->consecutive_days_played))
            $this->data->consecutive_days_played = 0;
        if (!isset($this->data->last_consecutive_bonus_collect_date))
            $this->data->last_consecutive_bonus_collect_date = "";
        if (!isset($this->data->ad_source))
            $this->data->ad_source = "";

        if (!isset($this->data->owned_games))
            $this->data->owned_games = array();
    }

     public function IsLoaded()
    {
        return $this->bLoaded;
    }

    public function IsErrored()
    {
        return $this->bErrored;
    }

    public function OwnsGame($gid)
    {
        if ($this->bLoaded)
        {
            if ($this->data->owned_games)
            {
                return in_array((int)$gid, $this->data->owned_games);
            }
        }
    }

    public function AddOwnedGame($gid)
    {
        if ($this->bLoaded)
        {
            if (isset($this->data->owned_games))
            {
                if (!in_array((int)$gid, $this->data->owned_games))
                {
                    $this->data->owned_games[] = $gid;
                    return true;
                }
            }
            else
            {
                return false;
            }
        }        
    }


    /**
    * PRIVATE FUNCTIONS
    **/

    private function getPlayerID($uid=null, $source)
    {
        //grab the playerID for given request (facebook,ios,android,kindle)
        //check if player exists, if not create the playe
        //Log::info("getPlayerID Called with unique id: " . $uid . " and source: " . $source);
        if($uid == null)
        {
            return false;
        }
        switch($source)
        {
            case 'facebook':
                //see if player exists
                $result = DB::table('player')->select('player_id')->where('facebook_id', $uid)->first();

                if($result)
                {
                    //Log::info("GetPlayerID found a result for facebook_id = " . $uid);
                    return $result->player_id;
                }
                else
                {
                    //No player existed, let's create one
                    //Log::info("GetPlayerID - no player found in database, so we're going to call createPlayer");
                    return $this->createPlayer($uid, 'facebook');
                }

            break;
        }
        
    }

    private function createPlayer($uid = null, $source = 'facebook')
    {
        //See if this player already exists, if so return player_id
        //Log::info("Create player called wth unique id: " . $uid ." FROM source " . $source);
        switch($source)
        {
            case 'facebook':

                //log_message('debug', 'Attempting to create a couchbase player: ' . $uid . ' from source ' . $source);
                $id = DB::table('player')->insertGetId(array('facebook_id' => $uid, 'first_name' => $this->data->first_name, 'last_name' => $this->data->last_name, 'create_date' => date('Y-m-d h:i:s') ));
                $playerID = $id;
                $this->createCouchbasePlayer($id, 'facebook');

            break;
        }

        return $playerID;

    }

    private function createCouchbasePlayer($playerID)
    {
        //Log::info("Creating couchbase player with id: " . $playerID);
        if ($playerID <= 0)
        {
            //Log::error("Couchbase Error: Trying to create a player with an invalid ID: " . $playerID . " UA: " . $_SERVER['HTTP_USER_AGENT'] . " XFWD: " . $_SERVER['HTTP_X_FORWARDED_FOR']);
            
        }
        else
        {
            //log_message('debug', 'createCouchbasePlayer() :: Creating player with id: ' . $playerID);
           // self::data->player_id = $playerID;
           $cb = CouchbaseHelper::connect();
           $this->data->player_id = $playerID;
           $this->InitUnsetFields();
           $this->bLoaded = true;
           $this->savePlayer($cb);
        }

        

    }

   
}