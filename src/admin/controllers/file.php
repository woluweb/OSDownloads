<?php
/**
 * @package   OSDownloads
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2016 Open Source Training, LLC. All rights reserved
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

use Alledia\Framework\Factory;

class OSDownloadsControllerFile extends JControllerForm
{
    /**
     * Alledia Extension
     * @var \Alledia\Framework\Joomla\Extension\Licensed
     */
    protected $extension;

    public function __construct($default = array())
    {
        parent::__construct($default);

        $this->registerTask('apply', 'save');
        $this->registerTask('unpublish', 'publish');

        // Load the extension
        $this->extension = Factory::getExtension('OSDownloads', 'component');
        $this->extension->loadLibrary();
    }

    public function save()
    {
        $app = JFactory::getApplication();

        JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
        JTable::addIncludePath(JPATH_COMPONENT.'/tables');
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');

        $row  = JTable::getInstance('Document', 'OsdownloadsTable');
        $post = JRequest::get('post');

        $row->bind($post['jform']);

        $text    = $post['jform']['description_1'];
        $text    = str_replace('<br>', '<br />', $text);
        $pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
        $tagPos  = preg_match($pattern, $text);
        if ($tagPos == 0) {
            $row->brief = $text;
            $row->description_1 = "";
        } else {
            list($row->brief, $row->description_1) = preg_split($pattern, $text, 2);
        }

        $row->require_email = (int) $row->require_email;
        $row->require_agree = (int) $row->require_agree;

        $files = $app->input->files->get('jform', null, 'raw');
        if (isset($files['file'])) {
            $file        = $files['file'];
            $fileName    = $file["name"];
            $fileTmpName = $file["tmp_name"];
        } else {
            $fileName    = '';
            $fileTmpName = '';
        }

        if (!empty($fileName)) {
            $fileName = JFile::makeSafe($fileName);

            if (isset($fileName) && $fileName) {
                $uploadDir = JPATH_SITE . "/media/com_osdownloads/files/";

                if (isset($post["old_file"]) && JFile::exists(JPath::clean($uploadDir . $post["old_file"]))) {
                    unlink(JPath::clean($uploadDir . $post["old_file"]));
                }

                if (!JFolder::exists(JPath::clean($uploadDir))) {
                    JFolder::create(JPath::clean($uploadDir));
                }

                $timestamp = md5(microtime());
                $filepath = JPath::clean($uploadDir . $timestamp . "_" . $fileName);
                $row->file_path = $timestamp . "_" . $fileName;


                $safeFileOptions = array(
                    // Null byte in file name
                    'null_byte'                  => true,
                    // Forbidden string in extension (e.g. php matched .php, .xxx.php, .php.xxx and so on)
                    'forbidden_extensions'       => array(),
                    // <?php tag in file contents
                    'php_tag_in_content'         => false,
                    // <? tag in file contents
                    'shorttag_in_content'        => false,
                    // Which file extensions to scan for short tags
                    'shorttag_extensions'        => array(),
                    // Forbidden extensions anywhere in the content
                    'fobidden_ext_in_content'    => false,
                    // Which file extensions to scan for .php in the content
                    'php_ext_content_extensions' => array(),
                );

                if (!JFile::upload($fileTmpName, $filepath, false, false, $safeFileOptions)) {
                    // Upload failed and message already queued
                    $this->setRedirect("index.php?option=com_osdownloads&view=file&cid=" . $row->id);
                    return;
                }
            }
        }

        $row->store();

        switch ($this->getTask()) {
            case "apply":
                $this->setRedirect("index.php?option=com_osdownloads&view=file&cid=" . $row->id, JText::_("COM_OSDOWNLOADS_DOCUMENT_IS_SAVED"));
                break;
            default:
                $this->setRedirect("index.php?option=com_osdownloads&view=files", JText::_("COM_OSDOWNLOADS_DOCUMENT_IS_SAVED"));
        }
    }

    public function publish()
    {
        // Check for request forgeries
        JRequest::checkToken() or jexit('Invalid Token');

        $db   = JFactory::getDBO();
        $date = JFactory::getDate();
        $user = JFactory::getUser();

        $cid     = JRequest::getVar('cid', array(), '', 'array');
        $publish = ($this->getTask() == 'publish' ? 1 : 0);

        JArrayHelper::toInteger($cid);

        $cids = implode(',', $cid);

        $query = 'UPDATE `#__osdownloads_documents`'
        . ' SET published = ' . (int) $publish . ','
        . ' modified_user_id = ' . (int) $user->get('id') . ','
        . ' modified_time = ' . $date->toSql()
        . ' WHERE id IN ('. $cids .')';
        $db->setQuery($query);
        if (!$db->query()) {
            JError::raiseError(500, $db->getErrorMsg());
        }
        $this->setRedirect('index.php?option=com_osdownloads&view=files');
    }

    public function delete()
    {
        JRequest::checkToken() or jexit('Invalid Token');
        JTable::addIncludePath(JPATH_COMPONENT.'/tables');

        jimport('joomla.filesystem.file');

        $db  = JFactory::getDBO();
        $cid = JRequest::getVar('cid', array(), '', 'array');

        JArrayHelper::toInteger($cid);

        $cids = implode(',', $cid);

        $query = 'SELECT * FROM `#__osdownloads_documents` WHERE id IN ('. $cids .')';
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $uploadDir = JPATH_SITE . "/media/com_osdownloads/files/";

        foreach ($rows as $item) {
            $filepath = JPath::clean($uploadDir . $item->file_path);
            if (JFile::exists($filepath)) {
                JFile::delete($filepath);
            }
        }

        foreach ($cid as $id) {
            $document = JTable::getInstance('Document', 'OsdownloadsTable');
            if (!$document->delete(array('id' => $id))) {
                JError::raiseError(500, $db->getErrorMsg());
            } else {
                $query = 'SELECT id FROM `#__osdownloads_emails` WHERE document_id = '. (int) $id;
                $db->setQuery($query);
                $emails = $db->loadObjectList();

                if (!empty($emails)) {
                    foreach ($emails as $emailId) {
                        if (!empty($emailId)) {
                            $email = JTable::getInstance('Email', 'OsdownloadsTable');
                            $email->delete(array('id' => $emailId->id));
                        }
                    }
                }
            }
        }

        $this->setRedirect('index.php?option=com_osdownloads&view=files', JText::_("COM_OSDOWNLOADS_FILES_ARE_DELETED"));

    }
}
