<?php
require_once(APPPATH.'classes/Couchbase.php');

class Leaderboard extends CouchbaseHelper
{
	protected $data;
    protected $bLoaded = false;
    protected $allowed_fields = array();
    
    public function __construct()
    {
        $this->allowed_fields = array(
            "version"
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
    
    public function Load($cb)
    {  
         $jsonStr = $this->getDocument("leaderboard_version", $cb);
      
        if ($jsonStr == COUCHBASE_KEY_ENOENT || $jsonStr == null || strlen($jsonStr) == 0)
        {
            Log::info("GF: Failed to load leaderboard_version");
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
                $this->data->version = 0;
                $this->bLoaded = true;
            }
        }
    }
    
    public function Save($cb)
    {
        if ($this->bLoaded)
        {
            echo(json_encode($this->data));
            $this->setDocument("leaderboard_version", json_encode($this->data), $cb);
        }
    } 

    public function IsLoaded()
    {
        return $this->bLoaded;
    }  
}