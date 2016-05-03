<?php
    
require_once("UUErrorCodes.php");
require_once("UUTools.php");
require_once("UUError.php");

define('UU_LOG_REQUEST_METRICS', 0);

class UUBaseController
{
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Member Variables
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	protected $db = NULL;
	protected $jsonResult = NULL;
	protected $outputJsonResult = true;
	protected $dieOnError = true;
	protected $startTime = NULL;
	protected $startMemory = NULL;
	protected $endTime = NULL;
	protected $endMemory = NULL;
	protected $logRequestMetrics = NULL;
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Construction/Destruction
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function __construct($dbHost, $dbUser, $dbPass, $dbName) 
	{
		// Assumption here is that construction of controller is tops in a file after includes
		ob_start("ob_gzhandler");
		header('Content-type: application/json');
		
		// Suppress all echo/print until destructor
		ob_start();

		$this->logRequestMetrics = UU_LOG_REQUEST_METRICS;
		$this->startTime = microtime(TRUE);
		$this->startMemory = memory_get_usage();
		
		$this->db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
		if ($this->db->connect_errno) 
		{
			$this->dieWithError("Could not connect to database - " . $this->db->connect_errno, ERR_DB_CONNECT);
		}
		
		mysqli_set_charset($this->db, 'utf8');
	}
	
	function __destruct() 
	{
		$this->db->close();
		
		ob_end_clean();
		
		if ($this->outputJsonResult)
		{
			//$json_string = json_encode($data, JSON_PRETTY_PRINT); // This only works with PHP 5.4
			//print_r($this->jsonResult);
			echo json_encode($this->jsonResult);
		}
		
		$this->endTime = microtime(TRUE);
		$this->endMemory = memory_get_usage();
		$this->logRequestMetrics();
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Public Methods
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function getRawJsonResult()
	{
		return $this->jsonResult;
	}
	
	public function toggleJsonOutput($val)
	{
		$this->outputJsonResult = $val;
	}
	
	public function toggleDieOnError($val)
	{
		$this->dieOnError = $val;
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Protected Methods
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	protected function dieWithError($errorMessage, $errorCode, $errorDetail = NULL, $logError = true)
	{
		$this->dieWithUUError(new UUError($errorMessage, $errorCode, $errorDetail), $logError);
	}
	
	protected function dieWithUUError($error, $logError = true)
	{
		$this->jsonResult = $error;
		
		if ($logError)
		{
			UULog::debug($error->error . ", source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
		}
		
		if ($this->dieOnError)
		{
			die();
		}
		else
		{
			throw new Exception($error->error, $error->errorCode);
		}
	}
	
	
	protected function requireHttpMethod($method)
	{
		$httpMethod = UUTools::getHttpMethod();
		
		if ($httpMethod != $method)
		{
			$this->dieWithError("Unsupported HTTP Method " . $httpMethod, ERR_HTTP_METHOD_NOT_SUPPORTED);
		}
	}
	
	protected function requireHttpGet()
	{
		$this->requireHttpMethod("GET");
	}
	
	protected function requireHttpPost()
	{
		$this->requireHttpMethod("POST");
	}
	
	protected function requireLocalRequest($whiteList = NULL)
	{
		$localIp = UUTools::getServerVar('SERVER_ADDR');
		$remoteIp = UUTools::getServerVar('REMOTE_ADDR');
		$scriptName = UUTools::getServerVar('SCRIPT_FILENAME');
	
		//UULog::debug("Verifying Local Request: localIp: $localIp, remoteIp: $remoteIp, scriptName: $scriptName");
		
		if ($whiteList)
		{
			if (in_array($remoteIp, $whiteList))
			{
				UULog::debug('Access to script: ' . $scriptName . ' allowed via white list to remote IP: ' . $remoteIp);
				return;
			}
		}
	
		if ($localIp != $remoteIp)
		{	
			UULog::error('Access to script: ' . $scriptName . ' denied to remote IP: ' . $remoteIp);
			$this->dieWithError("Remoate access denied", ERR_REMOTE_ACCESS_DENIED);
		}
	}
	
	protected function getIncomingPostAsJson()
	{
		return UUTools::getIncomingPostAsJson();
	}
	
	protected function getRequiredJsonField($post, $argName)
	{
		if (!$post)
		{
			UULog::error("getRequiredJsonField($argName), Null post body, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
			$this->dieWithError("Invalid Request", ERR_POST_BODY_EMPTY);
		}
		
		if (!isset($post->$argName) || $post->$argName === NULL)
		{
			$this->dieWithError($argName . " is required", ERR_MISSING_POST_FIELD);
		}
		
		return $post->$argName;
	}
	
	protected function getRequiredJsonFieldAsBoolean($post, $argName)
	{
		if (!$post)
		{
			UULog::error("getRequiredJsonFieldAsBoolean($argName), Null post body, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
			$this->dieWithError("Invalid Request", ERR_POST_BODY_EMPTY);
		}
		
		if (!isset($post->$argName) || $post->$argName === NULL)
		{
			$this->dieWithError($argName . " is required", ERR_MISSING_POST_FIELD);
		}
		
		$val = $post->$argName;
		return (($val == "true") || ($val == "1"));
	}
	
	protected function getOptionalJsonField($post, $argName, $defaultValue = NULL)
	{
		if (!$post)
		{
			UULog::error("getOptionalJsonField($argName), Null post body, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
			$this->dieWithError("Invalid Request", ERR_POST_BODY_EMPTY);
		}
		
		if (!isset($post->$argName) || $post->$argName === NULL)
		{
			return $defaultValue;
		}
		
		return $post->$argName;
	}
	
	protected function getOptionalJsonFieldAsBoolean($post, $argName)
	{
		if (!$post)
		{
			UULog::error("getOptionalJsonFieldAsBoolean($argName), Null post body, source ip: " . UUTools::getSourceIp() . ", script: " . UUTools::getCallingScript());
			$this->dieWithError("Invalid Request", ERR_POST_BODY_EMPTY);
		}
		
		if (!isset($post->$argName) || $post->$argName === NULL)
		{
			return NULL;
		}
		
		$val = $post->$argName;
		return (($val == "true") || ($val == "1"));
	}
	
	protected function getOptionalQueryStringArg($argName, $defaultValue = NULL)
	{
		if ($argName && isset($_GET[$argName]))
		{
			return $_GET[$argName];
		}
		
		return $defaultValue;
	}
	
	protected function getOptionalQueryStringArgAsBoolean($argName, $defaultValue = false)
	{
		if ($argName && isset($_GET[$argName]))
		{
			$val = $_GET[$argName];
			return (bool)(($val == "true") || ($val == "1"));
		}
		
		return (bool)$defaultValue;
	}
	
	protected function getRequiredQueryStringArg($argName)
	{
		if ($argName && isset($_GET[$argName]))
		{
			return $_GET[$argName];
		}
		
		$this->dieWithError($argName . " is required", ERR_MISSING_GET_FIELD);
		return NULL;
	}
	
	protected function getRequiredPostField($argName)
	{
		if ($argName && isset($_POST[$argName]))
		{
			return $_POST[$argName];
		}
		
		$this->dieWithError($argName . " is required", ERR_MISSING_POST_FIELD);
		return NULL;
	}
	
	protected function getOptionalPostField($argName)
	{
		if ($argName && isset($_POST[$argName]))
		{
			return $_POST[$argName];
		}
		
		return NULL;
	}
	
	protected function getOptionalPostFieldAsBoolean($argName, $defaultValue = false)
	{
		if ($argName && isset($_POST[$argName]))
		{
			$val = $_POST[$argName];
			return (bool)(($val == "true") || ($val == "1"));
		}
		
		return (bool)$defaultValue;
	}
	
	protected function getOptionalFileField($argName)
	{
		if ($argName && isset($_FILES[$argName]))
		{
			return $_FILES[$argName];
		}
		
		return NULL;
	}
	
	protected function getRequiredFileField($argName)
	{
		if ($argName && isset($_FILES[$argName]))
		{
			return $_FILES[$argName];
		}
		
		$this->dieWithError($argName . " is required", ERR_MISSING_POST_FIELD);
		return NULL;
	}
	
	protected function getRequiredHeaderField($argName)
	{
		$headers = getallheaders();
		if (isset($headers[$argName]))
		{
			return $headers[$argName];
		}
		
		$this->dieWithError($argName . " is required", ERR_MISSING_HEADER_FIELD);
		return NULL;
	}
	
	protected function validateUploadFileName($fileInfo)
	{
		$fileName = $fileInfo['name'];
		if (!$fileName)
		{
			$this->dieWithError("Unable to get file name from uploaded file info", ERR_MISSING_POST_FIELD);
		}
		
		return $fileName;
	}
	
	protected function validateUploadImageMimeType($fileInfo, $validMimeTypes)
	{
		$fileName = $fileInfo['name'];
		
		$isValid = false;
		
		$info = getimagesize($fileInfo['tmp_name']);
		if ($info)
		{
			if (is_array($info) && count($info) > 2)
			{
				$w = $info[0];
				$h = $info[1];
				//UULog::debug("Image width: $w, height: $h");
				
				if ($w > 0 && $h > 0)
				{
					if (isset($info['mime']))
					{
						$mime = $info['mime'];
						
						if (in_array($mime, $validMimeTypes))
						{
							$isValid = true;
						}
						
						//UULog::debug("Mime: $mime");
					}
				}
			}
		}
		
		if (!$isValid)
		{
			$this->dieWithError("Upload file is not an image", ERR_INVALID_IMAGE_UPLOAD);
		}
	}
	
	protected function isValidImageFile($filePath, $validMimeTypes)
	{
		$isValid = false;
		
		$info = getimagesize($filePath);
		if ($info)
		{
			if (is_array($info) && count($info) > 2)
			{
				$w = $info[0];
				$h = $info[1];
				//UULog::debug("Image width: $w, height: $h");
				
				if ($w > 0 && $h > 0)
				{
					if (isset($info['mime']))
					{
						$mime = $info['mime'];
						
						if (in_array($mime, $validMimeTypes))
						{
							$isValid = true;
						}
						
						//UULog::debug("Mime: $mime");
					}
				}
			}
		}
		
		return $isValid;
	}
	
	protected function validateUploadAudioFileMimeType($fileInfo, $validMimeTypes)
	{
		$fileName = $fileInfo['name'];
		
		$isValid = false;
		
		/*
		$info = getimagesize($fileInfo['tmp_name']);
		if ($info)
		{
			if (is_array($info) && count($info) > 2)
			{
				$w = $info[0];
				$h = $info[1];
				//UULog::debug("Image width: $w, height: $h");
				
				if ($w > 0 && $h > 0)
				{
					if (isset($info['mime']))
					{
						$mime = $info['mime'];
						
						if (in_array($mime, $validMimeTypes))
						{
							$isValid = true;
						}
						
						//UULog::debug("Mime: $mime");
					}
				}
			}
		}*/
		
		$isValid = true;
		
		if (!$isValid)
		{
			$this->dieWithError("Upload file is not an audio file", ERR_INVALID_AUDIO_UPLOAD);
		}
	}
	
	protected function getJsonResultObject($resultCode, $resultMessage)
	{
		$obj = new stdClass();
		$obj->resultCode = (int)$resultCode;
		$obj->resultMessage = $resultMessage;
		return $obj;
	} 
	
	protected function getJsonSuccessResult($resultMessage)
	{
		return $this->getJsonResultObject(0, $resultMessage);
	} 
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Private Methods
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	private function logRequestMetrics()
	{
		if ($this->logRequestMetrics == 1)
		{
			$time = $this->endTime - $this->startTime;
			$mem = $this->endMemory - $this->startMemory;  
			UULog::debug(sprintf("Script %s took %f seconds and used %d bytes", UUTools::getCallingScript(), $time, $mem));
		}
	}
}

?>