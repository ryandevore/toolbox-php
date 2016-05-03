<?php
    
require_once("UUTools.php");

class UUBaseRepository
{
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Member Variables
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	protected $db = NULL;
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Construction/Destruction
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function __construct($db) 
	{
		$this->db = $db;
	}
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Public Methods
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	// Protected Methods
	///////////////////////////////////////////////////////////////////////////////////////////////////////
	
	protected function clean($val)
	{
		return UUTools::clean($this->db, $val);
	}
	
	protected function clearSqlResults()
	{
		UUTools::clearResults($this->db);
	}
	
	protected function lastInsertId()
	{
		return UUTools::lastInsertId($this->db);
	}
	
	protected function logDbError($errorMessage)
	{
		UULog::dbError($this->db, $errorMessage);
	}
	
	public function updateObject($table, $idColumn, $idValue, $fieldsToUpdate)
	{
		//UULog::debug(UUTools::varDumpToString($fieldsToUpdate));
		
		$sql = "";
		$fieldsToUpdate['updated_at'] = UU_UPDATE_SQL_COLUMN_TO_NOW_DATE;
		
		if ($idColumn && $idValue)
		{
			// We are setting the query by this column, so no need to insert extra stuff
			unset($fieldsToUpdate[$idColumn]);
			
			$updateArgs = UUTools::buildUpdateArgs($this->db, $fieldsToUpdate);
			//UULog::debug("Update args: " . $updateArgs);
			$sql = sprintf("UPDATE %s SET %s WHERE %s = '%s';", $table, $updateArgs, $idColumn, $idValue);
		}
		else
		{
			$fieldsToUpdate['created_at'] = UU_UPDATE_SQL_COLUMN_TO_NOW_DATE;
	
			$sql = UUTools::buildInsertStatement($this->db, $table, $fieldsToUpdate);
		}
	
		//UULog::debug("SQL: " . $sql);
		
		$result = $this->db->query($sql);
		if (!$result)
		{
			// Log it
			UULog::dbError($this->db, 'update object failed: ' . $sql);
			return NULL;
		}
		
		if ($idValue)
		{
			return $idValue;
		}
		else
		{
			return UUTools::lastInsertId($this->db);
		}
	}
	
	public function formatInClause($list)
	{
		$result = "";
		
		if ($list)
		{
			$count = count($list);
			for ($i = 0; $i < $count; $i++)
			{
				$result .= "'" . $this->clean($list[$i]) . "'";
				
				if ($i < ($count - 1))
				{
					$result .= ",";
				}
			}
		}
		
		return $result;
	}
	
	public function objectFromSqlRow($classType, $row, $fieldMap)
	{
		$obj = new $classType;
		
		$keys = array_keys($fieldMap);
		
		foreach ($keys as $key)
		{
			$field = $key;
			$column = $fieldMap[$key];
			
			if (isset($row[$column]))
			{
				$obj->$field = $row[$column];
			}
		}
		
		return $obj;
	}
	
	public function getSqlUpdateFields($object, $fieldMap)
	{
		$fieldsToUpdate = array();
		
		$fieldMap = array_flip($fieldMap); // Swap keys and values
		$keys = array_keys($fieldMap);
		
		$objectVars = get_object_vars($object);

		foreach ($keys as $key)
		{
			$column = $key;
			$field = $fieldMap[$key];
			//UULog::debug("Column: $column, Field: $field");
			
			if (array_key_exists($field, $objectVars))
			{
				if (isset($object->$field))
				{	
					//UULog::debug("Setting $field");
					$fieldsToUpdate[$column] = $object->$field;
				}
				else if (is_null($object->$field))
				{
					//UULog::debug("Setting $field to NULL");
					$fieldsToUpdate[$column] = UU_UPDATE_SQL_COLUMN_TO_NULL;
				}
			}			
		}
		
		return $fieldsToUpdate;
	}
	
	public function beginTransaction()
	{
		$this->db->autocommit(FALSE);
	}
	
	public function rollbackTransaction()
	{
		$this->db->rollback();
		$this->db->autocommit(TRUE);
	}
	
	public function commitTransaction()
	{
		$this->db->commit();
		$this->db->autocommit(TRUE);
	}
	
	public function getAppConfig($fields, $tableName, $requireAllFields = true, $keyColumn = 'key', $valueColumn = 'value')
	{
		$whereClause = "";
		$inClause = $this->formatInClause($fields);
		if (strlen($inClause) > 0)
		{
			$whereClause = sprintf("WHERE `key` IN (%s)", $inClause);
		}
		
		$sql = sprintf(
			"SELECT `%s`, `%s` FROM %s %s;", 
			$this->clean($keyColumn), 
			$this->clean($valueColumn), 
			$this->clean($tableName), 
			$whereClause);
			
		//UULog::debug("getAppConfig, SQL: $sql");
		$result = UUTools::sqlMultiRowQuery($this->db, $sql);
		if ($result)
		{
			$config = array();
			
			foreach ($result as $r)
			{
				$config[$r->key] = $r->value;
			}
			
			if ($requireAllFields)
			{
				$configValid = true;
				foreach ($fields as $f)
				{
					if (!isset($config[$f]))
					{
						UULog::warning("WARNING - App Config key $f has no entry in table $tableName");
						$configValid = false;
					}
				}
			
				if (!$configValid)
				{
					$config = NULL;
				}
			}
			
			return $config;
		}
		else
		{
			return NULL;
		}
	}
}

?>