<?php
    
require_once("UUTools.php");
require_once("UULog.php");
require_once("UUError.php");

define ('APPLE_PUSH_DEV_DNS', 		'gateway.sandbox.push.apple.com');
define ('APPLE_PUSH_DNS', 			'gateway.push.apple.com');
define ('APPLE_PUSH_MESSAGE_PORT', 	'2195');

define ('APPLE_PUSH_FEEDBACK_DEV_DNS', 		'feedback.sandbox.push.apple.com');
define ('APPLE_PUSH_FEEDBACK_DNS', 			'feedback.push.apple.com');
define ('APPLE_PUSH_FEEDBACK_PORT', 		'2196');

define ('APPLE_PUSH_ERROR_SUCCESS',		   0);
define ('APPLE_PUSH_ERROR_NO_CONNECTION', -1);
define ('APPLE_PUSH_ERROR_SEND_FAILED',   -2);
define ('APPLE_PUSH_ERROR_READ_FAILED',	  -3);
define ('APPLE_PUSH_ERROR_NO_RESPONSE',	  -4);
define ('APPLE_PUSH_ERROR_INVALID_RESPONSE_LENGTH', -5);
define ('APPLE_PUSH_ERROR_PREPARE_TO_READ_FAILED', -6);

define ('APPLE_PUSH_RESPONSE_LENGTH', 6);

define ('APPLE_ERROR_CODE_SUCCESS', 0);
define ('APPLE_ERROR_CODE_SHUTDOWN', 10);

class UUApplePush
{
	private $devMode = false;
	private $certPath = NULL;
	private $certPassPhrase = NULL;
	private $connection = NULL;
	
	public $persistentConnection = false;
	
	public function __construct($certPath, $certPassPhrase, $devMode = false) 
	{
		$this->devMode = $devMode;
		$this->certPath = $certPath;
		$this->certPassPhrase = $certPassPhrase;
	}
	
	function __destruct() 
	{
		$this->closeConnection();
	}
        
    public function usePersistentConnection()
    {
    	$this->persistentConnection = true;
    }
    
    public function checkFeedbackService()
    {
    	$conn = $this->openFeedbackConnection();
		if (!$conn)
		{
			return APPLE_PUSH_ERROR_NO_CONNECTION;
		}
		
		$chunkSize = 1024;
		$fullResponse = "";
		
		while (true)
		{
			$readResponse = fread($conn, $chunkSize);
			if ($readResponse === FALSE)
			{
				UULog::debug("read returned FALSE");
				break;
			}
			
			$readLength = strlen($readResponse);
			UULog::debug("Read $readLength bytes");
			
			if ($readLength == 0)
			{
				break;
			}
			
			$fullResponse .= $readResponse;
		}
		
		$fullResponseLength = strlen($fullResponse);
		UULog::debug("Response, $fullResponseLength bytes: " . UUTools::varDumpToString($fullResponse));
		
		
		if (!$this->persistentConnection)
		{
			$this->closeConnection();
		}
		
		return APPLE_PUSH_ERROR_SUCCESS;
    }
    
	public function sendBulkMessages($messages)
	{
		foreach ($messages as $msg)
		{
			$msg->result = -1; // Retry all
		}
			
		$conn = $this->openMessageConnection();
		if (!$conn)
		{
			$conn = $this->openMessageConnection();
			if (!$conn)
			{
				return APPLE_PUSH_ERROR_NO_CONNECTION;
			}
		}
		
		foreach ($messages as $msg)
		{
			$msg->result = 0;
		}
		
		$msg = $this->formatMessagePayload($messages);
		
		$sendResult = fwrite($conn, $msg, strlen($msg));
		if (!$sendResult) // fwrite failed
		{
			UULog::debug("Failed to send push to Apple server, fwrite returned $sendResult");
			$this->closeConnection();
			
			foreach ($messages as $msg)
			{
				$msg->result = -1; // Retry all
			}
			
			return APPLE_PUSH_ERROR_SEND_FAILED;
		}
		
		$arr = array($conn);
		$null = NULL;
		$changedStreams = stream_select($arr, $null, $null, 0, 1000000);
		if ($changedStreams !== FALSE && $changedStreams > 0)
		{
			UULog::debug("Reading from Apple");
			$response = fread($conn, APPLE_PUSH_RESPONSE_LENGTH);
			if ($response === FALSE)
			{
				UULog::debug("Failed to read response from Apple server, fread returned FALSE");
				$this->closeConnection();
				
				foreach ($messages as $msg)
				{
					$msg->result = -1; // Retry all
				}
				
				return APPLE_PUSH_ERROR_READ_FAILED;
			}
		
			$responseLength = strlen($response);
			UULog::debug("Response Length: $responseLength");
			if ($responseLength == 0 )
			{
				UULog::debug("Zero length response from Apple");
				$this->closeConnection();
				
				foreach ($messages as $msg)
				{
					$msg->result = -1; // Retry all
				}
				
				return APPLE_PUSH_ERROR_NO_RESPONSE;
			}
		
			if ($responseLength != APPLE_PUSH_RESPONSE_LENGTH)
			{
				UULog::debug("Invalid response length returned from Apple: $responseLength");
				return APPLE_PUSH_ERROR_INVALID_RESPONSE_LENGTH;
			}
		
			$appleResponse = unpack('Ccommand/CstatusCode/Nidentifier', $response);
			if ($appleResponse)
			{
				$cmd = $appleResponse['command'];
				$id = $appleResponse['identifier'];
				$code = $appleResponse['statusCode'];
				UULog::debug("Apple Response, command: $cmd, statusCode: $code, identifier: $id");
				
				// Apple returns an error and anything after that identifier was not processed
				if ($code != APPLE_ERROR_CODE_SUCCESS)
				{
					foreach ($messages as $msg)
					{
						if ($msg->id == $id)
						{
							// This is a special error code indicating the connection was shutdown
							// and the identifier listed was the last successful one sent.
							if ($code == APPLE_ERROR_CODE_SHUTDOWN)
							{
								$msg->result = 0;
							}
							else
							{
								$msg->result = $code;
							}
						}
						else if ($msg->id > $id)
						{
							$msg->result = -1; // Retry because we don't know
						}
					}
					
					// Abort this connection
					if ($code == APPLE_ERROR_CODE_SHUTDOWN)
					{
						$this->closeConnection();
					}
				}
			}
		}
		else
		{
			UULog::debug("No response available, this means success");
		}
				
		if (!$this->persistentConnection)
		{
			$this->closeConnection();
		}
		
		return APPLE_PUSH_ERROR_SUCCESS;
	}
	
	//////////////////////////////////////////////////////////////////////////////////////
	// Private Methods
	//////////////////////////////////////////////////////////////////////////////////////
	
	private function formatMessagePayload($messages)
	{
		$data = "";
		
		foreach ($messages as $msg)
		{
			$data .= $this->formatSinglePayload($msg);
		}
		
		return $data;
	}
	
	private function formatSinglePayload($message)
	{
		$bodyContent = array();
		
		//UULog::debug("formatSinglePayload: " . UUTools::varDumpToString($message));
		
		if (isset($message->message))
		{
			$bodyContent['alert'] = $message->message;
		}
		
		if (isset($message->sound))
		{
			$bodyContent['sound'] = $message->sound;
		}
		
		if (isset($message->badge))
		{
			$bodyContent['badge'] = intval($message->badge);
		}
		
		if (isset($message->customPayload))
		{
			if (is_array($message->customPayload))
			{
				//UULog::debug("Array custom payload");
				$keys = array_keys($message->customPayload);
				foreach ($keys as $key)
				{
					$body[$key] = $message->customPayload[$key];
				}
			}
			else if (is_string($message->customPayload))
			{
				//UULog::debug("String custom payload: $message->customPayload");
				$json = json_decode($message->customPayload);
				if ($json)
				{
					//UULog::debug("Json converted string payload: " . UUTools::varDumpToString($json));
					
					$fields = get_object_vars($json);
					$fieldNames = array_keys($fields);
					foreach ($fieldNames as $field)
					{
						$body[$field] = $json->$field;
					}
				}
			}
		}
		
		if (isset($message->contentAvailable))
		{
			$bodyContent['content-available'] = $message->contentAvailable;
		}
		
		$body['aps'] = $bodyContent;

		$payload = json_encode($body);
		
		//UULog::debug("Payload Length: " . strlen($payload));

		/*
		// The enhanced notification format
		$msg = chr(1)                       		// command (1 byte)
		     . pack('N', $message->id)        		// identifier (4 bytes)
		     . pack('N', time() + 86400)    		// expire after 1 day (4 bytes)
		     . pack('n', 32)                		// token length (2 bytes)
		     . pack('H*', $message->deviceToken)    // device token (32 bytes)
		     . pack('n', strlen($payload))  		// payload length (2 bytes)
		     . $payload;                    		// the JSON payload
				
		return $msg;
		*/
		
		$tokenItem = chr(1) . pack('n', 32) . pack('H*', $message->deviceToken);
		$payloadItem = chr(2) . pack('n', strlen($payload)) . $payload;
		$idItem = chr(3) . pack('n', 4) . pack('N', $message->id);
		$expirationItem = chr(4) . pack('n', 4) . pack('N', time() + 86400);
		
		$priority = 10;
		if (isset($message->priority))
		{
			$priority = $message->priority;
		}
		
		$priorityItem = chr(5) . pack('n', 1) . chr($priority);
		
		$frameData = $tokenItem . $payloadItem . $idItem . $expirationItem . $priorityItem;
		
		$frame = chr(2) . pack('N', strlen($frameData)) . $frameData;
		
		return $frame;
	}
	
	private function openMessageConnection()
	{
		return $this->openConnection($this->formatMessageUrl());
	}
	
	private function openFeedbackConnection()
	{
		return $this->openConnection($this->formatFeedbackUrl());
	}
	
	private function openConnection($url)
	{
		if ($this->connection)
		{
			return $this->connection;
		}
		
		$streamContext = stream_context_create();
		stream_context_set_option($streamContext, 'ssl', 'local_cert', $this->certPath);
		stream_context_set_option($streamContext, 'ssl', 'passphrase', $this->certPassPhrase);

		$conn = stream_socket_client($url, $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $streamContext);
		
		if (!$conn)
		{
			UULog::debug("Failed to connect to push service, url: $url, err: $err, errorString: $errstr");
			return NULL;
		}
		
		stream_set_blocking($conn, 0);
		
		UULog::debug("Connection to $url successful");
		$this->connection = $conn;
		return $conn;
	}
	
	private function closeConnection()
	{
		UULog::debug("Closing connection");
		if ($this->connection)
		{
			fclose($this->connection);
		}
		
		$this->connection = NULL;
	}
	
	private function formatMessageUrl()
	{
		$port = APPLE_PUSH_MESSAGE_PORT;
		
		$dns = APPLE_PUSH_DNS;
		if ($this->devMode == true)
		{
			$dns = APPLE_PUSH_DEV_DNS;
		}
		
		return sprintf('ssl://%s:%d', $dns, $port);
	}
	
	private function formatFeedbackUrl()
	{
		$port = APPLE_PUSH_FEEDBACK_PORT;
		
		$dns = APPLE_PUSH_FEEDBACK_DNS;
		if ($this->devMode == true)
		{
			$dns = APPLE_PUSH_FEEDBACK_DEV_DNS;
		}
		
		return sprintf('ssl://%s:%d', $dns, $port);
	}
}

?>