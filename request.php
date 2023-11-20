<?php

/** @noinspection PhpUnhandledExceptionInspection
 * @noinspection UnknownInspectionInspection
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');

/**
 * Environment variables
 */
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASSWORD = 'mysql';
const DB_PORT = 3306;
const DB_NAME = 'db_cisco';


function dd(...$args)
{
    print_r($args);
    die();
}


/**************************************************
 * MODELS AND DB CONTROLLERS
 **************************************************/
interface IModel
{
    public static function factory(): self;

    public function toArray(): array;
}

abstract class ModelBase
{
    public string $_table = '';

    /**
     * @throws ConfigurationError
     */
    public function __construct($data = [])
    {
        if (!$this->_table) {
            $class = static::class;
            throw new ConfigurationError("Property table is not set in $class");
        }
        $this->fill($data);
    }

    private function fill(array $data): void
    {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }

    public static function getWhere(?string $conditions = ''): Collection
    {
        $db = Database::getClient();

        $class = static::class;
        $model = new $class([]);
        $rows = $db->fetchQuery($model->_table, $conditions);
        $return = [];
        if ($rows) {
            foreach ($rows as $row) {
                $return[] = new $class($row);
            }
        }
        return new Collection($return);
    }

    public static function getAll($query = ''): Collection
    {
        $db = Database::getClient();

        $class = static::class;
        $model = new $class([]);
        $rows = $db->fetchQuery($model->_table, $query);
        $return = [];
        if ($rows) {
            foreach ($rows as $row) {
                $return[] = new $class($row);
            }
        }
        return new Collection($return);
    }

    public static function delete($id): bool
    {
        if (!$id) {
            return true;
        }
        $db = Database::getClient();

        $class = static::class;
        $model = new $class([]);
        return $db->delete($model->_table, 'id', $id);
    }

    public static function create(array $data): self
    {
        $db = Database::getClient();

        $class = static::class;
        $model = new $class([]);
        $db->insert($model->_table, $data);
        return $model::getOne($db->getLastInsertId());
    }

    /**
     * @param $id
     * @return ModelBase|null
     */
    public static function getOne($id): ?self
    {
        if (!$id) {
            return null;
        }
        $db = Database::getClient();

        $class = static::class;
        $model = new $class([]);
        $rows = $db->fetchSingleRow($model->_table, 'id', $id);
        if ($rows) {
            return new $class($rows);
        }
        return null;
    }

    public static function update(array $data): self
    {
        $db = Database::getClient();

        $class = static::class;
        $model = new $class([]);
        $db->update($model->_table, $data, 'id', $data['id']);

        return new $class($data);
    }
}

class Collection
{
    public array $items;

    /**
     * @param ModelBase[] $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function toArray(): array
    {
        $array = [];
        foreach ($this->items as $item) {
            $array[] = $item->toArray();
        }
        return $array;
    }

    public function getIDs(): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            $ids[] = $item->id;
        }
        return $ids;
    }
}

class RequestItemModel extends ModelBase implements IModel
{
    public int $id;
    public int $request_id;
    public int $item_id;
    public string $_table = 'request_items';

    public static function factory(): IModel
    {
        return new self([
            'id' => 1,
            'request_id' => 1,
            'item_id' => 1,
        ]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'item_id' => $this->item_id,
        ];
    }
}

class RequestModel extends ModelBase implements IModel
{
    public ?int $id = null;
    public ?string $requested_by = null;
    public ?string $requested_on = null;
    public ?string $ordered_on = null;
    public string $_table = 'requests';

    public static function factory(): self
    {
        $items = new Collection([
            ItemModel::factory(),
            ItemModel::factory(),
            ItemModel::factory(),
        ]);
        return new self([
            'id' => 1,
            'requested_by' => 'Paper',
            'requested_on' => '2023-11-21 14:40:00',
            'ordered_on' => '2023-11-22 11:20:00',
            'items' => $items->toArray(),
        ]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'requested_by' => $this->requested_by,
            'requested_on' => $this->requested_on,
            'ordered_on' => $this->ordered_on,
            'items' => $this->items()->toArray(),
        ];
    }

    public function items(): Collection
    {
        $requestItems = RequestItemModel::getWhere("request_id = $this->id");
        $idsAux = [];
        foreach ($requestItems->items as $row) {
            /** @var RequestItemModel $row */
            $idsAux[] = $row->item_id;
        }
        if (count($idsAux)) {
            $ids = implode(',', $idsAux);
            $condition = " id in ($ids)";
            return ItemModel::getWhere($condition);
        }
        return new Collection([]);
    }

    public static function update(array $data): self
    {
        /** @var RequestModel $newModel */
        $newModel = parent::update([
            'id' => $data['id'] ?? null,
            'requested_by' => $data['requested_by'] ?? null,
            'requested_on' => $data['requested_on'] ?? null,
            'ordered_on' => $data['ordered_on'] ?? null,
        ]);

        self::deleteItems($data['id']);

        if (array_key_exists('items', $data)) {
            foreach ($data['items'] as $item) {
                RequestItemModel::create([
                    'request_id' => $newModel->id,
                    'item_id' => $item['id'],
                ]);
            }
            SummaryModel::syncRequest($data['requested_by']);
        }

        return $newModel;
    }

    private static function deleteItems($request_id): void
    {
        $db = Database::getClient();
        $class = RequestItemModel::class;
        $model = new $class([]);
        $db->delete($model->_table, 'request_id', $request_id);
    }

    public static function delete($id): bool
    {
        /** @var RequestModel $request */
        $request = self::getOne($id);
        if (!$request) {
            return true;
        }

        self::deleteItems($id);
        SummaryModel::syncRequest($request->requested_by);
        return parent::delete($id);
    }

    public static function create(array $data): self
    {
        /** @var RequestModel $newModel */
        $newModel = parent::create([
            'id' => $data['id'] ?? null,
            'requested_by' => $data['requested_by'] ?? null,
            'requested_on' => (new \DateTime())->format('Y-m-d H:i:s'),
            'ordered_on' => $data['ordered_on'] ?? null,
        ]);

        if (array_key_exists('items', $data)) {
            foreach ($data['items'] as $item) {
                RequestItemModel::create([
                    'request_id' => $newModel->id,
                    'item_id' => $item['id'],
                ]);
            }
        }

        SummaryModel::syncRequest($newModel->requested_by);

        return $newModel;
    }
}

class SummaryModel extends ModelBase implements IModel
{
    public ?int $id = null;
    public ?string $requested_by = null;
    public ?string $ordered_on = null;
    public ?string $items = null;
    public string $_table = 'summary';

    public static function factory(): self
    {
//        $items = new Collection([
//            ItemModel::factory(),
//            ItemModel::factory(),
//            ItemModel::factory(),
//        ]);
//        return new self([
//            'id' => 1,
//            'requested_by' => 'Paper',
//            'requested_on' => '2023-11-21 14:40:00',
//            'ordered_on' => '2023-11-22 11:20:00',
//            'items' => $items->toArray(),
//        ]);
        return new self();
    }

    /** @noinspection UnknownInspectionInspection */
    public static function syncRequest(string $requested_by = null): void
    {
        if (!$requested_by) {
            return;
        }
        /** @var SummaryModel $summary */
        $summaryQuery = self::getWhere("requested_by = '$requested_by'");

        if (count($summaryQuery->items)) {
            $summary = $summaryQuery->items[0];
        } else {
            $summary = self::create([
                'requested_by' => $requested_by,
                'ordered_on' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }

        $requestsIDs = RequestModel::getWhere("requested_by = '$requested_by'")->getIDs();

        $requestItems = RequestItemModel::getWhere('request_id IN (' . implode(',', $requestsIDs) . ')')->items;

        if (!count($requestItems)) {
            self::delete($summary->id);
            return;
        }

        $itemsIDs = [];
        foreach ($requestItems as $requestItem) {
            /** @var RequestItemModel $requestItem */
            $itemsIDs[] = $requestItem->item_id;
        }

        $items = ItemModel::getWhere('id in (' . implode(',', $itemsIDs) . ')');
        $itemTypesAux = [];
        foreach ($items->items as $item) {
            /** @var ItemModel $item */
            $itemTypesAux[$item->item_type_id][] = $item->id;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        self::update([
            'id' => $summary->id,
            'requested_by' => $summary->requested_by,
            'ordered_on' => $summary->ordered_on,
            'items' => json_encode($itemTypesAux, JSON_THROW_ON_ERROR)
        ]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'requested_by' => $this->requested_by,
            'ordered_on' => $this->ordered_on,
            'items' => $this->items(),
        ];
    }

    public function items(): array
    {
        if (!$this->items) {
            return [];
        }
        $items = json_decode($this->items, false, 512, JSON_THROW_ON_ERROR);
        $return = [];
        foreach ($items as $itemTypeId => $itemsIds) {
            $return[] = [
                'item_type' => ItemType::getWhere('id = ' . $itemTypeId)->items[0]->toArray(),
                'items' => ItemModel::getWhere('id in (' . implode(',', $itemsIds) . ')')->toArray(),
            ];
        }

        return $return;
    }
}

class ItemModel extends ModelBase implements IModel
{
    public int $id;
    public string $name;
    public ?int $item_type_id = null;
    public string $_table = 'items';

    public static function factory(): self
    {
        $item_type = ItemType::factory();
        $class = static::class;
        return new $class([
            'id' => 1,
            'name' => 'Paper',
            'item_type_id' => $item_type->id,
            'item_type' => $item_type->toArray(),
        ]);
    }

    public function toArray(): array
    {
        $item_type = $this->itemType();
        return [
            'id' => $this->id,
            'name' => $this->name,
            'item_type_id' => $this->item_type_id,
            'item_type' => $item_type ? $item_type->toArray() : null,
        ];
    }

    public function itemType(): ?ItemType
    {
        /** @noinspection UnknownInspectionInspection */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return ItemType::getOne($this->item_type_id);
    }
}

class ItemType extends ModelBase implements IModel
{
    public int $id;
    public string $name;
    public string $_table = 'item_types';

    public static function factory(): self
    {
        return new self([
            'id' => 1,
            'name' => 'Paper',
        ]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}

/**************************************************
 * REQUEST CONTROLLER
 **************************************************/
class Response
{

    public function __construct()
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * @throws JsonException
     */
    public function renderSuccess($data, int $code = 200): bool
    {
        http_response_code($code);
        echo json_encode([
            'data' => $data,
            'code' => $code
        ], JSON_THROW_ON_ERROR);
        return true;
    }

    /**
     * @throws JsonException
     */
    public function renderError(string $message, int $code, $data = []): bool
    {
        http_response_code($code);
        echo json_encode([
            'message' => $message,
            'data' => $data,
            'code' => $code
        ], JSON_THROW_ON_ERROR);
        return false;
    }
}

class RequestHandler
{
    public string $method;
    public array $mapping;

    public function __construct(array $mapping = [])
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->mapping = $mapping;
    }

    public function handle(): bool
    {
        $response = new Response();

        if (!array_key_exists($this->getRoute(), $this->mapping)) {
            throw new InvalidRequest();
        }

        $modelClass = $this->mapping[$this->getRoute()];

        if (!class_exists($modelClass)) {
            throw new InvalidMapping('Class ' . $modelClass . ' not found.');
        }
        $reflectionClass = new ReflectionClass($modelClass);
        if ($reflectionClass->isAbstract()) {
            throw new InvalidMapping('Class ' . $reflectionClass . ' must not be abstract.');
        }


        if ($this->isGet()) {
            $id = $this->getBodyValue('id');
            if ($id) {
                $model = $modelClass::getOne($id);
                if ($model) {
                    return $response->renderSuccess($model->toArray());
                }
            } else {
                $conditions = [];
                foreach ($this->getBodyClean() as $k => $v) {
                    $conditions[] = " $k = '$v'";
                }

                $models = $modelClass::getAll(implode(' AND', $conditions));
                return $response->renderSuccess($models->toArray());
            }
        } elseif ($this->isPost()) {
            $body = $this->getBody();
            $model = $modelClass::create($body);
            return $response->renderSuccess($model->toArray(), 201);
        } elseif ($this->isPut()) {
            $body = $this->getBody();
            $model = $modelClass::update($body);
            return $response->renderSuccess($model->toArray());
        } elseif ($this->isDelete()) {
            $id = $this->getBodyValue('id');
            if ($id) {
                $modelClass::delete($id);
                return $response->renderSuccess(['message' => 'ok']);
            }
        }
        return false;
    }

    public function getRoute(): string
    {
        return array_key_exists('_route', $_GET) ? $_GET['_route'] : '';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * @param $key
     * @return int|string|null
     * @throws JsonException
     */
    public function getBodyValue($key)
    {
        $body = $this->getBody();

        return $body[$key] ?? null;
    }

    public function getBody(): array
    {
        switch ($this->method) {
            case 'GET':
            case 'DELETE':
                return $_GET;
            case 'POST':
            case 'PUT':
                $inputJSON = file_get_contents('php://input');
                return json_decode($inputJSON, true, 512, JSON_THROW_ON_ERROR);
            default:
                return [];
        }
    }

    public function getBodyClean(): ?array
    {
        $array = $this->getBody();
        unset($array['_route'], $array['_']);
        return $array;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }
}

/**************************************************
 * EXCEPTIONS
 **************************************************/
class ModelNotFound extends Exception
{
    public string $model;
    public int $id;

    public function __construct($model, $id)
    {
        $message = $model . ' with id ' . $id . ' not found.';
        $this->model = $model;
        $this->id = $id;
        parent::__construct($message);
    }
}

class InvalidMapping extends Exception
{
}

class InvalidRequest extends Exception
{
    public string $route;
    public string $method;
    public array $querystring;

    public function __construct(RequestHandler $request = null)
    {
        $message = 'Invalid request';
        if (!$request) {
            $request = new RequestHandler();
        }
        $this->route = $request->getRoute();
        $this->method = $request->method;
        $this->querystring = $_GET;
        parent::__construct($message);
    }
}

class ConfigurationError extends Exception
{

}

/**************************************************
 * DATABASE PDO
 **************************************************/
class Database
{
    protected ?PDO $pdo;
    private string $error_message = '';

    public function __construct($hostname, $port_number, $username_db, $password_db, $db_name)
    {
        $this->pdo = new PDO(
            "mysql:host=" . $hostname . ";dbname=" . $db_name . ";port=" . $port_number, $username_db, $password_db
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(
            PDO::MYSQL_ATTR_INIT_COMMAND,
            "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
        );
    }

    public static function getClient(): self
    {
        return new Database(DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME);
    }

    /**
     * begin a transaction.
     */
    public function begin_transaction(): void
    {
        $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
        $this->pdo->beginTransaction();
    }

    /**
     * commit the transaction.
     */
    public function commit(): void
    {
        $this->pdo->commit();
        $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }

    /**
     * rollback the transaction.
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
        $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }

    public function fetchAll(string $table)
    {
        $sel = $this->pdo->query("SELECT * FROM $table");
        $sel->setFetchMode(PDO::FETCH_ASSOC);
        return $sel;
    }

    public function query(string $sql, array $data = null)
    {
        if ($data !== null) {
            $dat = array_values($data);
        }
        $sel = $this->pdo->prepare($sql);

        if ($data !== null) {
            $sel->execute($dat);
        } else {
            $sel->execute();
        }
        $sel->setFetchMode(PDO::FETCH_OBJ);
        return $sel;
    }

    public function fetchQuery(string $table, string $conditions = null)
    {
        /**
         * This is not escaped for development speed purposes
         */
        $where = '';
        if ($conditions) {
            $where = " WHERE $conditions";
        }

        $sel = $this->pdo->query("SELECT * FROM $table $where");
        $sel->setFetchMode(PDO::FETCH_ASSOC);
        return $sel;
    }


    /**
     * insert data to table
     *
     * @param string $table table name
     * @param array $dat associative array 'column_name'=>'val'
     */
    public function insert(string $table, array $dat): ?bool
    {
        $data = [];
        if ($dat) {
            $data = array_values($dat);
        }
        //grab keys
        $cols = array_keys($dat);
        $col = implode(', ', $cols);

        //grab values and change it value
        $mark = array();
        foreach ($data as $key) {
            $keys = '?';
            $mark[] = $keys;
        }
        $im = implode(', ', $mark);

        $ins = $this->pdo->prepare("INSERT INTO $table ($col) values ($im)");

        $ins->execute($data);
        return true;
    }

    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * update record
     *
     * @param string $table table name
     * @param array $dat associative array 'col'=>'val'
     * @param string $id primary key column name
     * @param int $val key value
     */
    public function update(string $table, array $dat, string $id, int $val): ?bool
    {
        if ($dat) {
            $data = array_values($dat);
        }
        $data[] = $val;

        $cols = array_keys($dat);
        $mark = array();
        foreach ($cols as $col) {
            $mark[] = $col . "=?";
        }
        $im = implode(', ', $mark);
        return $this->pdo->prepare("UPDATE $table SET $im where $id=?")->execute($data);
    }

    public function delete(string $table, string $where, int $id): ?bool
    {
        $data = array(
            $id
        );
        return $this->pdo->prepare("Delete from $table where $where=?")->execute($data);
    }

    public function fetchSingleRow(string $table, string $col, string $val)
    {
        $arr = array(
            $val
        );
        $sel = $this->pdo->prepare("SELECT * FROM $table WHERE $col=?");

        $sel->execute($arr);
        $sel->setFetchMode(PDO::FETCH_ASSOC);
        return $sel->fetch();
    }

    public function __destruct()
    {
        $this->pdo = null;
    }
}

try {
    $requestHandler = new RequestHandler([
        'requests' => RequestModel::class,
        'items' => ItemModel::class,
        'item_types' => ItemModel::class,
        'request_items' => RequestItemModel::class,
        'summary' => SummaryModel::class,
    ]);

    $requestHandler->handle();
} catch (ModelNotFound $e) {
    $requestHandler = new RequestHandler();
    /** @noinspection PhpUnhandledExceptionInspection
     * @noinspection UnknownInspectionInspection
     */
    (new Response())->renderError($e->getMessage(), 404, [
        'route' => $requestHandler->getRoute(),
        'id' => $requestHandler->getBodyValue('id'),
        'dump' => $e
    ]);
} catch (InvalidRequest $e) {
    (new Response())->renderError($e->getMessage(), 404, [
        'route' => $e->route,
        'method' => $e->method,
        'querystring' => $e->querystring,
    ]);
} catch (PDOException $e) {
    $requestHandler = new RequestHandler();
    /** @noinspection PhpUnhandledExceptionInspection
     * @noinspection UnknownInspectionInspection
     */
    (new Response())->renderError('Database server error', 500, [
        'route' => $requestHandler->getRoute(),
        'error' => $e->getMessage(),
        'dump' => $e
    ]);
//    dd($e);
} catch (Exception $e) {
    $requestHandler = new RequestHandler();
    /** @noinspection PhpUnhandledExceptionInspection
     * @noinspection UnknownInspectionInspection
     */
    (new Response())->renderError("Internal server error: {$e->getMessage()}", 500, [
        "route" => $requestHandler->getRoute(),
        'dump' => $e
    ]);
//    dd($e);
}