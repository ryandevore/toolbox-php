<?php
    
class UULog
{
	const LEVEL_NONE	= 255;
	const LEVEL_FATAL 	= 6;
	const LEVEL_ERROR 	= 5;
	const LEVEL_WARNING = 4;
	const LEVEL_INFO	= 3;
	CONST LEVEL_DEBUG	= 2;
	CONST LEVEL_TRACE	= 1;
	CONST LEVEL_ALL		= 0;
	
	private static $currentLogLevel = self::LEVEL_ALL;
	private static $alternateLogFile = NULL;
	
	public static function setCurrentLogLevel($level)
	{
		self::$currentLogLevel = $level;
	}
	
	public static function setAltLogFile($fileName)
	{
		self::$alternateLogFile = $fileName;
	}
			
	public static function dbError($db, $message)
	{
		UULog::log(self::LEVEL_ERROR, 'mysqli_error: ' . $db->error . ', mysqli_errno: ' . $db->errno . ', message: ' . $message);
	}
	
	public static function fatal($message, $exception = NULL)
	{
		UULog::log(self::LEVEL_FATAL, $message, $exception);
	}
	
	public static function error($message, $exception = NULL)
	{
		UULog::log(self::LEVEL_ERROR, $message, $exception);
	}
	
	public static function warning($message, $exception = NULL)
	{
		UULog::log(self::LEVEL_WARNING, $message, $exception);
	}
	
	public static function info($message, $exception = NULL)
	{
		UULog::log(self::LEVEL_INFO, $message, $exception);
	}
	
	public static function debug($message, $exception = NULL)
	{
		UULog::log(self::LEVEL_DEBUG, $message, $exception);
	}
	
	public static function trace($message, $exception = NULL)
	{
		UULog::log(self::LEVEL_TRACE, $message, $exception);
	}
	
	private static function levelString($level)
	{
		switch ($level)
		{
			case self::LEVEL_TRACE:
				return "T";
				
			case self::LEVEL_DEBUG:
				return "D";
				
			case self::LEVEL_INFO:
				return "I";
				
			case self::LEVEL_WARNING:
				return "W";
				
			case self::LEVEL_ERROR:
				return "E";
				
			case self::LEVEL_FATAL:
				return "F";
		}
	}
	
	private static function log($level, $message, $exception = NULL)
	{
		if ($level >= self::$currentLogLevel)
		{
			$exStr = "";
			if ($exception != NULL)
			{
				$exStr = ', exception: ' . $exception;
			}
		
			$msg = sprintf('%s %s, message: %s%s', getmypid(), UULog::levelString($level), $message, $exStr);
			//echo "\r\n" . $msg;
			UULog::writeToLog($msg);
		}
	}
	
	public static function logServerVars()
	{
		UULog::logArray($_SERVER, '_SERVER');
	}
	
	public static function logGETVars()
	{
		UULog::logArray($_GET, '_GET');
	}
	
	public static function logPOSTVars()
	{
		UULog::logArray($_POST, '_POST');
	}
	
	public static function logFILESVars()
	{
		UULog::logArray($_FILES, '_FILES');
	}
	
	public static function logSESSIONVars()
	{
		$a = NULL;
		if (isset($_SESSION))
		{
			$a = $_SESSION;
		}

		UULog::logArray($a, '_SESSION');
	}
	
	public static function logCookieVars()
	{
		$a = NULL;
		if (isset($_COOKIE))
		{
			$a = $_COOKIE;
		}

		UULog::logArray($a, '_COOKIE');
	}
	
	public static function logArray($array, $name)
	{
		if ($array)
		{
			UULog::debug($name . ' has ' . count($array) . ' entries');
			
			$keys = array_keys($array);
			foreach ($keys as $key)
			{
				$val = $array[$key];
				if (is_string($val))
				{
					UULog::debug($key . '=' . $val);
				}
				else
				{
					UULog::debug($key . '=' . UUTools::printRToString($val));
				}
			}
		}
		else
		{
			UULog::debug($name . ' is null');
		}
	}
	
	public static function logObject($obj)
	{
		if (is_string($obj))
		{
			UULog::debug("Object Type: string -- " . $obj);
		}
		else
		{
			UULog::debug("Object Type: " . get_class($obj) . " -- " . UUTools::printRToString($obj));
		}
	}
	
	public static function logHttpHeaders()
	{
		$headers = getallheaders();
		UULog::logArray($headers, "Incoming Request Headers");
	}
	
	private static function writeToLog($msg)
	{
		if (self::$alternateLogFile)
		{
			date_default_timezone_set('UTC');
			$fp = fopen(self::$alternateLogFile, 'ab');
			if ($fp)
			{
				fwrite($fp, date('c') . ' ' . $msg . PHP_EOL);
				fclose($fp);
			}
		}
		else
		{
			error_log($msg);
		}
	}
	
	public static function writeToDebugLogFile($logFile, $msg)
	{
		date_default_timezone_set('UTC');
		$fp = fopen($logFile, 'ab');
		if ($fp)
		{
			fwrite($fp, date('c') . ' ' . $msg . PHP_EOL);
			fclose($fp);
		}
	}
}
    
?>