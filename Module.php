<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordIredmailPlugin;

use Aurora\Modules\Mail\Models\MailAccount;
use Aurora\System\Notifications;

/**
 * Allows users to change passwords on their email accounts hosted by [iRedMail](http://www.iredmail.org/) mail server.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    public function init()
    {
        $this->subscribeEvent('Mail::Account::ToResponseArray', array($this, 'onMailAccountToResponseArray'));
        $this->subscribeEvent('ChangeAccountPassword', array($this, 'onChangeAccountPassword'));
    }

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Adds to account response array information about if allowed to change the password for this account.
     * @param array $aArguments
     * @param mixed $mResult
     */
    public function onMailAccountToResponseArray($aArguments, &$mResult)
    {
        $oAccount = $aArguments['Account'];

        if ($oAccount && $this->checkCanChangePassword($oAccount)) {
            if (!isset($mResult['Extend']) || !is_array($mResult['Extend'])) {
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

        $oAccount = $aArguments['Account'] instanceof MailAccount ? $aArguments['Account'] : false;
        if ($oAccount && $this->checkCanChangePassword($oAccount) && $oAccount->getPassword() === $aArguments['CurrentPassword']) {
            $bPasswordChanged = $this->changePassword($oAccount, $aArguments['NewPassword']);
            $bBreakSubscriptions = true; // break if Iredmail plugin tries to change password in this account.
        }

        if (is_array($mResult)) {
            $mResult['AccountPasswordChanged'] = $mResult['AccountPasswordChanged'] || $bPasswordChanged;
        }

        return $bBreakSubscriptions;
    }

    /**
     * Checks if allowed to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @return bool
     */
    protected function checkCanChangePassword($oAccount)
    {
        $bFound = in_array('*', $this->oModuleSettings->SupportedServers);

        if (!$bFound) {
            $oServer = $oAccount->getServer();

            if ($oServer && in_array($oServer->IncomingServer, $this->oModuleSettings->SupportedServers)) {
                $bFound = true;
            }
        }

        return $bFound;
    }

    /**
     * Tries to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @param string $sPassword
     * @return boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    protected function changePassword($oAccount, $sPassword)
    {
        $bResult = false;
        if (0 < strlen($oAccount->getPassword()) && $oAccount->getPassword() !== $sPassword) {
            $iredmail_dbuser = $this->oModuleSettings->DbUser;
            $iredmail_dbpass = $this->oModuleSettings->DbPass;

            if ($iredmail_dbpass && !\Aurora\System\Utils::IsEncryptedValue($iredmail_dbpass)) {
                $this->setConfig('DbPass', \Aurora\System\Utils::EncryptValue($iredmail_dbpass));
                $this->saveModuleConfig();
            } else {
                $iredmail_dbpass = \Aurora\System\Utils::DecryptValue($iredmail_dbpass);
            }

            $mysqlcon = @mysqli_connect('localhost', $iredmail_dbuser, $iredmail_dbpass, 'vmail');
            if ($mysqlcon) {
                $sRandomSalt = substr(md5(rand()), 0, 15);
                $sPasshash = "{SSHA512}" . base64_encode(hash('sha512', $sPassword . $sRandomSalt, true) . $sRandomSalt);
                $sql = "UPDATE mailbox SET password='" . $sPasshash . "' WHERE username='" . $oAccount->IncomingLogin . "'";
                $bResult = mysqli_query($mysqlcon, $sql);
                if (!$bResult) {
                    throw new \Aurora\System\Exceptions\ApiException(Notifications::CanNotChangePassword);
                }
                mysqli_close($mysqlcon);
            } else {
                throw new \Aurora\System\Exceptions\ApiException(Notifications::CanNotChangePassword);
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

        $sSupportedServers = implode("\n", $this->oModuleSettings->SupportedServers);

        $aAppData = array(
            'SupportedServers' => $sSupportedServers,
            'DbUser' => $this->oModuleSettings->DbUser,
            'HasDbPass' => $this->oModuleSettings->DbPass !== '',
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
        $this->setConfig('DbPass', \Aurora\System\Utils::EncryptValue($DbPass));
        $this->saveModuleConfig();
        return true;
    }
}
