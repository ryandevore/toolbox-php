<?php
    
require_once('UUErrorCodes.php');
require_once('UULog.php');

define('UU_UPDATE_SQL_COLUMN_TO_NULL', '__UPDATE_SQL_COLUMN_TO_NULL__');
define('UU_UPDATE_SQL_COLUMN_TO_NOW_DATE', '__UPDATE_SQL_COLUMN_TO_NOW_DATE__');

class UUTools
{   
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Public Static Methods
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public static function clearResults($db)
	{
		while($db->more_results() && $db->next_result())
		{
			if($tmpResult = $db->store_result())
			{
				$tmpResult->free();
			}
		}
	}
	
	public static function failWithErrorAndCode($error, $code) 
	{
		$obj = new stdClass();
		$obj->error = $error;
		$obj->errorCode = $code;
		
		die ( json_encode($obj) );
	}
	
	public static function sqlNoResultQuery($db, $query)
	{
		$result = $db->query($query);
		if (!$result)
		{
			UULog::dbError($db, 'sqlNoResultQuery failed! query: ' . $query);
			return false;
		}
		
		return true;
	}
	
	public static function sqlSingleCellQuery($db, $query, $defaultVal = NULL)
	{
		$result = $db->query($query);
		if (!$result)
		{
			UULog::dbError($db, 'sqlSingleCellQuery failed! query: ' . $query);
			return NULL;
		}
		
		$returnVal = $defaultVal;
		
		if ($row = $result->fetch_array())
		{
			$returnVal = $row[0];
		}
		
		//$result->close();
		
		return $returnVal;
	}
	
	public static function sqlSingleCellBoolQuery($db, $query)
	{
		$result = $db->query($query);
		//echo $query . "\r\n";
		if (!$result)
		{
			UULog::dbError($db, 'sqlSingleCellBoolQuery failed! query: ' . $query);
			return false;
		}
		
		$returnVal = false;
		
		if ($row = $result->fetch_array())
		{
			$returnVal = (($row[0] > 0) ? true : false);
		}
		
		//$result->close();
		
		return $returnVal;
	}
	
	public static function sqlSingleRowQuery($db, $query, $rowTransformInstance = NULL, $rowTransformFunction = NULL)
	{
		$result = $db->query($query);
		if ($result)
		{
			if ($row = $result->fetch_assoc())
			{
				if ($rowTransformInstance && $rowTransformFunction)
				{
					$obj = $rowTransformInstance->$rowTransformFunction($row);
					return $obj;
				}
				else
				{
					return $row;
				}
			}
		}
		else
		{
			UULog::dbError($db, 'sqlSingleRowQuery failed! query: ' . $query);
		}
		
		return NULL;
	}
	
	public static function sqlMultiRowQuery($db, $query, $rowTransformInstance = NULL, $rowTransformFunction = NULL)
	{
		$list = array();
		
		$result = $db->query($query);
		if ($result)
		{
			while ($row = $result->fetch_assoc())
			{
				if ($rowTransformInstance && $rowTransformFunction)
				{
					$obj = $rowTransformInstance->$rowTransformFunction($row);
					$list[] = $obj;
				}
				else
				{
					$obj = UUTools::arrayToObject($row);
					$list[] = $obj;
				}
			}
		}
		else
		{
			UULog::dbError($db, 'sqlMultiRowQuery failed! query: ' . $query);
		}
		
		return $list;
	}
	
	public static function sqlMultiRowSingleColumnQuery($db, $query)
	{
		$list = array();
		
		$result = $db->query($query);
		if ($result)
		{
			while ($row = $result->fetch_array())
			{
				$list[] = $row[0];
			}
		}
		else
		{
			UULog::dbError($db, 'sqlMultiRowSingleColumnQuery failed! query: ' . $query);
		}
		
		return $list;
	}
	
	public static function arrayToObject($array)
	{
		$obj = new stdClass();
		
		$keys = array_keys($array);
		foreach ($keys as $key)
		{
			$obj->$key = $array[$key];
		}
		
		return $obj;
	}
	
	public static function sqlRandomSingleRowQuery($db, $query, $rowTransformInstance = NULL, $rowTransformFunction = NULL)
	{
		$result = $db->query($query);
		if ($result)
		{
			$rowCount = $result->num_rows;
			if ($rowCount > 0)
			{
				$index = mt_rand(0, $rowCount - 1);
				$result->data_seek($index);
			
				if ($row = $result->fetch_assoc())
				{
					if ($rowTransformInstance && $rowTransformFunction)
					{
						$obj = $rowTransformInstance->$rowTransformFunction($row);
						return $obj;
					}
					else
					{
						return $row;
					}
				}
			}
		}
		else
		{
			UULog::dbError($db, 'sqlRandomeSingleRowQuery failed! query: ' . $query);
		}
		
		return NULL;
	}
	
	public static function sqlRandomSingleCellQuery($db, $query)
	{
		$result = $db->query($query);
		if ($result)
		{
			$rowCount = $result->num_rows;
			if ($rowCount > 0)
			{
				$index = mt_rand(0, $rowCount - 1);
				$result->data_seek($index);
			
				if ($row = $result->fetch_array())
				{
					return $row[0];
				}
			}
		}
		else
		{
			UULog::dbError($db, 'sqlRandomSingleCellQuery failed! query: ' . $query);
		}
		
		return NULL;
	}
	
	public static function sqlInsertQuery($db, $query)
	{
		$result = UUTools::sqlNoResultQuery($db, $query);
		if ($result)
		{
			return UUTools::lastInsertId($db);
		}
		else
		{
			return NULL;
		}
	}
	
	public static function createLocation($lat, $lng, $hAcc, $timestamp)
	{
		if ($lat != NULL && $lng != NULL)
		{
			$location = new stdClass();
			$location->latitude = $lat;
			$location->longitude = $lng;
			$location->accuracy = $hAcc;
			$location->timestamp = $timestamp;
			return $location;
		}
		else
		{
			return NULL;
		}
	}
	
	public static function safeGetDateTime($dateTimeString, $timeZone = 'UTC')
	{
		$result = NULL;
		
		try
		{
			if ($dateTimeString)
			{
				$tz = new DateTimeZone($timeZone);
				$result = new DateTime($dateTimeString, $tz);
				//UULog::debug("Parsed $dateTimeString into " . self::printRToString($result));
			}
		}
		catch (Exception $ex)
		{
			$result = NULL;
		}
		
		return $result;
	}
	
	public static function isValidDate($dateTimeString, $timeZone = 'UTC')
	{
		return (self::safeGetDateTime($dateTimeString, $timeZone) != NULL);
	}
	
	/*
	public static function parseDateTimeString($dateTimeString)
	{
		$tz = new DateTimeZone('UTC');
			
		$sql = sprintf("SELECT time_zone FROM zd_user WHERE id = %d;", $userId);
		$timeZone = UUTools::sqlSingleCellQuery($this->db, $sql);
		if ($timeZone)
		{
			//UULog::debug("Time Zone string for $userId is $timeZone");
			
			try
			{
				$tz = new DateTimeZone($timeZone);
			}
			catch(Exception $e)
			{
				UULog::debug("User $userId time zone appears to be invalid, tz: $timeZone");
			}
		}
		
		$d = new DateTime('now', $tz);
		$now = $d->format('Y-m-d H:i:s');
		//UULog::debug("Current local time for user $userId is $now");
		return $now;
		
	$stringDate = $obj->Publication->PublishedDate;
			$parts = explode('/', $stringDate);
			if ($parts && count($parts) == 3)
			{
				$year = $parts[2];
				$month = $parts[0];
				$day = $parts[1];
				$result = sprintf("%s-%s-%s", $year, $month, $day);
			}
	}*/
	
	public static function isValidLocation($location)
	{
		if ($location)
		{
			$fields = get_object_vars($location);
			$fieldNames = array_keys($fields);
			if (in_array('latitude', $fieldNames) &&
				in_array('longitude', $fieldNames) &&
				in_array('accuracy', $fieldNames) &&
				in_array('timestamp', $fieldNames))
			{
				if (!is_numeric($location->latitude))
				{
					//echo "Latitude is not a number!\r\n";
					return false;
				}
				
				if (!is_numeric($location->longitude))
				{
					//echo "Longitude is not a number!\r\n";
					return false;
				}
				
				if (!is_numeric($location->accuracy))
				{
					//echo "Accuracy is not a number!\r\n";
					return false;
				}
				
				if (!UUTools::isValidDate($location->timestamp))
				{
					//echo "Timestamp is not valid!\r\n";
					return false;
				}
				
				return true;
			}
		}
		
		return false;
	}
	
	public static function lastInsertId($mysqli)
	{
		return UUTools::sqlSingleCellQuery($mysqli, "SELECT LAST_INSERT_ID();");
	}
	
	public static function getHttpMethod()
	{
		return $_SERVER['REQUEST_METHOD'];
	}
	
	public static function isPost()
	{
		return (UUTools::getHttpMethod() == 'POST');
	}
	
	public static function isGet()
	{
		return (UUTools::getHttpMethod() == 'GET');
	}
	
	public static function getIncomingPostAsJson()
	{
		if (UUTools::isPost())
		{
			$incoming_post = file_get_contents('php://input');
			if ($incoming_post === false)
			{
				UULog::error("getIncomingPostAsJson, file_get_contents returned false, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
				//UULog::logHttpHeaders();
				//UULog::logServerVars();
			}
			
			if (is_null($incoming_post))
			{
				UULog::error("getIncomingPostAsJson, file_get_contents returned null, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
				//UULog::logHttpHeaders();
				//UULog::logServerVars();
			}
			
			$json = json_decode($incoming_post);
			if (is_null($json))
			{
				UULog::error("getIncomingPostAsJson, failed to convert to JSON, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
				//UULog::debug("incoming_post: " . $incoming_post);
				//UULog::logHttpHeaders();
				//UULog::logServerVars();
				
				/*
				$incoming_post_3 = file_get_contents('php://input');
				if ($incoming_post_3 === false)
				{
					UULog::debug("after parse json, Retrying file_get_contents still returned false");
				}
				else
				{
					UULog::debug("after parse json, Retry of file_get_contents did not return false, strlen(incoming_post_3) = " . strlen($incoming_post_3));
				}*/
			}
			
			return $json;
		}
		else
		{
			UULog::error("getIncomingPostAsJson, request is not a POST, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
			return NULL;
		}
	}
	
	public static function toQueryStringArgs($args)
	{
		$queryString = "";
		
		$first = 1;
		
		// Now delete every item, but leave the array itself intact:
		foreach ($args as $key => $value) 
		{
			if ($key && $value)
			{
				if ($first)
				{
					$queryString .= "?";
					$first = 0;
				}
				else
				{
					$queryString .= "&";
				}
				
				$queryString .= $key . "=" . $value;
			}   
		}
		
		return $queryString;
	}
	
	public static function buildUpdateArgs($db, $dictionary)
	{
		$tmp = '';
		
		if ($dictionary && is_array($dictionary))
		{
			$keys = array_keys($dictionary);
			foreach ($keys as $key)
			{
				$val = $dictionary[$key];
				//UULog::debug("key=$key, val=$val");
				
				if (!is_null($key) && !is_null($val) && strlen($key) > 0 && strlen($val) > 0)
				{
					if (strlen($tmp) > 0)
					{
						$tmp .= ', ';
					}
					
					//UULog::debug("key=$key, val=$val");
					$quote = "'";
					if ($val === UU_UPDATE_SQL_COLUMN_TO_NULL)
					{
						$quote = "";
						$val = 'NULL';
					}
					else if ($val === UU_UPDATE_SQL_COLUMN_TO_NOW_DATE)
					{
						$quote = "";
						$val = 'NOW()';
					}
					
					$tmp .= sprintf("%s = %s%s%s", UUTools::clean($db, $key), $quote, UUTools::clean($db, $val), $quote);
				}
			}
		}
		
		return $tmp;
	}

	public static function buildInsertStatement($db, $table, $dictionary, $semiColon = ";")
	{
		if ($dictionary && is_array($dictionary))
		{
			$keys = array_keys($dictionary);
			$columnStr = '';
			$valueStr = '';
			
			foreach ($keys as $key)
			{
				$val = $dictionary[$key];
				
				if (!is_null($key) && !is_null($val) && strlen($key) > 0 && strlen($val) > 0)
				{
					if (strlen($columnStr) > 0)
					{
						$columnStr .= ', ';
					}
					
					$columnStr .= UUTools::clean($db, $key);
					
					$quote = "'";
					if ($val === UU_UPDATE_SQL_COLUMN_TO_NULL)
					{
						$quote = "";
						$val = 'NULL';
					}
					else if ($val === UU_UPDATE_SQL_COLUMN_TO_NOW_DATE)
					{
						$quote = "";
						$val = 'NOW()';
					}
					
					if (strlen($valueStr) > 0)
					{
						$valueStr .= ', ';
					}
					
					$valueStr .= sprintf("%s%s%s", $quote, UUTools::clean($db, $val), $quote);
				}
			}
			
			if (strlen($columnStr) > 0 && strlen($valueStr) > 0)
			{
				$sql = sprintf("INSERT INTO %s (%s) VALUES (%s)%s", $table, $columnStr, $valueStr, $semiColon);
				//var_dump($sql);
				return $sql;
			}
		}
		
		return NULL;
	}
	
	public static function cleanNullableUpdateField(&$obj, $field)
	{
		if ($obj && $field && isset($obj->$field))
		{
			//UULog::debug("$field: '" . $obj->$field . "'");
			$obj->$field = str_replace(' ', '', $obj->$field);
			$obj->$field = trim($obj->$field);
			
			if (strlen($obj->$field) <= 0)
			{
				$obj->$field = UU_UPDATE_SQL_COLUMN_TO_NULL;
			}
		}
	}
	
	public static function splitString($string, $delim)
	{
		//$expr = '/[' . $delim . ' ]+/';
		//return preg_split($expr, $string, -1, PREG_SPLIT_NO_EMPTY ); 
		return explode($delim, $string);
	}
	
	public static function doesMatchPattern($text, $pattern)
	{
		//var_dump($text);
		//var_dump($pattern);
		
		/*
		$filtered = preg_filter($pattern, "", $text);
		var_dump($filtered);
		
		if (is_null($filtered))
		{
			return true;
		}
		
		return ($filtered == "");*/
		
		if (preg_match_all($pattern, $text, $matches))
		{
			//var_dump($matches);
			
			if ($matches && count($matches) == 1)
			{
				return true;
			}
		}
		
		return false;
	}
	
	public static function concatStringArray($stringArray, $delim)
	{
		$result = "";
		
		if ($stringArray)
		{
			foreach ($stringArray as $str)
			{
				if (strlen($result) > 0)
				{
					$result .= $delim;
				}
				
				$result .= $str;
			}
		}
		
		return $result;
	}
	
	public static function removeMultipleSpaces($string)
	{
		$pattern = "/\s{2,}/";
		$results = preg_replace($pattern, " ", $string);
		return trim($results);
	}
	
	public static function stripTags($string)
	{
		if (is_string($string))
		{
			$result = strip_tags($string);
			$result = self::removeMultipleSpaces($result);
			return $result;
		}
		else
		{
			return $string;
		}
	}
	
	public static function startsWith($input, $check)
	{
		$length = strlen($check);
		return (substr($input, 0, $length) === $check);
	}
	
	public static function endsWith($input, $check)
	{
		$length = strlen($check);
		if ($length == 0) 
		{
			return true;
		}
	
		$start  = $length * -1; //negative
		return (substr($input, $start) === $check);
	}
	
	public static function findFirstIndexEndingWithCharacter($list, $character)
	{
		if ($list && $character && is_array($list))
		{
			$count = count($list);
			for ($i = 0; $i < $count; $i++)
			{
				if (UUTools::endsWith($list[$i], $character))
				{
					return (int)$i;
				}
			}
		}
		
		return NULL;
	}

	
	public static function hasHtml($str)
	{
		if ($str)
		{
			if(strlen($str) != strlen(strip_tags($str)))
			{
				return true;
			}
		}
		
		return false;
	}
	
	public static function curl($url, $allowRedirect = true, $trustAllSsl = false, $requestHeaders = false)
	{
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL,$url);
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		
		if ($allowRedirect)
		{
			curl_setopt($curl_handle,CURLOPT_FOLLOWLOCATION,1);
		}
		
		if ($trustAllSsl)
		{
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);
		}
		
		if ($requestHeaders)
		{
			curl_setopt($curl_handle, CURLOPT_HEADER, true);
		}
		
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'UUTools/1.0');
		$query = curl_exec($curl_handle);

		// Check if any error occured
		if(curl_errno($curl_handle))
		{
			$info = curl_getinfo($curl_handle);
			UULog::error("got an error from curl, url=". $info['url'] . ", curl_getinfo: " . UUTools::varDumpToString($info));
		}

		curl_close($curl_handle);
		
		$response = UUTools::parseCurlResponse($query, $requestHeaders);
		return $response;
	}
	
	private static function parseCurlResponse($response, $expectHeaders = false)
	{
		if ($expectHeaders)
		{
			list($header, $body) = explode("\r\n\r\n", $response, 2);
		
			$tmp = explode("\r\n", $header);
		
			$headers = array();
			foreach ($tmp as $h)
			{
				$a = explode(" ", $h);
				$headers[str_replace(":", "", $a[0])] = $a[1];
			}
			
			$parsedResponse = new stdClass();
			$parsedResponse->headers = $headers;
			$parsedResponse->body = $body;
			return $parsedResponse;
		}
		else
		{
			return $response;
		}
	}
	
	public static function curlPostJson($url, $post, $trustAllSsl = false)
	{
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL,$url);
		curl_setopt($curl_handle, CURLOPT_POST, 1);
		curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'UUTools/1.1');
		
		if ($trustAllSsl)
		{
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);
		}

		$query = curl_exec($curl_handle);
		
		if(curl_errno($curl_handle))
		{
			$info = curl_getinfo($curl_handle);
			UULog::error("got an error from curlPostJson, url=". $info['url'] . ", curl_getinfo: " . UUTools::varDumpToString($info));
		}
		
		curl_close($curl_handle);
		return $query;
	}
	
	public static function curlPostForm($url, $form, $trustAllSsl = false)
	{
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL,$url);
		curl_setopt($curl_handle, CURLOPT_POST, 1);
		curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $form);
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'UUTools/1.1');
		
		if ($trustAllSsl)
		{
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);
		}
		
		$query = curl_exec($curl_handle);
		
		$info = curl_getinfo($curl_handle);
		
		// Check if any error occured
		if(curl_errno($curl_handle))
		{
			$info = curl_getinfo($curl_handle);
			UULog::error("got an error from curlPostForm, url=". $info['url'] . ", curl_getinfo: " . UUTools::varDumpToString($info));
		}

		curl_close($curl_handle);
		return $query;
	}
	
	public static function toCsvList($list)
	{
		$result = "";
		
		if ($list)
		{
			$count = count($list);
			for ($i = 0; $i < $count; $i++)
			{
				$result .= $list[$i];
				
				if ($i < ($count - 1))
				{
					$result .= ",";
				}
			}
		}
		
		return $result;
	}
	
	public static function toSqlInClause($list)
	{
		$result = "";
		
		if ($list)
		{
			$count = count($list);
			for ($i = 0; $i < $count; $i++)
			{
				$result .= "'" . $list[$i] . "'";
				
				if ($i < ($count - 1))
				{
					$result .= ",";
				}
			}
		}
		
		return $result;
	}
	
	public static function clean($db, $value)
	{
		if ($value && is_string($value))
		{
			return mysqli_real_escape_string($db, $value);
		}
		else
		{
			return $value;
		}
	}
	
	public static function createMailHeaders($from)
	{
		$headers = 
			'MIME-Version: 1.0' . "\r\n" .
			'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
			'From: ' . $from . "\r\n" .
			'Reply-To: ' . $from . "\r\n" .
			'Return-Path: ' . $from . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
		
		return $headers;
	}
	
	public static function sendMail($to, $from, $subject, $body)
	{
		$headers = UUTools::createMailHeaders($from);
		
		$result = mail($to, $subject, $body, $headers, '-f ' . $from);
		if (!$result)
		{
			// Log it somewhere??
			//echo "SendMail result: " . $result . "\r\n";
		}
	}
	
	public static function getFileLastModification($fileName)
	{
		date_default_timezone_set('UTC');
		if (file_exists($fileName))
		{
			$raw = filemtime($fileName);
			
			return date('Y-m-d H:i:s', $raw);
		}
		
		return NULL;
	}
	
	public static function varDumpToString($obj)
	{
		ob_start();
		var_dump($obj);
		$dump = ob_get_contents();
		ob_end_clean();
		return $dump;
	}
	
	public static function printRToString($obj)
	{
		ob_start();
		print_r($obj);
		$dump = ob_get_contents();
		ob_end_clean();
		return $dump;
	}
	
	public static function valuesForKeyPath($array, $keyPath)
	{
		$list = array();
		
		if ($array && is_array($array) && $keyPath && is_string($keyPath) && strlen($keyPath) > 0)
		{
			foreach($array as $obj)
			{
				if (isset($obj->$keyPath))
				{
					$list[] = $obj->$keyPath;
				}
			}
		}
		
		return $list;
	}
	
	public static function saveFileToLocalMachine($fileInfo, $destRelativePath)
	{
		if ($fileInfo)
		{
			$tempFileName = $fileInfo['tmp_name'];
			
			$serverRoot = $_SERVER['SERVER_NAME'];
			$wwwRoot = $_SERVER['DOCUMENT_ROOT'];
			
			$destUrl = 'http://' . $serverRoot . $destRelativePath;
			$destLocalFileName = $wwwRoot . $destRelativePath;
			//UULog::debug('DestURL: ' . $destUrl);
			//UULog::debug('DestLocalFileName: ' . $destLocalFileName);
	
			if($tempFileName && $destLocalFileName)
			{
				$result = move_uploaded_file($tempFileName, $destLocalFileName);
				if($result)
				{
					return $destUrl;
				}
				else
				{
					UULog::warning('Failed to save photo ' . $tempFileName);
				}
			}
		}
		
		return NULL;
	}
	
	public static function saveFileToAbsolutePath($fileInfo, $localPath)
	{
		if ($fileInfo && $localPath)
		{
			$tempFileName = $fileInfo['tmp_name'];
			if (!$tempFileName)
			{
				UULog::warning('unable to get temp file name for image ' . $localPath);
				return false;
			}
			
			$result = move_uploaded_file($tempFileName, $localPath);
			if($result)
			{
				return true;
			}
			else
			{
				UULog::warning('Failed to save file ' . $tempFileName . ', localPath=' . $localPath);
				return false;
			}
		}
		
		return false;
	}
	
	public static function getLocalPath($relativePath)
	{
		$wwwRoot = $_SERVER['DOCUMENT_ROOT'];
		$localPath = $wwwRoot . $relativePath;
		return $localPath;
	}
	
	public static function saveFileToLocalMachineRelCurdir($fileInfo, $destRelativePath)
	{
		if ($fileInfo)
		{
			$tempFileName = $fileInfo['tmp_name'];
			//get current url
			$curUrl = UUTools::curPageURL();
			//remove whatever is after last slash
			$curUrl = substr($curUrl, 0, strrpos( $curUrl, '/') );
			//dest url
			$destUrl = $curUrl . $destRelativePath;
			$destUrl = UUTools::removeParentPathsURL($destUrl);
			//now determine folder to save file
			$destLocalFileName = dirname($_SERVER["SCRIPT_FILENAME"]) . $destRelativePath;
			$result = move_uploaded_file($tempFileName, $destLocalFileName);
			if($result)
			{
				return $destUrl;
			}
			else
			{
				UULog::warning('Failed to save photo ' . $tempFileName);
			}
		}
		
		return NULL;
	}
	
	public static function curPageURL()
	{
		$pageURL = 'http';
		if(isset($_SERVER["HTTPS"]))
		{
			if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
		}
		$pageURL .= "://";
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		return $pageURL;
	}
	
	public static function removeParentPathsURL($url)
	{
		$urlComps = parse_url($url);
		$path = $urlComps["path"];
		$pathTokens = explode("/", $path);
		$prevKey = NULL;
		foreach ($pathTokens as $key => $value) {
			if(".." == $value) {
				//remove this token
				unset($pathTokens[$key]);
				//remove previous token
				unset($pathTokens[$prevKey]);
			}
			//keep reference to trailing element
			$prevKey = $key;
		}
		//now put the path back together
		$path = implode("/", $pathTokens);
		//return the new url
		$retval = $urlComps["scheme"] . "://" . $urlComps["host"] . $path;
		return $retval;
	}
	
	public static function fileUploaded($fieldName)
	{
		if(empty($_FILES)) {
			return false;       
		} 
		$file = $_FILES[$fieldName];
		if(!file_exists($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])){
			return false;
		}   
		return true;
	}
	
	public static function getQueryStringArg($argName)
	{
		return self::getFromArray($_GET, $argName);
	}
	
	public static function getPostField($argName)
	{
		return self::getFromArray($_POST, $argName);
	}
	
	public static function getSessionVar($argName)
	{
		$arr = NULL;
		if (isset($_SESSION))
		{
			$arr = $_SESSION;
		}
		
		return self::getFromArray($arr, $argName);
	}
	
	public static function getCookieVar($argName)
	{
		$arr = NULL;
		if (isset($_COOKIE))
		{
			$arr = $_COOKIE;
		}
		
		return self::getFromArray($arr, $argName);
	}
	
	public static function getServerVar($argName)
	{
		return self::getFromArray($_SERVER, $argName);
	}
	
	public static function formatAddressFromObject($object)
	{
		$address = UUTools::getFieldIfSet($object, 'address');
		$city = UUTools::getFieldIfSet($object, 'city');
		$state = UUTools::getFieldIfSet($object, 'state');
		$zip = UUTools::getFieldIfSet($object, 'zip');
		return UUTools::formatAddressFromParts($address, $city, $state, $zip);
	}
	
	public static function formatAddressFromParts($address, $city, $state, $zip)
	{
		$sb = '';
			
		if (isset($address))
		{
			$sb .= trim($address);
		}
		
		if (isset($city) && isset($state))
		{
			if (strlen($sb) > 0)
			{
				$sb .= ', ';
			}
			
			$sb .= trim($city);
			$sb .= ', ';
			$sb .= trim($state);
		}
			
		if (isset($zip))
		{
			if (strlen($sb) > 0)
			{
				$sb .= ' ';
			}
			
			$sb .= trim($zip);
		}
		
		return $sb;
	}
	
	public static function getFieldIfSet($obj, $fieldName, $default = NULL)
	{
		if ($obj && $fieldName && isset($obj->$fieldName))
		{
			return $obj->$fieldName;
		}
		
		return $default;
	}
	
	public static function getRandomCsvValue($csvString)
	{
		//echo "input:";
		//var_dump($csvString);
		if (!is_null($csvString) && is_string($csvString))
		{
			$arr = UUTools::splitString($csvString, ',');
			if ($arr == NULL || count($arr) <= 0)
			{
				return $csvString;
			}
			
			//var_dump($arr);
			$index = rand(0, count($arr)-1);
			//var_dump($index);
			return $arr[$index];
		}
		
		return NULL;
	}
	
	public static function randomElement($array)
	{
		$result = NULL;
		
		if (is_array($array))
		{
			$count = count($array);
			if ($count > 0)
			{
				$pick = rand(0, $count - 1);
				$result = $array[$pick];
			}
		}
		
		return $result;
	}
	
	public static function getSourceIp()
	{
		return UUTools::getFromArray($_SERVER, 'REMOTE_ADDR');
	}
	
	public static function getCallingScript()
	{
		return UUTools::getFromArray($_SERVER, 'SCRIPT_FILENAME');
	}
	
	public static function calculateGeoDistance($latA, $lngA, $latB, $lngB, $meters = true)
	{
		if ($latA && $latB && $lngA && $lngB)
		{
			if ($latA == $latB && $lngA == $lngB)
			{
				return 0;
			}
		
			$constant = 3959; // Miles
			if ($meters)
			{
				$constant = 6371; // Kilometers
			}
			
			return ( $constant * acos( cos( deg2rad($latA) ) * cos( deg2rad( $latB ) ) * cos( deg2rad( $lngB ) - deg2rad($lngA) ) + sin( deg2rad($latA) ) * sin( deg2rad( $latB ) ) ) );
		}
		else
		{
			return NULL;
		}
	}
	
	public static function isValidTimeZone($tz)
	{
    	try
    	{
        	$obj = new DateTimeZone($tz);
	    }
	    catch(Exception $e)
	    {
        	return FALSE;
    	}
    	
    	return TRUE; 
	}
	
	public static function createGuid()
	{
		mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = //chr(123)// "{"
                substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);
                //.chr(125);// "}"
        return $uuid;
    }
    
    public static function setArrayValue(&$array, $destKey, $obj, $sourceKey)
    {
    	//UULog::debug("arrayCount: " . count($array) . ", destKey: $destKey, sourceKey: $sourceKey");
    	
    	if (!is_null($array) && !is_null($destKey) && !is_null($obj) && !is_null($sourceKey))
    	{
    		//UULog::debug("SourceKey: $sourceKey, obj->sourceKey: " . $obj->$sourceKey);
    		
    		if (isset($obj->$sourceKey))
    		{	
    			$array[$destKey] = $obj->$sourceKey;
    		}
    	}
    }
	
	public static function isValidUrl($string)
	{
		if (filter_var($string, FILTER_VALIDATE_URL) === FALSE)
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	public static function copySimpleStruct($obj)
	{
		$copy = new stdClass();
		
		$fields = get_object_vars($obj);
		$fieldNames = array_keys($fields);
		foreach ($fieldNames as $field)
		{
			$copy->$field = $obj->$field;
		}
		
		return $copy;
	}
	
	public static function trimFields($obj, $fieldsToKeep)
	{
		if ($obj && is_object($obj) && $fieldsToKeep && is_array($fieldsToKeep))
		{
			$fields = get_object_vars($obj);
			$fieldNames = array_keys($fields);
		
			foreach ($fieldNames as $field)
			{
				if (!in_array($field, $fieldsToKeep))
				{
					unset($obj->$field);
				}
			}	
		}
	}
	
	public static function stripTagsFromObject($obj, $fieldsToStrip)
	{
		if ($obj && is_object($obj) && $fieldsToStrip && is_array($fieldsToStrip))
		{
			$fields = get_object_vars($obj);
			$fieldNames = array_keys($fields);
		
			foreach ($fieldNames as $field)
			{
				if (in_array($field, $fieldsToStrip))
				{
					if (is_string($obj->$field))
					{	
						$before = $obj->$field;
						$obj->$field = self::stripTags($obj->$field);
						$after = $obj->$field;
					}
				}
			}	
		}
	}
	
	public static function getFromArray($array, $argName, $default = NULL)
	{
		if ($array && is_array($array) && $argName && isset($array[$argName]))
		{
			return $array[$argName];
		}
		
		return $default;
	}
	
	public static function startPhpFileIfNeeded($scriptPath, $scriptName)
	{
		try
		{
			UULog::debug("Checking if script $scriptName is running");
			$cmdLine = "pgrep -fl $scriptName.ph[p]";
			exec($cmdLine, $output, $return);
			UULog::debug("pgrep returnCode: $return");
			// 0	 One or more processes were matched.
			// 1	 No processes were matched.
			// 2	 Invalid options were specified on the command line.
			// 3	 An internal error occurred.
	
			$needToStart = false;
	
			// On the ubuntu server, the pgrep command returns zero when there are no processes
			// found, so as an added check we'll parse through the list
			if ($return == 0)
			{
				if ($output)
				{
					$count = count($output);
					UULog::debug("There are $count instances of $scriptName already running");
					$needToStart = ($count <= 0);
			
					foreach ($output as $o)
					{
						UULog::debug("PID: $o");
					}
				}
				else
				{
					UULog::debug("No output returned, watchdog will start service");
					$needToStart = true;
				}
			}
	
			if ($return == 1)
			{
				$needToStart = true;
			}
			else if ($return != 0)
			{
				UULog::error("pgrep failed with return code: $return, cmdLine was: $cmdLine");
			}
			else
			{
				UULog::debug("Service already running");
			}
	
			if ($needToStart)
			{
				$path =  "$scriptPath/$scriptName.php";
				$cmdLine = sprintf("nohup php %s > /dev/null &", $path);
				UULog::debug("Starting PHP file with command line: " . $cmdLine);
				exec($cmdLine);
			}
		}
		catch (Exception $e)
		{
			UULog::error("Error starting PHP File: " . $e);
		}
	}
}

?>