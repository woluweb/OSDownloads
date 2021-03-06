<?php
/**
 * @package   OSDownloads
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2016 Open Source Training, LLC. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Alledia\OSDownloads\Free\Joomla\Model;

defined('_JEXEC') or die();

use Alledia\Framework\Joomla\Model\Base as BaseModel;
use Alledia\Framework\Factory;
use Alledia\OSDownloads\Free\Joomla\Component\Site as FreeComponentSite;
use Alledia\OSDownloads\Free\Helper;
use JRoute;
use JEventDispatcher;
use JPluginHelper;
use JFactory;


class Item extends BaseModel
{
    /**
     * Get document's data from db
     *
     * @param  int $documentId
     *
     * @return object
     */
    public function getItem($documentId)
    {
        $db    = $this->getDbo();
        $query = $this->getItemQuery($documentId);

        $db->setQuery($query);

        $item = $db->loadObject();

        if (!empty($item)) {
            $item->agreementLink = '';
            if ((bool)$item->require_agree && (int)$item->agreement_article_id) {
                \JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');

                $item->agreementLink = JRoute::_(\ContentHelperRoute::getArticleRoute($item->agreement_article_id));
            }
        }

        return $item;
    }

    /**
     * Get the document's query
     *
     * @param  int $documentId
     *
     * @return \JDatabaseQuery
     */
    public function getItemQuery($documentId = null)
    {
        $app       = JFactory::getApplication();
        $db        = $this->getDbo();
        $user      = Factory::getUser();
        $groups    = $user->getAuthorisedViewLevels();
        $component = FreeComponentSite::getInstance();

        $filterOrder    = $app->getUserStateFromRequest("com_osdownloads.files.filter_order", 'filter_order',
            'doc.ordering', '');
        $filterOrderDir = $app->getUserStateFromRequest("com_osdownloads.files.filter_order_Dir", 'filter_order_Dir',
            'asc', 'word');

        $query = $db->getQuery(true)
            ->select('doc.*')
            ->select('cat.access AS cat_access')
            ->select('cat.title AS cat_title')
            ->from('#__osdownloads_documents AS doc')
            ->leftJoin(
                '#__categories AS cat'
                . ' ON (doc.cate_id = cat.id AND cat.extension = ' . $db->quote('com_osdownloads') . ')'
            )
            ->where(
                array(
                    // It must be published
                    'cat.published = 1',
                    'doc.published = 1',
                    // The user needs to have access to it
                    'doc.access IN (' . implode(',', $groups) . ')',
                    'cat.access IN (' . implode(',', $groups) . ')'
                )
            )
            ->order($db->quoteName($filterOrder) . ' ' . $filterOrderDir);

        if (!empty($documentId)) {
            $query->where('doc.id = ' . $db->quote((int)$documentId));
        }

        if ($component->isFree()) {
            $query->select("doc.require_email as require_user_email");
        }

        return $query;
    }

    public function incrementDownloadCount($itemId)
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->update('#__osdownloads_documents')
            ->set('downloaded = downloaded + 1')
            ->where('id = ' . $db->quote($itemId));
        $db->setQuery($query);
        $db->execute();
    }
}
