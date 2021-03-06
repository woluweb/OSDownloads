<?php
/**
 * @package   OSDownloads
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2016 Open Source Training, LLC. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Alledia\OSDownloads\Free\Joomla\View\Site;

defined('_JEXEC') or die();

use Alledia\Framework\Factory;
use Alledia\OSDownloads\Free\Joomla\Component\Site as FreeComponentSite;
use Joomla\Registry\Registry;
use JRoute;
use JText;

if (!class_exists('JViewLegacy')) {
    jimport('legacy.view.legacy');
}


class Item extends Base
{
    /**
     * @var object
     */
    protected $item = null;

    /**
     * @var int
     */
    protected $itemId = null;

    /**
     * @var object[]
     */
    protected $paths = null;

    /**
     * @var Registry
     */
    protected $params = null;

    /**
     * @var bool
     */
    protected $isPro = null;

    /**
     * @var \OSDownloadsModelItem
     */
    protected $model = null;

    public function display($tpl = null)
    {
        $app       = Factory::getApplication();
        $component = FreeComponentSite::getInstance();
        $model     = $component->getModel('Item');
        $params    = $app->getParams('com_osdownloads');
        $id        = (int)$app->input->getInt('id');
        $itemId    = (int)$app->input->getInt('Itemid');

        if (empty($id)) {
            $id = (int)$params->get("document_id");
        }

        $item = $model->getItem($id);

        if (empty($item)) {
            throw new \Exception(JText::_('COM_OSDOWNLOADS_THIS_DOWNLOAD_ISNT_AVAILABLE'), 404);
        }

        $paths = null;
        $this->buildBreadcrumbs($paths, $item);

        // Load the extension
        $component->loadLibrary();
        $isPro = $component->isPro();

        $this->item   = $item;
        $this->itemId = $itemId;
        $this->paths  = $paths;
        $this->params = $params;
        $this->isPro  = $isPro;
        $this->model  = $model;

        parent::display($tpl);
    }

    public function buildPath(&$paths, $categoryID)
    {
        if (empty($categoryID)) {
            return;
        }

        $db = Factory::getDbo();
        $db->setQuery("SELECT *
                       FROM `#__categories`
                       WHERE extension='com_osdownloads'
                           AND id = " . $db->q((int)$categoryID));
        $category = $db->loadObject();

        if ($category) {
            $paths[] = $category;
        }

        if ($category && $category->parent_id) {
            $this->buildPath($paths, $category->parent_id);
        }
    }

    protected function buildBreadcrumbs(&$paths, $item)
    {
        $this->buildPath($paths, $item->cate_id);

        $app     = Factory::getApplication();
        $pathway = $app->getPathway();
        $itemID  = $app->input->getInt('Itemid');

        $countPaths = count($paths) - 1;
        for ($i = $countPaths; $i >= 0; $i--) {
            $pathway->addItem(
                $paths[$i]->title,
                JRoute::_("index.php?option=com_osdownloads&view=downloads&id={$paths[$i]->id}&Itemid={$itemID}")
            );
        }
    }
}
