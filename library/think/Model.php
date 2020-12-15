<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use InvalidArgumentException;
use think\db\Query;

/**
 * Class Model
 * @package think
 * @mixin Query
 * @method $this scope(string|array $scope) static 查询范围
 * @method $this where(mixed $field, string $op = null, mixed $condition = null) static 查询条件
 * @method $this whereRaw(string $where, array $bind = [], string $logic = 'AND') static 表达式查询
 * @method $this whereExp(string $field, string $condition, array $bind = [], string $logic = 'AND') static 字段表达式查询
 * @method $this when(mixed $condition, mixed $query, mixed $otherwise = null) static 条件查询
 * @method $this join(mixed $join, mixed $condition = null, string $type = 'INNER', array $bind = []) static JOIN查询
 * @method $this view(mixed $join, mixed $field = null, mixed $on = null, string $type = 'INNER') static 视图查询
 * @method $this with(mixed $with, callable $callback = null) static 关联预载入
 * @method $this count(string $field = '*') static Count统计查询
 * @method $this min(string $field, bool $force = true) static Min统计查询
 * @method $this max(string $field, bool $force = true) static Max统计查询
 * @method $this sum(string $field) static SUM统计查询
 * @method $this avg(string $field) static Avg统计查询
 * @method $this field(mixed $field, boolean $except = false, string $tableName = '', string $prefix = '', string $alias = '') static 指定查询字段
 * @method $this fieldRaw(string $field) static 指定查询字段
 * @method $this union(mixed $union, boolean $all = false) static UNION查询
 * @method $this limit(mixed $offset, integer $length = null) static 查询LIMIT
 * @method $this order(mixed $field, string $order = null) static 查询ORDER
 * @method $this orderRaw(string $field, array $bind = []) static 查询ORDER
 * @method $this cache(mixed $key = null , integer|\DateTime $expire = null, string $tag = null) static 设置查询缓存
 * @method mixed value(string $field, mixed $default = null) static 获取某个字段的值
 * @method array column(string $field, string $key = '') static 获取某个列的值
 * @method $this find(mixed $data = null) static 查询单个记录
 * @method $this findOrFail(mixed $data = null) 查询单个记录
 * @method Collection|$this[] select(mixed $data = null) static 查询多个记录
 * @method $this get(mixed $data = null,mixed $with = [],bool $cache = false, bool $failException = false) static 查询单个记录 支持关联预载入
 * @method $this getOrFail(mixed $data = null,mixed $with = [],bool $cache = false) static 查询单个记录 不存在则抛出异常
 * @method $this findOrEmpty(mixed $data = null) static 查询单个记录  不存在则返回空模型
 * @method Collection|$this[] all(mixed $data = null,mixed $with = [],bool $cache = false) static 查询多个记录 支持关联预载入
 * @method $this withAttr(array $name,\Closure $closure = null) static 动态定义获取器
 * @method $this withJoin(string|array $with, string $joinType = '') static
 * @method $this withCount(string|array $relation, bool $subQuery = true) static 关联统计
 * @method $this withSum(string|array $relation, string $field, bool $subQuery = true) static 关联SUM统计
 * @method $this withMax(string|array $relation, string $field, bool $subQuery = true) static 关联MAX统计
 * @method $this withMin(string|array $relation, string $field, bool $subQuery = true) static 关联Min统计
 * @method $this withAvg(string|array $relation, string $field, bool $subQuery = true) static 关联Avg统计
 * @method Paginator|$this paginate() static 分页
 */
abstract class Model implements \JsonSerializable, \ArrayAccess
{
    use model\concern\Attribute;
    use model\concern\RelationShip;
    use model\concern\ModelEvent;
    use model\concern\TimeStamp;
    use model\concern\Conversion;

    /**
     * 是否存在数据
     * @var bool
     */
    private $_exists = false;

    /**
     * 是否Replace
     * @var bool
     */
    private $_replace = false;

    /**
     * 是否强制更新所有数据
     * @var bool
     */
    private $_force = false;

    /**
     * 更新条件
     * @var array
     */
    private $_updateWhere;

    /**
     * 数据库配置信息
     * @var array|string
     */
    protected $_connection = [];

    /**
     * 数据库查询对象类名
     * @var string
     */
    protected $_query;

    /**
     * 模型名称
     * @var string
     */
    protected $_name;

    /**
     * 数据表名称
     * @var string
     */
    protected $_table;

    /**
     * 写入自动完成定义
     * @var array
     */
    protected $_auto = [];

    /**
     * 新增自动完成定义
     * @var array
     */
    protected $_insert = [];

    /**
     * 更新自动完成定义
     * @var array
     */
    protected $_update = [];

    /**
     * 初始化过的模型.
     * @var array
     */
    protected static $_initialized = [];

    /**
     * 是否从主库读取（主从分布式有效）
     * @var array
     */
    protected static $_readMaster;

    /**
     * 查询对象实例
     * @var Query
     */
    protected $_queryInstance;

    /**
     * 错误信息
     * @var mixed
     */
    protected $_error;

    /**
     * 软删除字段默认值
     * @var mixed
     */
    protected $_defaultSoftDelete;

    /**
     * 全局查询范围
     * @var array
     */
    protected $_globalScope = [];

    /**
     * 架构函数
     * @access public
     * @param  array|object $data 数据
     */
    public function __construct($data = [])
    {
        if (is_object($data)) {
            $this->_data = get_object_vars($data);
        } else {
            $this->_data = $data;
        }

        if ($this->_disuse) {
            // 废弃字段
            foreach ((array) $this->_disuse as $key) {
                if (array_key_exists($key, $this->_data)) {
                    unset($this->_data[$key]);
                }
            }
        }

        // 记录原始数据
        $this->_origin = $this->_data;

        $config = Db::getConfig();

        if (empty($this->_name)) {
            // 当前模型名
            $name       = str_replace('\\', '/', static::class);
            $this->_name = basename($name);
            if (Container::get('config')->get('class_suffix')) {
                $suffix     = basename(dirname($name));
                $this->_name = substr($this->_name, 0, -strlen($suffix));
            }
        }

        if (is_null($this->_autoWriteTimestamp)) {
            // 自动写入时间戳
            $this->_autoWriteTimestamp = $config['auto_timestamp'];
        }

        if (is_null($this->_dateFormat)) {
            // 设置时间戳格式
            $this->_dateFormat = $config['datetime_format'];
        }

        if (is_null($this->_resultSetType)) {
            $this->_resultSetType = $config['resultset_type'];
        }

        if (!empty($this->_connection) && is_array($this->_connection)) {
            // 设置模型的数据库连接
            $this->_connection = array_merge($config, $this->_connection);
        }

        if ($this->_observerClass) {
            // 注册模型观察者
            static::observe($this->_observerClass);
        }

        // 执行初始化操作
        $this->initialize();
    }

    /**
     * 获取当前模型名称
     * @access public
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * 是否从主库读取数据（主从分布有效）
     * @access public
     * @param  bool     $all 是否所有模型有效
     * @return $this
     */
    public function readMaster($all = false)
    {
        $model = $all ? '*' : static::class;

        static::$_readMaster[$model] = true;

        return $this;
    }

    /**
     * 创建新的模型实例
     * @access public
     * @param  array|object $data 数据
     * @param  bool         $isUpdate 是否为更新
     * @param  mixed        $where 更新条件
     * @return Model
     */
    public function newInstance($data = [], $isUpdate = false, $where = null)
    {
        return (new static($data))->isUpdate($isUpdate, $where);
    }

    /**
     * 创建模型的查询对象
     * @access protected
     * @return Query
     */
    protected function buildQuery()
    {
        // 设置当前模型 确保查询返回模型对象
        $query = Db::connect($this->_connection, false, $this->_query);
        $query->model($this)
            ->name($this->_name)
            ->json($this->_json, $this->_jsonAssoc)
            ->setJsonFieldType($this->_jsonType);

        if (isset(static::$_readMaster['*']) || isset(static::$_readMaster[static::class])) {
            $query->master(true);
        }

        // 设置当前数据表和模型名
        if (!empty($this->_table)) {
            $query->table($this->_table);
        }

        if (!empty($this->_pk)) {
            $query->pk($this->_pk);
        }

        return $query;
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @param  Query $query 查询对象实例
     * @return $this
     */
    public function setQuery($query)
    {
        $this->_queryInstance = $query;
        return $this;
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @param  bool|array $useBaseQuery 是否调用全局查询范围（或者指定查询范围名称）
     * @return Query
     */
    public function db($useBaseQuery = true)
    {
        if ($this->_queryInstance) {
            return $this->_queryInstance;
        }

        $query = $this->buildQuery();

        // 软删除
        if (property_exists($this, '_withTrashed') && !$this->_withTrashed && method_exists($this, 'withNoTrashed')) {
            $this->withNoTrashed($query);
        }

        // 全局作用域
        if (true === $useBaseQuery && method_exists($this, 'base')) {
            call_user_func_array([$this, 'base'], [ & $query]);
        }

        $globalScope = is_array($useBaseQuery) && $useBaseQuery ? $useBaseQuery : $this->_globalScope;

        if ($globalScope && false !== $useBaseQuery) {
            $query->scope($globalScope);
        }

        // 返回当前模型的数据库查询对象
        return $query;
    }

    /**
     *  初始化模型
     * @access protected
     * @return void
     */
    protected function initialize()
    {
        if (!isset(static::$_initialized[static::class])) {
            static::$_initialized[static::class] = true;
            static::init();
        }
    }

    /**
     * 初始化处理
     * @access protected
     * @return void
     */
    protected static function init()
    {}

    /**
     * 数据自动完成
     * @access protected
     * @param  array $auto 要自动更新的字段列表
     * @return void
     */
    protected function autoCompleteData($auto = [])
    {
        foreach ($auto as $field => $value) {
            if (is_integer($field)) {
                $field = $value;
                $value = null;
            }

            if (!isset($this->_data[$field])) {
                $default = null;
            } else {
                $default = $this->_data[$field];
            }

            $this->setAttr($field, !is_null($value) ? $value : $default);
        }
    }

    /**
     * 更新是否强制写入数据 而不做比较
     * @access public
     * @param  bool $force
     * @return $this
     */
    public function force($force = true)
    {
        $this->_force = $force;
        return $this;
    }

    /**
     * 判断force
     * @access public
     * @return bool
     */
    public function isForce()
    {
        return $this->_force;
    }

    /**
     * 新增数据是否使用Replace
     * @access public
     * @param  bool $replace
     * @return $this
     */
    public function replace($replace = true)
    {
        $this->_replace = $replace;
        return $this;
    }

    /**
     * 设置数据是否存在
     * @access public
     * @param  bool $exists
     * @return $this
     */
    public function exists($exists)
    {
        $this->_exists = $exists;
        return $this;
    }

    /**
     * 判断数据是否存在数据库
     * @access public
     * @return bool
     */
    public function isExists()
    {
        return $this->_exists;
    }

    /**
     * 判断模型是否为空
     * @access public
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->_data);
    }

    /**
     * 保存当前数据对象
     * @access public
     * @param  array  $data     数据
     * @param  array  $where    更新条件
     * @param  string $sequence 自增序列名
     * @return bool
     * @throws
     */
    public function save($data = [], $where = [], $sequence = null)
    {
        if (is_string($data)) {
            $sequence = $data;
            $data     = [];
        }

        if (!$this->checkBeforeSave($data, $where)) {
            return false;
        }

        $result = $this->_exists ? $this->updateData($where) : $this->insertData($sequence);

        if (false === $result) {
            return false;
        }

        // 写入回调
        $this->trigger('after_write');

        // 重新记录原始数据
        $this->_origin = $this->_data;
        $this->_set    = [];

        return $result;
    }

    /**
     * 写入之前检查数据
     * @access protected
     * @param  array   $data  数据
     * @param  array   $where 保存条件
     * @return bool
     */
    protected function checkBeforeSave($data, $where)
    {
        if (!empty($data)) {
            // 数据对象赋值
            foreach ($data as $key => $value) {
                $this->setAttr($key, $value, $data);
            }

            if (!empty($where)) {
                $this->_exists      = true;
                $this->_updateWhere = $where;
            }
        }

        // 数据自动完成
        $this->autoCompleteData($this->_auto);

        // 事件回调
        if (false === $this->trigger('before_write')) {
            return false;
        }

        return true;
    }

    /**
     * 检查数据是否允许写入
     * @access protected
     * @param  array   $append 自动完成的字段列表
     * @return array
     */
    protected function checkAllowFields(array $append = [])
    {
        // 检测字段
        if (empty($this->_field) || true === $this->_field) {
            $query = $this->db(false);
            $table = $this->_table ?: $query->getTable();

            $this->_field = $query->getConnection()->getTableFields($table);

            $field = $this->_field;
        } else {
            $field = array_merge($this->_field, $append);

            if ($this->_autoWriteTimestamp) {
                array_push($field, $this->_createTime, $this->_updateTime);
            }
        }

        if ($this->_disuse) {
            // 废弃字段
            $field = array_diff($field, (array) $this->_disuse);
        }

        return $field;
    }

    /**
     * 更新写入数据
     * @access protected
     * @param  mixed   $where 更新条件
     * @return bool
     * @throws \Exception
     */
    protected function updateData($where)
    {
        // 自动更新
        $this->autoCompleteData($this->_update);

        // 事件回调
        if (false === $this->trigger('before_update')) {
            return false;
        }

        // 获取有更新的数据
        $data = $this->getChangedData();

        if (empty($data)) {
            // 关联更新
            if (!empty($this->_relationWrite)) {
                $this->autoRelationUpdate();
            }

            return true;
        } elseif ($this->_autoWriteTimestamp && $this->_updateTime && !isset($data[$this->_updateTime])) {
            // 自动写入更新时间
            $data[$this->_updateTime] = $this->autoWriteTimestamp($this->_updateTime);

            $this->_data[$this->_updateTime] = $data[$this->_updateTime];
        }

        if (empty($where) && !empty($this->_updateWhere)) {
            $where = $this->_updateWhere;
        }

        // 检查允许字段
        $allowFields = $this->checkAllowFields(array_merge($this->_auto, $this->_update));

        // 保留主键数据
        foreach ($this->_data as $key => $val) {
            if ($this->isPk($key)) {
                $data[$key] = $val;
            }
        }

        $pk    = $this->getPk();
        $array = [];

        foreach ((array) $pk as $key) {
            if (isset($data[$key])) {
                $array[] = [$key, '=', $data[$key]];
                unset($data[$key]);
            }
        }

        if (!empty($array)) {
            $where = $array;
        }

        foreach ((array) $this->_relationWrite as $name => $val) {
            if (is_array($val)) {
                foreach ($val as $key) {
                    if (isset($data[$key])) {
                        unset($data[$key]);
                    }
                }
            }
        }

        // 模型更新
        $db = $this->db(false);
        $db->startTrans();

        try {
            $result = $db->where($where)
                ->strict(false)
                ->field($allowFields)
                ->update($data);

            // 关联更新
            if (!empty($this->_relationWrite)) {
                $this->autoRelationUpdate();
            }

            $db->commit();

            // 更新回调
            $this->trigger('after_update');

            return $result;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 新增写入数据
     * @access protected
     * @param  string   $sequence 自增序列名
     * @return bool
     * @throws \Exception
     */
    protected function insertData($sequence)
    {
        // 自动写入
        $this->autoCompleteData($this->_insert);

        // 时间戳自动写入
        $this->checkTimeStampWrite();

        if (false === $this->trigger('before_insert')) {
            return false;
        }

        // 检查允许字段
        $allowFields = $this->checkAllowFields(array_merge($this->_auto, $this->_insert));

        $db = $this->db(false);
        $db->startTrans();

        try {
            $result = $db->strict(false)
                ->field($allowFields)
                ->insert($this->_data, $this->_replace, false, $sequence);

            // 获取自动增长主键
            if ($result && $insertId = $db->getLastInsID($sequence)) {
                $pk = $this->getPk();

                foreach ((array) $pk as $key) {
                    if (!isset($this->_data[$key]) || '' == $this->_data[$key]) {
                        $this->_data[$key] = $insertId;
                    }
                }
            }

            // 关联写入
            if (!empty($this->_relationWrite)) {
                $this->autoRelationInsert();
            }

            $db->commit();

            // 标记为更新
            $this->_exists = true;

            // 新增回调
            $this->trigger('after_insert');

            return $insertId ?? true;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 字段值(延迟)增长
     * @access public
     * @param  string  $field    字段名
     * @param  integer $step     增长值
     * @param  integer $lazyTime 延时时间(s)
     * @return bool
     * @throws Exception
     */
    public function setInc($field, $step = 1, $lazyTime = 0)
    {
        // 读取更新条件
        $where = $this->getWhere();

        // 事件回调
        if (false === $this->trigger('before_update')) {
            return false;
        }

        $result = $this->db(false)
            ->where($where)
            ->setInc($field, $step, $lazyTime);

        if (true !== $result) {
            $this->_data[$field] += $step;
        }

        // 更新回调
        $this->trigger('after_update');

        return true;
    }

    /**
     * 字段值(延迟)减少
     * @access public
     * @param  string  $field    字段名
     * @param  integer $step     减少值
     * @param  integer $lazyTime 延时时间(s)
     * @return bool
     * @throws Exception
     */
    public function setDec($field, $step = 1, $lazyTime = 0)
    {
        // 读取更新条件
        $where = $this->getWhere();

        // 事件回调
        if (false === $this->trigger('before_update')) {
            return false;
        }

        $result = $this->db(false)
            ->where($where)
            ->setDec($field, $step, $lazyTime);

        if (true !== $result) {
            $this->_data[$field] -= $step;
        }

        // 更新回调
        $this->trigger('after_update');

        return true;
    }

    /**
     * 获取当前的更新条件
     * @access protected
     * @return mixed
     */
    protected function getWhere()
    {
        // 删除条件
        $pk = $this->getPk();

        $where = [];
        if (is_string($pk) && isset($this->_data[$pk])) {
            $where[] = [$pk, '=', $this->_data[$pk]];
        } elseif (is_array($pk)) {
            foreach ($pk as $field) {
                if (isset($this->_data[$field])) {
                    $where[] = [$field, '=', $this->_data[$field]];
                }
            }
        }
        if (empty($where)) {
            $where = empty($this->updateWhere) ? null : $this->updateWhere;
        }

        return $where;
    }

    /**
     * 保存多个数据到当前数据对象
     * @access public
     * @param  array   $dataSet 数据
     * @param  boolean $replace 是否自动识别更新和写入
     * @return Collection
     * @throws \Exception
     */
    public function saveAll($dataSet, $replace = true)
    {
        $db = $this->db(false);
        $db->startTrans();

        try {
            $pk = $this->getPk();

            if (is_string($pk) && $replace) {
                $auto = true;
            }

            $result = [];

            foreach ($dataSet as $key => $data) {
                if ($this->_exists || (!empty($auto) && isset($data[$pk]))) {
                    $result[$key] = self::update($data, [], $this->_field);
                } else {
                    $result[$key] = self::create($data, $this->_field, $this->_replace);
                }
            }

            $db->commit();

            return $this->toCollection($result);
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 是否为更新数据
     * @access public
     * @param  mixed  $update
     * @param  mixed  $where
     * @return $this
     */
    public function isUpdate($update = true, $where = null)
    {
        if (is_bool($update)) {
            $this->_exists = $update;

            if (!empty($where)) {
                $this->_updateWhere = $where;
            }
        } else {
            $this->_exists      = true;
            $this->_updateWhere = $update;
        }

        return $this;
    }

    /**
     * 删除当前的记录
     * @access public
     * @return bool
     * @throws \Exception
     */
    public function delete()
    {
        if (!$this->_exists || false === $this->trigger('before_delete')) {
            return false;
        }

        // 读取更新条件
        $where = $this->getWhere();

        $db = $this->db(false);
        $db->startTrans();

        try {
            // 删除当前模型数据
            $db->where($where)->delete();

            // 关联删除
            if (!empty($this->_relationWrite)) {
                $this->autoRelationDelete();
            }

            $db->commit();

            $this->trigger('after_delete');

            $this->_exists = false;

            return true;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 设置自动完成的字段（ 规则通过修改器定义）
     * @access public
     * @param  array $fields 需要自动完成的字段
     * @return $this
     */
    public function auto($fields)
    {
        $this->_auto = $fields;

        return $this;
    }

    /**
     * 写入数据
     * @access public
     * @param  array      $data  数据数组
     * @param  array|true $field 允许字段
     * @param  bool       $replace 使用Replace
     * @return static
     */
    public static function create($data = [], $field = null, $replace = false)
    {
        $model = new static();

        if (!empty($field)) {
            $model->allowField($field);
        }

        $model->isUpdate(false)->replace($replace)->save($data, []);

        return $model;
    }

    /**
     * 更新数据
     * @access public
     * @param  array      $data  数据数组
     * @param  array      $where 更新条件
     * @param  array|true $field 允许字段
     * @return static
     */
    public static function update($data = [], $where = [], $field = null)
    {
        $model = new static();

        if (!empty($field)) {
            $model->allowField($field);
        }

        $model->isUpdate(true)->save($data, $where);

        return $model;
    }

    /**
     * 删除记录
     * @access public
     * @param  mixed $data 主键列表 支持闭包查询条件
     * @return bool
     */
    public static function destroy($data)
    {
        if (empty($data) && 0 !== $data) {
            return false;
        }

        $model = new static();

        $query = $model->db();

        if (is_array($data) && key($data) !== 0) {
            $query->where($data);
            $data = null;
        } elseif ($data instanceof \Closure) {
            $data($query);
            $data = null;
        }

        $resultSet = $query->select($data);

        if ($resultSet) {
            foreach ($resultSet as $data) {
                $data->delete();
            }
        }

        return true;
    }

    /**
     * 设置错误信息
     * @access public
     * @return mixed
     */
    public function setError($msg)
    {
        $this->_error = $msg;
    }

    /**
     * 获取错误信息
     * @access public
     * @return mixed
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * 解序列化后处理
     */
    public function __wakeup()
    {
        $this->initialize();
    }

    public function __debugInfo()
    {
        return [
            '_data'     => $this->_data,
            '_origin'   => $this->_origin,
            '_relation' => $this->_relation,
        ];
    }

    /**
     * 修改器 设置数据对象的值
     * @access public
     * @param  string $name  名称
     * @param  mixed  $value 值
     * @return void
     */
    public function __set($name, $value)
    {
        $this->setAttr($name, $value);
    }

    /**
     * 获取器 获取数据对象的值
     * @access public
     * @param  string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttr($name);
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param  string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        try {
            return !is_null($this->getAttr($name));
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * 销毁数据对象的值
     * @access public
     * @param  string $name 名称
     * @return void
     */
    public function __unset($name)
    {
        unset($this->_data[$name], $this->_relation[$name]);
    }

    // ArrayAccess
    public function offsetSet($name, $value)
    {
        $this->setAttr($name, $value);
    }

    public function offsetExists($name)
    {
        return $this->__isset($name);
    }

    public function offsetUnset($name)
    {
        $this->__unset($name);
    }

    public function offsetGet($name)
    {
        return $this->getAttr($name);
    }

    /**
     * 设置是否使用全局查询范围
     * @access public
     * @param  bool|array $use 是否启用全局查询范围（或者用数组指定查询范围名称）
     * @return Query
     */
    public static function useGlobalScope($use)
    {
        $model = new static();

        return $model->db($use);
    }

    public function __call($method, $args)
    {
        if ('withattr' == strtolower($method)) {
            return call_user_func_array([$this, 'withAttribute'], $args);
        }

        return call_user_func_array([$this->db(), $method], $args);
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();

        return call_user_func_array([$model->db(), $method], $args);
    }
}
