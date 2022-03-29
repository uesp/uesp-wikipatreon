<?php
/*
 * TODO:
 */


class UespPatreonApi extends ApiBase 
{

	public function __construct($mainModule, $moduleName, $modulePrefix = '') 
	{
		parent::__construct($mainModule, $moduleName, $modulePrefix);
	}
	
	
	protected function getAllowedParams() {
		return [
				'code' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				],
		];
	}
	
	
	public function isWriteMode()
	{
		return true;
	}
	
	
	public function execute()
	{
		$params = $this->extractRequestParams();
		$apiResult = $this->getResult();
		
		$this->requireOnlyOneParameter($params, "code");
		
		$appCode = strtolower($params['code']);
		
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_user', '*', [ 'appCode' => $appCode]);
		$isAppCodeValid = "false";
		
		if ($res->numRows() == 0) 
		{
			$isAppCodeValid = "false";
		}
		else
		{
			$isAppCodeValid = "true";
		}
		
		$apiResult->addValue(null, "validcode", $isAppCodeValid);
	}
	
};
