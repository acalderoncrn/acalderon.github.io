<?php
/**
 * Pts Prestashop Theme Framework for Prestashop 1.6.x
 *
 * @package   ptsblockreinsurance
 * @version   2.0
 * @author    http://www.prestabrain.com
 * @copyright Copyright (C) October 2013 prestabrain.com <@emai:prestabrain@gmail.com>
 *               <info@prestabrain.com>.All rights reserved.
 * @license   GNU General Public License version 2
 */

if (!defined('_CAN_LOAD_FILES_'))
	exit;

include_once _PS_MODULE_DIR_.'ptsblockreinsurance/ptsreinsuranceClass.php';

class PtsBlockreinsurance extends Module
{
	public function __construct()
	{
		$this->name = 'ptsblockreinsurance';
		if (version_compare(_PS_VERSION_, '1.4.0.0') >= 0)
			$this->tab = 'front_office_features';
		else
			$this->tab = 'Blocks';
		$this->version = '2.1';
		$this->author = 'PrestaBrain';

		$this->bootstrap = true;
		parent::__construct();	

		$this->displayName = $this->l('Pts Customer reassurance block');
		$this->description = $this->l('Adds an information block aimed at offering helpful information to reassure customers that your store is trustworthy.');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
	}

	public function install()
	{
		return parent::install() &&
			$this->installDB() &&
			Configuration::updateValue('PTSBLOCKREINSURANCE_NBBLOCKS', 5) &&
			$this->registerHook('footer') && $this->registerHook('header') && $this->installFixtures() &&
			// Disable on mobiles and tablets
			$this->disableDevice(Context::DEVICE_TABLET | Context::DEVICE_MOBILE);
	}
	
	public function installDB()
	{
		$return = true;
		return true;
		$return &= Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ptsreinsurance` (
				`id_ptsreinsurance` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`id_shop` int(10) unsigned NOT NULL ,
				`file_name` VARCHAR(100) NOT NULL,
				`addition_class` varchar(255) DEFAULT NULL,
				PRIMARY KEY (`id_ptsreinsurance`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;');
		
		$return &= Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ptsreinsurance_lang` (
				`id_ptsreinsurance` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`id_lang` int(10) unsigned NOT NULL ,
				`text` VARCHAR(300) NOT NULL,
				`title` varchar(255) DEFAULT NULL,
				PRIMARY KEY (`id_ptsreinsurance`, `id_lang`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;');
		
		return $return;
	}

	public function uninstall()
	{
		// Delete configuration
		return Configuration::deleteByName('PTSBLOCKREINSURANCE_NBBLOCKS') &&
			$this->uninstallDB() &&
			parent::uninstall();
	}

	public function uninstallDB()
	{
		return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'ptsreinsurance`') && Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'ptsreinsurance_lang`');
	}

	public function addToDB()
	{
		if (Tools::getIsset('nbblocks'))
		{
			for ($i = 1; $i <= (int)Tools::getValue('nbblocks'); $i++)
			{
				$filename = explode('.', $_FILES['info'.$i.'_file']['name']);
				if (isset($_FILES['info'.$i.'_file']) && isset($_FILES['info'.$i.'_file']['tmp_name']) && !empty($_FILES['info'.$i.'_file']['tmp_name']))
				{
					if ($error = ImageManager::validateUpload($_FILES['info'.$i.'_file']))
						return false;
					elseif (!($tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS')) || !move_uploaded_file($_FILES['info'.$i.'_file']['tmp_name'], $tmpName))
						return false;
					elseif (!ImageManager::resize($tmpName, dirname(__FILE__).'/img/'.$filename[0].'.jpg'))
						return false;
					unlink($tmpName);
				}
				Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'ptsreinsurance` (`filename`,`text`)
											VALUES ("'.((isset($filename[0]) && $filename[0] != '') ? pSQL($filename[0]) : '').
					'", "'.((Tools::getIsset('info'.$i.'_text') && Tools::getValue('info'.$i.'_text') != '') ? pSQL(Tools::getValue('info'.$i.'_text')) : '').'")');
			}
			return true;
		} else
			return false;
	}

	public function removeFromDB()
	{
		$dir = opendir(dirname(__FILE__).'/img');
		while (false !== ($file = readdir($dir)))
		{
			$path = dirname(__FILE__).'/img/'.$file;
			if ($file != '..' && $file != '.' && !is_dir($file))
				unlink($path);
		}
		closedir($dir);

		return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'ptsreinsurance`');
	}

	public function getContent()
	{
		$html = '';
		$id_ptsreinsurance = (int)Tools::getValue('id_ptsreinsurance');

		if (Tools::isSubmit('saveptsblockreinsurance'))
		{
			if ($id_ptsreinsurance = Tools::getValue('id_ptsreinsurance'))
				$reinsurance = new ptsreinsuranceClass((int)$id_ptsreinsurance);
			else
				$reinsurance = new ptsreinsuranceClass();
			$reinsurance->copyFromPost();
			$reinsurance->id_shop = $this->context->shop->id;
			
			if ($reinsurance->validateFields(false) && $reinsurance->validateFieldsLang(false))
			{
				$reinsurance->save();
				if (isset($_FILES['image']) && isset($_FILES['image']['tmp_name']) && !empty($_FILES['image']['tmp_name']))
				{
					if ($error = ImageManager::validateUpload($_FILES['image']))
						return false;
					elseif (!($tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS')) || !move_uploaded_file($_FILES['image']['tmp_name'], $tmpName))
						return false;
					elseif (!ImageManager::resize($tmpName, dirname(__FILE__).'/img/reinsurance-'.(int)$reinsurance->id.'-'.(int)$reinsurance->id_shop.'.jpg'))
						return false;
					unlink($tmpName);
					$reinsurance->file_name = 'reinsurance-'.(int)$reinsurance->id.'-'.(int)$reinsurance->id_shop.'.jpg';
					$reinsurance->save();
				}
				$this->_clearCache('ptsblockreinsurance.tpl');
			}
			else
				$html .= '<div class="conf error">'.$this->l('An error occurred while attempting to save.').'</div>';
		}
		
		if (Tools::isSubmit('updateptsblockreinsurance') || Tools::isSubmit('addptsblockreinsurance'))
		{
			$helper = $this->initForm();
            $reinsurance = new ptsreinsuranceClass((int)$id_ptsreinsurance);
			foreach (Language::getLanguages(false) as $lang){
				if ($id_ptsreinsurance) {
					$helper->fields_value['text'][(int)$lang['id_lang']] = $reinsurance->text[(int)$lang['id_lang']];
					$helper->fields_value['title'][(int)$lang['id_lang']] = $reinsurance->title[(int)$lang['id_lang']];
				} else {
					$helper->fields_value['text'][(int)$lang['id_lang']] = Tools::getValue('text_'.(int)$lang['id_lang'], '');
					$helper->fields_value['title'][(int)$lang['id_lang']] = Tools::getValue('title_'.(int)$lang['id_lang'], '');
                }
            }
            
			if ($id_ptsreinsurance = Tools::getValue('id_ptsreinsurance'))
			{
				$this->fields_form[0]['form']['input'][] = array('type' => 'hidden', 'name' => 'id_ptsreinsurance');
				$helper->fields_value['id_ptsreinsurance'] = (int)$id_ptsreinsurance;
 			}
			$helper->fields_value['addition_class'] = Tools::getValue('addition_class', $reinsurance->addition_class);
            
			return $html.$helper->generateForm($this->fields_form);
		}
		else if (Tools::isSubmit('deleteptsblockreinsurance'))
		{
			$reinsurance = new ptsreinsuranceClass((int)$id_ptsreinsurance);
			if (file_exists(dirname(__FILE__).'/img/'.$reinsurance->file_name))
				unlink(dirname(__FILE__).'/img/'.$reinsurance->file_name);
			$reinsurance->delete();
			$this->_clearCache('ptsblockreinsurance.tpl');
			Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
		}
		else
		{
			$helper = $this->initList();
			return $html.$helper->generateList($this->getListContent((int)Configuration::get('PS_LANG_DEFAULT')), $this->fields_list);
		}

		if (isset($_POST['submitModule']))
		{
			Configuration::updateValue('PTSBLOCKREINSURANCE_NBBLOCKS', ((Tools::getIsset('nbblocks') && Tools::getValue('nbblocks') != '') ? (int)Tools::getValue('nbblocks') : ''));
			if ($this->removeFromDB() && $this->addToDB())
			{
				$this->_clearCache('ptsblockreinsurance.tpl');
				$output = '<div class="conf confirm">'.$this->l('The block configuration has been updated.').'</div>';
			}
			else
				$output = '<div class="conf error"><img src="../img/admin/disabled.gif"/>'.$this->l('An error occurred while attempting to save.').'</div>';
		}
	}

	protected function getListContent($id_lang)
	{
		return  Db::getInstance()->executeS('
			SELECT r.`id_ptsreinsurance`, r.`id_shop`, r.`file_name`, r.`addition_class`, rl.`text`, rl.`title`
			FROM `'._DB_PREFIX_.'ptsreinsurance` r
			LEFT JOIN `'._DB_PREFIX_.'ptsreinsurance_lang` rl ON (r.`id_ptsreinsurance` = rl.`id_ptsreinsurance`)
			WHERE `id_lang` = '.(int)$id_lang.' '.Shop::addSqlRestrictionOnLang());
	}

	protected function initForm()
	{
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$this->fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('New reassurance block'),
			),
			'input' => array(
				array(
					'type' => 'file',
					'label' => $this->l('Image'),
					'name' => 'image',
					'value' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('Title'),
					'lang' => true,
					'name' => 'title',
				),
				array(
					'type' => 'textarea',
					'label' => $this->l('Text'),
					'lang' => true,
					'autoload_rte' => true,
					'name' => 'text',
					'cols' => 40,
					'rows' => 10
				),
                array(
					'type' => 'text',
					'label' => $this->l('Addition Class'),
					'name' => 'addition_class',
				),
			),
			'submit' => array(
				'title' => $this->l('Save'),
			)
		);

		$helper = new HelperForm();
		$helper->module = $this;
		$helper->name_controller = 'ptsblockreinsurance';
		$helper->identifier = $this->identifier;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		foreach (Language::getLanguages(false) as $lang)
			$helper->languages[] = array(
				'id_lang' => $lang['id_lang'],
				'iso_code' => $lang['iso_code'],
				'name' => $lang['name'],
				'is_default' => ($default_lang == $lang['id_lang'] ? 1 : 0)
			);

		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		$helper->toolbar_scroll = true;
		$helper->title = $this->displayName;
		$helper->submit_action = 'saveptsblockreinsurance';
		$helper->toolbar_btn =  array(
			'save' =>
			array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
			),
			'back' =>
			array(
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);
		return $helper;
	}

	protected function initList()
	{
		$this->fields_list = array(
			'id_ptsreinsurance' => array(
				'title' => $this->l('ID'),
				'width' => 120,
				'type' => 'text',
				'search' => false,
				'orderby' => false
			),
			'title' => array(
				'title' => $this->l('Title'),
				'width' => 140,
				'type' => 'text',
				'search' => false,
				'orderby' => false
			),
			'text' => array(
				'title' => $this->l('Text'),
				'width' => 140,
				'type' => 'text',
				'search' => false,
				'orderby' => false
			),
		);

		if (Shop::isFeatureActive())
			$this->fields_list['id_shop'] = array('title' => $this->l('ID Shop'), 'align' => 'center', 'width' => 25, 'type' => 'int');

		$helper = new HelperList();
		$helper->shopLinkType = '';
		$helper->simple_header = false;
		$helper->identifier = 'id_ptsreinsurance';
		$helper->actions = array('edit', 'delete');
		$helper->show_toolbar = true;
		$helper->imageType = 'jpg';
		$helper->toolbar_btn['new'] =  array(
			'href' => AdminController::$currentIndex.'&configure='.$this->name.'&add'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
			'desc' => $this->l('Add new')
		);

		$helper->title = $this->displayName;
		$helper->table = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		return $helper;
	}
    
    public function hookHeader(){
        $this->context->controller->addCSS($this->_path.'style.css', 'all');
    }
    
	public function hookRightColumn($params)
	{
		if (!$this->isCached('ptsblockreinsurance.tpl', $this->getCacheId()))
		{
			$infos = $this->getListContent($this->context->language->id);
			$this->context->smarty->assign(array('infos' => $infos, 'nbblocks' => count($infos)));
		}
		return $this->display(__FILE__, 'ptsblockreinsurance.tpl', $this->getCacheId());
	}
	
	public function hookDisplayPromoteTop($params)
	{
		if (!$this->isCached('ptsblockreinsurance.tpl', $this->getCacheId()))
		{
			$infos = $this->getListContent($this->context->language->id);
			$this->context->smarty->assign(array('infos' => $infos, 'nbblocks' => count($infos)));
		}
		return $this->display(__FILE__, 'ptsblockreinsurance.tpl', $this->getCacheId());
	}

    public function hookDisplayTop($params)
    {
    	return $this->hookRightColumn($params);
    }

    /***/
    public function hookDisplaySlideshow($params) 
    {
        return $this->hookRightColumn($params);
    }

	public function hookdisplayTopColumn($params) 
    {
        return $this->hookRightColumn($params);
    }

    public function hookDisplayContentBottom($params) 
    {
        return $this->hookRightColumn($params);
    }

    public function hookDisplayBottom($params) 
    {
        return $this->hookRightColumn($params);
    }

    public function hookDisplayFooterTop($params) 
    {
        return $this->hookRightColumn($params);
    }

    public function hookDisplayFooterBottom($params) 
    {
		return $this->hookRightColumn($params);
    }

    /***/
    public function hookDisplayHome($params) 
    {
        return $this->hookRightColumn($params);
    }

    public function hookDisplayLeftColumn($params) 
    {
        return $this->hookRightColumn($params);
    }
    
    public function hookDisplayFooter($params) 
    {
        return $this->hookRightColumn($params);
    }  

	public function installFixtures()
	{
		return true;
		$return = true;
		$tab_texts = array(
			array('addition_class' => '', 'title' => $this->l('Money back guarantee.'), 'text' => $this->l('Money back guarantee.'), 'file_name' => 'reinsurance-1-1.jpg'),
			array('addition_class' => '', 'title' => $this->l('In-store exchange.'), 'text' => $this->l('In-store exchange.'), 'file_name' => 'reinsurance-2-1.jpg'),
			array('addition_class' => '', 'title' => $this->l('Payment upon shipment.'), 'text' => $this->l('Payment upon shipment.'), 'file_name' => 'reinsurance-3-1.jpg'),
			array('addition_class' => '', 'title' => $this->l('Free Shipping.'), 'text' => $this->l('Free Shipping.'), 'file_name' => 'reinsurance-4-1.jpg'),
			array('addition_class' => '', 'title' => $this->l('100% secure payment processing.'), 'text' => $this->l('100% secure payment processing.'), 'file_name' => 'reinsurance-5-1.jpg')
		);
		
		foreach($tab_texts as $tab)
		{
			$reinsurance = new ptsreinsuranceClass();
			foreach (Language::getLanguages(false) as $lang){
				$reinsurance->text[$lang['id_lang']] = $tab['text'];
				$reinsurance->title[$lang['id_lang']] = $tab['title'];
			}
			$reinsurance->file_name = $tab['file_name'];
			$reinsurance->addition_class = $tab['addition_class'];
			$reinsurance->id_shop = $this->context->shop->id;
			$return &= $reinsurance->save();
		}
		return $return;
	}
}
