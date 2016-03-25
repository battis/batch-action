<?php
	
namespace Battis\BatchAction;

use PWGen;

/**
 * An action that generates an .htacess file to use HTTP Auth to protect a
 * specific directory
 * 
 * @author Seth Battis <seth@battis.net>
 */
class HttpAuthDirectoryAction extends Action {
	
	// TODO replace with SandboxReplaceableData
	/** @var string The path to the directory to be secured */
	private $dirPath;
	
	// TODO replace with SandboxReplaceableData
	/** @var string[] Array of passwords with usernames as keys */
	private $users;
	
	// TODO replace with SandboxReplaceableData
	/** @var string The path to the `.htpasswd` file */
	private $htpasswdFilePath;
	
	/**
	 * Construct a new Action
	 * 
	 * {@inheritDoc}
	 *
	 * @param string $dirPath Path to the directory to be secured
	 * @param string|string[] $users Username to use. Can also be an array of
	 *		usernames, or mixed array of usernames and username => password pairs.
	 *		(default: 'admin')
	 * @param string $htpasswdFilePath Path to `.htpasswd` file. If the file
	 *		exists, users will be appended, otherwise the file will be created. If no
	 *		path is specified, the .htpasswd file will be created in the secured
	 *		directory. (default: `null`)
	 * @param array $prerequisites {@inheritDoc}
	 * @param array $tags {@inheritDoc}
	 */
	public function __construct(string $dirPath, $users = 'admin', string $htpasswdFilePath = null, $prerequisites = array(), $tags = array()) {
		parent::__construct($prerequisites, $tags);
		$this->dirPath = $dirPath;
		$this->users = $users;
		$this->htPasswdFilePath = $htpasswdFilePath;
	}

	/**
	 * Secure a directory with HTTP Auth
	 *
	 * Edit or create an `.htpasswd` and `.htaccess` file.
	 *
	 * @param array $environment {@inheritDoc}
	 *
	 * @return Result
	 */
	public function act(&$environment) {

		if (realpath($this->dirPath)) {
			
			/* convert a single username into an array */
			$_users = $this->users;
			if (is_string($this->users)) {
				$_users = array(
					$this->users => null 
				);
			}
			
			/*
			 * convert an array with mixed numeric and string keys (usernames)
			 * to use string values as keys to replace numeric keys
			 */
			$new = array();
			foreach ($_users as $key => $value) {
				if (is_string($key)) {
					$new [$key] = $value;
				} else {
					$new [$value] = null;
				}
			}
			$_users = $new;
			
			/*
			 * generate passwords for all users with empty passwords in the
			 * array
			 */
			if (is_array($_users)) {
				$pwgen = new PWGen(16, true, true, true, false, false, true);
				foreach ($_users as $user => $password) {
					if (empty($password)) {
						$_users[$user] = $pwgen->generate();
					}
				}
			} else {
				throw new Action_Exception(
					__METHOD__ . ": users array `$_users` could not be understood",
					Action_Exception::PARAMETER_MISMATCH);
			}
			
			/*
			 * use $dirPath as (a fairly insecure) location for unspecified
			 * $htpasswdFilePath
			 */
			// FIXME Find a more secure default location for .htpasswd file
			if (empty($htpasswdFilePath)) {
				$htpasswdFilePath = $dirPath . '/.htpasswd';
			}
			$htpasswd = "";
			if (realpath($htpasswdFilePath)) {
				$htpasswd = file_get_contents($htpasswdFilePath);
			}
			
			/* update/create .htpasswd file */
			// TODO It would be nice to include an option to delete existing
			// .htpasswd files and re-create from scratch
			foreach ($_users as $user => $password) {
				$entry = $user . ':{SHA}' . base64_encode(
					sha1($password, TRUE)) . "\n";
				$count = 0;
				$htpasswd = preg_replace("/^$user:.*$\n/m", $entry, $htpasswd, 
					1, $count);
				if ($count < 1) {
					$htpasswd .= $entry;
				}
			}
			file_put_contents($htpasswdFilePath, $htpasswd);
			
			/* update/create .htaccess file */
			if (realpath($htpasswdFilePath)) {
				$pre = '# ' . __METHOD__ . " BEGIN\n";
				$post = '# ' . __METHOD__ . " END\n";
				// TODO It would be classy to support custom AuthName
				// parameters (e.g. the app name)
				$htaccess = "$pre<FilesMatch \"^.ht*\">\nOrder allow,deny\nDeny from all\n</FilesMatch>\n\nAuthType Basic\nAuthName \"Protected\"\nAuthUserFile $htpasswdFilePath\nRequire valid-user\n$post";
				$htaccessFilePath = $this->dirPath . '/.htaccess';
				
				if (realpath($htaccessFilePath)) {
					file_put_contents($htaccessFilePath, 
						preg_replace("/$pre(.*)$post/", $htaccess, 
							file_get_contents($htaccessFilePath)));
				} else {
					file_put_contents($htaccessFilePath, $htaccess);
				}
				
				if (!realpath($htaccessFilePath)) {
					throw new Exception(__METHOD__ . ": .htaccess file not found at `$htaccessFilePath`", Exception::INVALID_PATH);
				}
			} else {
				throw new Exception(__METHOD__ . ": .htpasswd file not found at `$htpasswdFilePath`", Exception::INVALID_PATH);
			}
		} else {
			throw new Action_Exception(
				__METHOD__ . ": Directory `{$this->dirPath}` does not exist", Exception::INVALID_PATH);
		}
		
		return new Result(
			'Directory secured with HTTP Auth',
			"`{$this->dirPath}` has been secured using HTTP Auth. The `.htpasswd` file was stored at `$htaccessFilePath`.",
			Result::SUCCESS,
			true,
			$users
		);
	}

}