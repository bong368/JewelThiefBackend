<?php
class DateHelper
{
	public static function getDateString($now, $date)
	{
		if(self::isToday($now, $date))
			return 'today';
		if(self::isTomorrow($now, $date))
			return 'tomorrow';
		else
			return 'expired';
	}
	
	public static function isTomorrow($now, $date)
	{
		if($date == "")
			return false;
		else
		{
			$now = date('Y-m-d H:i:s');
			$date = date('Y-m-d H:i:s', strtotime('+1 day'));
			$tomorrow = DateTime::createFromFormat('Y-m-d H:i:s', $now);
			$date2 = DateTime::createFromFormat('Y-m-d H:i:s', $date);
			$tomorrow->add(new DateInterval('P1D'));
			return($tomorrow->format('Y-m-d') == $date2->format('Y-m-d'));
		}
	}
	
	public static function isToday($now, $date)
	{
		if($date == "")
			return false;
		else
		{
			$now = date('Y-m-d H:i:s',time());
			$date = date('Y-m-d H:i:s', strtotime('+ 1 day'));

			$today = DateTime::createFromFormat('Y-m-d H:i:s', $now);
			$date2 = DateTime::createFromFormat('Y-m-d H:i:s', $date);
			return($today->format('Y-m-d') == $date2->format('Y-m-d'));
		}
	}

	public static function getSecondsRemaining($date)
	{
		if($date == null || $date == "")
			return 0;
		else
		{	
			$seconds = strtotime($date) - time();
			return ($seconds > 0) ? $seconds : 0; 
		}	
	}

	static function ToCouchbaseDateString($dt)
	{
		return $dt->format("Y-m-d H:i:s");
	}

	static function FromCouchbaseDateString($s)
	{
		return DateTime::createFromFormat("Y-m-d H:i:s", $s);
	}

	static function GetSecondsBetween($dt1, $dt2)
	{
		if(gettype($dt1) == 'object' && gettype($dt2) == 'object')
			return $dt2->getTimestamp() - $dt1->getTimestamp();
		elseif(gettype($dt1) == 'string' && gettype($dt2) == 'string' && self::IsDateValid($dt1) && self::IsDateValid($dt2))
		{
			$dt1 = new DateTime($dt1);
			$dt2 = new DateTime($dt2);
			return $dt2->getTimestamp() - $dt1->getTimestamp();
		}
		else
			return null;
	} 

	static function GetDaysBetweenStrings($dt1str, $dt2str)
	{
		if(self::IsDateValid($dt1str) && self::IsDateValid($dt2str))
		{
			$dt1 = new DateTime($dt1str);
			$dt2 = new DateTime($dt2str);

			$interval = $dt1->diff($dt2);
			return $interval->format("%r%a");
		}
		else
			return -1;
	}

	static function GetDaysBetween($dt1, $dt2)
	{
		$interval = $dt1->diff($dt2);
		return $interval->format("%r%a");
	}

	static function IsDateValid($dt)
	{
		if(($dt != null) && ($dt != "") && (gettype($dt) == 'string') && (strlen($dt) == 19))
		{
			return true;
		}
		else
		{
			//debug_print_backtrace();
			return false;
		}
	}

	static function GetDaysSince($t)
	{
		if (gettype($t) == 'string')
			$dt = self::FromCouchbaseDateString($t);
		else
			$dt = $t;

		$nowTime = new DateTime;

		return self::GetDaysBetween($dt, $nowTime);
	}

	static function GetDaysUntil($t)
	{
		if (gettype($t) == 'string')
			$dt = self::FromCouchbaseDateString($t);
		else
			$dt = $t;

		$nowTime = new DateTime;

		return self::GetDaysBetween($nowTime, $dt);
	}

	static function GetSecondsSince($t)
	{
		if (gettype($t) == 'string')
			$dt = self::FromCouchbaseDateString($t);
		else
			$dt = $t;

		$nowTime = new DateTime;

		return self::GetSecondsBetween($dt, $nowTime);
	}

	static function GetSecondsUntil($t)
	{
		if (gettype($t) == 'string')
			$dt = self::FromCouchbaseDateString($t);
		else
			$dt = $t;

		$nowTime = new DateTime;

		return self::GetSecondsBetween($nowTime, $dt);
	}

	static function IsPastTodaysDate($t)
	{
		if($t == null || $t == "")
			return true;
		else
		{
			$today = new DateTime();
			$mydate = self::FromCouchbaseDateString($t);
			$formatStr = 'Y-m-d H:i:s';
			return ($today->format($formatStr) > $mydate->format($formatStr));
		}
	}

	function AddMinutesToExpirationDate($dateStr, $minutes)
	{
		if($minutes == "" || $minutes < 1)
		{
			$errorDate = new DateTime();
			return $errorDate->format('Y-m-d H:i:s');
		}
		
		$datePlusMinutes = "";
		$myDate = new DateTime();
		
		if($dateStr != "" && !self::IsPastTodaysDate($dateStr))
			$myDate = self::FromCouchbaseDateString($dateStr);
		
		$format = 'PT' . $minutes . 'M';
		$datePlusMinutes = $myDate->add(new DateInterval($format));
		
		return ($datePlusMinutes->format('Y-m-d H:i:s'));
	}

	static function IsCurrentTimeValid($endTime)
	{	
		$today = new DateTime();
		$end = DateTime::createFromFormat('Y-m-d H:i:s', $endTime);
		
		return ($today < $end);
	}

}