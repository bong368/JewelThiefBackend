<?php
class GameController extends PhantomController
{

	public function checkFacebookRequests($playerID)
	{
		
	}

	public function init()
	{
		//init game here.
		//refactor code from JPC index.php

		$db = $this->get_dbInstance();

		Session::put('trackReferal', false);
		$data = array();

		//create activement docs :D
		$query = 'SELECT a.achievement_title, achievement_desc, g.achievement_game_folder_name FROM achievement_info a LEFT JOIN game g ON g.game_id = a.game_id';
		$results = mysqli_query($db, $query);

		while($ach = mysqli_fetch_array($results))
		{

		}

		if( isset( $_SERVER['HTTP_REFERER']) && !isset( $_REQUEST['code']) )
		{
			$_SESSION['trackReferal'] = true;
		}

		$data['appSource'] 		= isset($_REQUEST['appSource']) ? $_REQUEST['appSource'] : '1';
		$data['fbPostID'] 		= isset($_REQUEST['fb_post_id']) ? $_REQUEST['fb_post_id'] : '';
		$data['linkPID'] 		= isset($_REQUEST['linkPID']) ? $_REQUEST['linkPID'] : '';

		if( isset($_REQUEST['error']) )
		{
			$data['fbid'] = Config::get('facebook.appid');
			//return View::make('landingpages.allowApp', $data);
			exit();
		}

		if( isset($_GET['request_ids']))
		{
			$_SESSION['trackReferal'] = false;
			$_SESSION['showTrackingTag'] = null;
			$_SESSION['retargetTrackingRequest'] = null;
			$_SESSION['unqiueTrackingTag'] = null;
			$_SESSION['bWasInvited'] = true;

			if( !is_array($_REQUEST['request_ids']))
			{
				try
				{
					//extract parameters that was stored in the data fields
					//kt_u, kt_st1, kt_st2, kt_st3

					//We also store the unique tracking tag parameter to the session

					$requestIds  	= explode(',', $_GET['request_ids']);
					$data['requestID'] 		= $requestIds[sizeof($requestIds)-1];

					//Call Kontagent
					//Also call Facebook Graph to get id's.
					//See index.php on line 81
				}
				catch (FacebookApiException $e) {}
			}
		}
		else if ( isset($_GET['kt_type']))
		{
			Session::put('trackReferal', false);
			Session::put('shortTrackingTag', null);
		}
		else if( isset($_GET['fb_source']))
		{
			Session::put('shortTrackingTag', $this->genShortUniqueTrackingTag());
			Session::put('retargetTrackingRequest', null);
			Session::put('uniqueTrackingTag', null);

			if ( $_GET['fb_source'] != 'notification' || $_GET['notif_t'] != 'app_notification')
			{
				//Kontagent Request.
			}
		}

		$fb_app_url = Config::get('facebook.app_url');
		$appSource = '';
		$fbPostID = '';
		$linkPID = '';

		if($fbPostID != '')
		{
			$redirect_uri .= '&fb_post_id='.$fbPostID;
		}
		if($linkPID != '')
		{
			$redirect_url .= '&linkPID='.$linkPID;
		}

		if(!isset($_GET['code']))
		{
			Session::put('state', md5(uniqid(rand(), true)));
			$dialog = '';
		}
		else
		{

		}
		//Instansiate Facebook

		if (isset($_GET['code'])){
        	header("location: " . Config::get('facebook.app_url'));
        	exit;
    	}
    //~~
    
    //
    if (isset($_GET['request_ids'])){
        //user comes from invitation
        //track them if you need
    }
    
    $user = null; //facebook user uid
    $facebook = App::make('facebook');

    $loginUrl = $facebook->getLoginUrl(
    	array(
                'scope' => 'email,publish_stream,user_birthday,user_location,user_work_history,user_about_me,user_hometown'
            )
    );

   

    $user = $facebook->getUser();

    if ($user) {
      try {
        // Proceed knowing you have a logged in user who's authenticated.
        $user_profile = $facebook->api('/me');
        $user_profile = (object)$user_profile;
      } catch (FacebookApiException $e) {
        //you should use error_log($e); instead of printing the info on browser
        //Log::error($e); // d is a debug function defined at the end of this file
        $user = null;
      }
    }

    if (!$user) {
        echo "<script type='text/javascript'>top.location.href = '$loginUrl';</script>";
        //header('location:'.$loginUrl);
        //exit;
    }

    $source = 'facebook';//getting player id  from facebook.

	//var_dump($user_profile);
	//$accessToken = $facebook->setExtendedAccessToken();
	$accessToken = $facebook->getAccessToken();

	
	$player = new PlayerHelper($user_profile->id, $source, $user_profile);
	

	//Everything looks good.  Set some more variables to be used in the HTML content down below
	//$sql = "select swf_name, swf_version FROM base_swf_names WHERE swf_const_val = 'master'";
	//$result = mysqli_query(JackpotPartyDB::$connection, $sql);
	$row = DB::table('base_swf_names')->select('swf_name', 'swf_version')->where('swf_const_val','=','master')->first();

	$masterSwfName = '';
	$inTestGroup = false;
	$buildVersion = 0;
	
	if($row)
	{
		$buildVersion = $row->swf_version;

		if ((int)$row->swf_version > 0)
			//$masterSwfName = $row[0] . '-' . $row[1] . '.swf';
			$masterSwfName = $row->swf_name . '-' . $row->swf_version . '.swf';
		else
			//$masterSwfName = $row[0] . '.swf';
			$masterSwfName = $row->swf_name . '.swf';
	}
	//$sql = 'SELECT test_group_id FROM ab_test_groups WHERE ' . $playerID . ' >= pid_min AND ' . $playerID . ' <= pid_max AND test_id = 999';
	//$result = mysqli_query(JackpotPartyDB::$connection, $sql);
	$row = DB::table('ab_test_groups')->select('test_group_id')->whereRaw( $player->player_id . ' >= ' . 'pid_min AND ' . $player->player_id . ' <= pid_max AND test_id = 999' )->first();
	if ($row)
	{
		$inTestGroup = true;
	}

	if ($inTestGroup)
	{
		
		$row = DB::table('test_swf_names')->select('swf_name', 'swf_version')->where('swf_const_val','=','master')->where('test_id','=',999)->first();

		$masterSwfName = '';
		$inTestGroup = false;

		if ($row)
		{
			if ($row->swf_version > 0)
				$masterSwfName = $row->test_swf_name . '-' . $row->swf_version . '.swf';
			else
				$masterSwfName = $row->test_swf_name . '.swf';
		}
	}

	//grab all ab tests this player is in, and display on landing page.
	$abResults = DB::table('ab_test_groups')->whereRaw( $player->player_id . ' >= pid_min  AND ' . $player->player_id . ' <= pid_max AND test_id = 999 OR ( MOD(' . $player->player_id .', mod_value) = modulus )')->get();
	//$abResults = AB::CheckABTests(10000637, $player->level);
	//$playerInfo = $player->loadPlayer();
	//$result = DB::select('SELECT test_group_id FROM ab_test_groups WHERE ? >= pid_min AND ? <= pid_max AND test_id = ?', array($playerID, $playerID, 999));
	
	if($user_profile->locale)
	{
		$locale = $user_profile->locale;
	}
	else
	{
		$locale = "en_US";
	}

	$isHttps = (isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) ? true : false;

	$data['og_url_prefix'] = 'prefix';
	$data['gender'] = $player->gender;
	$data['locale'] = $locale;
	$data['og_namespace'] = '';
	$data['master_swf_name'] = $masterSwfName;
	$data['cdnHostname'] = Config::get('app.cdnHost');
	$data['buildVersion'] = $buildVersion;
	$data['facebookID'] =  $user_profile->id;
	$data['country'] = "US";
	$data['accessToken'] = $accessToken;
	$data['playerID'] = $player->player_id;
	$data['birthday'] = $player->birth_date;
	$data['hostname'] = Config::get('app.url');
	$data['fbAppId'] = $facebook->getAppId();
	$data['appServerHostname'] = Config::get('app.url');
	$data['spinServerHostname']  = $data['appServerHostname'];
	$data['isHttps'] = $isHttps;
	$data['inTestGroup'] = 'false';
	$data['appUrl'] = Config::get('facebook.app_url');
	$data['playerABTests'] = (array)$abResults;

	return View::make('landingpages.main', $data);
  
	}

	public function loadgame($data)
	{
		/**
		* header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		* header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); 
		* header("Cache-Control: no-store, no-cache, must-revalidate"); 
		* header("Cache-Control: post-check=0, pre-check=0", false);
		* header("Pragma: no-cache");
		* header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR CNT NAV COM UNI INT IND CURa ADMa DEVa TAIa"');
		*/

	}

	

	public function channel()
	{
		return View::make('landingpages.channel');
	}

	public function lobby($playerID=null)
	{
		$gameExpirationTimePeriod = 'INTERVAL ' . Config::get('app.fifo_game_unlock_expires_time');

		//well blah.
		$games = DB::table('game')->select(DB::raw('( owned_game.player_id IS NOT NULL ) AND ( TIME_TO_SEC( TIMEDIFF( DATE_ADD( owned_game.unlock_date, ' . $gameExpirationTimePeriod . '), NOW() ) ) > 0 ) as player_owned'), DB::raw('TIME_TO_SEC(TIMEDIFF(DATE_ADD( owned_game.unlock_date, ' . $gameExpirationTimePeriod . '),NOW())) as owned_slot_time_remaining'), 'owned_game.player_id', DB::raw('DATE_FORMAT(game.game_expiration, "%m/%d/%Y %H:%m:%s") game_expiration'), DB::raw('UNIX_TIMESTAMP (game.game_expiration) as game_expiration_secs'), 'game.game_id','group_id as groupID', 'game.level_restriction', 'game.name', DB::raw('CONCAT_WS("/", "' . Config::get('app.slotUrl') . '" , game.game_folder_name, game.slot_icon_url) slot_icon_url'), DB::raw(' IF(game.game_background_img IS NOT NULL, CONCAT_WS("/", "' . Config::get('app.slotUrl') . '" , game.game_folder_name, game.game_background_img)  , NULL) slot_background_url'), 'game.description', 'game.swf_url', 'game.earned_achievements_img_url', 'game.unearned_achievements_img_url', 'game.display_order', 'game_folder_name', 'game.is_fifo_game', 'game_series.game_series_name', 'game_series.game_series_desc as gameSeriesDesc', DB::raw( 'CONCAT_WS("/", "' . Config::get('app.slotIconUrl') . '", game_series.game_series_img) gameSeriesImg'))
		->leftJoin('game_series', 'game_series.game_series_id', '=', 'game.game_series_id')
		->leftJoin('owned_game', 'owned_game.game_id','=',
			DB::raw('game.game_id AND owned_game.player_id = ' . (int)$playerID))
		->groupBy('game.game_id')
		->orderBy('game.level_restriction')
		->get();

		$trackGames = array();
		$fifoGames = array();
		
		foreach($games as $game)
		{
			if($game->is_fifo_game)
			{
				$fifoGames[] = (array)$game;
			}
			else
			{
				$trackGames[] = (array)$game;
			}
		}

		$gameArray = array('fifoGames' => $fifoGames, 'trackGames' => $trackGames);

		echo json_encode($gameArray);
	}

	public function genShortUniqueTrackingTag()
	{
		return substr(md5(uniqid(rand(), true)), -8);
	}

	public function unlockGame($playerID)
	{
		//which game.
		$gameID = $_REQUEST['gameID'];

		if($playerID == null || $playerID == 0 || $gameID == '' || $gameID == null)
		{
			$data['status'] = 'error';
			$data['errorCode'] = '501';
			$data['errorMessage'] = "Invalid Player ID";
			echo json_encode($data);
			exit();
		}
		else
		{

			$hash = md5( Config::get('app.key') . $playerID);

			if($hash != $_REQUEST['key'])
			{
				$data['status'] = 'error';
				$data['errorCode'] = '100';
				$data['errorMessage'] = "Unauthorized access, your IP has been recorded";

				echo json_encode($data);
			}
			else
			{
				//Log::info('Correct hash for unlock game');
				$p = new PlayerHelper();
				$p->LoadPlayer((int)$playerID); //we now have the players data.

				$cb = CouchbaseHelper::connect();//create a couchbase instance.

				//umm for now we'll just unlock it and make it an owned game.
				//check if this person can actually unlock
				//then we'll update there rubies_total to 0;
				
				//$maxRubies = RubyHelper::getMaxRubies($playerID, $p->level);
				$maxRubies = Ruby::getMaxRubies($playerID, $p->rubies_claimed);

				if($p->total_rubies >= $maxRubies)
				{
					//Log::info('Player has enough rubies to unlock');
					//we have enough.
					$p->total_rubies = 0; //since we are redeeming let's set his rubies to ZERO

					//we'll increase there rubies_claimed + 1
					$rubiesClaimed = (int)$p->rubies_claimed;
					$rubiesClaimed = $rubiesClaimed + 1;
					$p->rubies_claimed = $rubiesClaimed;

					//add game to owned game.
					//we'll adda timestamp to.
					$gameExpirationTimePeriod = ' INTERVAL ' . Config::get('app.fifo_game_unlock_expires_time');
					//check if game exists if not then add it.
					$gameExists = DB::table('owned_game')->where('player_id','=',$playerID)->where('game_id','=',$gameID)->first();
					Log::info('The Game Expiration Time Period is ' . $gameExpirationTimePeriod);
					if($gameExists)
					{
						Log::info('game existed');
						DB::table('owned_game')
							->where('game_id', $gameID)
							->where('player_id', $playerID)
							->update(array('unlock_date' => DB::raw('NOW()')
						));
					}
					else
					{
						Log::info('game didnt exist');
						DB::table('owned_game')
							->insert(array(
							'player_id' => $playerID,
							'game_id' => $gameID,
							'unlock_date' => DB::raw('NOW()')
						));
					}
					
					//track purchases
					DB::table('game_purchase')->insert(array(
						'player_id' => $playerID,
						'game_id' => $gameID,
						'purchase_date' => DB::raw('NOW()')
					));
					
					
					
					//update player record
					$p->savePlayer($cb);

					$row = DB::table('owned_game')->select( DB::raw('TIME_TO_SEC(TIMEDIFF(DATE_ADD( owned_game.unlock_date, ' . $gameExpirationTimePeriod . '),NOW())) as fifoGameExpires'))
								->where('player_id','=', $playerID)
								->where('game_id', '=', $gameID)
								->first();
					
					//return  status
					
					$data['status'] = 'ok';
					$data['fifoGameExpires'] = $row->fifoGameExpires;
					$data['gameID'] = $gameID;
					
					echo json_encode($data);
					
				} else {
					$data['status'] = 'error';
					$data['errorMessage'] = "Not enought rubies.";
					//Log::info("Whoops not enough rubies!");

					echo json_encode($data);
				}
			}

		}

	}
}