<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */


/**
 * Baseclass for OM-building classes.
 *
 * OM-building classes are those that build a PHP (or other) class to service
 * a single table.  This includes Peer classes, Entity classes, Map classes,
 * Node classes, Nested Set classes, etc.
 *
 * @author     Hans Lellelid <hans@xmpl.org>
 * @package    propel.generator.builder.om
 */
abstract class OMBuilder extends DataModelBuilder
{
    /**
     * Declared fully qualified classnames, to build the 'namespace' statements
     * according to this table's namespace.
     *
     * @var array
     */
    protected $declaredClasses = array();

    /**
     * Builds the PHP source for current class and returns it as a string.
     *
     * This is the main entry point and defines a basic structure that classes should follow.
     * In most cases this method will not need to be overridden by subclasses.  This method
     * does assume that the output language is PHP code, so it will need to be overridden if
     * this is not the case.
     *
     * @return string The resulting PHP sourcecode.
     */
    public function build()
    {
        $this->validateModel();

        $script = '';

        if ($this->isAddIncludes()) {
            $this->addIncludes($script);
        }

        $this->addClassOpen($script);
        $this->addClassBody($script);
        $this->addClassClose($script);

        if ($useStatements = $this->getUseStatements($ignoredNamespace = $this->getNamespace())) {
            $script = $useStatements . $script;
        }

        if ($namespaceStatement = $this->getNamespaceStatement()) {
            $script = $namespaceStatement . $script;
        }

        $script = "<?php

" . $script;

        return $this->clean($script);
    }

    /**
     * Validates the current table to make sure that it won't
     * result in generated code that will not parse.
     *
     * This method may emit warnings for code which may cause problems
     * and will throw exceptions for errors that will definitely cause
     * problems.
     */
    protected function validateModel()
    {
        // Validation is currently only implemented in the subclasses.
    }

    /**
     * Creates a $obj = new Book(); code snippet. Can be used by frameworks, for instance, to
     * extend this behavior, e.g. initialize the object after creating the instance or so.
     *
     * @return string Some code
     */
    public function buildObjectInstanceCreationCode($objName, $clsName)
    {
        return "$objName = new $clsName();";
    }

    /**
     * Returns the qualified (prefixed) classname that is being built by the current class.
     * This method must be implemented by child classes.
     *
     * @return string
     */
    abstract public function getUnprefixedClassname();

    /**
     * Returns the prefixed classname that is being built by the current class.
     *
     * @return string
     * @see        DataModelBuilder#prefixClassname()
     */
    public function getClassname()
    {
        return $this->prefixClassname($this->getUnprefixedClassname());
    }

    /**
     * Returns the namespaced classname if there is a namespace, and the raw classname otherwise
     *
     * @return string
     */
    public function getFullyQualifiedClassname()
    {
        if ($namespace = $this->getNamespace()) {
            return $namespace . '\\' . $this->getClassname();
        } else {
            return $this->getClassname();
        }
    }

    /**
     * Gets the dot-path representation of current class being built.
     *
     * @return string
     */
    public function getClasspath()
    {
        if ($this->getPackage()) {
            $path = $this->getPackage() . '.' . $this->getClassname();
        } else {
            $path = $this->getClassname();
        }

        return $path;
    }

    /**
     * Gets the full path to the file for the current class.
     *
     * @return string
     */
    public function getClassFilePath()
    {
        return ClassTools::createFilePath($this->getPackagePath(), $this->getClassname());
    }

    /**
     * Gets package name for this table.
     * This is overridden by child classes that have different packages.
     *
     * @return string
     */
    public function getPackage()
    {
        $pkg = ($this->getTable()->getPackage() ? $this->getTable()->getPackage() : $this->getDatabase()->getPackage());
        if (!$pkg) {
            $pkg = $this->getBuildProperty('targetPackage');
        }

        return $pkg;
    }

    /**
     * Returns filesystem path for current package.
     *
     * @return string
     */
    public function getPackagePath()
    {
        $pkg = $this->getPackage();

        if (false !== strpos($pkg, '/')) {
            return preg_replace('#\.(map|om)$#', '/\1', $pkg);
        }

        if ('.' === substr($pkg, 0, 1)) {
            $pkg = substr($pkg, 1);
        }

        return strtr($pkg, '.', '/');
    }

    /**
     * Return the user-defined namespace for this table,
     * or the database namespace otherwise.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->getTable()->getNamespace();
    }

    public function declareClassNamespace($class, $namespace = '')
    {
        if (isset($this->declaredClasses[$namespace]) && in_array($class, $this->declaredClasses[$namespace])) {
            return;
        }
        $this->declaredClasses[$namespace][] = $class;
    }

    public function declareClass($fullyQualifiedClassName)
    {
        $fullyQualifiedClassName = trim($fullyQualifiedClassName, '\\');
        if (($pos = strrpos($fullyQualifiedClassName, '\\')) !== false) {
            $this->declareClassNamespace(substr($fullyQualifiedClassName, $pos + 1), substr($fullyQualifiedClassName, 0, $pos));
        } else {
            // root namespace
            $this->declareClassNamespace($fullyQualifiedClassName);
        }
    }

    public function declareClassFromBuilder($builder)
    {
        $this->declareClassNamespace($builder->getClassname(), $builder->getNamespace());
    }

    public function declareClasses()
    {
        $args = func_get_args();
        foreach ($args as $class) {
            $this->declareClass($class);
        }
    }

    public function getDeclaredClasses($namespace = null)
    {
        if (null !== $namespace && isset($this->declaredClasses[$namespace])) {
            return $this->declaredClasses[$namespace];
        } else {
            return $this->declaredClasses;
        }
    }

    public function getNamespaceStatement()
    {
        $namespace = $this->getNamespace();
        if ($namespace != '') {
            return sprintf("namespace %s;

", $namespace);
        }
    }

    public function getUseStatements($ignoredNamespace = null)
    {
        $script = '';
        $declaredClasses = $this->declaredClasses;
        unset($declaredClasses[$ignoredNamespace]);
        ksort($declaredClasses);
        foreach ($declaredClasses as $namespace => $classes) {
            sort($classes);
            foreach ($classes as $class) {
                $script .= sprintf("use %s\\%s;
", $namespace, $class);
            }
        }

        return $script;
    }

    /**
     * Shortcut method to return the [stub] peer classname for current table.
     * This is the classname that is used whenever object or peer classes want
     * to invoke methods of the peer classes.
     *
     * @return string (e.g. 'MyPeer')
     * @see        StubPeerBuilder::getClassname()
     */
    public function getPeerClassname()
    {
        return $this->getStubPeerBuilder()->getClassname();
    }

    /**
     * Shortcut method to return the [stub] query classname for current table.
     * This is the classname that is used whenever object or peer classes want
     * to invoke methods of the query classes.
     *
     * @return string (e.g. 'Myquery')
     * @see        StubQueryBuilder::getClassname()
     */
    public function getQueryClassname()
    {
        return $this->getStubQueryBuilder()->getClassname();
    }

    /**
     * Returns the object classname for current table.
     * This is the classname that is used whenever object or peer classes want
     * to invoke methods of the object classes.
     *
     * @return string (e.g. 'My')
     * @see        StubPeerBuilder::getClassname()
     */
    public function getObjectClassname()
    {
        return $this->getStubObjectBuilder()->getClassname();
    }

    /**
     * Get the column constant name (e.g. PeerName::COLUMN_NAME).
     *
     * @param Column $col       The column we need a name for.
     * @param string $classname The Peer classname to use.
     *
     * @return string If $classname is provided, then will return $classname::COLUMN_NAME; if not, then the peername is looked up for current table to yield $currTablePeer::COLUMN_NAME.
     *
     * @throws Exception
     */
    public function getColumnConstant($col, $classname = null)
    {
        if ($col === null) {
            $e = new Exception("No col specified.");
            print $e;
            throw $e;
        }
        if ($classname === null) {
            return $this->getBuildProperty('classPrefix') . $col->getConstantName();
        }
        // was it overridden in schema.xml ?
        if ($col->getPeerName()) {
            $const = strtoupper($col->getPeerName());
        } else {
            $const = strtoupper($col->getName());
        }

        return $classname . '::' . $const;
    }

    /**
     * Gets the basePeer path if specified for table/db.
     * If not, will return 'propel.util.BasePeer'
     *
     * @return string
     */
    public function getBasePeer(Table $table)
    {
        $class = $table->getBasePeer();
        if ($class === null) {
            $class = "propel.util.BasePeer";
        }

        return $class;
    }

    /**
     * Convenience method to get the foreign Table object for an fkey.
     *
     * @deprecated use ForeignKey::getForeignTable() instead
     * @return Table
     */
    protected function getForeignTable(ForeignKey $fk)
    {
        return $this->getTable()->getDatabase()->getTable($fk->getForeignTableName());
    }

    /**
     * Convenience method to get the default Join Type for a relation.
     * If the key is required, an INNER JOIN will be returned, else a LEFT JOIN will be suggested,
     * unless the schema is provided with the DefaultJoin attribute, which overrules the default Join Type
     *
     * @param ForeignKey $fk
     *
     * @return string
     */
    protected function getJoinType(ForeignKey $fk)
    {
        if ($defaultJoin = $fk->getDefaultJoin()) {
            return "'" . $defaultJoin . "'";
        }
        if ($fk->isLocalColumnsRequired()) {
            return 'Criteria::INNER_JOIN';
        }

        return 'Criteria::LEFT_JOIN';
    }

    /**
     * Gets the PHP method name affix to be used for fkeys for the current table (not referrers to this table).
     *
     * The difference between this method and the getRefFKPhpNameAffix() method is that in this method the
     * classname in the affix is the foreign table classname.
     *
     * @param ForeignKey $fk     The local FK that we need a name for.
     * @param boolean    $plural Whether the php name should be plural (e.g. initRelatedObjs() vs. addRelatedObj()
     *
     * @return string
     */
    public function getFKPhpNameAffix(ForeignKey $fk, $plural = false)
    {
        if ($fk->getPhpName()) {
            if ($plural) {
                return $this->getPluralizer()->getPluralForm($fk->getPhpName());
            } else {
                return $fk->getPhpName();
            }
        } else {
            $className = $fk->getForeignTable()->getPhpName();
            if ($plural) {
                $className = $this->getPluralizer()->getPluralForm($className);
            }

            return $className . $this->getRelatedBySuffix($fk);
        }
    }

    /**
     * Gets the "RelatedBy*" suffix (if needed) that is attached to method and variable names.
     *
     * The related by suffix is based on the local columns of the foreign key.  If there is more than
     * one column in a table that points to the same foreign table, then a 'RelatedByLocalColName' suffix
     * will be appended.
     *
     * @return string
     *
     * @throws Exception
     */
    protected static function getRelatedBySuffix(ForeignKey $fk)
    {
        $relCol = '';
        foreach ($fk->getLocalForeignMapping() as $localColumnName => $foreignColumnName) {
            $localTable = $fk->getTable();
            $localColumn = $localTable->getColumn($localColumnName);
            if (!$localColumn) {
                throw new Exception("Could not fetch column: $localColumnName in table " . $localTable->getName());
            }
            if (count($localTable->getForeignKeysReferencingTable($fk->getForeignTableName())) > 1
             || count($fk->getForeignTable()->getForeignKeysReferencingTable($fk->getTableName())) > 0
             || $fk->getForeignTableName() == $fk->getTableName()) {
                // self referential foreign key, or several foreign keys to the same table, or cross-reference fkey
                $relCol .= $localColumn->getPhpName();
            }
        }

        if ($relCol != '') {
            $relCol = 'RelatedBy' . $relCol;
        }

        return $relCol;
    }

    /**
     * Gets the PHP method name affix to be used for referencing foreign key methods and variable names (e.g. set????(), $coll???).
     *
     * The difference between this method and the getFKPhpNameAffix() method is that in this method the
     * classname in the affix is the classname of the local fkey table.
     *
     * @param ForeignKey $fk     The referrer FK that we need a name for.
     * @param boolean    $plural Whether the php name should be plural (e.g. initRelatedObjs() vs. addRelatedObj()
     *
     * @return string
     */
    public function getRefFKPhpNameAffix(ForeignKey $fk, $plural = false)
    {
        if ($fk->getRefPhpName()) {
            if ($plural) {
                return $this->getPluralizer()->getPluralForm($fk->getRefPhpName());
            } else {
                return $fk->getRefPhpName();
            }
        } else {
            $className = $fk->getTable()->getPhpName();
            if ($plural) {
                $className = $this->getPluralizer()->getPluralForm($className);
            }

            return $className . $this->getRefRelatedBySuffix($fk);
        }
    }

    protected static function getRefRelatedBySuffix(ForeignKey $fk)
    {
        $relCol = '';
        foreach ($fk->getLocalForeignMapping() as $localColumnName => $foreignColumnName) {
            $localTable = $fk->getTable();
            $localColumn = $localTable->getColumn($localColumnName);
            if (!$localColumn) {
                throw new Exception("Could not fetch column: $localColumnName in table " . $localTable->getName());
            }
            $foreignKeysToForeignTable = $localTable->getForeignKeysReferencingTable($fk->getForeignTableName());
            if ($fk->getForeignTableName() == $fk->getTableName()) {
                // self referential foreign key
                $relCol .= $fk->getForeignTable()->getColumn($foreignColumnName)->getPhpName();
                if (count($foreignKeysToForeignTable) > 1) {
                    // several self-referential foreign keys
                    $relCol .= array_search($fk, $foreignKeysToForeignTable);
                }
            } elseif (count($foreignKeysToForeignTable) > 1 || count($fk->getForeignTable()->getForeignKeysReferencingTable($fk->getTableName())) > 0) {
                // several foreign keys to the same table, or symmetrical foreign key in foreign table
                $relCol .= $localColumn->getPhpName();
            }
        }

        if ($relCol != '') {
            $relCol = 'RelatedBy' . $relCol;
        }

        return $relCol;
    }

    /**
     * Whether to add the include statements.
     * This is based on the build property propel.addIncludes
     */
    protected function isAddIncludes()
    {
        return $this->getBuildProperty('addIncludes');
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $modifier The name of the modifier object providing the method in the behavior
     *
     * @return boolean
     */
    public function hasBehaviorModifier($hookName, $modifier)
    {
        $modifierGetter = 'get' . $modifier;
        foreach ($this->getTable()->getBehaviors() as $behavior) {
            if (method_exists($behavior->$modifierGetter(), $hookName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $modifier The name of the modifier object providing the method in the behavior
     * @param string &$script  The script will be modified in this method.
     * @param string $tab
     */
    public function applyBehaviorModifierBase($hookName, $modifier, &$script, $tab = "		")
    {
        $modifierGetter = 'get' . $modifier;
        foreach ($this->getTable()->getBehaviors() as $behavior) {
            $modifier = $behavior->$modifierGetter();

            if (method_exists($modifier, $hookName)) {
                if (strpos($hookName, 'Filter') !== false) {
                    // filter hook: the script string will be modified by the behavior
                    $modifier->$hookName($script, $this);
                } else {
                    // regular hook: the behavior returns a string to append to the script string
                    if (!$addedScript = $modifier->$hookName($this)) {
                        continue;
                    }

                    $script .= "
" . $tab . '// ' . $behavior->getName() . " behavior
";
                    $script .= preg_replace('/^/m', $tab, $addedScript);
                }
            }
        }
    }

    /**
     * Checks whether any registered behavior content creator on that table exists a contentName
     *
     * @param string $contentName The name of the content as called from one of this class methods, e.g. "parentClassname"
     * @param string $modifier    The name of the modifier object providing the method in the behavior
     */
    public function getBehaviorContentBase($contentName, $modifier)
    {
        $modifierGetter = 'get' . $modifier;
        foreach ($this->getTable()->getBehaviors() as $behavior) {
            $modifier = $behavior->$modifierGetter();
            if (method_exists($modifier, $contentName)) {
                return $modifier->$contentName($this);
            }
        }
    }

    /**
     * Most of the code comes from the PHP-CS-Fixer project
     */
    private function clean($content)
    {
        // trailing whitespaces
        $content = preg_replace('/[ \t]*$/m', '', $content);

        // indentation
        $content = preg_replace_callback('/^([ \t]+)/m', array($this, 'fixTrailingWhitespaces'), $content);

        // line feed
        $content = str_replace("\r\n", "\n", $content);

        // Unused "use" statements
        preg_match_all('/^use (?P<class>[^\s;]+)(?:\s+as\s+(?P<alias>.*))?;/m', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (isset($match['alias'])) {
                $short = $match['alias'];
            } else {
                $parts = explode('\\', $match['class']);
                $short = array_pop($parts);
            }

            preg_match_all('/\b' . $short . '\b/i', str_replace($match[0] . "\n", '', $content), $m);
            if (!count($m[0])) {
                $content = str_replace($match[0] . "\n", '', $content);
            }
        }

        // end of line
        if (strlen($content) && "\n" != substr($content, -1)) {
            $content = $content . "\n";
        }

        return $content;
    }

    public function fixTrailingWhitespaces($matches)
    {
        return str_replace("\t", '    ', $matches[0]);
    }
}
