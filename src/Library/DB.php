<?php
namespace Litesupabase\Library;
use Exception;
use PDO;
use PDOException;
use PDOStatement;

class DB {
    /**
     * @var PDO
     */
    private PDO $connection;
    /**
     * @var array
     */
    private array $data = [];
    /**
     * @var int
     */
    private int $affected;

    /**
     * Constructor
     *
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $dbname
     * @param string $port
     * @throws Exception
     */
    public function __construct(string $host, string $user, string $pass, string $dbname, string $port = '3306') {

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5, // 5 seconds timeout
        ];
        try {
            $this->connection = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
        } catch (PDOException $e) {
            throw new \Exception('Error: Could not make a database link using ' . $user . '@' . $host . ',' .$e->getMessage());
        }
    }

    /**
     * Query
     *
     * @param string $sql
     *
     * @return   mixed
     * @throws Exception
     */
    public function query(string $sql): mixed
    {
        $statement = $this->connection->prepare($sql);
        return $this->execute($statement, $sql);
    }

    /**
     * @param string $sql
     * @return false|PDOStatement
     */
    public function prepare(string $sql): bool|PDOStatement
    {
        return $this->connection->prepare($sql);
    }

    /**
     * @param PDOStatement $statement
     * @param $key
     * @param $param
     * @param $type
     * @return PDOStatement
     */
    public function bindParam(PDOStatement $statement, $key, $param, $type): PDOStatement
    {
        $paramType = PDO::PARAM_STR;
        if ($type==='int') $paramType = PDO::PARAM_INT;
        $statement->bindParam($key, $param, $paramType);
        return $statement;
    }

    /**
     * @throws Exception
     */
    public function execute($statement, $sql): bool|\stdClass
    {
        try {
            if ($statement && $statement->execute($this->data)) {
                $this->data = [];
                if ($statement->columnCount()) {
                    $data = $statement->fetchAll(PDO::FETCH_ASSOC);
                    $result = new \stdClass();
                    $result->row = $data[0] ?? [];
                    $result->rows = $data;
                    $result->num_rows = count($data);
                    $this->affected = 0;
                    $statement->closeCursor();
                    return $result;
                } else {
                    $this->affected = $statement->rowCount();
                    $statement->closeCursor();
                    return true;
                }
            } else {
                return false;
            }
        } catch (PDOException $e) {
            throw new Exception('Error: ' . $e->getMessage() . ' <br>Error Code : ' . $e->getCode() . ' <br>'. $sql);
        }
    }

    /**
     * Escape
     *
     * @param    string $value value
     * @return   string
     */
    public function escape(string $value): string {
        $key = ':' . count($this->data);
        $this->data[$key] = $value;
        return $key;
    }

    /**
     * countAffected
     *
     * @return   int
     */
    public function countAffected(): int {
        return $this->affected;
    }

    /**
     * getLastId
     *
     * @return   int
     */
    public function getLastId(): int {
        return $this->connection->lastInsertId();
    }

    /**
     * isConnected
     *
     * @return   PDO
     */
    public function isConnected(): PDO
    {
        return $this->connection;
    }

    public function setAttribute(): void
    {
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }
    public function commit(): void
    {
        $this->connection->commit();
    }
    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function setData($data): void
    {
        $this->data = $data;
    }

    public function lastInsertId(): bool|string
    {
        return $this->connection->lastInsertId();
    }
}