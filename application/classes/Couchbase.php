<?php
class CouchbaseHelper
{
	public $cb;
	protected $data;
    protected $bLoaded = false;
    protected $bErrored = false;
    
    protected $allowed_fields = array();

    public static function connect()
    {
    	//$this->load->config('cb');
    	$ci =& get_instance();
    	$cbConfig = $ci->config->item('couchbase');
    	$hosts = $cbConfig['hosts'];
    	$username = $cbConfig['username'];
    	$password = $cbConfig['password'];
    	$bucket = $cbConfig['bucket'];
      //  extract(Config::get('database.connections.couchbase'));
        return new Couchbase($hosts, $username, $password, $bucket, true);
    }

    public function CouchbaseSet($key, $data, $cb)
    {
        $retries = 5;

        while ($retries > 0)
        {
            try
            {
                $cb->set($key, $data);
                break;
            }
            catch (CouchbaseException $e)
            {
                $retries--;
                error_log("WARNING: Couchbase Exception caught... retrying " . $retries . " more times.");
            }
        }
    }

    public function CouchbaseGet($key, $cb)
    {
        $retries = 5;

        while ($retries > 0)
        {
            try
            {
                $data = $cb->get($key);

                if ($cb->getResultCode() == COUCHBASE_KEY_ENOENT)
                {
                    return COUCHBASE_KEY_ENOENT;
                }
                else
                {
                    return $data;
                }
            }
            catch (CouchbaseException $e)
            {
                $retries--;
                error_log("WARNING: Couchbase Exception caught... retrying " . $e->getMessage() .' ' . $retries . " more times.");
            }
        }

        return "";
    }

    function getDocument($doc, $cb)
    {
        $retries = 5;
        while ($retries > 0)
        {
            try
            {
                $data = $cb->get($doc);
                if ($cb->getResultCode() == COUCHBASE_KEY_ENOENT)
                {
                    return COUCHBASE_KEY_ENOENT;
                }
                else
                {
                    return $data;
                }
            }
            catch (CouchbaseException $e)
            {
                $retries--;
                error_log("WARNING: Couchbase Exception caught... retrying " . $e->getMessage(). ' ' . $retries . " more times.");
            }
        }
        return "";
    }

    function setDocument($key, $data, $cb = null)
    {
        $retries = 5;
        while ($retries > 0)
        {
            try
            {
                $cb->set($key, $data);
                break;
            }
            catch (CouchbaseException $e)
            {
                $retries--;
                error_log("WARNING: Couchbase Exception caught... retrying " . $retries . " more times.");
            }
        }
    }

    public function CouchbaseGetMulti($keyArray, $cb)
    {
        $retries = 5;
        while ($retries > 0)
        {
            try
            {
                $data = $cb->getMulti($keyArray);

                //TODO when a record isn't found, what should we do?
                return $data;
            }
            catch (CouchbaseException $e)
            {
                $retries--;
                error_log("WARNING: Couchbase Exception caught... retrying " . $retries . " more times.");
            }
        }
        return "";
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
            error_log("Couchbase Error: Trying to set unsupported field " . $name . " UA: " . $_SERVER['HTTP_USER_AGENT'] . " XFWD: " . $_SERVER['HTTP_X_FORWARDED_FOR']);
        }
    }
    
    public function SetLastSpinTime($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->last_spin_time = $dt->format("Y-m-d H:i:s");
        }
    }

    public function SetLastSpinsClaimedTime($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->last_spins_claimed_time = $dt->format("Y-m-d H:i:s");
        }
    }


    public function SetLastLoyaltyLoginDate($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->last_loyalty_login_date = $dt->format("Y-m-d H:i:s");
        }
    }

    public function SetMysteryJackpotCollectTime($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->mystery_jackpot_collect_time = $dt->format("Y-m-d H:i:s");
        }
    }

    public function SetXPMultiplierExpirationDate($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->xp_multiplier_expiration_date = $dt->format("Y-m-d H:i:s");
        }        
    }

    public function SetTimedOfferExpirationDate($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->timed_offer_expiration_date = $dt->format("Y-m-d H:i:s");
        }
    }    

    public function SetLastIOSPurchaseDate($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->last_ios_purchase_date = $dt->format("Y-m-d H:i:s");
        }
    }    

    public function SetPromoUnlockExpirationDate($dt)
    {
        if ($this->bLoaded)
        {
            $this->data->promo_unlock_expiration_date = $dt->format("Y-m-d H:i:s");
        }
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
}