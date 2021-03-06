<?php
class Shopware_Components_DbDiff_Mysql
{
    /**
     * @var PDO
     */
    protected $source;

    /**
     * @var PDO
     */
    protected $target;

    public function __construct(PDO $source, PDO $target)
    {
        $this->source = $source;
        $this->target = $target;
    }

    public function listTables()
    {
        $query = $this->source->query('SHOW TABLES');
        $sourceTables = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        $query = $this->target->query('SHOW TABLES');
        $targetTables = $query->fetchAll(PDO::FETCH_COLUMN, 0);

        return array_unique(array_merge($sourceTables, $targetTables));
    }

    public function getTableUpdate($table, $options = array())
    {
        $diff = '';
        $backupTable = isset($options['backupTable']) ? $options['backupTable'] : 'backup_' . $table;
        $newTable = isset($options['newTable']) ? $options['newTable'] : 'new_' . $table;
        $sourceFields = $this->getTableFields($this->source, $table);
        $targetFields = $this->getTableFields($this->target, $table);

        if(empty($sourceFields)) {
            if(empty($options['backup'])) {
                $diff .= "DROP TABLE IF EXISTS `$table`;\n";
            } else {
                $diff .= "DROP TABLE IF EXISTS `$backupTable`;\n";
                $diff .= "RENAME TABLE `$table` TO `$backupTable`;\n";
            }
        } elseif(empty($targetFields)) {
            $diff .= "DROP TABLE IF EXISTS `$table`;\n";
            $diff .= $this->getTable($this->source, $table);
            $diff .= $this->getTableData($this->source, $table);
        } else {
            $new = !empty($options['backup']) || count($sourceFields) != count($targetFields);
            if(!$new) {
                foreach($sourceFields as $sourceField => $sourceFieldData) {
                    if(!isset($targetFields[$sourceField])
                      || $sourceFieldData != $targetFields[$sourceField]) {
                        $new = true; break;
                    }
                }
            }
            if($new) {
                if(isset($options['mapping'])) {
                    foreach($options['mapping'] as $source => $target) {
                        if(isset($targetFields[$source])) {
                            $targetFields[$target] = $targetFields[$source];
                        }
                    }
                }
                $intersectFields = array_keys(array_intersect_key($sourceFields, $targetFields));
                $intersectFields = '`' . implode('`, `', $intersectFields) . '`';

                $diff .= $this->getTable($this->source, $table, array(
                    'newTable' => $newTable,
                    'ifNotExists' => true
                ));
                $tableData = null;
                if(!empty($options['backup'])) {
                    $tableData = $this->getTableData($this->source, $table, array(
                        'newTable' => $newTable
                    ));
                }
                if($tableData === null) {
                    $tableData = "INSERT IGNORE INTO `$newTable` ($intersectFields)\n";
                    if(isset($options['mapping'])) {
                        foreach($options['mapping'] as $source => $target) {
                            $intersectFields = str_replace("`$target`", "`$source`", $intersectFields);
                        }
                    }
                    $tableData .= "SELECT $intersectFields FROM `$table`;\n";
                }
                $diff .= $tableData;
                if(empty($options['backup'])) {
                    $diff .= "DROP TABLE IF EXISTS `$table`;\n";
                } else {
                    $diff .= "DROP TABLE IF EXISTS `$backupTable`;\n";
                    $diff .= "RENAME TABLE `$table` TO `$backupTable`;\n";
                }
                $diff .= "RENAME TABLE `$newTable` TO `$table`;\n";
            } else {
                $sourceStatus = $this->getTableStatus($this->source, $table, false);
                $targetStatus = $this->getTableStatus($this->target, $table, false);
                if($sourceStatus != $targetStatus) {
                    $newStatus = str_replace(' DEFAULT CHARSET=', ', CONVERT TO CHARACTER SET ', $sourceStatus);
                    $newStatus = str_replace(' COLLATE=', ', COLLATE ', $newStatus);
                    $diff .= "ALTER TABLE `$table` $newStatus;\n";
                }
            }
        }
        $diff .= "\n";
        return $diff;
    }

    public function getTableDiff($table)
    {
        $diff = '';
        $sourceFields = $this->getTableFields($this->source, $table);
        $targetFields = $this->getTableFields($this->target, $table);

        if(empty($sourceFields)) {
            $diff .= "DROP TABLE `$table`;\n";
        } elseif(empty($targetFields)) {
            $diff .= $this->getTable(
                $this->source, $table,
                array('autoIncrement' => false)
            );
        } else {
            $sourceKeys = $this->getTableKeys($this->source, $table);
            $targetKeys = $this->getTableKeys($this->target, $table);
            $keys = array_keys(array_merge($sourceKeys, $targetKeys));
            $fields = array_keys(array_merge($sourceFields, $targetFields));

            foreach($keys as $key) {
                if(!isset($sourceKeys[$key])) {
                    $diff .= "ALTER TABLE `$table` DROP INDEX `$key`;\n";
                }
            }

            foreach($fields as $field) {
                if(!isset($targetFields[$field])) {
                    $diff .= "ALTER TABLE `$table` ADD `$field` {$sourceFields[$field]};\n";
                } elseif(!isset($sourceFields[$field])) {
                    $diff .= "ALTER TABLE `$table` DROP `$field`;\n";
                } elseif($sourceFields[$field] != $targetFields[$field]) {
                    $diff .= "ALTER TABLE `$table` CHANGE `$field` `$field` {$sourceFields[$field]};\n";
                }
            }

            foreach($keys as $key) {
                if(!isset($targetKeys[$key])) {
                    $diff .= "ALTER TABLE `$table` ADD {$sourceKeys[$key]};\n";
                } elseif(isset($sourceKeys[$key]) && $sourceKeys[$key] != $targetKeys[$key]) {
                    $diff .= "ALTER TABLE `$table` CHANGE `$key` {$sourceKeys[$key]};\n";
                }
            }

            $sourceStatus = $this->getTableStatus($this->source, $table, false);
            $targetStatus = $this->getTableStatus($this->target, $table, false);
            if($sourceStatus != $targetStatus) {
                $diff .= "ALTER TABLE `$table` $sourceStatus;\n";
            }
        }

        return $diff;
    }

    public static function getTable(PDO $db, $table, $options = array())
    {
        $sql = "SHOW CREATE TABLE `$table`";
        $result = $db->query($sql);
        $return = $result->fetchColumn(1);
        if($return === false) {
            return null;
        }
        if(!empty($options['newTable'])) {
            $return = str_replace("CREATE TABLE `$table`", "CREATE TABLE `{$options['newTable']}`", $return);
        }
        if(!empty($options['ifNotExists'])) {
            $return = str_replace("CREATE TABLE ", "CREATE TABLE IF NOT EXISTS ", $return);
        }
        if(isset($options['autoIncrement'])) {
            $replace = $options['autoIncrement'] === false ? '' : ' AUTO_INCREMENT=' . (int)$options['autoIncrement'];
            $return = preg_replace("# AUTO_INCREMENT=[0-9]+#", $replace, $return);
        }
        if(!empty($options['dropIfExists'])) {
            $newTable = isset($options['newTable']) ? $options['newTable'] : $table;
            $return = "DROP TABLE IF EXISTS `$newTable`;\n" . $return;
        }
        $return .= ";\n";
        return $return;
    }

    public static function getTableCollation(PDO $db, $table)
    {
        $sql = 'SHOW TABLE STATUS WHERE Name=?';
        $query = $db->prepare($sql);
        $query->execute(array($table));
        $status = $query->fetch(PDO::FETCH_ASSOC);
        if(!isset($status['Collation'])) {
            return null;
        }
        return $status['Collation'];
    }

    public static function getTableData(PDO $db, $table, $options = array())
    {
        $newTable = isset($options['newTable']) ? $options['newTable'] : $table;
        $sql = "SELECT * FROM `{$table}`";
        if(isset($options['limit'])) {
            $limit = (int)$options['limit'];
            $offset = isset($options['offset']) ? (int)$options['offset'] : 0;
            $sql .= " LIMIT $limit OFFSET $offset";
        }
        $result = $db->query($sql);
        if (!$result->rowCount()) {
            return null;
        }

        $values = $result->fetch(PDO::FETCH_ASSOC);
        $fields = array_keys($values);
        $values = array_values($values);
        $rows = array();
        do {
            $row = array();
            foreach ($values as $value) {
                $row[] = $value === NULL ? 'NULL' : $db->quote($value);
            }
            $rows[] = implode(', ', $row);
        } while ($values = $result->fetch(PDO::FETCH_NUM));
        $rows = implode("),\n(", $rows);

        $fields = '`' . implode('`, `', $fields) . '`';
        $return = "INSERT IGNORE INTO `$newTable` ($fields) VALUES\n($rows);\n";

        return $return;
    }

    public static function getTableStatus(PDO $db, $table, $withAutoIncrement = true)
    {
        $sql = 'SHOW TABLE STATUS WHERE Name=?';
        $query = $db->prepare($sql);
        $query->execute(array($table));
        $status = $query->fetch(PDO::FETCH_ASSOC);
        if($status === false) {
            return null;
        }
        $line = '';
        if (!empty($status['Engine'])) {
            $line .= 'ENGINE=' . $status['Engine'];
        }
        if (!empty($status['Collation'])) {
            $status['Charset'] = strstr($status['Collation'], '_', true);
            $line .= ' DEFAULT CHARSET=' . $status['Charset'] .' ' .
                'COLLATE=' . $status['Collation'];
        }
        if ($withAutoIncrement && !empty($status['Auto_increment'])) {
            $line .= ' AUTO_INCREMENT=' . $status['Auto_increment'];
        }
        return $line;
    }

    public static function getTableFields(PDO $db, $table)
    {
        $sql = "SHOW FULL COLUMNS FROM `$table`";
        $result = $db->query($sql);
        if($result == false) {
            return array();
        }
        $tableCollation = self::getTableCollation($db, $table);
        $fields = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $line = "{$row['Type']}";
            if (!empty($row['Collation']) && $row['Collation'] != $tableCollation) {
                $line .= ' COLLATE ' . $row['Collation'];
            }
            if ($row['Null'] != 'YES') {
                $line .= ' NOT NULL';
            }
            if ($row['Default'] == 'CURRENT_TIMESTAMP') {
                $line .= ' default CURRENT_TIMESTAMP';
            } elseif (isset($row['Default'])) {
                $line .= " default '{$row['Default']}'";
            } elseif ($row['Null'] == 'YES') {
                $line .= ' default NULL';
            }
            if (!empty($row['Extra'])) {
                $line .= ' ' . $row['Extra'];
            }
            $fields[$row['Field']] = $line;
        }
        return $fields;
    }

    public static function getTableKeys(PDO $db, $table)
    {
        $keys = array();
        $keyTypes = array();

        $sql = "SHOW KEYS FROM `$table`";
        $result = $db->query($sql);

        if ($result->rowCount()) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                if ($row['Key_name'] == 'PRIMARY') {
                    $keys['PRIMARY'][] = $row['Column_name'];
                } elseif ($row['Index_type'] == 'FULLTEXT') {
                    $keyTypes[$row['Key_name']] = 'FULLTEXT';
                    $keys[$row['Key_name']][] = $row['Column_name'];
                } elseif ($row['Non_unique'] == 0) {
                    $keyTypes[$row['Key_name']] = 'UNIQUE';
                    $keys[$row['Key_name']][] = $row['Column_name'];
                } else {
                    $keyTypes[$row['Key_name']] = 'INDEX';
                    $keys[$row['Key_name']][] = $row['Column_name'];
                }
            }
            foreach($keys as $name => $key) {
                $type = isset($keyTypes[$name]) ? $keyTypes[$name] : 'PRIMARY';
                $line = $type;
                if ($type != 'INDEX') {
                    $line .= " KEY";
                }
                if ($type != 'PRIMARY') {
                    $line .= " `$name`";
                };
                $line .= " (`" . implode("`, `", $key) . "`)";
                $keys[$name] = $line;
            }
        }
        return $keys;
    }
}