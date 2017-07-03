<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordIredmailPlugin;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	/**
	 * @param CApiPluginManager $oPluginManager
	 */
	
	public function init() 
	{
		$this->oMailModule = \Aurora\System\Api::GetModule('Mail');
	
		$this->subscribeEvent('Mail::ChangePassword::before', array($this, 'onBeforeChangePassword'));
	}
	
	/**
	 * 
	 * @param array $aArguments
	 * @param mixed $mResult
	 */
	public function onBeforeChangePassword($aArguments, &$mResult)
	{
		$mResult = true;
		
		$oAccount = $this->oMailModule->GetAccount($aArguments['AccountId']);

		if ($oAccount && $this->checkCanChangePassword($oAccount))
		{
			$mResult = $this->сhangePassword($oAccount, $aArguments['NewPassword']);
		}
	}

	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	protected function checkCanChangePassword($oAccount)
	{
		$bFound = in_array("*", $this->getConfig('SupportedServers', array()));
		
		if (!$bFound)
		{
			$oServer = $this->oMailModule->GetServer($oAccount->ServerId);
			if ($oServer && in_array($oServer->Name, $this->getConfig('SupportedServers')))
			{
				$bFound = true;
			}
		}

		return $bFound;
	}
	
	/**
	 * @param CAccount $oAccount
	 */
	protected function сhangePassword($oAccount, $sPassword)
	{
	    $bResult = false;
	    if (0 < strlen($oAccount->IncomingPassword) && $oAccount->IncomingPassword !== $sPassword )
	    {
			$iredmail_dbuser = $this->getConfig('DbUser','');
			$iredmail_dbpass = $this->getConfig('DbPass','');

			$mysqlcon=mysqli_connect('localhost', $iredmail_dbuser, $iredmail_dbpass, 'vmail');
			if($mysqlcon){
				$sPasshash = exec("doveadm pw -s 'ssha512' -p '".$sPassword."'");
				$sql = "UPDATE mailbox SET password='".$sPasshash."' WHERE username='".$oAccount->IncomingLogin."'";
				$bResult = mysqli_query($mysqlcon,$sql);
				if (!$bResult){
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordUpdateError);
				}
				mysqli_close($mysqlcon);
			}else{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordUpdateError);
	    }
	    return $bResult;
	}
	
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$sSupportedServers = implode("\n", $this->getConfig('SupportedServers', array()));
		
		$aAppData = array(
			'SupportedServers' => $sSupportedServers,
			'DbUser' => $this->getConfig('DbUser', ''),
			'DbPass' => $this->getConfig('DbPass', ''),
		);

		return $aAppData;
	}
	
	public function UpdateSettings($SupportedServers, $DbUser, $DbPass)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		$aSupportedServers = preg_split('/\r\n|[\r\n]/', $SupportedServers);
		
		$this->setConfig('SupportedServers', $aSupportedServers);
		$this->setConfig('DbUser', $DbUser);
		$this->setConfig('DbPass', $DbPass);
		$this->saveModuleConfig();
		return true;
	}
}