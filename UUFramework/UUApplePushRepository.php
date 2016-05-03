<?php

require_once('UUBaseRepository.php');

///////////////////////////////////////////////////////////////////////////////////////////////////////
// Database access for apple push support.  This class assumes the database connection has the following
// two tables:
//
// 1. Push Queue Table
// MUST have the following columns at a minimum:
// id (unique row id)
// status INT
// try_count INT
// created_at DATETIME
// sent_at DATETIME
// environment VARCHAR(32) -- production, dev, enterprise, enterprise_dev
// device_token VARCHAR(128)
// message VARCHAR(255)
// sound VARCHAR(50)
// payload VARCHAR(2048)
//
//
// 2. App Config Table 
//
// key VARCHAR(32)
// value VARCHAR(255)
//
// 3. App Config Table must have the following entries:
// 
// INSERT IGNORE INTO dd_app_config (`key`, `value`) VALUES
// ('apple_push_batch_size_env', '50'),
// ('apple_push_sleep_interval_env', '60'),
// ('apple_push_cert_path_env', '/some/local/path/cert.pem'),
// ('apple_push_cert_pass_env', 'password');
//
// The key values in app config are dynamic so that a given server can potentially run
// multiple instances and configs of push servers (ie - prod and dev)
//
///////////////////////////////////////////////////////////////////////////////////////////////////////
class UUApplePushRepository extends UUBaseRepository
{   
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Private Variables
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	private $pushQueueTableName = NULL;
	private $appConfigTableName = NULL;
	private $pushEnvironment = NULL;
	private $appConfigBatchSizeKey = NULL;
	private $appConfigSleepIntervalKey = NULL;
	private $appConfigCertPathKey = NULL;
	private $appConfigCertPassKey = NULL;
	private $appConfigMaxRetryCountKey = NULL;
	private $appConfigRetryDelayKey = NULL;
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Construction/Destruction
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function __construct(
		$db, 
		$pushQueueTableName, 
		$appConfigTableName, 
		$environment) 
	{
		parent::__construct($db);
		
		$this->pushQueueTableName = $pushQueueTableName;
		$this->appConfigTableName = $appConfigTableName;
		$this->pushEnvironment = $environment;
		$this->appConfigBatchSizeKey = 'apple_push_batch_size_' . $environment;
		$this->appConfigSleepIntervalKey = 'apple_push_interval_' . $environment;
		$this->appConfigCertPathKey = 'apple_push_cert_path_' . $environment;
		$this->appConfigCertPassKey = 'apple_push_cert_pass_' . $environment;
		$this->appConfigMaxRetryCountKey = 'apple_push_retry_count_' . $environment;
		$this->appConfigRetryDelayKey = 'apple_push_retry_delay_' . $environment;
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Public Methods
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function listPendingMessages($limit, $maxTries = 3)
	{
		$this->beginTransaction();
		
		$limitClause = "";
		if ($limit)
		{
			$limitClause = sprintf(" LIMIT %d ", $limit);
		}
		
		$sql = sprintf(
			"SELECT id, try_count AS tryCount, environment, device_token AS deviceToken, message, sound, payload AS customPayload " .
			"FROM %s " .
			"WHERE environment = '%s' AND status = 0 AND try_count < %d AND ISNULL(sent_at) AND NOW() > send_after " .
			"ORDER BY id ASC %s;",
			$this->clean($this->pushQueueTableName),
			$this->clean($this->pushEnvironment),
			$maxTries,
			$limitClause);
		
		//UULog::debug("listPendingMessages: " . $sql);
		$result = UUTools::sqlMultiRowQuery($this->db, $sql);
		if (!$result)
		{
			$this->rollbackTransaction();
			return false;
		}
		
		$idList = array();
		foreach ($result as $o)
		{	
			$idList[] = $o->id;
		}
		
		if (!$this->setMessageStatus($idList, 1, 0, 0))
		{
			$this->rollbackTransaction();
			return false;
		}
		
		$this->commitTransaction();
		return $result;
	}
	
	public function markMessagesAtSent($idList)
	{
		$inClause = $this->formatInClause($idList);
		if (strlen($inClause) > 0)
		{
			$sql = sprintf("UPDATE %s SET sent_at = NOW(), status = 2 WHERE id IN (%s);", $this->clean($this->pushQueueTableName), $inClause);
				
			//UULog::debug("markMessagesAtSent: " . $sql);
			return UUTools::sqlNoResultQuery($this->db, $sql);
		}
		else
		{
			return true;
		}
	}
	
	public function setMessageStatus($idList, $status, $sendAfterAddition = 0, $tryCountAddition = 1)
	{
		$inClause = $this->formatInClause($idList);
		if (strlen($inClause) > 0)
		{
			$sql = sprintf(
				"UPDATE %s SET status = %d, try_count = (try_count + %d), send_after = DATE_ADD(NOW(), INTERVAL %d SECOND) WHERE id IN (%s);", 
				$this->clean($this->pushQueueTableName), $status, $tryCountAddition, $sendAfterAddition, $inClause);
				
			//UULog::debug("setMessageStatus: " . $sql);
			return UUTools::sqlNoResultQuery($this->db, $sql);
		}
		else
		{
			return true;
		}
	}
	
	public function getPushConfig()
	{
		$batchSizeKey = $this->appConfigBatchSizeKey;
		$sleepIntervalKey = $this->appConfigSleepIntervalKey;
		$certPathKey = $this->appConfigCertPathKey;
		$certPassKey = $this->appConfigCertPassKey;
		$retryCountKey = $this->appConfigMaxRetryCountKey;
		$retryDelaykey = $this->appConfigRetryDelayKey;
		$table = $this->appConfigTableName;
		
		$fields = array(
			$batchSizeKey,
			$sleepIntervalKey,
			$certPathKey,
			$certPassKey,
			$retryCountKey,
			$retryDelaykey);
			
		$result = $this->getAppConfig($fields, $table);
		
		$configValid = true;
		if (!isset($result[$batchSizeKey]))
		{
			UULog::warning("WARNING - Batch Size key $batchSizeKey has no entry in table $table");
			$configValid = false;
		}
		
		if (!isset($result[$sleepIntervalKey]))
		{
			UULog::warning("WARNING - Sleep Interval key $sleepIntervalKey has no entry in table $table");
			$configValid = false;
		}
		
		if (!isset($result[$certPathKey]))
		{
			UULog::warning("WARNING - Cert Path key $certPathKey has no entry in table $table");
			$configValid = false;
		}
		
		if (!isset($result[$certPassKey]))
		{
			UULog::warning("WARNING - Cert Pass key $certPassKey has no entry in table $table");
			$configValid = false;
		}
		
		if (!isset($result[$retryCountKey]))
		{
			UULog::warning("WARNING - Cert Pass key $retryCountKey has no entry in table $table");
			$configValid = false;
		}
		
		if (!isset($result[$retryDelaykey]))
		{
			UULog::warning("WARNING - Cert Pass key $retryDelaykey has no entry in table $table");
			$configValid = false;
		}
		
		if ($configValid)
		{
			$config = new stdClass();
			$config->batchSize = intval($result[$batchSizeKey]);
			$config->interval = intval($result[$sleepIntervalKey]);
			$config->certPath = $result[$certPathKey];
			$config->certPass = $result[$certPassKey];
			$config->maxRetries = intval($result[$retryCountKey]);
			$config->retryDelaySeconds = intval($result[$retryDelaykey]);
			return $config;
		}
		else
		{
			return NULL;
		}
		
	}
}
    
?>