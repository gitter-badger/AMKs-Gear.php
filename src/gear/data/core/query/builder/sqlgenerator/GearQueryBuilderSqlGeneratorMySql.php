<?php
//$SOURCE_LICENSE$

/*<namespace.current>*/
namespace gear\data\core\query\builder\sqlgenerator;
    /*</namespace.current>*/
/*<namespace.use>*/
use gear\arch\core\GearArgumentNullException;
use gear\arch\core\GearInvalidOperationException;
use gear\data\core\query\builder\GearQueryBuilder;
use gear\data\core\query\builder\sqlgenerator\IGearQueryBuilderSqlGenerator;

/*</namespace.use>*/

/*<bundles>*/
/*</bundles>*/

/*<module>*/

class GearQueryBuilderSqlGeneratorMySql implements IGearQueryBuilderSqlGenerator
{
    public function createSelect(
        $table,
        $cols,
        $conditions,
        $limit,
        $grouping,
        $ordering,
        $join)
    {
        $result = $this->_createSelect(
            $table,
            $cols,
            $conditions,
            $limit,
            $grouping,
            $ordering,
            $join);

        return $result;
    }

    private function _createSelect(
        $table,
        $cols,
        $conditions,
        $limit,
        $grouping,
        $ordering,
        $join)
    {
        if ($table == null) {
            throw new GearArgumentNullException('table');
        }

        if ($cols == null) {
            $cols = '*';
        }

        if ($conditions != null) {
            $conditions = "WHERE $conditions";
        }

        if ($limit != null) {
            $limit = $this->formatLimit($limit);
        }

        return trim("SELECT $cols FROM $table $conditions $limit $join");
    }

    public function formatLimit($limit)
    {
        if ($limit == null) {
            return null;
        } else if($limit == GearQueryBuilder::GearQueryBuilderLimitOne) {
            return 'LIMIT 1';
        }

        $col = strpos($limit, ':');
        if ($col === false) {
            return null;
        }
        $limitType = substr($limit, 0, $col);
        $limitValue = substr($limit, $col);

        switch ($limitType) {
            case GearQueryBuilder::GearQueryBuilderLimitNRecordSig:
                return "LIMIT $limitValue";
            case GearQueryBuilder::GearQueryBuilderLimitRangeSig:
                $parts = explode('-', $limitValue);
                if (count($parts) != 2) {
                    return null;
                }
                $offset = intval($parts[0]);
                $highVal = intval($parts[1]);
                if ($offset > $highVal) {
                    throw new GearInvalidOperationException("Invalid range encountered on query");
                }
                $count = $highVal - $offset;
                return "LIMIT $count OFFSET {$parts[0]}";
            case GearQueryBuilder::GearQueryBuilderLimitOffsetNRecordSig:
                $parts = explode('-', $limitValue);
                if (count($parts) != 2) {
                    return null;
                }
                $offset = intval($parts[0]);
                $count = intval($parts[1]);
                return "LIMIT $count OFFSET {$parts[0]}";
            default: return null;
        }
    }
}

/*</module>*/
?>