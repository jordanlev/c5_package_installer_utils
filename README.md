c5_package_installer_utils
==========================

A bunch of utility functions which encapsulate common installation tasks in package controllers.

## Installation
1. Create a `libraries` directory in your package (if it doesn't already exist).
2. Drop the `package_installer_utils.php` file into that `libraries` directory.
3. To prevent conflicts with other packages using this library, change the name of the file so it contains your package handle, then change the class name in the file to correspond with that.

## Usage
I tried to make most of the functions usable whether something is already installed or not (a lot of function names are in the "getOrCreate___()" style). This is because I like to keep things DRY and have both the `install()` and `upgrade()` controller methods use the same code (so I usually make a `private _upgrade()` method that accepts the `$pkg` object and gets called by both `install()` and `upgrade()`).

For example (assuming we changed the library file name to `my_cool_thing_package_installer_utils.php` and the class name within that file to `MyCoolThingPackageInstallerUtils`):

    <?php defined('C5_EXECUTE') or die(_("Access Denied."));

    class MyCoolThingPackage extends Package {
    	protected $pkgHandle = 'my_cool_thing';
    	protected $appVersionRequired = '5.5';
    	protected $pkgVersion = '1.0';
        
    	public function getPackageName() {
    		return t('My Cool Thing');
    	}

    	public function getPackageDescription() {
    		return t('This thing is really cool.');
    	}
        
    	public function install($args) {
    		$pkg = parent::install();
    		$this->_upgrade($pkg);
    	}

    	public function upgrade() {
    		$pkg = Package::getByHandle($this->pkgHandle);
    		$this->_upgrade($pkg);
    		parent::upgrade();
    	}

    	private function _upgrade(&$pkg) {
    		Loader::library('my_cool_thing_package_installer_utils', $this->pkgHandle);
    		$utils = new MyCoolThingPackageInstallerUtils($pkg);
            
    		$utils->getOrInstallTheme('beautiful_stuff');
            
            $ctHome = $utils->getOrInstallCollectionType('home', t('Home'));
    		
    		$utils->getOrInstallBlockType('handy_block');
            
    		$footerBlocks = array(
				array(
					'btHandle' => 'content',
					'data' => array('content' => '<p>&copy;2012 My Awesome Site</p>',
				),
				array(
					'btHandle' => 'handy_block',
				),
			);
			$utils->addBlocksToNewOrEmptyArea($footerBlocks, 'Footer');
    	}

    }
    