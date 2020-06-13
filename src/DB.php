<?php
namespace Kore;

use Kore\Config;
use Kore\Log;

class DB
{
    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @param string $dbconfig
     * @return void
     */
    final private function __construct($dbconfig)
    {
        $this->connect($dbconfig);
    }

    /**
     * @return void
     * @throws \Exception
     */
    final public function __clone()
    {
        throw new \Exception('__clone is not allowed!');
    }

    /**
     * @param string $dbconfig
     * @return self
     */
    public static function connection($dbconfig='database')
    {
        static $instances = [];
        if (empty($instances[$dbconfig])) {
            $instances[$dbconfig] = new static($dbconfig);
        }
        return $instances[$dbconfig];
    }

    /**
     * @param string $dbconfig
     * @return void
     * @throws \PDOException
     */
    protected function connect($dbconfig)
    {
        $config = Config::get($dbconfig);
        if ($config === null) {
            throw new \Exception("Databse config $dbconfig is not found!");
        }
        $host = $config['host'];
        $db = $config['db'];
        $port = $config['port'];
        $user = $config['user'];
        $pass = $config['pass'];

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8', $host, $port, $db);
        $this->pdo = new \PDO($dsn, $user, $pass);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param string $query
     * @param array<mixed> $params
     * @return array<mixed>|false
     */
    public function select($query, $params = [])
    {
        $stm = $this->execute($query, $params);

        $result = $stm->fetchAll();
        return $result;
    }

    /**
     * @param string $query
     * @param array<mixed> $params
     * @return mixed|false
     */
    public function count($query, $params = [])
    {
        $stm = $this->execute($query, $params);

        $result = $stm->fetchColumn();
        return $result;
    }

    /**
     * @param string $query
     * @param array<mixed> $params
     * @return string
     */
    public function insert($query, $params = [])
    {
        $stm = $this->execute($query, $params);

        return $this->pdo->lastInsertId();
    }

    /**
     * @param string $query
     * @param array<mixed> $params
     * @return int
     */
    public function update($query, $params = [])
    {
        $stm = $this->execute($query, $params);

        return $stm->rowCount();
    }

    /**
     * @param string $query
     * @param array<mixed> $params
     * @return int
     */
    public function delete($query, $params = [])
    {
        $stm = $this->execute($query, $params);

        return $stm->rowCount();
    }

    /**
     * @param callable $callback
     * @return void
     */
    public function transaction($callback)
    {
        try {
            $this->pdo->beginTransaction();
            $callback();
            $this->pdo->commit();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->pdo->rollback();
        }
    }

    /**
     * @param string $marker
     * @param array<mixed> $values
     * @return array<mixed>
     */
    public static function getInClause($marker, $values)
    {
        $inClause = 'IN (';
        $params = [];
        foreach ($values as $i => $value) {
            if ($i !== 0) {
                $inClause .= ', ';
            }
            $key = $marker.'_'.$i;
            $inClause .= $key;
            $params[$key] = $value;
        }
        $inClause .= ')';
        return [$inClause, $params];
    }

    /**
     * @param string $query
     * @param array<mixed> $params
     * @return \PDOStatement<mixed>
     */
    private function execute($query, $params)
    {
        $stm = $this->pdo->prepare($query);

        $keys = [];
        $values = [];
        foreach ($params as $key => $value) {
            $parameter = ":{$key}";
            $keys[] = $parameter;
            $values[] = is_string($value) ? "'$value'" : $value;
            $stm->bindValue($parameter, $value, $this->dataType($value));
        }

        $start = microtime(true);
        $stm->execute();
        $end = microtime(true);
        Log::debug(sprintf('%f - %s', $end-$start, str_replace($keys, $values, preg_replace(['/[\n\t]/', '/\s+/'], ['', ' '], $query))));
        return $stm;
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function dataType($value)
    {
        switch (gettype($value)) {
            case 'boolean':
                $datatype = \PDO::PARAM_BOOL;
                // no break
            case 'integer':
                $datatype = \PDO::PARAM_INT;
                // no break
            case 'double':
                // doubleに対応するdatatypeがないのでSTR
                $datatype = \PDO::PARAM_STR;
                // no break
            case 'string':
                $datatype = \PDO::PARAM_STR;
                // no break
            case 'NULL':
                $datatype = \PDO::PARAM_NULL;
                // no break
            default:
                $datatype = \PDO::PARAM_STR;
        }
        return $datatype;
    }
}
