<?php
    
class UUError
{
	public $errorCode = NULL;
	public $error = NULL;
	public $errorDetail = NULL; // JSON object with extra info
	
	public function __construct($message, $code, $errorDetail = NULL) 
	{
		$this->errorCode = $code;
		$this->error = $message;
		$this->errorDetail = $errorDetail;
	}
}
    
?>