<?php defined('_JEXEC') or die;

class plgVMPaymentInvoiceboxInstallerScript {
    
    public function postflight($type, $parent) {
        
        jimport('joomla.filesystem.file');
        
        if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
        $path = JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'invoicebox' . DS . 'install' . DS;
        $app  = JFactory::getApplication();
        
       
        if (!JFile::move($path . 'invoicebox.png', JPATH_ROOT . DS . 'images' . DS . 'invoicebox.png')) {
            $app->enqueueMessage('Couldnt move file');
        }        
        
        JFolder::delete($path);
    }
}