<?php
/**
 * Copyright 2009-2010 Zikula Foundation - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv2 (or at your option, any later version).
 * @package Zikula
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Configuration class for LOCAL driver. please see documentation for FileSystem_Configuration
 * for more details on configuration classes. This class extends FileSystem_Configuration.
 * The only purpose to this class is to provide a configuration object to be used by the
 * Local driver.
 * @author kage
 *
 */
class FileSystem_Configuration_Local extends FileSystem_Configuration
{
    protected $dir = "";
    
    public function __construct($dir = "")
    {
        $this->dir = $dir;
    }
    
    public function getDir()
    {
        return $this->dir;
    }
}