<?php
	
namespace Battis\BatchAction\Actions;

use Battis\BatchAction\Action;
use Battis\BatchAction\Action_Exception;
use Battis\BatchAction\Result;
use PWGen;

/**
 * An action that generates an .htacess file to use HTTP Auth to protect a
 * specific directory
 * 
 * @author Seth Battis <seth@battis.net>
 */
class HttpAuthDirectoryAction extends Action {
	
	/** @var string The path to the directory to be secured */
	protected $dirPath;
	
	/** @var string[] Associative array of passwords with usernames as keys */
	protected $users;
	
	/** @var string The path to the `.htpasswd` file */
	protected $htpasswdFilePath;
	
	/**
	 * {@inheritDoc}
	 *
	 * {@inheitDoc}
	 * 
	 * @param string $dirPath Path to the directory to be secured
	 * @param string|string[] $users Associative array of passwords with usernames
	 *		as keys. Can also be a single username. If no password is provided for a
	 *		user, one will be generated automatically. Array can represent a user with
	 *		no password as either `'username' => null` or simply `'username'` (with no
	 *		associative key). (default: `array('admin' => null)`)
	 * @param string $htpasswdFilePath Path to the `.htpasswd` file to be created/
	 *		updated. If no path is provided, the `.htpasswd` file will be created in
	 *		the protected directory (not best practice). (default: empty string)
	 * @param array $prerequisites {@inheritDoc}
	 * @param array $tags {@inheritDoc}
	 */
	public function __construct($dirPath, $users = array('admin' => null), $htpasswdFilePath = '', $prerequisites = array(), $tags = array()) {
		parent::__construct($prerequisites, $tags);
		$this->setDirPath($dirPath);
		$this->setUsers($users);
		$this->setHtpasswdFilePath($htpasswdFilePath);
	}
	
	/**
	 * Overrideable method to process `$dirPath` parameter of `__construct()`
	 * 
	 * @param string $dirPath Path to the directory to be secured
	 *
	 * @return string
	 */
	protected function setDirPath($dirPath) {
		if (!empty((string) $dirPath)) {
			$this->dirPath = $dirPath;
		} else {
			throw new Action_Exception(
				'Expected a non-empty directory file path string, received' . (is_string($dirPath) ? ' empty string' : ' instance of `' . get_class($dirPath) . '`') . ' instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
		
		return $this->dirPath;
	}
	
	/**
	 * Overrideable method to process `$users` parameter of `__construct()`
	 * 
	 * @param string|string[] $users Associative array of passwords with usernames
	 *		as keys. Can also be a single username. If no password is provided for a
	 *		user, one will be generated automatically. Array can represent a user with
	 *		no password as either `'username' => null` or simply `'username'` (with no
	 *		associative key). (default: `array('admin' => null)`)
	 *
	 * @return string[]
	 */
	protected function setusers($users) {
		if (is_string($users) && !empty($users)) {
			$this->users = array($users => null);
		} elseif (is_array($users)) {
			foreach ($users as $key => $value) {
				if (is_string($key)) {
					$this->users[$key] = (string) $value;
				} else {
					$this->users[$value] = null;
				}
			}
		} else {
			throw new Action_Exception(
				'Expected a username string, an array(username) or an array(username => password), received' . (is_string($users) ? ' empty string' : ' instance of `' . get_class($users) . '`') . ' instead.',
				Action_Exception::PARAMETER_MISMATCH
			);
		}
		
		return $this->users;
	}
	
	/**
	 * Overrideable method to process `$htpasswdFilePath` parameter of `__construct()`
	 * 
	 * @param string $htpasswdFilePath Path to the `.htpasswd` file to be created/
	 *		updated. If no path is provided, the `.htpasswd` file will be created in
	 *		the protected directory (not best practice). (default: empty string)
	 *
	 * @return string
	 */
	protected function setHtpasswdFilePath($htpasswdFilePath) {
		$this->htpasswdFilePath = (string) $htpasswdFilePath;
		return $this->htpasswdFilePath;
	}

	/**
	 * {@inheritDoc}
	 *
	 * {@inheritDoc}
	 *
	 * @param array $environment {@inheritDoc}
	 *
	 * @return Result {@inheritDoc}
	 */
	public function act(array &$environment) {
		return $this->httpAuthDirectory($this->dirPath, $this->users, $this->htpasswdFilePath);
	}
	
	/**
	 * Secure a directory with HTTP Auth
	 * 
	 * Createa a `.htaccess` file in the specified directory that refers to either
	 * a newly-created or updated `.htpasswd` file provided access for all
	 * specified users. Users without passwords will have a password generated
	 * automatically. The usernames and passwords are returned in clear text in the
	 * `Result` data element.
	 *
	 * @param string $dirPath Path to the directory to be secured
	 * @param string[] $users Associative array of passwords with usernames
	 *		as keys. If no password is provided for a
	 *		user, one will be generated automatically. (default: `array('admin' =>
	 *		null)`)
	 * @param string $htpasswdFilePath Path to the `.htpasswd` file to be created/
	 *		updated. If no path is provided, the `.htpasswd` file will be created in
	 *		the protected directory (not best practice).
	 *
	 * @return Result
	 */
	protected function httpAuthDirectory($dirPath, array $users, $htpasswdFilePath) {
		$dirPath = (string) $dirPath;
		$htpasswdFilePath = (string) $htpasswdFilePath;
	
		if (realpath($dirPath)) {
			/*
			 * generate passwords for all users with empty passwords in the
			 * array
			 */
			// TODO include PWGen configuration options in constructor
			$pwgen = new PWGen(16, true, true, true, false, false, true);
			$_users = array();
			foreach ($users as $user => $password) {
				if (empty($password)) {
					$_users[$user] = $pwgen->generate();
				} else {
					$_users[$user] = $password;
				}
			}
			
			/*
			 * use $dirPath as (a fairly insecure) location for unspecified
			 * $htpasswdFilePath
			 */
			// TODO Find a more secure default location for .htpasswd file
			if (empty($htpasswdFilePath)) {
				$htpasswdFilePath = "$dirPath/.htpasswd";
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
				$pre = '# ' . __CLASS__ . " BEGIN\n\n";
				$post = "\n# " . __CLASS__ . " END\n";
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
					throw new Action_Exception(
						__METHOD__ . ": .htaccess file not found at `$htaccessFilePath`",
						Action_Exception::FILE_NOT_FOUND
					);
				}
			} else {
				throw new Action_Exception(
					__METHOD__ . ": .htpasswd file not found at `$htpasswdFilePath`",
					Action_Exception::FILE_NOT_FOUND
				);
			}
		} else {
			throw new Action_Exception(
				__METHOD__ . ": Directory `{$this->dirPath}` does not exist",
				Action_Exception::FILE_NOT_FOUND
			);
		}
		
		return new Result(
			get_class($this),
			'Directory secured with HTTP Auth',
			"`{$this->dirPath}` has been secured using HTTP Auth. The `.htpasswd` file was stored at `$htaccessFilePath`.",
			Result::SUCCESS,
			true,
			$_users
		);
	}
}