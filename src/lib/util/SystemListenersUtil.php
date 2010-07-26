<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Util
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * System listeners util.
 */
class SystemListenersUtil
{

    /**
     * If enabled and logged in, save login name of user in Apache session variable for Apache logs.
     *
     * @param Zikula_Event $event The event handler.
     *
     * @return void
     */
    public static function sessionLogging(Zikula_Event $event)
    {
        if ($event['stage'] & System::STAGES_SESSIONS) {
            // If enabled and logged in, save login name of user in Apache session variable for Apache logs
            if (isset($GLOBALS['ZConfig']['Log']['log.apache_uname']) && UserUtil::isLoggedIn()) {
                if (function_exists('apache_setenv')) {
                    apache_setenv('Zikula-Username', UserUtil::getVar('uname'));
                }
            }
        }
    }

    /**
     * Call system hooks.
     *
     * @param Zikula_Event $event The event handler.
     *
     * @return void
     */
    public static function systemHooks(Zikula_Event $event)
    {
        if (!System::isInstalling()) {
            // call system init hooks
            $systeminithooks = FormUtil::getPassedValue('systeminithooks', 'yes', 'GETPOST');
            if (SecurityUtil::checkPermission('::', '::', ACCESS_ADMIN) && (isset($systeminithooks) && $systeminithooks == 'no')) {
                // omit system hooks if requested by an administrator
            } else {
                ModUtil::callHooks('zikula', 'systeminit', 0, array('module' => 'zikula'));
            }
        }
    }

    /**
     * Load system plugins.
     *
     * @param Zikula_Event $event The event handler.
     *
     * @return void
     */
    public static function systemPlugins(Zikula_Event $event)
    {
        if ($event['stage'] & System::STAGES_LANGS) {
            if (!System::isInstalling()) {
                PluginUtil::loadPlugins(realpath(dirname(__FILE__) . "/../../plugins"), "SystemPlugin");
                EventUtil::loadPersistentEvents();
            }
        }
    }

    /**
     * Setup default error reporting.
     *
     * @param Zikula_Event $event The event.
     *
     * @return void
     */
    public static function defaultErrorReporting(Zikula_Event $event)
    {
        $serviceManager = ServiceUtil::getManager();

        if (!$serviceManager['log.enabled']) {
            return;
        }

        if ($serviceManager->hasService('system.errorreporting')) {
            return;
        }

        $class = 'Zikula_ErrorHandler_Standard';
        if ($event['stage'] & System::STAGES_AJAX) {
            $class = 'Zikula_ErrorHandler_Ajax';
        }

        $errorHandler = new $class($serviceManager);
        $serviceManager->attachService('system.errorreporting', $errorHandler);
        set_error_handler(array($errorHandler, 'handler'));
        $event->setNotified();
    }

    public static function setupLoggers(Zikula_Event $event)
    {
        if (!($event['stage'] & System::STAGES_CONFIG)) {
            return;
        }

        $serviceManager = ServiceUtil::getManager();
        if (!$serviceManager['log.enabled']) {
            return;
        }

        if ($serviceManager['log.to_display'] || $serviceManager['log.sql.to_display']) {
            $displayLogger = $serviceManager->attachService('zend.logger.display', new Zend_Log());
            // load writer first because of hard requires in the Zend_Log_Writer_Stream
            $writer = new Zend_Log_Writer_Stream('php://output');
            $formatter = new Zend_Log_Formatter_Simple('%priorityName% (%priority%): %message% <br />' . PHP_EOL);
            $writer->setFormatter($formatter);
            $displayLogger->addWriter($writer);
        }
        if ($serviceManager['log.to_file'] || $serviceManager['log.sql.to_file']) {
            $fileLogger = $serviceManager->attachService('zend.logger.file', new Zend_Log());
            $filename = LogUtil::getLogFileName();
            // load writer first because of hard requires in the Zend_Log_Writer_Stream
            $writer = new Zend_Log_Writer_Stream($filename);
            $formatter = new Zend_Log_Formatter_Simple('%timestamp% %priorityName% (%priority%): %message%' . PHP_EOL);

            $writer->setFormatter($formatter);
            $fileLogger->addWriter($writer);
        }
    }

    public static function errorLog(Zikula_Event $event)
    {
        // Check for error supression.  if error @ supression was used.
        // $errno wil still contain the real error that triggered the handler - drak
        if (error_reporting() == 0) {
            return;
        }

        $handler = $event->getSubject();

        // array('trace' => $trace, 'type' => $type, 'errno' => $errno, 'errstr' => $errstr, 'errfile' => $errfile, 'errline' => $errline, 'errcontext' => $errcontext)
        $message = $event['errstr'];
        if (is_string($event['errstr'])) {
            $message = __f("%s: %s in %s line %s", array(Zikula_ErrorHandler::translateErrorCode($event['errno']), $event['errstr'], $event['errfile'], $event['errline']));
        }

        $serviceManager = $event->getSubject()->getServiceManager();

        if ($serviceManager['log.to_display'] && !$handler instanceof Zikula_ErrorHandler_Ajax) {
            if (abs($handler->getType()) <= $serviceManager['log.display_level']) {
                $serviceManager->getService('zend.logger.display')->log($message, abs($event['type']));
            }
        }

        if ($serviceManager['log.to_file']) {
            if (abs($handler->getType()) <= $serviceManager['log.file_level']) {
                $serviceManager->getService('zend.logger.file')->log($message, abs($event['type']));
            }
        }

//        $trace = $event['trace'];
//        unset($trace[0]);
//        foreach ($trace as $key => $var) {
//            if (isset($trace[$key]['object'])) {
//                unset($trace[$key]['object']);
//            }
//            if (isset($trace[$key]['args'])) {
//                unset($trace[$key]['args']);
//            }
//        }

        if ($handler instanceof Zikula_ErrorHandler_Ajax) {
            throw new Zikula_Exception_Fatal($message);
            AjaxUtil::error($message);
        }
    }

    /**
     * Listener for log.sql events.
     *
     * This listener logs the queries via Zend_Log to file / console.
     *
     * @param Zikula_Event $event Event.
     */
    public static function logSqlQueries(Zikula_Event $event)
    {
        $serviceManager = ServiceUtil::getManager();
        if (!$serviceManager['log.enabled']) {
            return;
        }

        $message = __f('SQL Query: %s took %s sec', array($event['query'], $event['time']));

        if ($serviceManager['log.sql.to_display']) {
            $serviceManager->getService('zend.logger.display')->log($message, Zend_Log::DEBUG);
        }

        if ($serviceManager['log.sql.to_file']) {
            $serviceManager->getService('zend.logger.file')->log($message, Zend_Log::DEBUG);
        }
    }

    /**
     * Debug toolbar startup.
     *
     * @param Zikula_Event $event Event.
     */
    public static function setupDebugToolbar(Zikula_Event $event)
    {
        if ($event['stage'] == System::STAGES_CONFIG && System::isDevelopmentMode() && $event->getSubject()->getServiceManager()->getArgument('log.to_debug_toolbar')) {
            $sm = $event->getSubject()->getServiceManager();
            // create definitions
            $toolbar = new Zikula_ServiceManager_Definition(
                    'Zikula_DebugToolbar',
                    array(),
                    array('addPanels' => array(new Zikula_ServiceManager_Service('debug.toolbar.panel.version'),
                                               new Zikula_ServiceManager_Service('debug.toolbar.panel.config'),
                                               new Zikula_ServiceManager_Service('debug.toolbar.panel.momory'),
                                               new Zikula_ServiceManager_Service('debug.toolbar.panel.rendertime'),
                                               new Zikula_ServiceManager_Service('debug.toolbar.panel.sql'),
                                               new Zikula_ServiceManager_Service('debug.toolbar.panel.view'),
                                               new Zikula_ServiceManager_Service('debug.toolbar.panel.exec'),
                                               new Zikula_ServiceManager_Service('debug.toolbar.panel.logs')))
            );

            $versionPanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_Version');
            $configPanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_Config');
            $momoryPanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_Memory');
            $rendertimePanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_RenderTime');
            $sqlPanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_SQL');
            $viewPanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_View');
            $execPanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_Exec');
            $logsPanel = new Zikula_ServiceManager_Definition('Zikula_DebugToolbar_Panel_Log');

            // save start time (required by rendertime panel)
            $sm->setArgument('debug.toolbar.panel.rendertime.start', microtime(true));

            // register services

            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.version', $versionPanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.config', $configPanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.momory', $momoryPanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.rendertime', $rendertimePanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.sql', $sqlPanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.view', $viewPanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.exec', $execPanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar.panel.logs', $logsPanel, true));
            $sm->registerService(new Zikula_ServiceManager_Service('debug.toolbar', $toolbar, true));

            // setup rendering event listeners
            EventUtil::attach('theme.prefooter', array('SystemListenersUtil', 'debugToolbarRendering'));

            // setup event listeners
            EventUtil::attach('view.init', new Zikula_ServiceHandler('debug.toolbar.panel.view', 'initRenderer'));
            EventUtil::attach('module.preexecute', new Zikula_ServiceHandler('debug.toolbar.panel.exec', 'modexecPre'));
            EventUtil::attach('module.postexecute', new Zikula_ServiceHandler('debug.toolbar.panel.exec', 'modexecPost'));
            EventUtil::attach('module.execute_not_found', new Zikula_ServiceHandler('debug.toolbar.panel.logs', 'logExecNotFound'));
            EventUtil::attach('log', new Zikula_ServiceHandler('debug.toolbar.panel.logs', 'log'));
            EventUtil::attach('log.sql', new Zikula_ServiceHandler('debug.toolbar.panel.sql', 'logSql'));
            EventUtil::attach('controller.method_not_found', new Zikula_ServiceHandler('debug.toolbar.panel.logs', 'logModControllerNotFound'));
            EventUtil::attach('controller_api.method_not_found', new Zikula_ServiceHandler('debug.toolbar.panel.logs', 'logModControllerAPINotFound'));
        }
    }

    /**
     * Debug toolbar rendering (listener for theme.prefooter event).
     *
     * @param Zikula_Event $event Event.
     */
    public static function debugToolbarRendering(Zikula_Event $event) {
        $toolbar = ServiceUtil::getManager()->getService('debug.toolbar');
        $toolbar->addHTMLToTooter();
    }
}

