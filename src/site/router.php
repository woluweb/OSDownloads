<?php
/**
 * @package   OSDownloads
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2016 Open Source Training, LLC. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die();

jimport('joomla.log.log');

if (!defined('OSDOWNLOADS_LOADED')) {
    require_once JPATH_ADMINISTRATOR . '/components/com_osdownloads/include.php';
}

/**
 * Routing class from com_osdownloads
 */
class OsdownloadsRouter extends JComponentRouterBase
{
    /**
     * Class constructor.
     *
     * @param   JApplicationCms  $app   Application-object that the router should use
     * @param   JMenu            $menu  Menu-object that the router should use
     *
     * @since   3.4
     */
    public function __construct($app = null, $menu = null)
    {
        parent::__construct($app, $menu);

        JLog::addLogger(
            array('text_file' => 'com_osdownloads.router.errors.php'),
            JLog::ALL,
            array('com_osdownloads.router')
        );
    }

    /**
     * Build the route for the com_content component
     *
     * @param   array  &$query  An array of URL arguments
     *
     * @return  array  The URL arguments to use to assemble the subsequent URL.
     */
    public function build(&$query)
    {
        $segments = array();

        $view   = ArrayHelper::getValue($query, 'view');
        $layout = ArrayHelper::getValue($query, 'layout');
        $id     = ArrayHelper::getValue($query, 'id');
        $task   = ArrayHelper::getValue($query, 'task');

        unset(
            $query['view'],
            $query['layout'],
            $query['id'],
            $query['task'],
            $query['tmpl']
        );

        if (!empty($task)) {
            if (in_array($task, array('download', 'routedownload'))) {
                if ($layout !== 'thankyou') {
                    $segments[] = $task;
                } else {
                    $segments[] = 'thankyou';
                }

                // Append the categories before the alias of the file
                $catId = $this->getCategoryIdFromFile($id);

                $this->appendCategoriesToSegments($segments, $catId);

                $segments[] = $this->getFileAlias($id);
            } elseif ($task === 'confirmemail') {
                $segments[] = 'confirmemail';
                $segments[] = ArrayHelper::getValue($query, 'data');

                unset($query['data']);
            }
        }

        if (!empty($view)) {
            if ($view === 'downloads') {
                $segments[] = "category";

                $this->appendCategoriesToSegments($segments, $id);
            } elseif ($view === 'item') {
                if ($layout === 'thankyou') {
                    $segments[] = "thankyou";
                } else {
                    $segments[] = "file";
                }

                $catId = $this->getCategoryIdFromFile($id);

                if (!empty($catId)) {
                    $this->appendCategoriesToSegments($segments, $catId);
                }

                // Append the file alias
                $segments[] = $this->getFileAlias($id);
            }
        }

        return $segments;
    }

    /**
     * @param string[] $segments
     *
     * @return mixed[]
     */
    public function parse(&$segments)
    {
        $lastSegmentIndex = count($segments) - 1;
        $vars             = array();

        if ($segments[0] === 'category') {
            $vars['view'] = 'downloads';

            if (isset($segments[1])) {
                // Get category Id from category alias
                $category = $this->getCategoryFromAlias($segments[$lastSegmentIndex]);

                if (!empty($category)) {
                    $vars['id'] = $category->id;
                }
            }
        }

        if ($segments[0] === 'file') {
            $vars['view'] = 'item';
            $vars['id']   = $this->getFileIdFromAlias($segments[$lastSegmentIndex]);
        }

        if ($segments[0] === 'thankyou') {
            $vars['view']   = 'item';
            $vars['layout'] = 'thankyou';
            $vars['tmpl']   = 'component';
            $vars['task']   = 'routedownload';
            $vars['id']     = $this->getFileIdFromAlias($segments[$lastSegmentIndex]);
        }

        if ($segments[0] === 'download') {
            $vars['task'] = 'download';
            $vars['tmpl'] = 'component';
            $vars['id']   = $this->getFileIdFromAlias($segments[$lastSegmentIndex]);
        }

        if ($segments[0] === 'routedownload') {
            $vars['task'] = 'routedownload';
            $vars['tmpl'] = 'component';
            $vars['id']   = $this->getFileIdFromAlias($segments[$lastSegmentIndex]);
        }

        if ($segments[0] === 'confirmemail') {
            $vars['task'] = 'confirmemail';
            $vars['data'] = $segments[$lastSegmentIndex];
        }

        return $vars;
    }

    /**
     * Build the path to a category, considering the parent categories.
     *
     * @param array $categories
     * @param int   $catId
     */
    protected function buildCategoriesPath(&$categories, $catId)
    {
        if (empty($catId)) {
            return;
        }

        $category = $this->getCategory($catId);

        if (!empty($category) && $category->alias !== 'root') {
            $categories[] = $category->alias;
        }

        if (!empty($category) && $category->parent_id) {
            $this->buildCategoriesPath($categories, $category->parent_id);
        }
    }

    /**
     * Append the category path to the segments.
     *
     * @param array $segments
     * @param int   $catId
     */
    protected function appendCategoriesToSegments(&$segments, $catId)
    {
        // Append the categories before the alias of the file
        $categories = array();

        $this->buildCategoriesPath($categories, $catId);

        for ($i = count($categories) - 1; $i >= 0; $i--) {
            $segments[] = $categories[$i];
        }
    }

    /**
     * Returns the alias of a file based on the file id.
     *
     * @param int $id
     *
     * @return string
     */
    protected function getFileAlias($id)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select('alias')
            ->from('#__osdownloads_documents')
            ->where('id = ' . $db->quote((int)$id));

        $alias = $db->setQuery($query)->loadResult();

        if (empty($alias)) {
            JLog::add(
                JText::sprintf(
                    'COM_OSDOWNLOADS_ERROR_FILE_NOT_FOUND',
                    $id,
                    'getFileAlias'
                ),
                JLog::WARNING
            );
        }

        return $alias;
    }

    /**
     * Returns the id of a file based on the file's alias.
     *
     * @param string $alias
     *
     * @return string
     */
    protected function getFileIdFromAlias($alias)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__osdownloads_documents')
            ->where('alias = ' . $db->quote($alias));

        $id = $db->setQuery($query)->loadResult();

        if (empty($id)) {
            JLog::add(
                JText::sprintf(
                    'COM_OSDOWNLOADS_ERROR_FILE_NOT_FOUND',
                    $alias,
                    'getFileIdFromAlias'
                ),
                JLog::WARNING
            );
        }

        return $id;
    }

    /**
     * Returns the category id based on the file id.
     *
     * @param int $fileId
     *
     * @return int
     */
    protected function getCategoryIdFromFile($fileId)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select('cate_id')
            ->from('#__osdownloads_documents')
            ->where('id = ' . (int)$fileId);


        $catId = $db->setQuery($query)->loadResult();

        if (empty($catId)) {
            JLog::add(
                JText::sprintf(
                    'COM_OSDOWNLOADS_ERROR_FILE_NOT_FOUND',
                    $fileId,
                    'getCategoryIdFromFile'
                ),
                JLog::WARNING
            );
        }

        return $catId;
    }

    /**
     * Returns the category as object based on the id.
     *
     * @param int $id
     *
     * @return stdClass
     */
    protected function getCategory($id)
    {
        $db = JFactory::getDBO();

        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__categories')
            ->where(
                array(
                    'extension IN ("com_osdownloads", "system")',
                    'id = ' . (int)$id
                )
            );

        $category = $db->setQuery($query)->loadObject();

        if (!is_object($category)) {
            JLog::add(
                JText::sprintf(
                    'COM_OSDOWNLOADS_ERROR_CATEGORY_NOT_FOUND',
                    $id,
                    'getCategory'
                ),
                JLog::WARNING
            );
        }

        return $category;
    }

    /**
     * Returns the category as object based on the alias.
     *
     * @param string $alias
     *
     * @return stdClass
     */
    protected function getCategoryFromAlias($alias)
    {
        $db = JFactory::getDBO();

        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__categories')
            ->where(
                array(
                    'extension IN ("com_osdownloads", "system")',
                    'alias = ' . $db->quote($alias)
                )
            );

        $category = $db->setQuery($query)->loadObject();

        if (!is_object($category)) {
            JLog::add(
                JText::sprintf(
                    'COM_OSDOWNLOADS_ERROR_CATEGORY_NOT_FOUND',
                    $alias,
                    'getCategoryFromAlias'
                ),
                JLog::WARNING
            );
        }

        return $category;
    }
}
