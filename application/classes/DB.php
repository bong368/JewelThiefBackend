<?php
class DB
{
	static $db_host = "127.0.0.1";
	static $db_username = "jpp42";
	static $db_password = "J@CKP0TP@RTY12345";
	static $db_database = "jackpot_party_db";

	static $connection = null;
	
	public static function connect()
	{
		self::$connection = mysqli_connect(self::$db_host, self::$db_username, self::$db_password, self::$db_database);
	}
	
	public static function releaseConnection()
	{
		mysqli_close(self::$connection);
		self::$connection = null;
	}
}