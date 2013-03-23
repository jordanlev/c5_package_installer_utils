<?php defined('C5_EXECUTE') or die(_("Access Denied."));

/**
 * https://github.com/jordanlev/c5_package_installer_utils
 * Version 2013-03-23
 */

class PackageInstallerUtils {
	private $pkg;
	
	public function __construct(&$pkg) {
		$this->pkg = $pkg;
	}
	
	public static function deleteTables($tableNames = array()) {
	//When a block is uninstalled, C5 doesn't delete any of its tables (primary or otherwise), so do it manually during the package uninstall() method if desired.
		if (!empty($tableNames)) {
			$db = Loader::db();
			$sql = 'DROP TABLE IF EXISTS ' . implode(', ', $tableNames);
			$db->Execute($sql);
		}
	}
	
	public function getOrInstallBlockType($btHandle) {
		$bt = BlockType::getByHandle($btHandle);
		if (empty($bt)) {
			BlockType::installBlockTypeFromPackage($btHandle, $this->pkg);
			$bt = BlockType::getByHandle($btHandle);
		}
		return $bt;
	}
	
	public function getOrInstallTheme($themeHandle) {
		Loader::model('page_theme');
		
		$theme = PageTheme::getByHandle($themeHandle);
		if (is_null($theme)) {
			$theme = PageTheme::add($themeHandle, $this->pkg);
		}
		
		return $theme;
	}
	
	public function getOrInstallCollectionAttributeSelect($akHandle, $akName, $values = array(), $allowMultipleValues = false, $allowOtherValues = false, $displayOrder = 'display_asc', $isSearchableInFrontEnd = false, $isSearchableInDashboardSitemap = false) {
		Loader::model('attribute/type');
		Loader::model('attribute/types/select/controller');
		Loader::model('attribute/categories/collection');
		
		if (!in_array($displayOrder, array('display_asc', 'alpha_asc', 'popularity_desc'))) {
			$displayOrder = 'display_asc';
		}
		
		$akSettings = array(
			'akHandle' => $akHandle,
			'akName' => $akName,
			'akSelectAllowMultipleValues' => $allowMultipleValues,
			'akSelectAllowOtherValues' => $allowOtherValues,
			'akSelectOptionDisplayOrder' => $displayOrder, 
			'akIsSearchableIndexed' => $isSearchableInFrontEnd,
			'akIsSearchable' => $isSearchableInDashboardSitemap,
		);
		
		
		$ak = CollectionAttributeKey::getByHandle($akHandle);
		if (!is_object($ak)) {
			$at = AttributeType::getByHandle('select');
			$ak = CollectionAttributeKey::add($at, $akSettings, $this->pkg);
		}
		
		//Add values to select list
		foreach ($values as $val) {
			SelectAttributeTypeOption::add($ak, $val);
		}
		
		return $ak;
	}
	
	public function getOrInstallCollectionType($ctHandle, $ctName) {
		Loader::model('collection_types');
		
		$ct = CollectionType::getByHandle($ctHandle);
		if (is_null($ct)) {
			$ct = CollectionType::add(array('ctHandle' => $ctHandle, 'ctName' => $ctName), $this->pkg);
		}
		
		return $ct;
	}
	
	
	public function associateAttributeWithCollectionType(&$ak, &$ct) {
		$ct->populateAvailableAttributeKeys();
		if (!$ct->isAvailableCollectionTypeAttribute($ak->getAttributeKeyID())) {
			$ct->assignCollectionAttribute($ak);
		}
	}
	
	public function addAttributeToSet(&$ak, $asHandle) {
		$as = AttributeSet::getByHandle($asHandle);
		if (!is_null($as)) {
			$as->addKey($ak);
		}
	}
	
	public function setPageCollectionType(&$page, &$ct) {
		$data = array('ctID' => $ct->getCollectionTypeID());
		$page->update($data);
	}
	
	public function setPageAttributeValue(&$page, &$ak, $value) {
		$page->setAttribute($ak, $value); //dev note: if you didn't have the $ak object, you could pass in the akHandle string instead
	}
	
	public function getOrAddSinglePage($cPath, $cName = '', $cDescription = '') {
		Loader::model('single_page');
		
		$sp = SinglePage::add($cPath, $this->pkg);
		
		if (is_null($sp)) {
			//SinglePage::add() returns null if page already exists
			$sp = Page::getByPath($cPath);
		} else {
			//Set page title and/or description...
			$data = array();
			if (!empty($cName)) {
				$data['cName'] = $cName;
			}
			if (!empty($cDescription)) {
				$data['cDescription'] = $cDescription;
			}
			
			if (!empty($data)) {
				$sp->update($data);
			}
		}
		
		return $sp;
	}
	
	public function addOrSetConfigItem($key, $val) {
		$this->pkg->saveConfig($key, $val);
		
		//The above call is shortcut for this:
		// $co = new Config();
		// $co->setPackageObject($this->pkg);
		// $co->save($key, $val);
	}
	
	public function getConfigValue($key) {
		$this->pkg->config($key, $val);
		
		//The above call is shortcut for this:
		// $co = new Config();
		// $co->setPackageObject($this->pkg);
		// return $co->get($key);
	}
	
	public function clearConfigItem($key) {
		$this->pkg->clearConfig($key);
	}
	
	/**
	 * Creates a new page area (or global area stack) if it doesn't exist already.
	 * Then, if we created it *or* if it existed already but had no blocks in it,
	 * we add new blocks to it based on the given info.
	 *
	 * @param array $blocksInfoArray should look like this:
	 *  array => (
	 *      array(
	 *          'btHandle' => 'your_block_type_handle',
	 *          'data' => array('field1name' => 'field1data', 'field2name' => 'field2data', etc.),
	 *      ),
	 *      array( //this one has a custom template...
	 *          'btHandle' => 'your_other_block_type_handle',
	 *          'data' => array('field1name' => 'field1data', 'field2name' => 'field2data', etc.),
	 *          'template' => 'custom_template', //*OR* 'custom_template/view' (if it's a view.php file inside a template folder)
	 *      ),
	 *      array( //this one has no data (and no custom template)...
	 *          'btHandle' => 'another_block_type_handle',
	 *      );
	 *  );
	 * 
	 * @param string $arHandleOrStackName Area handle (for a page area) or global area handle (which is the stack name).
	 * @param object $c The collection object of the page we are adding blocks to (or NULL to indicate this is a global area).
	 * 
	 * @return object Returns the collection object (for page areas) or the stack object (for global areas)
	 */
	public function addBlocksToNewOrEmptyArea($blocksInfoArray, $arHandleOrStackName, &$c = null) {
		if (is_null($c)) {
			return $this->addBlocksToNewOrEmptyGlobalArea($arHandleOrStackName, $blocksInfoArray);
		} else {
			return $this->addBlocksToNewOrEmptyPageArea($c, $arHandleOrStackName, $blocksInfoArray);
		}
	}
	
		private function addBlocksToNewOrEmptyPageArea(&$c, $arHandle, $blocksInfoArray) {
			$a = Area::getOrCreate($c, $arHandle);
			if (count($a->getAreaBlocksArray($c)) == 0) {
				foreach ($blocksInfoArray as $blockInfo) {
					$data = empty($blockInfo['data']) ? array() : $blockInfo['data'];
					$template = empty($blockInfo['template']) ? '' : $blockInfo['template'];
					$this->addBlockToArea($c, $arHandle, $blockInfo['btHandle'], $data, $template);
				}
			}
			return $c;
		}
	
		private function addBlocksToNewOrEmptyGlobalArea($stackName, $blocksInfoArray) {
			//Note that first we explicitly create the global area
			// (because we want to add some blocks to it right now
			// -- if we didn't want to add any blocks to it right now,
			// then we wouldn't need to create it here because C5 would
			// automatically create it when a page is first viewed).
			$stack = $this->getOrCreateGlobalArea($stackName);
		
			//Check that the stack is empty (we don't want to add blocks to it if it already exists and has other blocks in it)
			if (count($stack->getBlocks(STACKS_AREA_NAME)) == 0) {

				foreach ($blocksInfoArray as $blockInfo) {
					$data = empty($blockInfo['data']) ? array() : $blockInfo['data'];
					$template = empty($blockInfo['template']) ? '' : $blockInfo['template'];
					$this->addBlockToArea($stack, STACKS_AREA_NAME, $blockInfo['btHandle'], $data, $template);
				}

			}
		
			return $stack;
		}
	
		private function addBlockToArea(&$c, $arHandle, $btHandle, $data = array(), $template = '') {
			$bt = BlockType::getByHandle($btHandle);
			if (is_null($bt)) {
				return;
			}
		
			$b = $c->addBlock($bt, $arHandle, $data);
		
			if (!empty($template)) {
				$ext = pathinfo($template, PATHINFO_EXTENSION);
				$bFilename = $template . (empty($ext) ? '.php' : '');
				$b->setCustomTemplate($bFilename); // full file name (including ".php" extension)
			}
		}
	
		private function getOrCreateGlobalArea($arHandle) {
			if (version_compare(APP_VERSION, '5.5.2', '<')) {
				 //due to a bug in 5.5.0 and 5.5.1, Stack::getOrCreateGlobalArea() doesn't return the newly created global area stack
				Stack::getOrCreateGlobalArea($arHandle);
				$stack = Stack::getByName($arHandle);
			} else {
				$stack = Stack::getOrCreateGlobalArea($arHandle);
			}
			return $stack;
		}
	//END addBlocksToNewOrEmptyArea() helper functions
	
	public function getAreaBlocks(&$c, $arHandle, $limitToBtHandle = '', $limitToCount = 0) {
		$pageBlocks = $c->getBlocks($arHandle);
		$limitToCount = empty($limitToCount) ? count($pageBlocks) : $limitToCount;
		$retBlocks = array();
		foreach ($pageBlocks as $pb) {
			if (empty($limitToBtHandle) || ($limitToBtHandle == $pb->getBlockTypeHandle())) {
				$retBlocks[] = $pb;
			}
			if (count($retBlocks) >= $limitToCount) {
				break;
			}
		}
		return $retBlocks;
	}
	public function getAreaFirstBlock(&$c, $arHandle, $limitToBtHandle = '') {
		$blocks = $this->getAreaBlocks($c, $arHandle, $limitToBtHandle, 1);
		return empty($blocks) ? null : $blocks[0];
	}
	
	/**
	 * Deletes a block from a global area *IF* it's the only block in the area 
	 * AND if it's of the given blockType AND if its data matches the given data exactly (for the fields given).
	 * The given data should be an array with keys as field names and values as field values.
	 * We will look to the block for each field you have a key for in the given array,
	 * but will not check all block fields against the given data. This means you should always include
	 * all fields that a block type could have in your given data array (even if the value is empty/null)!
	 */
	public function deleteExactMatchBlockFromGlobalArea($globalAreaName, $btHandle, $data) {
		$stack = Stack::getByName($globalAreaName);
		if (!is_null($stack)) {
			$blocks = $stack->getBlocks(STACKS_AREA_NAME);
			if (count($blocks) == 1) {
				$block = $blocks[0];
				if ($block->getBlockTypeHandle() == $btHandle) {
					$blockRecord = $block->getInstance()->getBlockControllerData();
					foreach ($data as $key => $val) {
						if (!property_exists($blockRecord, $key)) {
							return;
						} else if ($blockRecord->$key !== $val) {
							return;
						}
					}
					//If we made it this far, it means all given data existed and matched in the block record,
					// so go ahead and delete the block.
					$block->deleteBlock();
				}
			}
		}
	}
	
	public function deleteEmptyGlobalArea($globalAreaName) {
		$stack = Stack::getByName($globalAreaName);
		if (!is_null($stack) && count($stack->getBlocks(STACKS_AREA_NAME))) {
			$this->deleteGlobalArea($globalAreaName);
		}
	}
	
	public function deleteGlobalArea($globalAreaName) {
		//Note: just calling GlobalArea::deleteByName() doesn't work. Delete the stack instead.
		$stack = Stack::getByName($globalAreaName);
		if (!is_null($stack)) {
			$stack->delete();
		}
	}
	
/*** UNTESTED, UNREFACTORED, LESSER-USED THINGS.... ***/
	
	public function installMailImporter($miHandle, $miUsername, $miPassword, $miServer, $miPort, $miEncryption, $miIsEnabled) {
	//You probably shouldn't use this -- it's a pain in the ass (and doesn't work out-of-the-box for importing mail from external systems -- it assumes it's for mail that originated FROM the website itself because it looks for tokens generated by C5, e.g. Private Messages)
	
		//Install Mail Importer
		Loader::library('mail/importer');
		$mailImporterArgs = array(
			'miHandle' => $miHandle, //sample_gmail_import
			'miUsername' => $miUsername, // example@gmail.com
			'miPassword' => $miPassword, // gmail password
			'miServer' => $miServer, // 'pop.gmail.com'
			'miPort' => $miPort, // '995'
			'miEncryption' => $miEncryption, // 'SSL'
			'miIsEnabled' => $miIsEnabled, // 1
		);
		MailImporter::add($mailImporterArgs, $this->pkg);
	
		//Ensure the /files/tmp/ directory exists (the mail importer relies on this):
		if (!is_dir(DIR_TMP)) {
			mkdir(DIR_TMP);
		}
	
	}
	
	public function setCreationTimestampField($tableName, $fieldName) {
		//ADODB's schema XML doesn't let you specify a default value
		// for TIMESTAMP fields (it does, but it sets the CURRENT_TIMESTAMP value
		// on both INSERT *and* UPDATE, which is useless to us).
		$db = Loader::db();
		$sql = "ALTER TABLE {$tableName} CHANGE {$fieldName} {$fieldName} TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
		$db->Execute($sql);
	}
	
	public function installCollectionAttributeType($atHandle, $atName) {
		$at = AttributeType::getByHandle($atHandle);
		if (!$at || !intval($at->getAttributeTypeID())) {
			$at = AttributeType::add($atHandle, $atName, $this->pkg);
			$akc = AttributeKeyCategory::getByHandle('collection');
			$akc->associateAttributeKeyType($at);
		}
		return $at;
	}
	
	public function getOrInstallCollectionAttributeGeneric(&$at, $akHandle, $akName, $isSearchableInFrontEnd = false, $isSearchableInDashboardSitemap = false) {
		Loader::model('attribute/categories/collection');
		
		$akSettings = array(
			'akHandle' => $akHandle,
			'akName' => $akName,
			'akIsSearchableIndexed' => $isSearchableInFrontEnd,
			'akIsSearchable' => $isSearchableInDashboardSitemap,
		);
		
		$ak = CollectionAttributeKey::getByHandle($akHandle);
		if (!is_object($ak)) {
			$ak = CollectionAttributeKey::add(
				$at,
				$akSettings,
				$this->pkg
			);
		}
		
		return $ak;
	}
	
	public function installJob($jHandle) {
		Loader::model('job');
		$job = Job::getByHandle($jHandle, $this->pkg);
		if (!$job || !is_object($job) || !intval($job->jID)) {
			Job::installByPackage($jHandle, $this->pkg);
		}
	}
	
	public function installSeedDataFromFile($sqlFilePath) {
		$db = Loader::db();
		$sql = file_get_contents($sqlFilePath);
		$r = $db->execute($sql);
		if (!$r) { 
			throw new Exception(t('Unable to install data: %s', $db->ErrorMsg()));
		}
	}
	
	/* HANDY TECHNIQUE FOR ADDING AN EVENT HANDLER IN A PACKAGE CONTROLLER ITSELF (minimal code, requires no other files):
	 *
	 *   public function on_start() {
	 *      if (!defined('ENABLE_APPLICATION_EVENTS')) { define('ENABLE_APPLICATION_EVENTS', true); } //<--REQUIRED IN 5.5+ (despite what c5 docs say!!)
	 *      
	 *   	$event = 'on_before_render';
	 *   	Events::extend($event, __CLASS__, $event, __FILE__);
	 *   }
	 *   
	 *   public function on_before_render(&$page) { //<--just an example of an event -- could be any event though (and then the function args would be different)
	 *   	//do whatevs...
     *   }
     */

}
