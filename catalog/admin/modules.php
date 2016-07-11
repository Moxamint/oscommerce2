<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2013 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\Apps;
  use OSC\OM\HTML;
  use OSC\OM\OSCOM;
  use OSC\OM\Registry;

  require('includes/application_top.php');

  $set = (isset($_GET['set']) ? $_GET['set'] : '');

  $modules = $cfgModules->getAll();

  if (empty($set) || !$cfgModules->exists($set)) {
    $set = $modules[0]['code'];
  }

  $module_type = $cfgModules->get($set, 'code');
  $module_directory = $cfgModules->get($set, 'directory');
  $module_language_directory = $cfgModules->get($set, 'language_directory');
  $module_key = $cfgModules->get($set, 'key');
  define('HEADING_TITLE', $cfgModules->get($set, 'title'));
  $template_integration = $cfgModules->get($set, 'template_integration');

  $appModuleType = null;

  switch ($module_type) {
    case 'dashboard':
      $appModuleType = 'AdminDashboard';
      break;

    case 'payment':
      $appModuleType = 'Payment';
      break;
  }

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  if (tep_not_null($action)) {
    switch ($action) {
      case 'save':
        foreach( $_POST['configuration'] as $key => $value ) {
          $key = tep_db_prepare_input($key);
          $value = tep_db_prepare_input($value);

          tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . tep_db_input($value) . "' where configuration_key = '" . tep_db_input($key) . "'");
        }
        OSCOM::redirect(FILENAME_MODULES, 'set=' . $set . '&module=' . $_GET['module']);
        break;
      case 'install':
      case 'remove':
        if (strpos($_GET['module'], '\\') !== false) {
          $class = Apps::getModuleClass($_GET['module'], $appModuleType);

          if (class_exists($class)) {
            $file_extension = '';
            $module = new $class();
            $class = $_GET['module'];
          }
        } else {
          $file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
          $class = basename($_GET['module']);
          if (file_exists($module_directory . $class . $file_extension)) {
            include($module_directory . $class . $file_extension);
            $module = new $class;
          }
        }

        if (isset($module)) {
          if ($action == 'install') {
            if ($module->check() > 0) { // remove module if already installed
              $module->remove();
            }

            $module->install();

            $modules_installed = explode(';', constant($module_key));

            if (!in_array($class . $file_extension, $modules_installed)) {
              $modules_installed[] = $class . $file_extension;
            }

            Registry::get('Db')->save('configuration', ['configuration_value' => implode(';', $modules_installed)], ['configuration_key' => $module_key]);
            OSCOM::redirect(FILENAME_MODULES, 'set=' . $set . '&module=' . $class);
          } elseif ($action == 'remove') {
            $module->remove();

            $modules_installed = explode(';', constant($module_key));

            if (in_array($class . $file_extension, $modules_installed)) {
              unset($modules_installed[array_search($class . $file_extension, $modules_installed)]);
            }

            Registry::get('Db')->save('configuration', ['configuration_value' => implode(';', $modules_installed)], ['configuration_key' => $module_key]);
            OSCOM::redirect(FILENAME_MODULES, 'set=' . $set);
          }
        }
        OSCOM::redirect(FILENAME_MODULES, 'set=' . $set . '&module=' . $class);
        break;
    }
  }

  require(DIR_WS_INCLUDES . 'template_top.php');

  $modules_installed = (defined($module_key) ? explode(';', constant($module_key)) : array());
  $new_modules_counter = 0;

  $file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
  $directory_array = array();
  if ($dir = @dir($module_directory)) {
    while ($file = $dir->read()) {
      if (!is_dir($module_directory . $file)) {
        if (substr($file, strrpos($file, '.')) == $file_extension) {
          if (isset($_GET['list']) && ($_GET['list'] == 'new')) {
            if (!in_array($file, $modules_installed)) {
              $directory_array[] = $file;
            }
          } else {
            if (in_array($file, $modules_installed)) {
              $directory_array[] = $file;
            } else {
              $new_modules_counter++;
            }
          }
        }
      }
    }
    $dir->close();
  }

  if (isset($appModuleType)) {
    foreach (Apps::getModules($appModuleType) as $k => $v) {
      if (isset($_GET['list']) && ($_GET['list'] == 'new')) {
        if (!in_array($k, $modules_installed)) {
          $directory_array[] = $k;
        }
      } else {
        if (in_array($k, $modules_installed)) {
          $directory_array[] = $k;
        } else {
          $new_modules_counter++;
        }
      }
    }
  }

  sort($directory_array);
?>

    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
<?php
  if (isset($_GET['list'])) {
    echo '            <td class="smallText" align="right">' . HTML::button(IMAGE_BACK, 'fa fa-chevron-left', OSCOM::link(FILENAME_MODULES, 'set=' . $set)) . '</td>';
  } else {
    echo '            <td class="smallText" align="right">' . HTML::button(IMAGE_MODULE_INSTALL . ' (' . $new_modules_counter . ')', 'fa fa-plus', OSCOM::link(FILENAME_MODULES, 'set=' . $set . '&list=new')) . '</td>';
  }
?>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_MODULES; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_SORT_ORDER; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
  $installed_modules = array();
  for ($i=0, $n=sizeof($directory_array); $i<$n; $i++) {
    $file = $directory_array[$i];

    if (strpos($file, '\\') !== false) {
      $file_extension = '';

      $class = Apps::getModuleClass($file, $appModuleType);

      $module = new $class();
      $module->code = $file;

      $class = $file;
    } else {
      $file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));

      include($module_language_directory . $language . '/modules/' . $module_type . '/' . $file);
      include($module_directory . $file);

      $class = substr($file, 0, strrpos($file, '.'));

      if (tep_class_exists($class)) {
        $module = new $class;
      }
    }

    if (isset($module)) {
      if ($module->check() > 0) {
        if (($module->sort_order > 0) && !isset($installed_modules[$module->sort_order])) {
          $installed_modules[$module->sort_order] = $file;
        } else {
          $installed_modules[] = $file;
        }
      }

      if ((!isset($_GET['module']) || (isset($_GET['module']) && ($_GET['module'] == $class))) && !isset($mInfo)) {
        $module_info = array('code' => $module->code,
                             'title' => $module->title,
                             'description' => $module->description,
                             'status' => $module->check(),
                             'signature' => (isset($module->signature) ? $module->signature : null),
                             'api_version' => (isset($module->api_version) ? $module->api_version : null));

        $module_keys = $module->keys();

        $keys_extra = array();
        for ($j=0, $k=sizeof($module_keys); $j<$k; $j++) {
          $key_value_query = tep_db_query("select configuration_title, configuration_value, configuration_description, use_function, set_function from " . TABLE_CONFIGURATION . " where configuration_key = '" . $module_keys[$j] . "'");
          $key_value = tep_db_fetch_array($key_value_query);

          $keys_extra[$module_keys[$j]]['title'] = $key_value['configuration_title'];
          $keys_extra[$module_keys[$j]]['value'] = $key_value['configuration_value'];
          $keys_extra[$module_keys[$j]]['description'] = $key_value['configuration_description'];
          $keys_extra[$module_keys[$j]]['use_function'] = $key_value['use_function'];
          $keys_extra[$module_keys[$j]]['set_function'] = $key_value['set_function'];
        }

        $module_info['keys'] = $keys_extra;

        $mInfo = new \ArrayObject($module_info, \ArrayObject::ARRAY_AS_PROPS);
      }

      if (isset($mInfo) && is_object($mInfo) && ($class == $mInfo->code) ) {
        if ($module->check() > 0) {
          echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . OSCOM::link(FILENAME_MODULES, 'set=' . $set . '&module=' . addslashes($class) . '&action=edit') . '\'">' . "\n";
        } else {
          echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">' . "\n";
        }
      } else {
        echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . OSCOM::link(FILENAME_MODULES, 'set=' . $set . (isset($_GET['list']) ? '&list=new' : '') . '&module=' . addslashes($class)) . '\'">' . "\n";
      }
?>
                <td class="dataTableContent"><?php echo $module->title; ?></td>
                <td class="dataTableContent" align="right"><?php if (in_array($module->code . $file_extension, $modules_installed) && is_numeric($module->sort_order)) echo $module->sort_order; ?></td>
                <td class="dataTableContent" align="right"><?php if (isset($mInfo) && is_object($mInfo) && ($class == $mInfo->code) ) { echo HTML::image(DIR_WS_IMAGES . 'icon_arrow_right.gif'); } else { echo '<a href="' . OSCOM::link(FILENAME_MODULES, 'set=' . $set . (isset($_GET['list']) ? '&list=new' : '') . '&module=' . $class) . '">' . HTML::image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
              </tr>
<?php
    }
  }

  if (!isset($_GET['list'])) {
    ksort($installed_modules);
    $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $module_key . "'");
    if (tep_db_num_rows($check_query)) {
      $check = tep_db_fetch_array($check_query);
      if ($check['configuration_value'] != implode(';', $installed_modules)) {
        Registry::get('Db')->save('configuration', ['configuration_value' => implode(';', $installed_modules), 'last_modified' => 'now()'], ['configuration_key' => $module_key]);
      }
    } else {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Installed Modules', '" . $module_key . "', '" . implode(';', $installed_modules) . "', 'This is automatically updated. No need to edit.', '6', '0', now())");
    }

    if ($template_integration == true) {
      $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'TEMPLATE_BLOCK_GROUPS'");
      if (tep_db_num_rows($check_query)) {
        $check = tep_db_fetch_array($check_query);
        $tbgroups_array = explode(';', $check['configuration_value']);
        if (!in_array($module_type, $tbgroups_array)) {
          $tbgroups_array[] = $module_type;
          sort($tbgroups_array);
          tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . implode(';', $tbgroups_array) . "', last_modified = now() where configuration_key = 'TEMPLATE_BLOCK_GROUPS'");
        }
      } else {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Installed Template Block Groups', 'TEMPLATE_BLOCK_GROUPS', '" . $module_type . "', 'This is automatically updated. No need to edit.', '6', '0', now())");
      }
    }
  }
?>
              <tr>
                <td colspan="3" class="smallText"><?php echo TEXT_MODULE_DIRECTORY . ' ' . $module_directory; ?></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();

  if (isset($mInfo) && (strpos($mInfo->code, '\\') !== false)) {
    $file_extension = '';
  } else {
    $file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
  }

  switch ($action) {
    case 'edit':
      $keys = '';
      foreach( $mInfo->keys as $key => $value ) {
        $keys .= '<strong>' . $value['title'] . '</strong><br />' . $value['description'] . '<br />';

        if ($value['set_function']) {
          eval('$keys .= ' . $value['set_function'] . "'" . $value['value'] . "', '" . $key . "');");
        } else {
          $keys .= HTML::inputField('configuration[' . $key . ']', $value['value']);
        }
        $keys .= '<br /><br />';
      }
      $keys = substr($keys, 0, strrpos($keys, '<br /><br />'));

      $heading[] = array('text' => '<strong>' . $mInfo->title . '</strong>');

      $contents = array('form' => HTML::form('modules', OSCOM::link(FILENAME_MODULES, 'set=' . $set . '&module=' . $_GET['module'] . '&action=save')));
      $contents[] = array('text' => $keys);
      $contents[] = array('align' => 'center', 'text' => '<br />' . HTML::button(IMAGE_SAVE, 'fa fa-save', null, 'primary') . HTML::button(IMAGE_CANCEL, 'fa fa-close', OSCOM::link(FILENAME_MODULES, 'set=' . $set . '&module=' . $_GET['module'])));
      break;
    default:
      $heading[] = array('text' => '<strong>' . $mInfo->title . '</strong>');

      if (in_array($mInfo->code . $file_extension, $modules_installed) && ($mInfo->status > 0)) {
        $keys = '';
        foreach( $mInfo->keys as $value ) {
          $keys .= '<strong>' . $value['title'] . '</strong><br />';
          if ($value['use_function']) {
            $use_function = $value['use_function'];
            if (preg_match('/->/', $use_function)) {
              $class_method = explode('->', $use_function);
              if (!isset(${$class_method[0]}) || !is_object(${$class_method[0]})) {
                include(DIR_WS_CLASSES . $class_method[0] . '.php');
                ${$class_method[0]} = new $class_method[0]();
              }
              $keys .= tep_call_function($class_method[1], $value['value'], ${$class_method[0]});
            } else {
              $keys .= tep_call_function($use_function, $value['value']);
            }
          } else {
            $keys .= $value['value'];
          }
          $keys .= '<br /><br />';
        }
        $keys = substr($keys, 0, strrpos($keys, '<br /><br />'));

        $contents[] = array('align' => 'center', 'text' => HTML::button(IMAGE_EDIT, 'fa fa-edit', OSCOM::link(FILENAME_MODULES, 'set=' . $set . '&module=' . $mInfo->code . '&action=edit')) . HTML::button(IMAGE_MODULE_REMOVE, 'fa fa-minus', OSCOM::link(FILENAME_MODULES, 'set=' . $set . '&module=' . $mInfo->code . '&action=remove')));

        if (isset($mInfo->signature) && (list($scode, $smodule, $sversion, $soscversion) = explode('|', $mInfo->signature))) {
          $contents[] = array('text' => '<br />' . HTML::image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '&nbsp;<strong>' . TEXT_INFO_VERSION . '</strong> ' . $sversion . ' (<a href="http://sig.oscommerce.com/' . $mInfo->signature . '" target="_blank">' . TEXT_INFO_ONLINE_STATUS . '</a>)');
        }

        if (isset($mInfo->api_version)) {
          $contents[] = array('text' => HTML::image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '&nbsp;<strong>' . TEXT_INFO_API_VERSION . '</strong> ' . $mInfo->api_version);
        }

        $contents[] = array('text' => '<br />' . $mInfo->description);
        $contents[] = array('text' => '<br />' . $keys);
      } elseif (isset($_GET['list']) && ($_GET['list'] == 'new')) {
        if (isset($mInfo)) {
          $contents[] = array('align' => 'center', 'text' => HTML::button(IMAGE_MODULE_INSTALL, 'fa fa-plus', OSCOM::link(FILENAME_MODULES, 'set=' . $set . '&module=' . $mInfo->code . '&action=install')));

          if (isset($mInfo->signature) && (list($scode, $smodule, $sversion, $soscversion) = explode('|', $mInfo->signature))) {
            $contents[] = array('text' => '<br />' . HTML::image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '&nbsp;<strong>' . TEXT_INFO_VERSION . '</strong> ' . $sversion . ' (<a href="http://sig.oscommerce.com/' . $mInfo->signature . '" target="_blank">' . TEXT_INFO_ONLINE_STATUS . '</a>)');
          }

          if (isset($mInfo->api_version)) {
            $contents[] = array('text' => HTML::image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '&nbsp;<strong>' . TEXT_INFO_API_VERSION . '</strong> ' . $mInfo->api_version);
          }

          $contents[] = array('text' => '<br />' . $mInfo->description);
        }
      }
      break;
  }

  if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
    echo '            <td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox($heading, $contents);

    echo '            </td>' . "\n";
  }
?>
          </tr>
        </table></td>
      </tr>
    </table>

<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
