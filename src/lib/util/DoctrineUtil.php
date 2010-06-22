<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv2.1 (or at your option, any later version).
 * @package Util
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * DoctrineUtil helper class
 */
class DoctrineUtil
{
    /**
     * Constructor
     */
    public function __construct()
    {
        throw new Exception(__f('Static class %s cannot be instanciated', get_class($this)));
    }

    /**
     * Create Tables from models for given module.
     *
     * @param string $modname Module name.
     * @param string $path Optional force path to Model directory (used by plugins).
     */
    public static function createTablesFromModels($modname, $path = null)
    {
        $modname = (isset($modname) ? strtolower((string)$modname) : '');
        $modinfo = ModUtil::getInfo(ModUtil::getIdFromName($modname));
        $osdir = DataUtil::formatForOS($modinfo['directory']);
        $base = $modinfo['type'] == ModUtil::TYPE_MODULE ? 'modules' : 'system';
        $dm = Doctrine_Manager::getInstance();
        $save = $dm->getAttribute(Doctrine::ATTR_MODEL_LOADING);
        $dm->setAttribute(Doctrine::ATTR_MODEL_LOADING, Doctrine::MODEL_LOADING_AGGRESSIVE);
        $path = (is_null($path)) ? "$base/$osdir/lib/$osdir/Model" : "$base/$osdir/$path";
        Doctrine_Core::createTablesFromModels(realpath($path));
        $dm->setAttribute(Doctrine::ATTR_MODEL_LOADING, $save);
    }

    /**
     * Aggressively load models.
     *
     * This helper is required because we are using PEAR naming standards with
     * our own autoloading.  Doctrine's model loading doesn't take this into
     * account in non agressive modes.
     *
     * In general, this method is NOT required.
     *
     * @param string Module name to load models for.
     */
    public static function loadModels($modname)
    {
        $modname = (isset($modname) ? strtolower((string)$modname) : '');
        $modinfo = ModUtil::getInfo(ModUtil::getIdFromName($modname));
        $osdir = DataUtil::formatForOS($modinfo['directory']);
        $base = $modinfo['type'] == ModUtil::TYPE_MODULE ? 'modules' : 'system';
        $dm = Doctrine_Manager::getInstance();
        $save = $dm->getAttribute(Doctrine::ATTR_MODEL_LOADING);
        $dm->setAttribute(Doctrine::ATTR_MODEL_LOADING, Doctrine::MODEL_LOADING_AGGRESSIVE);
        Doctrine_Core::loadModels(realpath("$base/$osdir/lib/$osdir/Model"));
        $dm->setAttribute(Doctrine::ATTR_MODEL_LOADING, $save);
    }

    public static function clearResultCache()
    {
        if (!(System::getVar('CACHE_ENABLE') && System::getVar('CACHE_RESULT'))) {
            return;
        }

        $driver = DBConnectionStack::getConnection()->getAttribute(Doctrine_Core::ATTR_RESULT_CACHE);
        $driver->deleteByPrefix($driver->getOption('prefix'));
    }

    public static function clearQueryCache()
    {
        if (!System::getVar('CACHE_ENABLE')) {
            return;
        }

        $driver = DBConnectionStack::getConnection()->getAttribute(Doctrine_Core::ATTR_QUERY_CACHE);
        $driver->deleteByPrefix($driver->getOption('prefix'));
    }

    public static function useResultsCache($query)
    {
        if (!System::getVar('CACHE_ENABLE')) {
            return $query;
        }

        return $query->useResultsCache(true);
    }

    /**
     * Decorates table name with prefix
     *
     * @param <string> $tableName
     * @return <string> decorated table name
     */
    public static function decorateTableName($tableName)
    {
        return Doctrine_Manager::connection()->formatter->getTableName($tableName);
    }


    /**
     * Create table
     *
     * @param <string> $tableName
     * @param <array> $columns
     * @param <array> $options
     */
    public static function createTable($tableName, array $columns, array $options=array())
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->createTable($tableName, $columns, $options);
    }


    /**
     * Drop table
     *
     * @param <string> $tableName
     */
    public static function dropTable($tableName)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->dropTable($tableName);
    }


    /**
     * Rename a table
     *
     * @param <string> $oldTableName
     * @param <string> $newTableName
     * @param <bool> $check default = true verifies request
     */
    public static function renameTable($oldTableName, $newTableName, $check=true)
    {
        $oldTableName = self::decorateTableName($oldTableName);
        $newTableName = self::decorateTableName($newTableName);
        Doctrine_Manager::connection()->export->alterTable($oldTableName, array('name' => $newTableName), $check);
    }


    /**
     * Add a column to table
     *
     * @param <string> $tableName
     * @param <string> $columnName
     * @param <array> $options
     * @param <bool> $check default = true verifies request
     */
    public static function createColumn($tableName, $columnName, $options=array(), $check=true)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->alterTable($tableName, array('add' => array($columnName => $options)), $check);
    }


    /**
     * Drop column from table
     *
     * @param <string> $tableName
     * @param <string> $columnName
     * @param <bool> $check default = true verifies request
     */
    public static function dropColumn($tableName, $columnName, $check=true)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->alterTable($tableName, array('remove' => array($columnName => array())), $check);
    }

    /**
     *  Rename column in table
     *
     * @param <string> $tableName
     * @param <string> $oldColumnName
     * @param <string> $newColumnName
     * @param <bool> $check default = true verifies request
     */
    public static function renameColumn($tableName, $oldColumnName, $newColumnName, $check=true)
    {
        $oldTableName = self::decorateTableName($oldTableName);
        $newTableName = self::decorateTableName($newTableName);
        $columnList = Doctrine_Manager::connection()->import->listTableColumns($tableName);
        if (isset($columnList[$column['oldColumnName']])) {
            Doctrine_Manager::connection()->export->alterTable($column['tableName'],
                    array('rename' => array($oldColumnName => array('name' => $newColumnName, 'definition' => $columnList[$oldColumnName]))), $check);
        }
    }


    /**
     * Modify a column
     *
     * @param <string> $tableName
     * @param <string> $columnName
     * @param <array> $options
     * @param <bool> $check default = true verifies request
     */
    public static function alterColumn($tableName, $columnName, array $options=array(), $check=true)
    {
        $options = array();
        $options = $column['options'];
        $options['type'] = $column['type'];
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->alterTable($tableName, array('change' => array($columnName => array('definition' => $options))), $check);
    }


    /**
     * Create index
     *
     * @param <string> $tableName
     * @param <string> $index
     * @param <array> $definition
     */
    public static function createIndex($tableName, $index, array $definition)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->createIndex($tableName, $indexName, $definition);
    }


    /**
     * Drop index
     *
     * @param <string> $tableName
     * @param <string> $indexName
     */
    public static function dropIndex($tableName, $indexName)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->dropIndex($tableName, $indexName);
    }


    /**
     * Create constraint
     *
     * @param <string> $tableName
     * @param <string> $constraintName
     * @param <array> $definition
     */
    public static function createConstraint($tableName, $constraintName, array $definition)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->createConstraint($tableName, $constraintName, $definition);
    }


    /**
     * Drop constraint
     *
     * @param <string> $tableName
     * @param <string> $constraintName
     * @param <array> $definition
     */
    public static function dropConstraint($tableName, $constraintName, array $definition)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->dropConstraint($tableName, $constraintName, isset($definition['primary']) && $definition['primary']);
    }


    /**
     * Create foreign key
     *
     * @param <string> $tableName
     * @param <array> $definition
     */
    public static function createForeignKey($tableName, array $definition)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->createForeignKey($tableName, $definition);
    }


    /**
     * Drop Foreign Key
     *
     * @param <string> $tableName
     * @param <array> $definition
     */
    public static function droppedForeignKey($tableName, array $definition)
    {
        $tableName = self::decorateTableName($tableName);
        Doctrine_Manager::connection()->export->dropForeignKey($tableName, $definition['name']);
    }

    /**
     * Change database table using Doctrine dictionary method.
     *
     * @param string $table      Table key in pntables.
     * @param array  $definition Table definition (default = null).
     * @param array  $tabopt     Table options.
     *
     * @return boolean
     */
    public static function changeTable($tableName, $definition = null, $tabopt = null)
    {
        $connection = Doctrine_Manager::connection();

        if (empty($definition) || !is_array($definition)) {
            $definition = self::getTableDefinition($table);
            if (!$definition) {
                throw new Exception(__f('Neither the sql parameter nor the table array contain the dictionary representation of table [%s]', array($table)));
            }
        }

        if (!isset($tabopt) || empty($tabopt)) {
            $tabopt = self::getTableOptions();
        }
        $tabopt['constraints'] = self::getTableConstraints($table);

        $tables = System::dbGetTables();
        $tableName = $tables[$table];

        if (empty($tableName)) {
            throw new Exception(__f('%s does not point to a valid table definition', $table));
        }

        $metaColumns = self::metaColumnNames($table);

        // first round - create any missing columns
        foreach ($definition as $key => $columnDefinition) {
            if (isset($metaColumns[$key])) {
                continue;
            }
            $alterTableDefinition = array('add' => array($key => $columnDefinition));
            try {
                $connection->export->alterTable($tableName, $alterTableDefinition);
            } catch (Exception $e) {
                return LogUtil::registerError(__('Error! Table update failed.') . ' ' . $e->getMessage());
            }
        }

        // second round, alter table structures to match new tables definition.
        foreach ($definition as $key => $columnDefinition) {
            $alterTableDefinition = array('change' => array($key => array('definition' => $columnDefinition)));
            try {
                $connection->export->alterTable($tableName, $alterTableDefinition);
            } catch (Exception $e) {
                return LogUtil::registerError(__('Error! Table update failed.') . ' ' . $e->getMessage());
            }
        }

        // drop all indexes
        $indexes = self::metaIndexes($table);
        foreach ($indexes as $index) {
            try {
                $connection->export->dropIndex($tableName, $index);
            } catch (Exception $e) {
                return LogUtil::registerError(__('Error! Table update failed.') . ' ' . $e->getMessage());
            }
        }

        // create additional indexes
        $tableIndex = $table . '_column_idx';
        if (array_key_exists($tableIndex, $tables) && is_array($tables[$tableIndex])) {
            $indexes = self::metaIndexes($table);
            foreach ($tables[$tableIndex] as $indexName => $indexDefinition) {
                if (!isset($indexes[$indexName])) {
                    self::createIndex($indexName, $table, $indexDefinition);
                }
            }
        }
    }
}