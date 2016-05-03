<?php

require_once('UULog.php');    
require_once('UUTools.php');
require_once('UUApplePushRepository.php');
require_once('UUApplePush.php');

define('UU_APPLE_PUSH_ENV_APPSTORE', 		'appstore');
define('UU_APPLE_PUSH_ENV_APPSTORE_DEV', 	'appstore_dev');
define('UU_APPLE_PUSH_ENV_ENTERPRISE', 		'enterprise');
define('UU_APPLE_PUSH_ENV_ENTERPRISE_DEV', 	'enterprise_dev');

class UUApplePushService
{   
	private $db = NULL;
	private $pushRepository = NULL;
	private $applePush = NULL;
	private $config = NULL;
	private $sleepTime = 60;
	private $batchSize = 50;
	private $useSandbox = false;
	
	public function __construct(
		$dbHost,
		$dbUser,
		$dbPass,
		$dbName,
		$pushQueueTableName, 
		$appConfigTableName, 
		$pushEnvironment) 
	{
		$this->useSandbox = false;
		if (UUTools::endsWith($pushEnvironment, '_dev'))
		{
			$this->useSandbox = true;
		}
		
		$this->initDbAndRepo($dbHost, $dbUser, $dbPass, $dbName, $pushQueueTableName, $appConfigTableName, $pushEnvironment);

		$this->config = $this->pushRepository->getPushConfig();
		if (!$this->config)
		{
			UULog::error("Failed to read app configs!");
			exit();
		}
		
		if (!file_exists($this->config->certPath))
		{
			UULog::error("Certificate file " . $this->config->certPath . " does not exist!");
			exit();
		}
		
		UULog::debug("Starting up with configs: " . UUTools::varDumpToString($this->config));
		
		$this->sleepTime = $this->config->interval;
		$this->batchSize = $this->config->batchSize;
		
		UULog::debug(sprintf("Using Apple %s Servers", $this->useSandbox ? 'Dev' : 'Production'));
		$this->applePush = new UUApplePush($this->config->certPath, $this->config->certPass, $this->useSandbox);

		$this->applePush->usePersistentConnection();
	}
	
	private function initDbAndRepo($dbHost, $dbUser, $dbPass, $dbName, $pushQueueTableName, $appConfigTableName, $pushEnvironment)
	{
		$now = microtime(TRUE);
		$end = $now + 30;
		
		//UULog::debug("Now: $now, Timeout: $end");
		
		$db = NULL;
		
		while (TRUE)
		{
			//UULog::debug("Connecting to push database...");
			$db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
			$now = microtime(TRUE);
			//UULog::debug("mysqli ctor returned, now is $now");
			
			if ($db->connect_errno) 
			{
				if ($microtime(TRUE) < $end && $db->connect_errno == 2003)
				{
					UULog::debug("MySQL could be starting up, sleeping five seconds and trying again.");
					sleep(5);
				}
				else
				{
					UULog::error("Could not connect to database - " . $db->connect_errno);
					exit();
				}
			}
			else
			{
				//UULog::debug("Database connection successful, breaking out of connect loop");
				break;
			}
		}
		
		UULog::debug("Database connection successful!");
		
		$this->db = $db;
		
		mysqli_set_charset($db, 'utf8');
            
		$this->pushRepository = new UUApplePushRepository($db, $pushQueueTableName, $appConfigTableName, $pushEnvironment);
	}
	
	public function run()
	{
		UULog::debug("UUApplePushService starting up");
		
		//$this->writeProcessFile();
		
		while (true)
		{
			//UULog::debug("Last db error, code: " . $this->db->errno . ", message: " . $this->db->error);
			
			if ($this->db->errno != 0)
			{
				UULog::debug("Last database operation failed with an error, re-initializing");
				$this->db->close();
				$this->initDbAndRepo();
			}
			
			$list = $this->pushRepository->listPendingMessages($this->batchSize, $this->config->maxRetries);
			if ($list)
			{
				UULog::debug(count($list) . " messages to process");
				$result = $this->applePush->sendBulkMessages($list);
				UULog::debug("sendBulkMessages returned $result");
				if (true)
				{
					$successIdList = array();
					$failedIdList = array();
					$retryIdList = array();
					
					foreach ($list as $msg)
					{
						switch ($msg->result)
						{
							case 0:
								$successIdList[] = $msg->id;
								break;
							
							case -1:
								$retryIdList[] = $msg->id;
								break;
									
							default:
								$failedIdList[] = $msg->id;
								break;
						}
					}
			
					$this->pushRepository->markMessagesAtSent($successIdList);
					$this->pushRepository->setMessageStatus($failedIdList, -1);
					$this->pushRepository->setMessageStatus($retryIdList, 0, $this->config->retryDelaySeconds);
				}
			}
			else
			{
				UULog::debug("No messages to process");
				sleep($this->sleepTime);
			}
		}
		
		UULog::debug("UUApplePushService all done.");
	}
}

?>