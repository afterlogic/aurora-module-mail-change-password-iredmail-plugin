<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordIredmailPlugin;

/**
 * Allows users to change passwords on their email accounts hosted by [iRedMail](http://www.iredmail.org/) mail server.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public function init() 
	{
		$this->subscribeEvent('Mail::Account::ToResponseArray', array($this, 'onMailAccountToResponseArray'));
		$this->subscribeEvent('Mail::ChangeAccountPassword', array($this, 'onChangeAccountPassword'));
	}
	
	/**
	 * Adds to account response array information about if allowed to change the password for this account.
	 * @param array $aArguments
	 * @param mixed $mResult
	 */
	public function onMailAccountToResponseArray($aArguments, &$mResult)
	{
		$oAccount = $aArguments['Account'];

		if ($oAccount && $this->checkCanChangePassword($oAccount))
		{
			if (!isset($mResult['Extend']) || !is_array($mResult['Extend']))
			{
				$mResult['Extend'] = [];
			}
			$mResult['Extend']['AllowChangePasswordOnMailServer'] = true;
		}
	}

	/**
	 * Tries to change password for account if allowed.
	 * @param array $aArguments
	 * @param mixed $mResult
	 */
	public function onChangeAccountPassword($aArguments, &$mResult)
	{
		$bPasswordChanged = false;
		$bBreakSubscriptions = false;
		
		$oAccount = $aArguments['Account'];
		if ($oAccount && $this->checkCanChangePassword($oAccount) && $oAccount->getPassword() === $aArguments['CurrentPassword'])
		{
			$bPasswordChanged = $this->changePassword($oAccount, $aArguments['NewPassword']);
			$bBreakSubscriptions = true; // break if Iredmail plugin tries to change password in this account. 
		}
		
		if (is_array($mResult))
		{
			$mResult['AccountPasswordChanged'] = $mResult['AccountPasswordChanged'] || $bPasswordChanged;
		}
		
		return $bBreakSubscriptions;
	}

	/**
	 * Checks if allowed to change password for account.
	 * @param \Aurora\Modules\Mail\Classes\Account $oAccount
	 * @return bool
	 */
	protected function checkCanChangePassword($oAccount)
	{
		$bFound = in_array('*', $this->getConfig('SupportedServers', array()));
		
		if (!$bFound)
		{
			$oServer = $oAccount->getServer();
			
			if ($oServer && in_array($oServer->IncomingServer, $this->getConfig('SupportedServers')))
			{
				$bFound = true;
			}
		}

		return $bFound;
	}
	
	/**
	 * Tries to change password for account.
	 * @param \Aurora\Modules\Mail\Classes\Account $oAccount
	 * @param string $sPassword
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	protected function changePassword($oAccount, $sPassword)
	{
	    $bResult = false;
	    if (0 < strlen($oAccount->getPassword()) && $oAccount->getPassword() !== $sPassword )
	    {
			$iredmail_dbuser = $this->getConfig('DbUser','');
			$iredmail_dbpass = $this->getConfig('DbPass','');

			$mysqlcon = @mysqli_connect('localhost', $iredmail_dbuser, $iredmail_dbpass, 'vmail');
			if (isset($mysqlcon) && $mysqlcon)
			{
				$sRandomSalt = substr(md5(rand()),0,15);
				$sPasshash = "{SSHA512}".base64_encode(hash('sha512', $sPassword.$sRandomSalt, true).$sRandomSalt);
				$sql = "UPDATE mailbox SET password='" . $sPasshash . "' WHERE username='" . $oAccount->IncomingLogin . "'";
				$bResult = mysqli_query($mysqlcon,$sql);
				if (!$bResult)
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordUpdateError);
				}
				mysqli_close($mysqlcon);
			}
			else
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordUpdateError);
			}
	    }
	    return $bResult;
	}

	/**
	 * Obtains list of module settings for super admin.
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		$sSupportedServers = implode("\n", $this->getConfig('SupportedServers', array()));

		$aAppData = array(
			'SupportedServers' => $sSupportedServers,
			'DbUser' => $this->getConfig('DbUser', ''),
			'HasDbPass' => $this->getConfig('DbPass', '') !== '',
		);

		return $aAppData;
	}
	
	/**
	 * Updates module's super admin settings.
	 * @param string $SupportedServers
	 * @param string $DbUser
	 * @param string $DbPass
	 * @return boolean
	 */
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
