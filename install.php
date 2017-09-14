<?php defined('_JEXEC') or die;

class plgVMPaymentInvoiceboxInstallerScript {
    
    public function postflight($type, $parent) {
        
        jimport('joomla.filesystem.file');
        
        if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
        $path = JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'invoicebox' . DS . 'install' . DS;
        $app  = JFactory::getApplication();
        
        if (!JFolder::move($path . 'helpers', JPATH_ROOT . DS . 'libraries' . DS . 'invoicebox')) {
            $app->enqueueMessage('Couldnt move file');
        }

        if (!JFile::move($path . 'fail.php', JPATH_ROOT . DS . 'fail.php')) {
            $app->enqueueMessage('Couldnt move file');
        }

        if (!JFile::move($path . 'success.php', JPATH_ROOT . DS . 'success.php')) {
            $app->enqueueMessage('Couldnt move file');
        }

        if (!JFile::move($path . 'invoicebox.gif', JPATH_ROOT . DS . 'images' . DS . 'invoicebox.png')) {
            $app->enqueueMessage('Couldnt move file');
        }        
        
        JFolder::delete($path);
    }
}